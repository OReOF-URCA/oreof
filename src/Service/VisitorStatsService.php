<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Service de statistiques de visites lu directement dans les logs Monolog rotatifs.
 *
 * Les fichiers peuvent atteindre plusieurs centaines de Mo : la lecture se fait
 * ligne à ligne (fopen/fgets) avec un préfiltre str_contains avant toute regex.
 */
final class VisitorStatsService
{
    private const TIMEZONE = 'Europe/Paris';

    private const TOP_ROUTES_LIMIT = 15;

    private const TOP_USERS_LIMIT = 10;

    /**
     * Connexion réussie : le contexte contient user=\"admin\"
     */
    private const LOGIN_PATTERN = '/user=\\\\\"([^\"\\\\]+)\\\\\"/';

    /**
     * Page vue : request.INFO: Matched route "app_homepage".
     */
    private const ROUTE_PATTERN = '/Matched route "([^"]+)"/';

    private const TIMESTAMP_PATTERN = '/^\[([^\]]+)\]/';

    public function __construct(
        #[Autowire('%kernel.logs_dir%')] private readonly string $logsDir,
        #[Autowire('%kernel.environment%')] private readonly string  $environment,
        private readonly CacheInterface                         $cache,
    )
    {
    }

    /**
     * Calcule les statistiques de visites sur une période (bornes incluses, comparées par date Y-m-d).
     *
     * @return array{
     *     days: array<string, array{logins: int, uniqueUsers: int, pageViews: int}>,
     *     totals: array{logins: int, uniqueUsers: int, pageViews: int, activeDays: int},
     *     topRoutes: array<string, int>,
     *     topUsers: array<string, int>,
     *     hours: array<int, int>
     * }
     */
    public function getStats(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');

        // Période inversée : on remet les bornes dans l'ordre
        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $days = [];
        $totals = ['logins' => 0, 'uniqueUsers' => 0, 'pageViews' => 0, 'activeDays' => 0];
        $allUsers = [];
        $allRoutes = [];
        $allHours = array_fill(0, 24, 0);

        $current = new \DateTimeImmutable($fromDate);
        $end = new \DateTimeImmutable($toDate);

        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $day = $this->parseDayWithCache($date);

            $logins = $day['logins'];
            $pageViews = $day['pageViews'];

            $days[$date] = [
                'logins' => $logins,
                'uniqueUsers' => count($day['users']),
                'pageViews' => $pageViews,
            ];

            $totals['logins'] += $logins;
            $totals['pageViews'] += $pageViews;
            if ($logins > 0 || $pageViews > 0) {
                ++$totals['activeDays'];
            }

            foreach ($day['users'] as $username => $count) {
                $allUsers[$username] = ($allUsers[$username] ?? 0) + $count;
            }

            foreach ($day['routes'] as $route => $count) {
                $allRoutes[$route] = ($allRoutes[$route] ?? 0) + $count;
            }

            foreach ($day['hours'] as $hour => $count) {
                $allHours[$hour] += $count;
            }

            $current = $current->modify('+1 day');
        }

        $totals['uniqueUsers'] = count($allUsers);

