<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Repository\EtablissementRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class LlmClient
{
    public function __construct(
        private readonly HttpClientInterface      $httpClient,
        private readonly ?EtablissementRepository $etablissementRepository = null,
        #[Autowire('%env(default::AI_API_URL)%')]
        private readonly ?string                  $envAiUrl = null,
        #[Autowire('%env(default::AI_API_KEY)%')]
        private readonly ?string                  $envAiKey = null,
        #[Autowire('%env(default::AI_MODEL)%')]
        private readonly ?string                  $envAiModel = null,
    )
    {
    }

    /**
     * @return array{url: ?string, apiKey: ?string, model: ?string, source: string}
     */
    public function resolveConfig(): array
    {
        $etablissement = $this->etablissementRepository?->findOneBy([]);
        $etabConfig = $etablissement?->getAiApiConfiguration();

        if ($etabConfig !== null && !empty($etabConfig['url'])) {
            $url = trim((string)$etabConfig['url']);
            if (!str_ends_with($url, '/chat/completions')) {
                $url = rtrim($url, '/') . '/chat/completions';
            }

            $apiKey = !empty($etabConfig['apiKey']) ? trim((string)$etabConfig['apiKey']) : null;
            $model = !empty($etabConfig['login']) ? trim((string)$etabConfig['login']) : null;
            if ($model === null && !empty($this->envAiModel)) {
                $model = trim((string)$this->envAiModel);
            }

            return [
                'url' => $url,
                'apiKey' => $apiKey,
                'model' => $model ?: 'default',
                'source' => sprintf('Établissement (%s)', $etabConfig['plateforme']),
            ];
        }

        $envUrl = trim((string)$this->envAiUrl);
        $envKey = trim((string)$this->envAiKey);
        $envModel = trim((string)$this->envAiModel);

        if ($envUrl !== '') {
            if (!str_ends_with($envUrl, '/chat/completions')) {
                $envUrl = rtrim($envUrl, '/') . '/chat/completions';
            }

            return [
                'url' => $envUrl,
                'apiKey' => $envKey !== '' && $envKey !== 'change-me' ? $envKey : null,
                'model' => $envModel !== '' ? $envModel : 'default',
                'source' => 'Environnement (.env)',
            ];
        }

        return [
            'url' => null,
            'apiKey' => null,
            'model' => null,
            'source' => 'none',
        ];
    }

    public function isConfigured(): bool
    {
        $config = $this->resolveConfig();
        if ($config['url'] === null) {
            return false;
        }

        if ($config['apiKey'] !== null) {
            return true;
        }

        $url = $config['url'];

        return str_contains($url, 'localhost')
            || str_contains($url, '127.0.0.1')
            || str_contains($url, 'host.docker.internal');
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @return array{content: string, model: string, source: string}
     */
    public function chatCompletion(
        array $messages,
        float $temperature = 0.15,
        int   $maxTokens = 1200,
        float $timeout = 25.0,
    ): array
    {
        $config = $this->resolveConfig();
        $apiUrl = $config['url'];
        if ($apiUrl === null) {
            throw new \RuntimeException('URL de l\'API IA non configurée.');
        }

        $model = $config['model'] ?: 'default';
        $apiKey = $config['apiKey'];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        if ($apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = $this->httpClient->request('POST', $apiUrl, [
            'headers' => $headers,
            'timeout' => $timeout,
            'json' => [
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'messages' => $messages,
            ],
        ]);

        $data = $response->toArray();
        $rawText = trim((string)($data['choices'][0]['message']['content'] ?? ''));

        if ($rawText === '') {
            throw new \RuntimeException('Réponse vide du modèle IA.');
        }

        return [
            'content' => $rawText,
            'model' => $model,
            'source' => $config['source'],
        ];
    }
}
