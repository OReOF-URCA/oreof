<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Entity\Etablissement;
use App\Repository\EtablissementRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RncpApiService
{
    private const DEFAULT_API_URL = 'https://api.apprentissage.beta.gouv.fr/api';
    private const FRANCE_COMPETENCES_BASE_URL = 'https://www.francecompetences.fr/recherche/rncp/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly EtablissementRepository $etablissementRepository,
    ) {
    }

    public function getEtablissement(?Etablissement $etablissement = null): ?Etablissement
    {
        if ($etablissement !== null) {
            return $etablissement;
        }

        return $this->etablissementRepository->findOneBy([]);
    }

    /**
     * @return array{plateforme: string, url: string, login?: string|null, apiKey?: string|null}|null
     */
    public function getConfiguration(?Etablissement $etablissement = null): ?array
    {
        return $this->getEtablissement($etablissement)?->getRncpApiConfiguration();
    }

    public function isConfigured(?Etablissement $etablissement = null): bool
    {
        $config = $this->getConfiguration($etablissement);

        return $config !== null && !empty($config['apiKey']);
    }

    /**
     * Normalise un code RNCP en majuscules (ex: "34436" -> "RNCP34436", "rncp 34436" -> "RNCP34436").
     */
    public function normalizeCode(string $code): string
    {
        $cleaned = strtoupper(trim(str_replace(' ', '', $code)));
        if ($cleaned === '') {
            return '';
        }

        if (preg_match('/^\d{3,5}$/', $cleaned) === 1) {
            return 'RNCP' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Vérifie un code RNCP auprès de l'API Apprentissage / France Compétences.
     *
     * @return array{
     *     code: string,
     *     valid: bool,
     *     intitule: ?string,
     *     actif: ?bool,
     *     dateFinValidite: ?string,
     *     dateDebutValidite: ?string,
     *     certificateur: ?string,
     *     niveau: ?string,
     *     url: string,
     *     message: string,
     *     connected: bool
     * }
     */
    public function verify(?Etablissement $etablissement, string $code): array
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === '') {
            return [
                'code' => '',
                'valid' => false,
                'intitule' => null,
                'actif' => null,
                'dateFinValidite' => null,
                'dateDebutValidite' => null,
                'certificateur' => null,
                'niveau' => null,
                'url' => '',
                'message' => 'Aucun code renseigné.',
                'connected' => false,
            ];
        }

        $numericCode = preg_replace('/^RNCP/i', '', $normalized) ?? '';
        $franceCompetencesUrl = self::FRANCE_COMPETENCES_BASE_URL . $numericCode;

        // Contrôle syntaxique : format RNCP suivi de 3 à 5 chiffres
        $syntaxValid = preg_match('/^RNCP\d{3,5}$/i', $normalized) === 1;
        if (!$syntaxValid) {
            return [
                'code' => $normalized,
                'valid' => false,
                'intitule' => null,
                'actif' => null,
                'dateFinValidite' => null,
                'dateDebutValidite' => null,
                'certificateur' => null,
                'niveau' => null,
                'url' => $franceCompetencesUrl,
                'message' => 'Format RNCP invalide (attendu : RNCP suivi de 3 à 5 chiffres, ex: RNCP34436).',
                'connected' => false,
            ];
        }

        $config = $this->getConfiguration($etablissement);
        $apiKey = trim((string) ($config['apiKey'] ?? ''));
        $isConnected = $apiKey !== '';

        $cacheKey = 'rncp_verify_' . md5($normalized . '_' . $apiKey);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($config, $normalized, $franceCompetencesUrl, $isConnected): array {
            $item->expiresAfter(86400); // 24h de cache

            if ($isConnected) {
                try {
                    $url = rtrim((string) (!empty($config['url']) ? $config['url'] : self::DEFAULT_API_URL), '/');
                    $token = trim((string) ($config['apiKey'] ?? ''));

                    $headers = [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ];

                    // 1) Appel principal : GET /certification/v1?identifiant.rncp=RNCPXXXXX
                    $endpoint = $url . '/certification/v1?identifiant.rncp=' . urlencode($normalized);
                    $response = $this->httpClient->request('GET', $endpoint, [
                        'headers' => $headers,
                        'timeout' => 6,
                    ]);

                    if ($response->getStatusCode() === 200) {
                        $data = $response->toArray(false);
                        $certItem = isset($data[0]) && is_array($data[0]) ? $data[0] : (isset($data['identifiant']) ? $data : null);

                        if ($certItem !== null) {
                            $intitule = $certItem['intitule']['rncp']
                                ?? $certItem['intitule']['cfd']['long']
                                ?? $certItem['intitule']['cfd']['court']
                                ?? $certItem['intitule']
                                ?? null;

                            $dateFin = $certItem['periode_validite']['fin']
                                ?? $certItem['date_fin_enregistrement']
                                ?? null;

                            $dateDebut = $certItem['periode_validite']['debut']
                                ?? $certItem['date_debut_enregistrement']
                                ?? null;

                            $isActif = $certItem['actif'] ?? null;
                            if ($isActif === null && $dateFin !== null) {
                                $isActif = strtotime((string) $dateFin) >= time();
                            }

                            $certificateursList = [];
                            if (!empty($certItem['certificateurs']) && is_array($certItem['certificateurs'])) {
                                foreach ($certItem['certificateurs'] as $c) {
                                    $nom = is_array($c) ? ($c['nom'] ?? '') : (string) $c;
                                    if ($nom !== '') {
                                        $certificateursList[] = $nom;
                                    }
                                }
                            }

                            $niveau = $certItem['niveau'] ?? null;

                            return [
                                'code' => $normalized,
                                'valid' => true,
                                'intitule' => is_string($intitule) ? $intitule : null,
                                'actif' => $isActif,
                                'dateFinValidite' => is_string($dateFin) ? $dateFin : null,
                                'dateDebutValidite' => is_string($dateDebut) ? $dateDebut : null,
                                'certificateur' => !empty($certificateursList) ? implode(', ', $certificateursList) : null,
                                'niveau' => is_string($niveau) ? $niveau : null,
                                'url' => $franceCompetencesUrl,
                                'message' => $isActif === false
                                    ? 'Fiche RNCP échue.'
                                    : 'Fiche RNCP active et reconnue.',
                                'connected' => true,
                            ];
                        }
                    }

                    // 2) Fallback : recherche via GET /formation/v1/search?rncp=RNCPXXXXX
                    $searchEndpoint = $url . '/formation/v1/search?rncp=' . urlencode($normalized);
                    $searchResp = $this->httpClient->request('GET', $searchEndpoint, [
                        'headers' => $headers,
                        'timeout' => 6,
                    ]);

                    if ($searchResp->getStatusCode() === 200) {
                        $searchData = $searchResp->toArray(false);
                        $formations = $searchData['formations'] ?? $searchData;
                        if (!empty($formations) && is_array($formations)) {
                            $firstFormation = $formations[0] ?? [];
                            $intitule = $firstFormation['intitule_long']
                                ?? $firstFormation['onisep']['intitule']
                                ?? $firstFormation['titre']
                                ?? null;

                            return [
                                'code' => $normalized,
                                'valid' => true,
                                'intitule' => is_string($intitule) ? $intitule : null,
                                'actif' => true,
                                'dateFinValidite' => null,
                                'dateDebutValidite' => null,
                                'certificateur' => $firstFormation['organisme_formateur']['nom'] ?? null,
                                'niveau' => $firstFormation['niveau'] ?? null,
                                'url' => $franceCompetencesUrl,
                                'message' => 'Fiche RNCP identifiée auprès de l\'API Apprentissage.',
                                'connected' => true,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning(sprintf('Erreur appel API Apprentissage RNCP (%s) : %s', $normalized, $e->getMessage()));
                }
            }

            // Mode repli hors-ligne / format vérifié
            return [
                'code' => $normalized,
                'valid' => true,
                'intitule' => null,
                'actif' => null,
                'dateFinValidite' => null,
                'dateDebutValidite' => null,
                'certificateur' => null,
                'niveau' => null,
                'url' => $franceCompetencesUrl,
                'message' => $isConnected
                    ? 'Code RNCP valide. Fiche non trouvée ou service distant temporairement indisponible.'
                    : 'Format RNCP valide (clé API non configurée dans l\'établissement).',
                'connected' => $isConnected,
            ];
        });
    }

    /**
     * Recherche de certifications par mot-clé ou code.
     *
     * @return array<int, array{code: string, intitule: ?string, actif: ?bool, url: string}>
     */
    public function search(?Etablissement $etablissement, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        // Si la requête ressemble à un code RNCP
        $normalized = $this->normalizeCode($query);
        if (preg_match('/^RNCP\d{3,5}$/i', $normalized) === 1) {
            $verification = $this->verify($etablissement, $normalized);

            return [[
                'code' => $verification['code'],
                'intitule' => $verification['intitule'] ?? 'Certification ' . $verification['code'],
                'actif' => $verification['actif'],
                'url' => $verification['url'],
            ]];
        }

        $config = $this->getConfiguration($etablissement);
        $apiKey = trim((string) ($config['apiKey'] ?? ''));
        if ($apiKey === '') {
            return [];
        }

        $cacheKey = 'rncp_search_' . md5($query . '_' . $apiKey);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($config, $query): array {
            $item->expiresAfter(3600); // 1h de cache

            try {
                $url = rtrim((string) (!empty($config['url']) ? $config['url'] : self::DEFAULT_API_URL), '/');
                $token = trim((string) ($config['apiKey'] ?? ''));

                $endpoint = $url . '/formation/v1/search?query=' . urlencode($query);
                $response = $this->httpClient->request('GET', $endpoint, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'timeout' => 5,
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray(false);
                    $formations = $data['formations'] ?? [];
                    $results = [];
                    $seenCodes = [];

                    foreach ($formations as $formation) {
                        $rncpCode = $formation['rncp_code'] ?? $formation['certification']['rncp'] ?? null;
                        if (!empty($rncpCode) && is_string($rncpCode)) {
                            $norm = $this->normalizeCode($rncpCode);
                            if (!isset($seenCodes[$norm])) {
                                $seenCodes[$norm] = true;
                                $num = preg_replace('/^RNCP/i', '', $norm) ?? '';
                                $results[] = [
                                    'code' => $norm,
                                    'intitule' => $formation['intitule_long'] ?? $formation['onisep']['intitule'] ?? 'Certification ' . $norm,
                                    'actif' => true,
                                    'url' => self::FRANCE_COMPETENCES_BASE_URL . $num,
                                ];
                            }
                        }
                    }

                    return array_slice($results, 0, 8);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Erreur recherche API Apprentissage : ' . $e->getMessage());
            }

            return [];
        });
    }
}
