<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\DpeParcours;
use App\Message\GenerateDpeMcccBackup;
use App\Repository\DpeParcoursRepository;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\Tools;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async_export')]
final readonly class GenerateDpeMcccBackupHandler
{
    private string $backupDirectory;

    public function __construct(
        private DpeParcoursRepository $dpeParcoursRepository,
        private TypeDiplomeResolver $typeDiplomeResolver,
        private EntityManagerInterface $entityManager,
        KernelInterface $kernel,
    ) {
        $this->backupDirectory = $kernel->getProjectDir().'/public/mccc/sauvegarde/';
    }

    public function __invoke(GenerateDpeMcccBackup $message): void
    {
        $dpeParcours = $this->dpeParcoursRepository->find($message->dpeParcoursId);
        if (!$dpeParcours instanceof DpeParcours) {
            throw new \RuntimeException(sprintf('DPE parcours %d non trouvé.', $message->dpeParcoursId));
        }

        $parcours = $dpeParcours->getParcours();
        if (null === $parcours) {
            throw new \RuntimeException('Parcours non trouvé.');
        }

        $formation = $parcours->getFormation();
        if (null === $formation) {
            throw new \RuntimeException('Pas de formation.');
        }

        $typeDiplome = $this->typeDiplomeResolver->fromTypeDiplome($formation->getTypeDiplome());
        if (null === $typeDiplome) {
            throw new \RuntimeException('Aucun modèle MCC n\'est défini pour ce diplôme.');
        }

        $campagneCollecte = $dpeParcours->getCampagneCollecte();
        if (null === $campagneCollecte) {
            throw new \RuntimeException('Aucune campagne de collecte n\'est définie pour ce diplôme.');
        }

        $filename = Tools::FileName(sprintf(
            'MCCC - %s - %d-v%s',
            $campagneCollecte->getAnneeUniversitaire()?->getLibelle(),
            $parcours->getId(),
            $dpeParcours->getVersion(),
        ));

        $export = $typeDiplome->exportExcelAndSaveVersionMccc(
            $campagneCollecte,
            $parcours,
            $this->backupDirectory,
            $filename,
        );

        if (false !== $export) {
            $dpeParcours->updateMinorVersion();
            $this->entityManager->flush();
        }
    }
}
