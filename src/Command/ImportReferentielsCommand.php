<?php
namespace App\Command;

use App\Entity\Referentiel;
use App\Entity\ReferentielValeur;
use App\Enums\ModaliteEnseignementEnum;
use App\Enums\RegimeInscriptionEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:referentiels:import', description: 'Importe les Enum métier dans les référentiels (idempotent).')]
final class ImportReferentielsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->import('modalite_enseignement', 'Modalité d’enseignement', ModaliteEnseignementEnum::cases(), true);
        $this->import('regime_inscription', 'Régime d’inscription', RegimeInscriptionEnum::cases(), true);
        $this->em->flush();
        $output->writeln('<info>Référentiels synchronisés.</info>');
        return Command::SUCCESS;
    }

    /** @param list<\BackedEnum> $cases */
    private function import(string $code, string $libelle, array $cases, bool $extensible): void
    {
        $repo = $this->em->getRepository(Referentiel::class);
        $referentiel = $repo->findOneBy(['code' => $code]);
        if ($referentiel === null) {
            $referentiel = (new Referentiel())->setCode($code)->setLibelle($libelle)->setExtensible($extensible);
            $this->em->persist($referentiel);
        }
        foreach ($cases as $index => $case) {
            $valueCode = strtolower($case->name);
            $value = $this->em->getRepository(ReferentielValeur::class)->findOneBy(['referentiel' => $referentiel, 'code' => $valueCode]);
            if ($value !== null) { continue; }
            $label = method_exists($case, 'libelle') ? $case->libelle() : (string) $case->value;
            $value = (new ReferentielValeur())->setReferentiel($referentiel)
                ->setCode($valueCode)->setLegacyValue((string) $case->value)
                ->setLibelle($label)->setOrdre($index)->setSysteme(true);
            if ($code === 'regime_inscription') {
                $value->setConfig(['requires_alternance_details' => !in_array($case, [RegimeInscriptionEnum::FI, RegimeInscriptionEnum::FC], true)]);
            }
            $this->em->persist($value);
        }
    }
}
