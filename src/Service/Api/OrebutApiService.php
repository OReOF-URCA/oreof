<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Entity\Etablissement;
use App\Entity\Formation;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class OrebutApiService
{
    public const DEFAULT_API_URL = 'https://orebut.iut.fr/api';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConfigured(Formation|Etablissement $subject): bool
    {
        return $subject->hasOrebutApi();
    }

    /**
     * @return array{plateforme: string, url: string, login?: string|null, apiKey?: string|null}|null
     */
    public function getConfiguration(Formation|Etablissement $subject): ?array
    {
        return $subject->getOrebutApiConfiguration();
    }

    public function getApiUrl(Formation|Etablissement|null $subject = null): string
    {
        if ($subject instanceof Formation) {
            return $subject->getOrebutApiUrl();
        }

        if ($subject instanceof Etablissement) {
            return $subject->getOrebutApiUrl();
        }

        return self::DEFAULT_API_URL;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(Formation|Etablissement|null $subject = null, string $accept = 'application/json'): array
    {
        $headers = [
            'Accept' => $accept,
        ];

        $config = null;
        if ($subject instanceof Formation) {
            $config = $subject->getOrebutApiConfiguration();
        } elseif ($subject instanceof Etablissement) {
            $config = $subject->getOrebutApiConfiguration();
        }

        if ($config !== null && !empty($config['apiKey'])) {
            $headers['Authorization'] = 'Bearer ' . trim((string) $config['apiKey']);
            $headers['X-API-KEY'] = trim((string) $config['apiKey']);
        }

        return $headers;
    }

    /**
     * Teste la connectivité avec l'API ORéBUT.
     */
    public function testConnection(Formation|Etablissement|null $subject = null): bool
    {
        try {
            $url = $this->getApiUrl($subject);
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $this->buildHeaders($subject, 'application/json'),
                'timeout' => 5,
            ]);

            return $response->getStatusCode() === 200;
        } catch (Throwable $e) {
            $this->logger->warning('ORéBUT API connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère la liste de tous les départements (spécialités nationales).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDepartements(Formation|Etablissement|null $subject = null): array
    {
        $url = $this->getApiUrl($subject) . '/departements';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $this->buildHeaders($subject, 'application/json'),
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray(false);
            if (isset($data['hydra:member']) && is_array($data['hydra:member'])) {
                return $data['hydra:member'];
            }

            return $data;
        } catch (Throwable $e) {
            $this->logger->error('Erreur lors de la récupération des départements ORéBUT: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère les informations d'un département donné (ex: INFO, CJ, CS...).
     *
     * @return array<string, mixed>|null
     */
    public function getDepartement(string $sigle, Formation|Etablissement|null $subject = null, ?int $annee = null): ?array
    {
        $cleanSigle = strtolower(trim($sigle));
        if ($cleanSigle === '') {
            return null;
        }

        $url = $this->getApiUrl($subject) . '/departements/' . urlencode($cleanSigle);
        if ($annee !== null) {
            $url .= '?annee=' . $annee;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $this->buildHeaders($subject, 'application/json'),
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 404) {
                return null;
            }

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning(sprintf('ORéBUT: Réponse code %d pour le département %s', $response->getStatusCode(), $cleanSigle));
                return null;
            }

            return $response->toArray(false);
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Erreur ORéBUT getDepartement(%s): %s', $cleanSigle, $e->getMessage()));
            return null;
        }
    }

    /**
     * Récupère les compétences de la spécialité.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCompetences(string $sigle, Formation|Etablissement|null $subject = null, ?int $annee = null): array
    {
        $cleanSigle = strtolower(trim($sigle));
        if ($cleanSigle === '') {
            return [];
        }

        $url = $this->getApiUrl($subject) . '/specialite/' . urlencode($cleanSigle) . '/competences?page=1';
        if ($annee !== null) {
            $url .= '&annee=' . $annee;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $this->buildHeaders($subject, 'application/ld+json'),
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray(false);
            return $data['hydra:member'] ?? [];
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Erreur ORéBUT getCompetences(%s): %s', $cleanSigle, $e->getMessage()));
            return [];
        }
    }

    /**
     * Récupère toutes les ressources d'une spécialité (avec gestion de la pagination si présente).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRessources(string $sigle, Formation|Etablissement|null $subject = null, ?int $annee = null): array
    {
        return $this->fetchPaginatedEndpoint('ressources', $sigle, $subject, $annee);
    }

    /**
     * Récupère toutes les SAÉs d'une spécialité (avec gestion de la pagination si présente).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSaes(string $sigle, Formation|Etablissement|null $subject = null, ?int $annee = null): array
    {
        return $this->fetchPaginatedEndpoint('saes', $sigle, $subject, $annee);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPaginatedEndpoint(string $endpoint, string $sigle, Formation|Etablissement|null $subject, ?int $annee = null): array
    {
        $cleanSigle = strtolower(trim($sigle));
        if ($cleanSigle === '') {
            return [];
        }

        $baseUrl = $this->getApiUrl($subject);
        $nextUrl = '/specialite/' . urlencode($cleanSigle) . '/' . $endpoint . '?page=1' . ($annee !== null ? '&annee=' . $annee : '');
        $results = [];
        $page = 1;
        $maxPages = 20; // Garde-fou anti-boucle infinie

        try {
            while ($nextUrl !== null && $page <= $maxPages) {
                $targetUrl = str_starts_with($nextUrl, 'http') ? $nextUrl : $baseUrl . (str_starts_with($nextUrl, '/') ? '' : '/') . $nextUrl;

                $response = $this->httpClient->request('GET', $targetUrl, [
                    'headers' => $this->buildHeaders($subject, 'application/ld+json'),
                    'timeout' => 15,
                ]);

                if ($response->getStatusCode() !== 200) {
                    break;
                }

                $data = $response->toArray(false);
                $members = $data['hydra:member'] ?? [];
                if (!is_array($members) || empty($members)) {
                    break;
                }

                foreach ($members as $item) {
                    $results[] = $item;
                }

                $nextUrl = $data['hydra:view']['hydra:next'] ?? null;
                $page++;
            }
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Erreur ORéBUT fetchPaginatedEndpoint(%s, %s): %s', $endpoint, $cleanSigle, $e->getMessage()));
        }

        return $results;
    }
}
