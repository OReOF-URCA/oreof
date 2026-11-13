<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\MessageAssistance;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Un message du fil d'une demande d'assistance (reçu, réponse envoyée ou note interne).
 * Le HTML affiché a été nettoyé à l'enregistrement (sanitizer « assistance_sanitizer »).
 *
 * <twig:MessageAssistanceCard :message="message" />
 */
#[AsTwigComponent('MessageAssistanceCard', template: 'components/message_assistance_card.html.twig')]
final class MessageAssistanceCard
{
    public MessageAssistance $message;
}
