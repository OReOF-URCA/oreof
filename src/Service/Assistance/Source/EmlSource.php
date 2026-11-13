<?php

namespace App\Service\Assistance\Source;

use DateTimeImmutable;
use InvalidArgumentException;
use Webklex\PHPIMAP\Message;

/**
 * Lit des mails depuis un fichier .eml ou un dossier de .eml (tests, reprise d'historique).
 * Tous les fichiers sont lus, quelle que soit leur date.
 */
class EmlSource implements SourceMailInterface
{
    public function __construct(
        private readonly string $chemin,
        private readonly MailEntrantFactory $factory,
    ) {
    }

    public function lire(DateTimeImmutable $depuis): iterable
    {
        if (is_dir($this->chemin)) {
            $fichiers = glob(rtrim($this->chemin, '/') . '/*.eml') ?: [];
            sort($fichiers);
        } elseif (is_file($this->chemin)) {
            $fichiers = [$this->chemin];
        } else {
            throw new InvalidArgumentException(sprintf('Fichier ou dossier introuvable : %s', $this->chemin));
        }

        foreach ($fichiers as $fichier) {
            yield $this->factory->depuisMessage(Message::fromFile($fichier));
        }
    }
}