        return [
            'days' => $days,
            'totals' => $totals,
            'topRoutes' => $this->takeTop($allRoutes, self::TOP_ROUTES_LIMIT),
            'topUsers' => $this->takeTop($allUsers, self::TOP_USERS_LIMIT),
            'hours' => $allHours,
        ];
    }

    /**
     * Analyse les deux fichiers de logs d'une journée.
     *
     * @return array{logins: int, users: array<string, int>, pageViews: int, routes: array<string, int>, hours: array<int, int>}
     */
    private function parseDay(string $date): array
    {
        $day = [
            'logins' => 0,
            'users' => [],
            'pageViews' => 0,
            'routes' => [],
            'hours' => [],
        ];

        $this->parseLogins($this->authLogPath($date), $day);
        $this->parsePageViews($this->allLogPath($date), $day);

        return $day;
    }

    /**
     * Les jours strictement antérieurs à aujourd'hui sont mis en cache, le jour courant est toujours recalculé.
     *
     * @return array{logins: int, users: array<string, int>, pageViews: int, routes: array<string, int>, hours: array<int, int>}
     */
    private function parseDayWithCache(string $date): array
    {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y-m-d');

        if ($date >= $today) {
            return $this->parseDay($date);
        }

        $cacheKey = sprintf(
            'visitor_stats_%s_%s_%s',
            $this->environment,
            $date,
            md5($this->fileSignature($this->authLogPath($date)) . $this->fileSignature($this->allLogPath($date)))
        );

        /** @var array{logins: int, users: array<string, int>, pageViews: int, routes: array<string, int>, hours: array<int, int>} $cached */
        $cached = $this->cache->get($cacheKey, fn (): array => $this->parseDay($date));

        return $cached;
    }

    /**
     * Compte les connexions réussies du fichier security du jour.
     *
     * @param array{logins: int, users: array<string, int>, pageViews: int, routes: array<string, int>, hours: array<int, int>} $day
     */
    private function parseLogins(string $path, array &$day): void
    {
        if (!is_readable($path)) {
            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            // Préfiltre avant regex : seules les lignes "Authenticator successful!" sont analysées
            if (!str_contains($line, 'security.INFO: Authenticator successful!')) {
                continue;
            }

            if (preg_match(self::LOGIN_PATTERN, $line, $matches) !== 1) {
                // Aucun identifiant exploitable : ligne ignorée
                continue;
            }

            $username = $matches[1];
            ++$day['logins'];
            $day['users'][$username] = ($day['users'][$username] ?? 0) + 1;
        }

        fclose($handle);
    }

    /**
     * Compte les pages vues GET du fichier all du jour.
     *
     * @param array{logins: int, users: array<string, int>, pageViews: int, routes: array<string, int>, hours: array<int, int>} $day
     */
    private function parsePageViews(string $path, array &$day): void
    {
        if (!is_readable($path)) {
            return;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            if (!str_contains($line, 'request.INFO: Matched route "') || !str_contains($line, '"method":"GET"')) {
                continue;
            }

            if (preg_match(self::ROUTE_PATTERN, $line, $matches) !== 1) {
                continue;
            }

            $route = $matches[1];

            // Routes techniques (profiler, wdt) et composants UX (live, autocomplete) : ignorées
            if (str_starts_with($route, '_') || str_starts_with($route, 'ux_')) {
                continue;
            }

            ++$day['pageViews'];
            $day['routes'][$route] = ($day['routes'][$route] ?? 0) + 1;

            $hour = $this->extractHour($line);
            if ($hour !== null) {
                $day['hours'][$hour] = ($day['hours'][$hour] ?? 0) + 1;
            }
        }

        fclose($handle);
    }

    /**
     * Heure (Europe/Paris) du timestamp Monolog en début de ligne.
     */
    private function extractHour(string $line): ?int
    {
        if (preg_match(self::TIMESTAMP_PATTERN, $line, $matches) !== 1) {
            return null;
        }

        try {
            $timestamp = new \DateTimeImmutable($matches[1]);
        } catch (\Exception) {
            return null;
        }

        return (int) $timestamp->setTimezone(new \DateTimeZone(self::TIMEZONE))->format('G');
    }

    /**
     * Trie par valeur décroissante et ne conserve que les $limit premiers éléments.
     *
     * @param array<string, int> $counts
     *
     * @return array<string, int>
     */
    private function takeTop(array $counts, int $limit): array
    {
        arsort($counts);

        return array_slice($counts, 0, $limit, true);
    }

    private function authLogPath(string $date): string
    {
        return sprintf('%s/%s.auth-%s.log', $this->logsDir, $this->environment, $date);
    }

    private function allLogPath(string $date): string
    {
        return sprintf('%s/%s.all-%s.log', $this->logsDir, $this->environment, $date);
    }

    /**
     * Empreinte d'un fichier de log (mtime + taille) pour invalider le cache, 0 si absent.
     */
    private function fileSignature(string $path): string
    {
        if (!is_file($path)) {
            return '0';
        }

        clearstatcache(true, $path);

        return sprintf('%d-%d', (int) filemtime($path), (int) filesize($path));
    }
}
