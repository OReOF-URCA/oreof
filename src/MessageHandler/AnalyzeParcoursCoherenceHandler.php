<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnalyzeParcoursCoherence;
use App\Repository\ParcoursRepository;
use App\Service\Coherence\CoherenceAnalyzerService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AnalyzeParcoursCoherenceHandler
{
    public function __construct(
        private ParcoursRepository       $parcoursRepository,
        private CoherenceAnalyzerService $coherenceAnalyzer,
    )
    {
    }

    public function __invoke(AnalyzeParcoursCoherence $message): void
    {
        $parcours = $this->parcoursRepository->find($message->parcoursId);
        if ($parcours === null) {
            return;
        }

        $this->coherenceAnalyzer->analyzeParcours(
            $parcours,
            useAi: $message->useAi,
            forceRefresh: $message->forceRefresh,
            persist: true,
        );
    }
}
