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
            ['logins' => 3, 'uniqueUsers' => 2, 'pageViews' => 1],
            $stats['days']['2025-10-01']
        );
        self::assertSame(
            ['logins' => 0, 'uniqueUsers' => 0, 'pageViews' => 0],
            $stats['days']['2025-10-02']
        );

        self::assertSame(
            ['logins' => 3, 'uniqueUsers' => 2, 'pageViews' => 1, 'activeDays' => 1],
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
}
