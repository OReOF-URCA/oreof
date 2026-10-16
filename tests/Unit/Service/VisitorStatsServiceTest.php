<?php

namespace App\Tests\Unit\Service;

use App\Service\VisitorStatsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class VisitorStatsServiceTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        $this->logsDir = sys_get_temp_dir() . '/visitor_stats_' . uniqid();

        if (!mkdir($this->logsDir, 0o777, true) && !is_dir($this->logsDir)) {
            self::fail('Impossible de créer le répertoire temporaire des logs.');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logsDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->logsDir)) {
            rmdir($this->logsDir);
        }
    }

    public function testGetStatsAggregatesTwoDays(): void
    {
        $this->writeAuthLog('2025-10-01', [
            '[2025-10-01T07:05:12.123456+00:00] security.INFO: Authenticator successful! {"token":{"Symfony\\\\Component\\\\Security\\\\Http\\\\Authenticator\\\\Token\\\\PostAuthenticationToken":"PostAuthenticationToken(user=\"admin\", roles=\"ROLE_ADMIN, ROLE_LECTEUR\")"},"authenticator":"App\\\\Security\\\\LoginFormAuthenticator"} []',
            '[2025-10-01T09:11:51.856794+00:00] security.INFO: Authenticator successful! {"token":{"Symfony\\\\Component\\\\Security\\\\Http\\\\Authenticator\\\\Token\\\\PostAuthenticationToken":"PostAuthenticationToken(user=\"admin\", roles=\"ROLE_ADMIN\")"},"authenticator":"App\\\\Security\\\\LoginFormAuthenticator"} []',
            '[2025-10-01T14:02:03.000000+00:00] security.INFO: Authenticator successful! {"token":{"Symfony\\\\Component\\\\Security\\\\Http\\\\Authenticator\\\\Token\\\\PostAuthenticationToken":"PostAuthenticationToken(user=\"bob\", roles=\"ROLE_LECTEUR\")"},"authenticator":"App\\\\Security\\\\LoginFormAuthenticator"} []',
            // Ligne à ignorer : simple changement d'utilisateur
            '[2025-10-01T14:05:00.000000+00:00] security.INFO: Attempting to switch to user. {"username":"bob"} []',
        ]);

        $this->writeAllLog('2025-10-01', [
            '[2025-10-01T07:10:57.736757+00:00] request.INFO: Matched route "app_homepage". {"route":"app_homepage","route_parameters":{"_route":"app_homepage","_controller":"App\\\\Controller\\\\DefaultController::index"},"request_uri":"http://localhost:8821/","method":"GET"} []',
            '[2025-10-01T07:11:02.100000+00:00] request.INFO: Matched route "_wdt". {"route":"_wdt","route_parameters":{"_route":"_wdt","_controller":"Symfony\\\\Bundle\\\\WebProfilerBundle\\\\Controller\\\\ProfilerController::toolbarAction"},"request_uri":"http://localhost:8821/_wdt","method":"GET"} []',
            '[2025-10-01T07:11:09.200000+00:00] request.INFO: Matched route "ux_live_component". {"route":"ux_live_component","route_parameters":{"_route":"ux_live_component"},"request_uri":"http://localhost:8821/ux/live_component","method":"GET"} []',
            // Ligne à ignorer : requête non GET
            '[2025-10-01T07:12:00.000000+00:00] request.INFO: Matched route "app_login". {"route":"app_login","route_parameters":{"_route":"app_login"},"request_uri":"http://localhost:8821/login","method":"POST"} []',
        ]);

        // Jour 2 : aucun fichier de log
        $service = $this->createService();

        $stats = $service->getStats(new \DateTimeImmutable('2025-10-01'), new \DateTimeImmutable('2025-10-02'));

        self::assertSame(['2025-10-01', '2025-10-02'], array_keys($stats['days']));
        self::assertSame(
            ['logins' => 3, 'uniqueUsers' => 2, 'pageViews' => 1, 'machineHits' => 0, 'estimated' => true],
            $stats['days']['2025-10-01']
        );
        self::assertSame(
            ['logins' => 0, 'uniqueUsers' => 0, 'pageViews' => 0, 'machineHits' => 0, 'estimated' => false],
            $stats['days']['2025-10-02']
        );

        self::assertSame(
            ['logins' => 3, 'uniqueUsers' => 2, 'pageViews' => 1, 'machineHits' => 0, 'estimatedDays' => 1, 'activeDays' => 1],
            $stats['totals']
        );

        self::assertSame('admin', array_key_first($stats['topUsers']));
        self::assertSame(2, $stats['topUsers']['admin']);
        self::assertSame(1, $stats['topUsers']['bob']);

        self::assertSame(['app_homepage' => 1], $stats['topRoutes']);

        self::assertCount(24, $stats['hours']);
        self::assertSame(1, $stats['hours'][9]);
        self::assertSame(0, $stats['hours'][7]);
    }

    public function testGetStatsInvertsPeriodWhenFromIsAfterTo(): void
    {
        $this->writeAuthLog('2025-10-01', [
            '[2025-10-01T07:05:12.123456+00:00] security.INFO: Authenticator successful! {"token":{"Symfony\\\\Component\\\\Security\\\\Http\\\\Authenticator\\\\Token\\\\PostAuthenticationToken":"PostAuthenticationToken(user=\"admin\", roles=\"ROLE_ADMIN\")"},"authenticator":"App\\\\Security\\\\LoginFormAuthenticator"} []',
        ]);

        $service = $this->createService();

        $stats = $service->getStats(new \DateTimeImmutable('2025-10-02'), new \DateTimeImmutable('2025-10-01'));

        self::assertSame(['2025-10-01', '2025-10-02'], array_keys($stats['days']));
        self::assertSame(1, $stats['totals']['logins']);
        self::assertSame(1, $stats['totals']['uniqueUsers']);
        self::assertSame(1, $stats['totals']['activeDays']);
    }

    public function testLegacyDaySeparatesMachineRoutesAndAuthFlow(): void
    {
        $line = static fn (string $route, string $uri): string => sprintf(
            '[2025-10-01T07:10:57.736757+00:00] request.INFO: Matched route "%1$s". {"route":"%1$s","route_parameters":{"_route":"%1$s"},"request_uri":"%2$s","method":"GET"} []',
            $route,
            $uri
        );

        $this->writeAllLog('2025-10-01', [
            $line('app_fiche_matiere_show', 'http://localhost:8821/fiche/matiere/abc'),
            $line('app_export_index', 'http://localhost:8821/export/'),
            $line('app_parcours_export_json_urca', 'http://localhost:8821/parcours/1/export-json-urca'),
            $line('app_parcours_mccc_export', 'http://localhost:8821/parcours/mccc/export/1.pdf'),
            $line('app_parcours_maquette_iframe', 'http://localhost:8821/parcours/1/maquette_iframe'),
            $line('some_api_route', 'http://localhost:8821/api/site/web/formations'),
            $line('app_login', 'http://localhost:8821/connexion'),
            $line('cas_return', 'http://localhost:8821/sso/cas/return'),
        ]);

        $stats = $this->createService()->getStats(new \DateTimeImmutable('2025-10-01'), new \DateTimeImmutable('2025-10-01'));

        self::assertSame(2, $stats['totals']['pageViews']);
        self::assertSame(['app_fiche_matiere_show' => 1, 'app_export_index' => 1], $stats['topRoutes']);
        self::assertSame(4, $stats['totals']['machineHits']);
        self::assertSame(1, $stats['topMachineRoutes']['app_parcours_export_json_urca']);
        self::assertArrayHasKey('some_api_route', $stats['topMachineRoutes']);
    }

    public function testVisitLogIsAuthoritativeAndOverridesRequestLog(): void
    {
        $this->writeVisitLog('2025-10-01', [
            '[2025-10-01T07:10:57.736757+00:00] visit.INFO: Page vue {"route":"app_homepage","user":"alice"} []',
            '[2025-10-01T13:00:00.000000+00:00] visit.INFO: Page vue {"route":"app_homepage","user":"bob"} []',
            '[2025-10-01T13:05:00.000000+00:00] visit.INFO: Page vue {"route":"app_parcours_show","user":"alice"} []',
            'ligne invalide',
        ]);
        // Le log all (robots inclus) ne compte plus comme pages vues, seuls les exports sont conservés à part
        $this->writeAllLog('2025-10-01', [
            '[2025-10-01T07:10:57.736757+00:00] request.INFO: Matched route "app_fiche_matiere_show". {"route":"app_fiche_matiere_show","request_uri":"http://localhost:8821/fiche/matiere/abc","method":"GET"} []',
            '[2025-10-01T07:10:58.000000+00:00] request.INFO: Matched route "app_parcours_export". {"route":"app_parcours_export","request_uri":"http://localhost:8821/parcours/1/export-pdf","method":"GET"} []',
        ]);

        $stats = $this->createService()->getStats(new \DateTimeImmutable('2025-10-01'), new \DateTimeImmutable('2025-10-01'));

        self::assertSame(
            ['logins' => 0, 'uniqueUsers' => 2, 'pageViews' => 3, 'machineHits' => 1, 'estimated' => false],
            $stats['days']['2025-10-01']
        );
        self::assertSame(['app_homepage' => 2, 'app_parcours_show' => 1], $stats['topRoutes']);
        self::assertSame(['app_parcours_export' => 1], $stats['topMachineRoutes']);
        self::assertSame(0, $stats['totals']['estimatedDays']);
        self::assertSame(1, $stats['hours'][9]);
        self::assertSame(2, $stats['hours'][15]);
        self::assertSame(2, $stats['totals']['uniqueUsers']);
    }

    private function createService(): VisitorStatsService
    {
        return new VisitorStatsService($this->logsDir, 'test', new ArrayAdapter());
    }

    /**
     * @param array<int, string> $lines
     */
    private function writeAuthLog(string $date, array $lines): void
    {
        file_put_contents($this->logsDir . '/test.auth-' . $date . '.log', implode("\n", $lines) . "\n");
    }

    /**
     * @param array<int, string> $lines
     */
    private function writeAllLog(string $date, array $lines): void
    {
        file_put_contents($this->logsDir . '/test.all-' . $date . '.log', implode("\n", $lines) . "\n");
    }

    /**
     * @param array<int, string> $lines
     */
    private function writeVisitLog(string $date, array $lines): void
    {
        file_put_contents($this->logsDir . '/test.visit-' . $date . '.log', implode("\n", $lines) . "\n");
    }
}
