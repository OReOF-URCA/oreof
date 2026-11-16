<?php

namespace App\Service\Assistance;

use App\Classes\Mailer;
use App\DTO\Assistance\MailEntrant;
use App\Entity\DemandeAssistance;
use App\Entity\MessageAssistance;
use App\Entity\PieceJointeAssistance;
use App\Entity\User;
use App\Enums\SensMessageAssistanceEnum;
use App\Enums\StatutDemandeAssistanceEnum;
use App\Repository\DemandeAssistanceRepository;
use App\Repository\MessageAssistanceRepository;
use App\Repository\UserRepository;
use App\Service\Assistance\Source\SourceMailInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Throwable;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Intègre un mail lu dans une source : déduplication, rattachement à une demande
 * (fil de discussion, puis sujet normalisé sur 30 jours, sinon nouvelle demande) et persistance.
 */
class IntegrationMailAssistance
{
    public const FENETRE_SUJET_JOURS = 30;

    /**
     * Au-delà de ce nombre de destinataires, un mail de l'équipe est une annonce (mail de masse).
     */
    public const SEUIL_ANNONCE = 10;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly DemandeAssistanceRepository $demandeRepository,
        private readonly MessageAssistanceRepository $messageRepository,
        private readonly UserRepository $userRepository,
        private readonly SujetNormaliseur $sujetNormaliseur,
        private readonly StockagePieceJointe $stockage,
        #[Target('assistance_sanitizer')]
        private readonly HtmlSanitizerInterface $sanitizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Intègre tous les mails d'une source. Une erreur sur un mail est journalisée et n'interrompt pas la relève.
     */
    public function integrerTout(SourceMailInterface $source, DateTimeImmutable $depuis): int
    {
        $nombre = 0;
        $estConnu = fn (string $messageId): bool => $this->messageRepository->existe($messageId);

        foreach ($source->lire($depuis, $estConnu) as $mail) {
            try {
                if ($this->integrer($mail) !== null) {
                    ++$nombre;
                }
            } catch (Throwable $e) {
                $this->logger->error('Assistance : intégration d\'un mail impossible', [
                    'messageId' => $mail->messageId,
                    'expediteur' => $mail->expediteurEmail,
                    'sujet' => $mail->sujet,
                    'exception' => $e,
                ]);
                $this->reouvrirSiFerme();
            }
        }

        return $nombre;
    }

    /**
     * @return MessageAssistance|null null si le mail est déjà intégré
     */
    public function integrer(MailEntrant $mail): ?MessageAssistance
    {
        $messageId = $mail->messageId ?? $this->idCalcule($mail);
        if ($this->messageRepository->existe($messageId)) {
            return null;
        }

        $sens = $this->estEquipe($mail->expediteurEmail) ? SensMessageAssistanceEnum::SORTANT : SensMessageAssistanceEnum::ENTRANT;
        $annonce = $sens === SensMessageAssistanceEnum::SORTANT && count($mail->destinataires) > self::SEUIL_ANNONCE;
        $sujetNormalise = $this->sujetNormaliseur->normaliser($mail->sujet);
        $demande = $this->trouverDemande($mail, $sujetNormalise, $annonce) ?? $this->nouvelleDemande($mail, $sujetNormalise, $annonce);

        $message = (new MessageAssistance())
            ->setSens($sens)
            ->setMessageId($messageId)
            ->setInReplyTo($mail->inReplyTo)
            ->setReferences($mail->references)
            ->setExpediteurEmail($mail->expediteurEmail)
            ->setExpediteurNom($mail->expediteurNom)
            ->setDestinataires($mail->destinataires)
            ->setSujet($mail->sujet)
            ->setCorpsTexte($mail->corpsTexte)
            ->setCorpsHtml($mail->corpsHtml !== null ? $this->sanitizer->sanitize($mail->corpsHtml) : null)
            ->setDateEnvoi($mail->date)
            ->setAutomatique($mail->automatique)
            ->setAuteur($sens === SensMessageAssistanceEnum::SORTANT ? $this->userRepository->findOneBy(['email' => $mail->expediteurEmail]) : null);

        $fichiers = [];
        try {
            foreach ($mail->piecesJointes as $piece) {
                $chemin = $this->stockage->enregistrer($piece);
                $fichiers[] = $chemin;
                $message->addPieceJointe(
                    (new PieceJointeAssistance())
                        ->setNomOriginal(str_replace(['/', '\\'], '-', $piece->nom))
                        ->setMimeType($piece->mimeType)
                        ->setTaille(strlen($piece->contenu))
                        ->setChemin($chemin)
                );
            }
        } catch (Throwable $e) {
            $this->stockage->supprimer($fichiers);
            throw $e;
        }

        $demande->addMessage($message);

        if ($sens === SensMessageAssistanceEnum::ENTRANT && !$mail->automatique
            && in_array($demande->getStatut(), [StatutDemandeAssistanceEnum::RESOLUE, StatutDemandeAssistanceEnum::EN_ATTENTE_USAGER], true)) {
            $demande->setStatut(StatutDemandeAssistanceEnum::EN_COURS);
        }

        $entityManager = $this->doctrine->getManager();
        assert($entityManager instanceof EntityManagerInterface);
        $entityManager->persist($demande);

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // relève concurrente : le mail vient d'être intégré par un autre processus
            $this->stockage->supprimer($fichiers);
            $this->reouvrirSiFerme();

            return null;
        } catch (Throwable $e) {
            $this->stockage->supprimer($fichiers);
            throw $e;
        }

