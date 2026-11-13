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
        $sujetNormalise = $this->sujetNormaliseur->normaliser($mail->sujet);
        $demande = $this->trouverDemande($mail, $sujetNormalise) ?? $this->nouvelleDemande($mail, $sujetNormalise);

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

    private function trouverDemande(MailEntrant $mail, string $sujetNormalise): ?DemandeAssistance
    {
        $parent = $this->messageRepository->findOneByMessageIds([$mail->inReplyTo, ...$mail->references]);
        if ($parent !== null) {
            return $parent->getDemande();
        }

        return $this->demandeRepository->findRecenteParSujet(
            $sujetNormalise,
            $mail->date->modify(sprintf('-%d days', self::FENETRE_SUJET_JOURS))
        );
    }

    private function nouvelleDemande(MailEntrant $mail, string $sujetNormalise): DemandeAssistance
    {
        $demandeurEmail = $mail->expediteurEmail;
        $demandeurNom = $mail->expediteurNom;
        if ($this->estEquipe($demandeurEmail)) {
            // mail initié par l'équipe : le demandeur est le premier destinataire extérieur
            foreach ($mail->destinataires as $destinataire) {
                if (!$this->estEquipe($destinataire)) {
                    $demandeurEmail = $destinataire;
                    $demandeurNom = null;
                    break;
                }
            }
        }

        return (new DemandeAssistance())
            // rebond ou réponse automatique hors de tout échange connu : rien à traiter
            ->setStatut($mail->automatique ? StatutDemandeAssistanceEnum::RESOLUE : StatutDemandeAssistanceEnum::NOUVELLE)
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
