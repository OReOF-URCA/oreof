<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @phpstan-type Day array{
 *     logins: int,
 *     users: array<string, int>,
 *     visitors: array<string, true>,
 *     pageViews: int,
 *     routes: array<string, int>,
 *     hours: array<int, int>,
 *     machineHits: int,
 *     machineRoutes: array<string, int>,
 *     estimated: bool
 * }
 *
 * Service de statistiques de visites lu directement dans les logs Monolog rotatifs.
 *
 * Les fichiers peuvent atteindre plusieurs centaines de Mo : la lecture se fait
 * ligne à ligne (fopen/fgets) avec un préfiltre str_contains avant toute regex.
 *
 * Pages vues : source fiable = log `visit` (VisitLogSubscriber : humains connectés, HTML 200, hors fragments).
 * Pour les jours sans log `visit` (historique), repli sur « Matched route » du log `all`, qui est écrit avant
 * le firewall : robots et visiteurs anonymes y sont comptés. On en retire au moins les exports, API, iframes
 * et routes d'authentification, et le jour est marqué « estimé ».
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

    /**
     * Page vue du log visit : visit.INFO: Page vue {"route":"app_homepage","user":"admin"} []
     */
    private const VISIT_PATTERN = '/visit\.INFO: Page vue (\{.*\}) \[\]\s*$/';

    /**
     * Chemin de la requête (sans hôte) dans le contexte « Matched route » ; sert à repérer /api.
     */
    private const API_URI_PATTERN = '#"request_uri":"[a-z]+://[^/"]+/api[/?"]#';

    /**
     * Routes du flux d'authentification : pas des consultations de contenu.
     */
    private const AUTH_ROUTES = ['app_login', 'app_logout', 'cas_return'];

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
     *     days: array<string, array{logins: int, uniqueUsers: int, pageViews: int, machineHits: int, estimated: bool}>,
     *     totals: array{logins: int, uniqueUsers: int, pageViews: int, machineHits: int, estimatedDays: int, activeDays: int},
     *     topRoutes: array<string, int>,
     *     topMachineRoutes: array<string, int>,
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
        $totals = ['logins' => 0, 'uniqueUsers' => 0, 'pageViews' => 0, 'machineHits' => 0, 'estimatedDays' => 0, 'activeDays' => 0];
        $allUsers = [];
        $allVisitors = [];
        $allRoutes = [];
        $allMachineRoutes = [];
        $allHours = array_fill(0, 24, 0);

        $current = new \DateTimeImmutable($fromDate);
        $end = new \DateTimeImmutable($toDate);

        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $day = $this->parseDayWithCache($date);

            $logins = $day['logins'];
            $pageViews = $day['pageViews'];

            // Utilisateur unique = connecté OU ayant consulté une page (une session SSO ouverte ne génère pas de connexion)
            $dayUsers = $day['users'] + $day['visitors'];

            $days[$date] = [
                'logins' => $logins,
                'uniqueUsers' => count($dayUsers),
                'pageViews' => $pageViews,
                'machineHits' => $day['machineHits'],
                'estimated' => $day['estimated'],
            ];

            $totals['logins'] += $logins;
            $totals['pageViews'] += $pageViews;
            $totals['machineHits'] += $day['machineHits'];
            if ($day['estimated']) {
                ++$totals['estimatedDays'];
            }
            if ($logins > 0 || $pageViews > 0) {
                ++$totals['activeDays'];
            }

            $allVisitors += $day['visitors'];

            foreach ($day['users'] as $username => $count) {
                $allUsers[$username] = ($allUsers[$username] ?? 0) + $count;
            }

            foreach ($day['routes'] as $route => $count) {
                $allRoutes[$route] = ($allRoutes[$route] ?? 0) + $count;
            }

            foreach ($day['machineRoutes'] as $route => $count) {
                $allMachineRoutes[$route] = ($allMachineRoutes[$route] ?? 0) + $count;
            }

            foreach ($day['hours'] as $hour => $count) {
                $allHours[$hour] += $count;
            }

            $current = $current->modify('+1 day');
        }

        $totals['uniqueUsers'] = count($allUsers + $allVisitors);

        return [
            'days' => $days,
            'totals' => $totals,
            'topRoutes' => $this->takeTop($allRoutes, self::TOP_ROUTES_LIMIT),
            'topMachineRoutes' => $this->takeTop($allMachineRoutes, self::TOP_ROUTES_LIMIT),
            'topUsers' => $this->takeTop($allUsers, self::TOP_USERS_LIMIT),
            'hours' => $allHours,
        ];
    }

    /**
     * Analyse les deux fichiers de logs d'une journée.
     *
     * @return Day
     */
    private function parseDay(string $date): array
    {
        $day = [
            'logins' => 0,
            'users' => [],
            'visitors' => [],
            'pageViews' => 0,
            'routes' => [],
            'hours' => [],
            'machineHits' => 0,
            'machineRoutes' => [],
            'estimated' => false,
        ];

        $this->parseLogins($this->authLogPath($date), $day);

        // Log visit présent : pages vues exactes. Sinon, estimation depuis le log all
        $hasVisitLog = is_file($this->visitLogPath($date));
        if ($hasVisitLog) {
            $this->parseVisits($this->visitLogPath($date), $day);
        }
        $this->parseRequests($this->allLogPath($date), $day, !$hasVisitLog);

        return $day;
    }

    /**
     * Les jours strictement antérieurs à aujourd'hui sont mis en cache, le jour courant est toujours recalculé.
     *
     * @return Day
     */
    private function parseDayWithCache(string $date): array
    {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y-m-d');

        if ($date >= $today) {
            return $this->parseDay($date);
        }

        $cacheKey = sprintf(
            'visitor_stats_v2_%s_%s_%s',
            $this->environment,
            $date,
            md5(
                $this->fileSignature($this->authLogPath($date))
                . $this->fileSignature($this->allLogPath($date))
                . $this->fileSignature($this->visitLogPath($date))
            )
        );

        /** @var Day $cached */
        $cached = $this->cache->get($cacheKey, fn (): array => $this->parseDay($date));

        return $cached;
    }

    /**
     * Compte les connexions réussies du fichier security du jour.
     *
     * @param Day $day
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
     * Compte les pages vues humaines du fichier visit du jour (écrit par VisitLogSubscriber).
     *
     * @param Day $day
     */
    private function parseVisits(string $path, array &$day): void
    {
        $handle = is_readable($path) ? fopen($path, 'rb') : false;
        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            if (!str_contains($line, 'visit.INFO: Page vue') || preg_match(self::VISIT_PATTERN, $line, $matches) !== 1) {
                continue;
            }

            $context = json_decode($matches[1], true);
            if (!is_array($context) || !is_string($context['route'] ?? null)) {
                continue;
            }

            $this->countPageView($day, $context['route'], $line);

            if (is_string($context['user'] ?? null) && $context['user'] !== '') {
                $day['visitors'][$context['user']] = true;
            }
        }

        fclose($handle);
    }

    /**
     * Parcourt les requêtes GET du fichier all du jour : exports/API/iframes comptés à part (appels machine) ;
     * les autres routes ne deviennent des pages vues (estimées) que si le jour n'a pas de log visit.
     *
     * @param Day $day
     */
    private function parseRequests(string $path, array &$day, bool $countPageViews): void
    {
        $handle = is_readable($path) ? fopen($path, 'rb') : false;
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

            if ($this->isMachineRoute($route, $line)) {
                ++$day['machineHits'];
                $day['machineRoutes'][$route] = ($day['machineRoutes'][$route] ?? 0) + 1;

                continue;
            }

            if (!$countPageViews || in_array($route, self::AUTH_ROUTES, true)) {
                continue;
            }

            $day['estimated'] = true;
            $this->countPageView($day, $route, $line);
        }

        fclose($handle);
    }

    /**
     * Exports (PDF/JSON/XML), API et iframes : consommés par des scripts, sites tiers ou téléchargements, pas des pages consultées.
     * La page d'interface /export (`app_export_*`) reste une vraie page.
     */
    private function isMachineRoute(string $route, string $line): bool
    {
        if (str_starts_with($route, 'api_') || str_contains($route, 'iframe')) {
            return true;
        }

        if (str_contains($route, 'export') && !str_starts_with($route, 'app_export_')) {
            return true;
        }

        return str_contains($line, '/api') && preg_match(self::API_URI_PATTERN, $line) === 1;
    }

    /**
     * @param Day $day
     */
    private function countPageView(array &$day, string $route, string $line): void
    {
        ++$day['pageViews'];
        $day['routes'][$route] = ($day['routes'][$route] ?? 0) + 1;

        $hour = $this->extractHour($line);
        if ($hour !== null) {
            $day['hours'][$hour] = ($day['hours'][$hour] ?? 0) + 1;
        }
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

    private function visitLogPath(string $date): string
    {
        return sprintf('%s/%s.visit-%s.log', $this->logsDir, $this->environment, $date);
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
