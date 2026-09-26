<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

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
    public function testParameterlessGetRoutesDoNotBreak(): void
    {
        $client = static::createClient();
        $router = static::getContainer()->get(RouterInterface::class);

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

            $variables = $route->compile()->getVariables();
            $missingVariables = array_values(array_filter(
                $variables,
                static fn (string $variable): bool => !$route->hasDefault($variable)
            ));

            if ([] !== $missingVariables) {
                $skipped[$name] = 'requires parameters: '.implode(', ', $missingVariables);
                continue;
            }

            try {
                $url = $router->generate($name, [], UrlGeneratorInterface::ABSOLUTE_PATH);
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

        self::assertGreaterThan(0, $tested, 'No application route was smoke-tested.');
        self::assertSame(
            [],
            $failures,
            sprintf(
                "Smoke-tested %d routes; skipped %d.\n%s",
                $tested,
                count($skipped),
                implode("\n", $failures)
            )
        );
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
