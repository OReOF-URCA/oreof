<?php

namespace App\MessageHandler;

use App\Entity\GenerationJob;
use App\Message\ProcessGenerationJobMessage;
use App\Service\PythonJobLauncher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

//#[AsMessageHandler]
class ProcessGenerationJobHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private PythonJobLauncher      $pythonJobLauncher
    )
    {
    }

    public function __invoke(ProcessGenerationJobMessage $message): void
    {
        $job = $this->em->getRepository(GenerationJob::class)->find($message->getGenerationJobId());

        if (!$job) {
            throw new \RuntimeException("Job {$message->getGenerationJobId()} not found");
        }

        // Met à jour le status
        $job->setStatus('running');
        $job->setStartedAt(new \DateTime());
        $this->em->flush();

        // Choix du script python
        $script = match ($job->getType()) {
            'formation_export' => 'generate_all_formations.py',
            'compare_snapshots' => 'compare_snapshots.py',
            'generation_pdf' => 'generate_pdf.py',
            default => throw new \RuntimeException("Type {$job->getType()} non supporté")
        };

        // Paramètres à passer
        $args = [
            $job->getId(),  // toujours l'id du job pour le suivi
            // tu peux ajouter ici les params métiers du job :
            // par exemple : $job->getParameters()['year'] ?? ''
        ];

        // Appel du Python
        $output = $this->pythonJobLauncher->launch($script, $args);

        echo "✅ Job {$job->getId()} terminé :\n$output\n";
    }
}
