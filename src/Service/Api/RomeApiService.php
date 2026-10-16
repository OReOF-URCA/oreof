<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Entity\Parcours;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RomeApiService
{
    private const DEFAULT_OAUTH_URL = 'https://entreprise.francetravail.fr/connexion/oauth2/access_data?realm=%2Fpartenaire';
    private const DEFAULT_API_URL = 'https://api.francetravail.io/partenaire/rome-fiches-metiers/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConfigured(Parcours $parcours): bool
    {
        return $parcours->hasRomeApi();
    }

    /**
     * @return array{plateforme: string, url: string, login?: string|null, apiKey?: string|null}|null
     */
    public function getConfiguration(Parcours $parcours): ?array
    {
        return $parcours->getRomeApiConfiguration();
    }

    /**
     * Recherche des métiers ROME par code ou mot-clé.
     *
     * @return array<int, array{code: string, libelle: string, famille?: string|null}>
     */
    public function search(Parcours $parcours, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $config = $this->getConfiguration($parcours);
        if ($config === null) {
            return $this->searchLocalFallback($query);
        }

        $cacheKey = 'rome_search_' . md5($query . '_' . ($config['apiKey'] ?? ''));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($config, $query): array {
            $item->expiresAfter(3600); // 1 heure de cache

            try {
                $token = $this->getAccessToken($config);
                $url = rtrim((string) ($config['url'] ?: self::DEFAULT_API_URL), '/');

                // Si la recherche ressemble à un code ROME (ex: M18 ou M1805)
                $isCode = preg_match('/^[A-Z]\d{0,4}$/i', $query) === 1;

                $endpoint = $isCode
                    ? $url . '/fiches-metiers/metier?code=' . urlencode(strtoupper($query))
                    : $url . '/fiches-metiers/metier?libelle=' . urlencode($query);

                $headers = ['Accept' => 'application/json'];
                if ($token !== null) {
                    $headers['Authorization'] = 'Bearer ' . $token;
                } elseif (!empty($config['apiKey'])) {
                    $headers['api-key'] = $config['apiKey'];
                }

                $response = $this->httpClient->request('GET', $endpoint, [
                    'headers' => $headers,
                    'timeout' => 5,
                ]);

                $statusCode = $response->getStatusCode();
                if ($statusCode === 200) {
                    $data = $response->toArray(false);
                    $results = [];
                    foreach ($data as $itemData) {
                        $code = (string) ($itemData['code'] ?? $itemData['codeRome'] ?? '');
                        $libelle = (string) ($itemData['libelle'] ?? $itemData['nom'] ?? '');
                        if ($code !== '') {
                            $results[] = [
                                'code' => strtoupper($code),
                                'libelle' => $libelle !== '' ? $libelle : 'Métier ' . $code,
                                'famille' => $itemData['famille'] ?? null,
                            ];
                        }
                    }

                    if (!empty($results)) {
                        return $results;
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Erreur lors de l\'appel à l\'API ROME 4.0: ' . $e->getMessage());
            }

            // Repli sur le référentiel local si l'appel distant échoue ou ne renvoie rien
            return $this->searchLocalFallback($query);
        });
    }

    /**
     * Vérifie la cohérence d'un ensemble de codes ROME.
     *
     * @param string[] $codes
     * @return array<int, array{code: string, libelle: string, valid: bool, message: string}>
     */
    public function verifyCodes(Parcours $parcours, array $codes): array
    {
        $config = $this->getConfiguration($parcours);
        $results = [];

        foreach ($codes as $rawCode) {
            $code = strtoupper(trim((string) $rawCode));
            if ($code === '') {
                continue;
            }

            $cacheKey = 'rome_verify_' . $code . '_' . ($config ? md5($config['apiKey'] ?? '') : 'noconfig');

            $results[] = $this->cache->get($cacheKey, function (ItemInterface $item) use ($config, $code): array {
                $item->expiresAfter(86400); // 24 heures de cache

                // Contrôle syntaxique ROME (1 lettre + 4 chiffres)
                if (preg_match('/^[A-Z]\d{4}$/', $code) !== 1) {
                    return [
                        'code' => $code,
                        'libelle' => 'Code syntaxiquement invalide',
                        'valid' => false,
                        'message' => 'Format ROME attendu : 1 lettre majuscule suivie de 4 chiffres (ex: M1805).',
                    ];
                }

                if ($config !== null) {
                    try {
                        $token = $this->getAccessToken($config);
                        $url = rtrim((string) ($config['url'] ?: self::DEFAULT_API_URL), '/');
                        $endpoint = $url . '/fiches-metiers/fiche-metier/' . urlencode($code);

                        $headers = ['Accept' => 'application/json'];
                        if ($token !== null) {
                            $headers['Authorization'] = 'Bearer ' . $token;
                        } elseif (!empty($config['apiKey'])) {
                            $headers['api-key'] = $config['apiKey'];
                        }

                        $response = $this->httpClient->request('GET', $endpoint, [
                            'headers' => $headers,
                            'timeout' => 5,
                        ]);

                        $status = $response->getStatusCode();
                        if ($status === 200) {
                            $data = $response->toArray(false);
                            $libelle = (string) ($data['libelle'] ?? $data['nom'] ?? '');

                            return [
                                'code' => $code,
                                'libelle' => $libelle !== '' ? $libelle : $this->getLocalLibelle($code),
                                'valid' => true,
                                'message' => 'Code vérifié et conforme ROME 4.0.',
                            ];
                        }

                        if ($status === 404) {
                            return [
                                'code' => $code,
                                'libelle' => 'Code non répertorié',
                                'valid' => false,
                                'message' => 'Code introuvable dans le référentiel officiel ROME 4.0.',
                            ];
                        }
                    } catch (\Throwable $e) {
                        $this->logger->warning(sprintf('Échec de vérification ROME distant pour %s: %s', $code, $e->getMessage()));
                    }
                }

                // Si pas d'API ou indisponible, validation via le référentiel local
                $localLibelle = $this->getLocalLibelle($code);
                $isRecognized = $localLibelle !== 'Métier ' . $code;

                return [
                    'code' => $code,
                    'libelle' => $localLibelle,
                    'valid' => true,
                    'message' => $isRecognized
                        ? 'Code reconnu dans le référentiel ROME.'
                        : 'Format valide (vérification distante en attente).',
                ];
            });
        }

        return $results;
    }

    /**
     * Obtient un jeton d'accès OAuth2 (avec cache de 25 minutes).
     */
    private function getAccessToken(array $config): ?string
    {
        $clientId = trim((string) ($config['login'] ?? ''));
        $clientSecret = trim((string) ($config['apiKey'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            // Clé d'API simple (sans login / OAuth2)
            return !empty($clientSecret) ? $clientSecret : null;
        }

        $cacheKey = 'rome_oauth_token_' . md5($clientId . '_' . $clientSecret);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($clientId, $clientSecret): ?string {
            $item->expiresAfter(1500); // 25 minutes

            try {
                $response = $this->httpClient->request('POST', self::DEFAULT_OAUTH_URL, [
                    'body' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'scope' => 'api_romev1',
                    ],
                    'timeout' => 5,
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray(false);

                    return $data['access_token'] ?? null;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Impossible de récupérer le token OAuth2 ROME: ' . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Recherche dans la base de référence locale.
     *
     * @return array<int, array{code: string, libelle: string, famille?: string|null}>
     */
    private function searchLocalFallback(string $query): array
    {
        $queryLower = mb_strtolower($query);
        $results = [];

        foreach ($this->getLocalDictionary() as $code => $libelle) {
            if (str_contains(mb_strtolower($code), $queryLower) || str_contains(mb_strtolower($libelle), $queryLower)) {
                $results[] = [
                    'code' => $code,
                    'libelle' => $libelle,
                    'famille' => $this->getFamilleFromCode($code),
                ];
            }
        }

        return array_slice($results, 0, 10);
    }

    private function getLocalLibelle(string $code): string
    {
        $dict = $this->getLocalDictionary();

        return $dict[$code] ?? 'Métier ' . $code;
    }

    private function getFamilleFromCode(string $code): string
    {
        $letter = $code[0] ?? '';

        return match ($letter) {
            'A' => 'Agriculture et Pêche',
            'B' => 'Arts et Façonnage d\'ouvrages d\'art',
            'C' => 'Banque, Assurance, Immobilier',
            'D' => 'Commerce, Vente et Grande distribution',
            'E' => 'Communication, Média et Multimédia',
            'F' => 'Construction, Bâtiment et Travaux publics',
            'G' => 'Hôtellerie-Restauration, Tourisme, Loisirs',
            'H' => 'Industrie',
            'I' => 'Installation et Maintenance',
            'J' => 'Santé',
            'K' => 'Services à la personne et à la collectivité',
            'L' => 'Spectacle',
            'M' => 'Support à l\'entreprise',
            'N' => 'Transport et Logistique',
            default => 'Autre domaine',
        };
    }

    /**
     * Référentiel des codes ROME fréquents de l'enseignement supérieur.
     *
     * @return array<string, string>
     */
    private function getLocalDictionary(): array
    {
        return [
            'M1801' => 'Administration de systèmes d\'information',
            'M1802' => 'Expertise et support en systèmes d\'information',
            'M1803' => 'Direction des systèmes d\'information',
            'M1804' => 'Études et développement de réseaux de télécoms',
            'M1805' => 'Études et développement informatique',
            'M1806' => 'Conseil et maîtrise d\'ouvrage en systèmes d\'information',
            'M1810' => 'Production et exploitation de systèmes d\'information',
            'M1401' => 'Conduite d\'enquêtes',
            'M1402' => 'Conseil en organisation et management d\'entreprise',
            'M1403' => 'Études et prospectives socio-économiques',
            'M1404' => 'Management et gestion d\'enquêtes',
            'M1201' => 'Analyse et ingénierie financière',
            'M1202' => 'Audit et contrôle comptables et financiers',
            'M1203' => 'Comptabilité',
            'M1204' => 'Contrôle de gestion',
            'M1205' => 'Direction administrative et financière',
            'M1206' => 'Management de groupe ou de service comptable',
            'M1207' => 'Trésorerie et financement',
            'M1501' => 'Assistanat en ressources humaines',
            'M1502' => 'Développement des ressources humaines',
            'M1503' => 'Management des ressources humaines',
            'M1701' => 'Administration des ventes',
            'M1702' => 'Analyse de tendance',
            'M1703' => 'Management et gestion de produit',
            'M1705' => 'Marketing',
            'E1101' => 'Animation de site multimédia',
            'E1103' => 'Communication',
            'E1104' => 'Conception de contenus multimédias',
            'E1106' => 'Journalisme et information média',
            'E1205' => 'Promotion des ventes',
            'E1401' => 'Développement et promotion des ventes',
            'E1402' => 'Gestion de contenu de plateforme en ligne',
            'H1206' => 'Management et ingénierie études, recherche et développement industriel',
            'H1208' => 'Recherche et développement en technologies industrielles',
            'H1401' => 'Management et ingénierie gestion industrielle et logistique',
            'H1402' => 'Management et ingénierie méthodes et industrialisation',
            'H1502' => 'Management et ingénierie qualité industrielle',
            'J1102' => 'Médecine de prévention',
            'J1506' => 'Pharmacie hospitalière',
            'K1201' => 'Action sociale',
            'K1401' => 'Conception et pilotage de la politique des pouvoirs publics',
            'K1403' => 'Management de structure de santé, sociale ou pénitentiaire',
            'K1802' => 'Développement local',
            'K2101' => 'Conseil en formation',
            'K2107' => 'Enseignement général du second degré',
            'K2108' => 'Enseignement supérieur',
            'K2401' => 'Recherche en sciences de l\'homme et de la société',
            'K2402' => 'Recherche en sciences de l\'univers, de la matière et du vivant',
        ];
    }
}