        return $message;
    }

    private function trouverDemande(MailEntrant $mail, string $sujetNormalise, bool $annonce): ?DemandeAssistance
    {
        $ids = [$mail->inReplyTo, ...$mail->references];

        if ($annonce) {
            // une annonce ne rejoint qu'une autre annonce du même fil
            return $this->messageRepository->findOneByMessageIds($ids, true)?->getDemande();
        }

        $parent = $this->messageRepository->findOneByMessageIds($ids, false);
        if ($parent !== null) {
            return $parent->getDemande();
        }

        $depuis = $mail->date->modify(sprintf('-%d days', self::FENETRE_SUJET_JOURS));
        if ($this->messageRepository->findOneByMessageIds($ids, true) !== null) {
            // réponse à une annonce : une demande par usager
            return $this->demandeRepository->findRecenteParSujet($sujetNormalise, $depuis, $this->interlocuteur($mail)[0]);
        }

        return $this->demandeRepository->findRecenteParSujet($sujetNormalise, $depuis);
    }

    /**
     * Usager concerné par le mail : l'expéditeur, ou pour un mail de l'équipe le premier destinataire extérieur.
     *
     * @return array{0: string, 1: ?string} email et nom
     */
    private function interlocuteur(MailEntrant $mail): array
    {
        if (!$this->estEquipe($mail->expediteurEmail)) {
            return [$mail->expediteurEmail, $mail->expediteurNom];
        }
        foreach ($mail->destinataires as $destinataire) {
            if (!$this->estEquipe($destinataire)) {
                return [$destinataire, null];
            }
        }

        return [$mail->expediteurEmail, $mail->expediteurNom];
    }

    private function nouvelleDemande(MailEntrant $mail, string $sujetNormalise, bool $annonce): DemandeAssistance
    {
        [$demandeurEmail, $demandeurNom] = $annonce ? [$mail->expediteurEmail, $mail->expediteurNom] : $this->interlocuteur($mail);

        return (new DemandeAssistance())
            ->setAnnonce($annonce)
            // annonce, rebond ou réponse automatique hors de tout échange connu : rien à traiter
            ->setStatut($annonce || $mail->automatique ? StatutDemandeAssistanceEnum::RESOLUE : StatutDemandeAssistanceEnum::NOUVELLE)
            ->setSujet($mail->sujet !== '' ? $mail->sujet : '(sans sujet)')
            ->setSujetNormalise($sujetNormalise)
            ->setDemandeurEmail($demandeurEmail)
            ->setDemandeurNom($demandeurNom)
            ->setDemandeur($this->userRepository->findOneBy(['email' => $demandeurEmail]))
            ->setCreated($mail->date)
            ->setDateDernierMessage($mail->date);
    }

    /**
     * Après une erreur de base, Doctrine ferme le gestionnaire d'entités : on le réinitialise
     * pour que les mails suivants puissent être intégrés.
     */
    private function reouvrirSiFerme(): void
    {
        $entityManager = $this->doctrine->getManager();
        if ($entityManager instanceof EntityManagerInterface && !$entityManager->isOpen()) {
            $this->doctrine->resetManager();
        }
    }

    private function estEquipe(string $email): bool
    {
        if ($email === mb_strtolower(Mailer::MAIL_GENERIC)) {
            return true;
        }
        $user = $this->userRepository->findOneBy(['email' => $email]);

        return $user instanceof User && in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    private function idCalcule(MailEntrant $mail): string
    {
        return '<' . sha1($mail->expediteurEmail . '|' . $mail->date->format(DATE_ATOM) . '|' . $mail->sujet) . '@assistance.oreof>';
    }
}
