<?php

namespace App\Service\Assistance;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Empêche deux relèves simultanées (cron + bouton « Relever maintenant ») via un verrou fichier.
 * Le verrou est libéré automatiquement par le système si le processus s'arrête.
 */
class VerrouReleveAssistance
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/assistance-releve.lock')]
        private readonly string $fichier,
    ) {
    }

    public function acquerir(): bool
    {
        $handle = @fopen($this->fichier, 'c');
        if ($handle === false) {
            return false;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }
        $this->handle = $handle;

        return true;
    }

    public function liberer(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
