<?php

namespace App\Service\Assistance;

use App\Service\Assistance\Source\ImapSource;
use App\Service\Assistance\Source\SourceMailInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Relève courte (1 jour) déclenchée depuis l'interface : bouton « Relever maintenant »
 * ou ouverture de la liste des demandes. La relève complète reste faite par le cron.
 *
 * À l'ouverture de la page, la relève est espacée d'au moins INTERVALLE_MINIMUM secondes,
 * tous administrateurs confondus, pour ne pas solliciter la boîte IMAP à chaque affichage.
 */
class ReleveWebAssistance
{
    public const INTERVALLE_MINIMUM = 60;

    private const CLE_DERNIERE_RELEVE = 'assistance_derniere_releve_web';

    public function __construct(
        #[Autowire(service: ImapSource::class)]
        private readonly SourceMailInterface $source,
        private readonly IntegrationMailAssistance $integration,
        private readonly VerrouReleveAssistance $verrou,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly ClockInterface $horloge,
    ) {
    }

    /**
     * @param bool $forcer ignore l'intervalle minimum (bouton « Relever maintenant »)
     *
     * @return int|null nombre de mails intégrés, null si la relève n'a pas eu lieu (trop récente ou déjà en cours)
     */
    public function relever(bool $forcer = false): ?int
    {
        $maintenant = $this->horloge->now();
        $derniere = $this->cache->getItem(self::CLE_DERNIERE_RELEVE);

        if (!$forcer && $derniere->isHit() && $maintenant->getTimestamp() - (int) $derniere->get() < self::INTERVALLE_MINIMUM) {
            return null;
        }

        if (!$this->verrou->acquerir()) {
            return null;
        }

        try {
            $this->cache->save($derniere->set($maintenant->getTimestamp()));

            return $this->integration->integrerTout($this->source, $maintenant->modify('-1 day'));
        } finally {
            $this->verrou->liberer();
        }
    }
}
