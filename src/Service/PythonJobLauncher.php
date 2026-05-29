<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/PythonJobLauncher.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/06/2025 16:41
 */

namespace App\Service;

class PythonJobLauncher
{
    private string $pythonBin;
    private string $scriptPath;

    public function __construct(string $projectDir)
    {
        $this->pythonBin = $projectDir . '/python_worker/venv/bin/python3';
        $this->scriptPath = realpath($projectDir . '/python_worker');
    }

    public function launch(string $script, array $params = []): string
    {
        $args = array_map('escapeshellarg', $params);

        $cmd = sprintf(
            '%s %s/%s %s',
            $this->pythonBin,
            $this->scriptPath,
            $script,
            implode(' ', $args)
        );

        // Log de début
        $startTime = microtime(true);
        echo "🔹 Lancement: $cmd\n";

        exec($cmd . ' 2>&1', $output, $returnCode);

        $duration = round(microtime(true) - $startTime, 2);

        // Log de fin
        echo "🔹 Fini en {$duration}s - code retour: $returnCode\n";

        if ($returnCode !== 0) {
            $outputText = implode("\n", $output);
            throw new \RuntimeException(
                "Erreur script Python {$script}:\n$outputText"
            );
        }

        // Retourne la sortie en texte brut (au besoin)
        return implode("\n", $output);
    }
}
