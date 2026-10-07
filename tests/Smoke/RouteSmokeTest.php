<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\Entity\User;
use App\Entity\CampagneCollecte;
use App\Tests\Support\RouteParameterResolver;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class RouteSmokeTest extends WebTestCase
{
    /**
     * Smoke-test every GET route that can be generated without inventing
     * parameters. Redirects and authorization responses are valid here:
     * this test is intended to catch broken routes and server errors.
     */
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

        $tested = 0;
        $skipped = [];
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

            $resolution = $resolver->resolve($route);
            if ([] !== $resolution['unresolved']) {
                $skipped[$name] = 'unresolved parameters: '.implode(', ', $resolution['unresolved']);
                continue;
            }

            try {
                $url = $router->generate($name, $resolution['parameters'], UrlGeneratorInterface::ABSOLUTE_PATH);
                $this->request($client, $url);
                ++$tested;

                $status = $client->getResponse()->getStatusCode();
                if (404 === $status || $status >= 500) {
                    $details = '';
                    if ($status >= 500) {
                        $body = trim((string) preg_replace('/\\s+/', ' ', strip_tags($client->getResponse()->getContent())));
                        $details = '' !== $body ? ' — '.mb_substr($body, 0, 500) : '';
                    }

                    $failures[] = sprintf('%s (%s) returned HTTP %d%s', $name, $url, $status, $details);
                }
            } catch (\Symfony\Component\Routing\Exception\InvalidParameterException $exception) {
                $skipped[$name] = 'cannot be generated without fixture parameters: '.$exception->getMessage();
            } catch (\Throwable $exception) {
                $failures[] = sprintf(
                    '%s failed with %s: %s',
                    $name,
                    $exception::class,
                    $exception->getMessage()
                );
            }
        }

        $skipReasons = [];
        foreach ($skipped as $reason) {
            $category = str_starts_with($reason, 'unresolved parameters:')
                ? $reason
                : explode(':', $reason, 2)[0];
            $skipReasons[$category] = ($skipReasons[$category] ?? 0) + 1;
        }
        arsort($skipReasons);

        fwrite(STDOUT, sprintf(
            "\nRoute smoke coverage: %d tested / %d skipped.\nSkipped: %s\n",
            $tested,
            count($skipped),
            implode(', ', array_map(
                static fn (string $reason, int $count): string => sprintf('%s (%d)', $reason, $count),
                array_keys($skipReasons),
                array_values($skipReasons)
            ))
        ));

        $known = $this->knownFailures();
        $newFailures = array_values(array_filter(
            $failures,
            static fn (string $failure): bool => !in_array(strtok($failure, ' '), $known, true)
        ));
        fwrite(STDOUT, sprintf(
            "Known failures tolerated: %d (tests/Smoke/known-failures.txt) / new failures: %d.\n",
            count($failures) - count($newFailures),
            count($newFailures)
        ));

        self::assertGreaterThan(0, $tested, 'No application route was smoke-tested.');
        self::assertSame(
            [],
            $newFailures,
            sprintf(
                "Smoke-tested %d routes; skipped %d. New failures (not in tests/Smoke/known-failures.txt):\n%s",
                $tested,
                count($skipped),
                implode("\n", $newFailures)
            )
        );
    }

    /**
     * @return list<string>
     */
    private function knownFailures(): array
    {
        $lines = file(__DIR__.'/known-failures.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            static fn (string $line): bool => '' !== $line && !str_starts_with($line, '#')
        ));
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
