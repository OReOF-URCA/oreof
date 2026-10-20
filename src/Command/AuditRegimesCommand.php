<?php

declare(strict_types=1);

namespace App\Command;

use App\Enums\RegimeInscriptionEnum;
use App\Referentiel\ReferentielManager;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Read-only inventory before migrating consumers of historical regime values.
 * No existing JSON column is rewritten.
 */
#[AsCommand(name: 'app:referentiels:audit-regimes', description: 'Audite les régimes stockés dans les formations, parcours et années.')]
final class AuditRegimesCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ReferentielManager $referentiels,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $known = array_fill_keys(array_keys($this->referentiels->choices('regime_inscription')), true);
        foreach (RegimeInscriptionEnum::cases() as $case) {
            $known[$case->value] = true;
        }

        $unknown = [];
        $invalid = [];
        $total = 0;
        foreach (['formation', 'parcours', 'annee'] as $table) {
            foreach ($this->connection->executeQuery('SELECT id, regime_inscription FROM ' . $table . ' WHERE regime_inscription IS NOT NULL')->iterateAssociative() as $row) {
                $values = json_decode((string) $row['regime_inscription'], true);
                if (!is_array($values) || !array_is_list($values)) {
                    $invalid[] = $table . '#' . $row['id'];
                    continue;
                }
                foreach ($values as $value) {
                    ++$total;
                    if (!is_string($value)) {
                        $invalid[] = $table . '#' . $row['id'];
                    } elseif (!isset($known[$value])) {
                        $unknown[$value] = ($unknown[$value] ?? 0) + 1;
                    }
                }
            }
        }

        $io->writeln(sprintf('Valeurs examinées : %d', $total));
        foreach ($unknown as $value => $count) {
            $io->warning(sprintf('Valeur hors référentiel : %s (%d occurrences)', $value, $count));
        }
        foreach ($invalid as $source) {
            $io->warning('JSON invalide ou non conforme : ' . $source);
        }
        if ($unknown === [] && $invalid === []) {
            $io->success('Aucune anomalie détectée. Aucune donnée modifiée.');
        }
        return $unknown === [] && $invalid === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
