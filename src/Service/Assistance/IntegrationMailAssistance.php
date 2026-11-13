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
        private readonly EntityManagerInterface $entityManager,
        private readonly DemandeAssistanceRepository $demandeRepository,
        private readonly MessageAssistanceRepository $messageRepository,
        private readonly UserRepository $userRepository,
        private readonly SujetNormaliseur $sujetNormaliseur,
        private readonly StockagePieceJointe $stockage,
        #[Target('assistance_sanitizer')]
        private readonly HtmlSanitizerInterface $sanitizer,
    ) {
    }

    public function integrerTout(SourceMailInterface $source, DateTimeImmutable $depuis): int
    {
        $nombre = 0;
        foreach ($source->lire($depuis) as $mail) {
            if ($this->integrer($mail) !== null) {
                ++$nombre;
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
        if ($this->messageRepository->findOneBy(['messageId' => $messageId]) !== null) {
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

        foreach ($mail->piecesJointes as $piece) {
            $message->addPieceJointe(
                (new PieceJointeAssistance())
                    ->setNomOriginal($piece->nom)
                    ->setMimeType($piece->mimeType)
                    ->setTaille(strlen($piece->contenu))
                    ->setChemin($this->stockage->enregistrer($piece))
            );
        }

        $demande->addMessage($message);

        if ($sens === SensMessageAssistanceEnum::ENTRANT && !$mail->automatique
            && in_array($demande->getStatut(), [StatutDemandeAssistanceEnum::RESOLUE, StatutDemandeAssistanceEnum::EN_ATTENTE_USAGER], true)) {
            $demande->setStatut(StatutDemandeAssistanceEnum::EN_COURS);
        }

        $this->entityManager->persist($demande);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // relève concurrente : le mail vient d'être intégré par un autre processus
            return null;
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
            ->setSujet($mail->sujet !== '' ? $mail->sujet : '(sans sujet)')
            ->setSujetNormalise($sujetNormalise)
            ->setDemandeurEmail($demandeurEmail)
            ->setDemandeurNom($demandeurNom)
            ->setDemandeur($this->userRepository->findOneBy(['email' => $demandeurEmail]))
            ->setCreated($mail->date)
            ->setDateDernierMessage($mail->date);
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
