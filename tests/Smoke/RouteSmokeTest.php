<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\Entity\CampagneCollecte;
use App\Entity\User;
use App\Tests\Support\RouteParameterResolver;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class RouteSmokeTest extends WebTestCase
{
    public function testResolvableGetRoutesDoNotBreak(): void
    {
        $client = static::createClient();
        $router = static::getContainer()->get(RouterInterface::class);
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $resolver = new RouteParameterResolver($entityManager);

        $admin = $entityManager->getRepository(User::class)->findOneBy(['username' => 'admin-test']);
        self::assertNotNull($admin, 'Functional fixtures are not loaded.');
        $client->loginUser($admin);
        $client->request('GET', '/');

        $campaign = $entityManager->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => true]);
        self::assertNotNull($campaign, 'Default test campaign is not loaded.');
        $client->getRequest()->getSession()->set('campagneCollecte', $campaign->getId());

        $known = $this->routeList(__DIR__.'/known-failures.txt');
        $excluded = $this->routeList(__DIR__.'/excluded-routes.txt');

        $getCandidates = 0;
        $testedRoutes = [];
        $skipped = [];
        $excludedRoutes = [];
        $failures = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            if ($this->isTechnicalRoute($name, $route->getPath())) {
                continue;
            }

            $methods = $route->getMethods();
            if ([] !== $methods && !in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
                $skipped[$name] = 'not a GET/HEAD route';
                continue;
            }

            ++$getCandidates;

            if (isset($excluded[$name])) {
                $excludedRoutes[$name] = $excluded[$name];
                continue;
            }

            $resolution = $resolver->resolve($route);
            if ([] !== $resolution['unresolved']) {
                $skipped[$name] = 'unresolved parameters: '.implode(', ', $resolution['unresolved']);
                continue;
            }

            try {
                $url = $router->generate($name, $resolution['parameters'], UrlGeneratorInterface::ABSOLUTE_PATH);
                $this->request($client, $url);
                $testedRoutes[$name] = true;

                $status = $client->getResponse()->getStatusCode();
                if (404 === $status || $status >= 500) {
                    $details = '';
                    if ($status >= 500) {
                        $body = trim((string) preg_replace('/\\s+/', ' ', strip_tags($client->getResponse()->getContent())));
                        $details = '' !== $body ? ' — '.mb_substr($body, 0, 500) : '';
                    }

                    $failures[$name] = sprintf('%s (%s) returned HTTP %d%s', $name, $url, $status, $details);
                }
            } catch (\Symfony\Component\Routing\Exception\InvalidParameterException $exception) {
                $skipped[$name] = 'cannot be generated without fixture parameters: '.$exception->getMessage();
            } catch (\Throwable $exception) {
                $testedRoutes[$name] = true;
                $failures[$name] = sprintf(
                    '%s failed with %s: %s',
                    $name,
                    $exception::class,
                    $exception->getMessage()
                );
            }
        }

        $knownObserved = array_intersect_key($failures, $known);
        $newFailures = array_diff_key($failures, $known);
        $recoveredKnown = array_diff_key(array_intersect_key($known, $testedRoutes), $failures);

        $skipReasons = [];
        foreach ($skipped as $reason) {
            $category = str_starts_with($reason, 'unresolved parameters:')
                ? $reason
                : explode(':', $reason, 2)[0];
            $skipReasons[$category] = ($skipReasons[$category] ?? 0) + 1;
        }
        arsort($skipReasons);

        fwrite(STDOUT, sprintf(
            "\nRoute smoke coverage\n--------------------\nGET/HEAD candidates      : %d\nTested                   : %d\nPassed                   : %d\nKnown failures observed  : %d\nExplicit exclusions      : %d\nSkipped / unresolved     : %d\nNew failures             : %d\nRecovered known failures : %d\n",
            $getCandidates,
            count($testedRoutes),
            count($testedRoutes) - count($failures),
            count($knownObserved),
            count($excludedRoutes),
            count($skipped),
            count($newFailures),
            count($recoveredKnown)
        ));

        if ([] !== $excludedRoutes) {
            fwrite(STDOUT, "\nExplicit exclusions:\n");
            foreach ($excludedRoutes as $name => $reason) {
                fwrite(STDOUT, sprintf("  - %s: %s\n", $name, $reason));
            }
        }

        if ([] !== $skipReasons) {
            fwrite(STDOUT, "\nSkipped: ".implode(', ', array_map(
                static fn (string $reason, int $count): string => sprintf('%s (%d)', $reason, $count),
                array_keys($skipReasons),
                array_values($skipReasons)
            ))."\n");
        }

        if ([] !== $recoveredKnown) {
            fwrite(STDOUT, "\nKNOWN FAILURES NOW PASS — remove them from tests/Smoke/known-failures.txt:\n");
            foreach (array_keys($recoveredKnown) as $name) {
                fwrite(STDOUT, sprintf("  - %s\n", $name));
            }
        }

        self::assertGreaterThan(0, count($testedRoutes), 'No application route was smoke-tested.');

        $problems = [];
        if ([] !== $newFailures) {
            $problems[] = "New failures (not in tests/Smoke/known-failures.txt):\n".implode("\n", $newFailures);
        }
        if ([] !== $recoveredKnown) {
            $problems[] = "Known failures now pass and must be removed from tests/Smoke/known-failures.txt:\n".implode("\n", array_keys($recoveredKnown));
        }

        self::assertSame([], $problems, implode("\n\n", $problems));
    }

    /**
     * Format: route_name | category | reason
     * The category/reason columns are optional for backward compatibility.
     *
     * @return array<string, string>
     */
    private function routeList(string $file): array
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $routes = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 3));
            $name = $parts[0];
            $description = implode(' — ', array_filter(array_slice($parts, 1)));
            $routes[$name] = '' !== $description ? $description : 'no reason documented';
        }

        return $routes;
    }

    private function request(KernelBrowser $client, string $url): void
    {
        $client->request('GET', $url);
    }

    private function isTechnicalRoute(string $name, string $path): bool
    {
        return in_array($name, ['cas_return'], true)
            || str_starts_with($name, '_')
            || str_starts_with($path, '/_wdt')
            || str_starts_with($path, '/_profiler');
    }
}
