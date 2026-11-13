<?php

namespace App\Service\Assistance;

use App\Classes\Mailer;
use App\Entity\DemandeAssistance;
use App\Entity\MessageAssistance;
use App\Entity\User;
use App\Enums\SensMessageAssistanceEnum;
use App\Enums\StatutDemandeAssistanceEnum;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoie une réponse à une demande d'assistance depuis l'adresse ORéOF, dans le fil du dernier échange,
 * et l'enregistre comme message sortant.
 */
class ReponseAssistance
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
        #[Target('assistance_sanitizer')]
        private readonly HtmlSanitizerInterface $sanitizer,
    ) {
    }

    /**
     * @param list<string> $destinataires
     */
    public function repondre(DemandeAssistance $demande, User $auteur, string $corpsHtml, array $destinataires, bool $resoudre = false): MessageAssistance
    {
        $destinataires = array_values(array_unique(array_filter(
            array_map(static fn (string $email) => mb_strtolower(trim($email)), $destinataires),
            static fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        )));
        if ($destinataires === []) {
            throw new InvalidArgumentException('Aucun destinataire valide.');
        }

        $html = $this->sanitizer->sanitize($corpsHtml);
        $texte = $this->texte($html);
        if ($texte === '') {
            throw new InvalidArgumentException('La réponse est vide.');
        }

        $sujet = preg_match('/^\s*re\s*:/i', $demande->getSujet()) === 1 ? $demande->getSujet() : 'Re: ' . $demande->getSujet();
        $idGenere = bin2hex(random_bytes(16)) . '@univ-reims.fr';
        $equipe = new Address(Mailer::MAIL_GENERIC, 'ORéOF');

        $email = (new Email())
            ->from($equipe)
            ->replyTo($equipe)
            ->to(...$destinataires)
            ->subject($sujet)
            ->html($html)
            ->text($texte);
        $email->getHeaders()->addIdHeader('Message-ID', $idGenere);

        $parent = $demande->getDernierMessageMail();
        $references = [];
        if ($parent !== null) {
            $references = [...$parent->getReferences(), $parent->getMessageId()];
            $email->getHeaders()->addIdHeader('In-Reply-To', $this->sansChevrons((string) $parent->getMessageId()));
            $email->getHeaders()->addIdHeader('References', array_map($this->sansChevrons(...), $references));
        }

        $this->mailer->send($email);

        $message = (new MessageAssistance())
            ->setSens(SensMessageAssistanceEnum::SORTANT)
            ->setMessageId('<' . $idGenere . '>')
            ->setInReplyTo($parent?->getMessageId())
            ->setReferences($references)
            ->setExpediteurEmail(Mailer::MAIL_GENERIC)
            ->setExpediteurNom($auteur->getDisplay())
            ->setDestinataires($destinataires)
            ->setSujet($sujet)
            ->setCorpsTexte($texte)
            ->setCorpsHtml($html)
            ->setDateEnvoi(new DateTimeImmutable())
            ->setAuteur($auteur);
        $demande->addMessage($message);
        $demande->setStatut($resoudre ? StatutDemandeAssistanceEnum::RESOLUE : StatutDemandeAssistanceEnum::EN_ATTENTE_USAGER);
        $this->entityManager->flush();

        return $message;
    }

    /**
     * Demandeur puis participants des messages reçus, hors adresse de l'équipe et hors messages automatiques.
     *
     * @return list<string>
     */
    public function destinatairesProposes(DemandeAssistance $demande): array
    {
        $emails = [$demande->getDemandeurEmail()];
        foreach ($demande->getMessages() as $message) {
            if ($message->getSens() !== SensMessageAssistanceEnum::ENTRANT || $message->isAutomatique()) {
                continue;
            }
            $emails = [...$emails, $message->getExpediteurEmail(), ...$message->getDestinataires()];
        }
        $equipe = mb_strtolower(Mailer::MAIL_GENERIC);

        return array_values(array_unique(array_filter($emails, static fn (string $email) => $email !== '' && $email !== $equipe)));
    }

    private function sansChevrons(string $id): string
    {
        return trim($id, '<> ');
    }

    private function texte(string $html): string
    {
        $html = (string) preg_replace('#<(br|/p|/div|/li|/h\d)\b[^>]*>#i', "\n", $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
