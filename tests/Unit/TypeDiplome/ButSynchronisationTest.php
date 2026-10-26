<?php

declare(strict_types=1);

namespace App\Tests\Unit\TypeDiplome;

use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Entity\Langue;
use App\Entity\NatureUeEc;
use App\Entity\TypeDiplome;
use App\Entity\TypeEc;
use App\Entity\TypeUe;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\LangueRepository;
use App\Repository\NatureUeEcRepository;
use App\Repository\TypeEcRepository;
use App\Repository\TypeUeRepository;
use App\Service\Api\OrebutApiService;
use App\TypeDiplome\Synchronisation\But;
use App\TypeDiplome\Synchronisation\ButSyncOptions;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class ButSynchronisationTest extends TestCase
{
    private function createService(
        OrebutApiService $orebutApiService,
        ?EntityManagerInterface $em = null,
        ?FicheMatiereRepository $fmRepo = null,
    ): But {
        $langueRepo = $this->createStub(LangueRepository::class);
        $typeEcRepo = $this->createStub(TypeEcRepository::class);
        $typeUeRepo = $this->createStub(TypeUeRepository::class);
        $natureUeEcRepo = $this->createStub(NatureUeEcRepository::class);
        $ecRepo = $this->createStub(ElementConstitutifRepository::class);
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/tmp');

        $langue = new Langue();
        $langue->setCodeIso('fr');
        $langueRepo->method('findOneBy')->willReturn($langue);

        $typeEcRessource = new TypeEc();
        $typeEcRessource->setLibelle('Ressource');
        $typeEcSae = new TypeEc();
        $typeEcSae->setLibelle('SAE');
        $typeEcRepo->method('findOneBy')->willReturnCallback(function (array $criteria) use ($typeEcRessource, $typeEcSae) {
            return ($criteria['libelle'] ?? '') === 'Ressource' ? $typeEcRessource : $typeEcSae;
        });

        $natureEc = new NatureUeEc();
        $natureEc->setLibelle('EC obligatoire');
        $natureUe = new NatureUeEc();
        $natureUe->setLibelle('UE Obligatoire');
        $natureUeEcRepo->method('findOneBy')->willReturnCallback(function (array $criteria) use ($natureEc, $natureUe) {
            return ($criteria['libelle'] ?? '') === 'EC obligatoire' ? $natureEc : $natureUe;
        });

        $typeUe = new TypeUe();
        $typeUe->setLibelle('Disciplinaire');
        $typeUeRepo->method('findOneBy')->willReturn($typeUe);

        $em = $em ?? $this->createStub(EntityManagerInterface::class);
        $fmRepo = $fmRepo ?? $this->createStub(FicheMatiereRepository::class);


        return new But(
            $langueRepo,
            $typeEcRepo,
            $typeUeRepo,
            $natureUeEcRepo,
            $em,
            $ecRepo,
            $fmRepo,
            $orebutApiService,
            $kernel,
        );
    }

    public function testRejectsNonButFormation(): void
    {
        $apiService = $this->createStub(OrebutApiService::class);
        $service = $this->createService($apiService);

        $typeLicence = new TypeDiplome();
        $typeLicence->setLibelleCourt('LICENCE');

        $formation = new Formation(null);
        $formation->setTypeDiplome($typeLicence);

        $campagne = new CampagneCollecte();
        $result = $service->synchroniserWithOptions($formation, $campagne, new ButSyncOptions());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('n\'est pas un Bachelor', $result->errors[0]);
    }

    public function testRejectsUnknownDepartment(): void
    {
        $apiService = $this->createStub(OrebutApiService::class);
        $apiService->method('getDepartement')->willReturn(null);
        $apiService->method('getApiUrl')->willReturn('https://orebut.iut.fr/api');

        $service = $this->createService($apiService);

        $typeBut = new TypeDiplome();
        $typeBut->setLibelleCourt('BUT');

        $formation = new Formation(null);
        $formation->setTypeDiplome($typeBut);
        $formation->setSigle('INCONNU');

        $campagne = new CampagneCollecte();
        $result = $service->synchroniserWithOptions($formation, $campagne, new ButSyncOptions());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('introuvable sur l\'API ORéBUT', $result->errors[0]);
    }

    public function testSyncParcoursAndCompetencesSuccess(): void
    {
        $apiService = $this->createStub(OrebutApiService::class);
        $apiService->method('getDepartement')->willReturn([
            'sigle' => 'INFO',
            'libelle' => 'Informatique',
            'typeStructure' => 'type2',
            'apcParcours' => [
                [
                    'id' => 10,
                    'code' => 'DA',
                    'libelle' => 'Déploiement d\'applications',
                    'textePresentation' => 'Présentation parcours DA',
                    'apcParcoursNiveaux' => [
                        [
                            'niveau' => [
                                'ordre' => 1,
                                'annee' => ['libelle' => 'BUT1'],
                                'competence' => [
                                    'id' => 1,
                                    'numero' => 1,
                                    'nom_court' => 'Réaliser',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $apiService->method('getCompetences')->willReturn([
            [
                'id' => 1,
                'numero' => 1,
                'nom_court' => 'Réaliser',
                'libelle' => 'Réaliser un développement d\'application',
                'apcSituationProfessionnelles' => [['libelle' => 'Participer à la conception']],
                'apcComposanteEssentielles' => [['libelle' => 'Qualité du code']],
                'apcNiveaux' => [
                    [
                        'ordre' => 1,
                        'libelle' => 'Développer des applications informatiques simples',
                        'annee' => ['libelle' => 'BUT1'],
                        'apcApprentissageCritiques' => [
                            ['code' => 'AC11.01', 'libelle' => 'Implémenter des algorithmes'],
                        ],
                    ],
                ],
            ],
        ]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($apiService, $em);

        $typeBut = new TypeDiplome();
        $typeBut->setLibelleCourt('BUT');

        $formation = new Formation(null);
        $formation->setTypeDiplome($typeBut);
        $formation->setSigle('INFO');

        $campagne = new CampagneCollecte();

        $options = new ButSyncOptions(
            syncParcours: true,
            syncCompetences: true,
            syncMaquette: false,
        );

        $result = $service->synchroniserWithOptions($formation, $campagne, $options);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->parcoursCreated);
        $this->assertSame(1, $result->competencesCount);
        $this->assertSame(1, $result->niveauxCount);
        $this->assertSame(1, $result->acsCount);
        $this->assertSame(2, $result->uesCreated); // 2 semestres en BUT1 (S1 & S2)

        $parcours = $formation->getParcours()->first();
        $this->assertNotNull($parcours);
        $dpe = $parcours->getDpeParcours()->first();
        $this->assertNotNull($dpe);
        $this->assertSame(['en_cours_redaction' => 1], $dpe->getEtatValidation());
    }

    public function testSyncMaquetteRessourcesAndSaesSuccess(): void
    {
        $apiService = $this->createStub(OrebutApiService::class);
        $apiService->method('getDepartement')->willReturn([
            'sigle' => 'INFO',
            'libelle' => 'Informatique',
            'typeStructure' => 'type2',
            'apcParcours' => [
                [
                    'id' => 10,
                    'code' => 'DA',
                    'libelle' => 'Déploiement d\'applications',
                    'apcParcoursNiveaux' => [
                        [
                            'niveau' => [
                                'ordre' => 1,
                                'annee' => ['libelle' => 'BUT1'],
                                'competence' => [
                                    'id' => 1,
                                    'numero' => 1,
                                    'nom_court' => 'Réaliser',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $apiService->method('getCompetences')->willReturn([
            [
                'id' => 1,
                'numero' => 1,
                'nom_court' => 'Réaliser',
                'libelle' => 'Compétence 1',
                'apcNiveaux' => [
                    [
                        'ordre' => 1,
                        'libelle' => 'Niveau 1',
                        'annee' => ['libelle' => 'BUT1'],
                        'apcApprentissageCritiques' => [
                            ['code' => 'AC11.01', 'libelle' => 'AC 1'],
                        ],
                    ],
                ],
            ],
        ]);

        $apiService->method('getRessources')->willReturn([
            [
                'codeMatiere' => 'R1.01',
                'libelle' => 'Initiation au dev',
                'description' => 'Bases de la programmation',
                'cmPpn' => 10,
                'tdPpn' => 15,
                'tpPpn' => 20,
                'heuresTotales' => 45,
                'ordre' => 1,
                'semestre' => ['ordreLmd' => 1],
                'apcRessourceCompetences' => [
                    ['competence' => ['id' => 1], 'coefficient' => 4],
                ],
                'apcRessourceApprentissageCritiques' => [
                    ['apprentissageCritique' => ['code' => 'AC11.01']],
                ],
            ],
        ]);

        $apiService->method('getSaes')->willReturn([
            [
                'codeMatiere' => 'S1.01',
                'libelle' => 'Projet dev',
                'description' => 'Développement d\'un projet',
                'objectifs' => 'Mise en application',
                'heuresTotales' => 30,
                'ordre' => 1,
                'semestre' => ['ordreLmd' => 1],
                'apcSaeCompetences' => [
                    ['competence' => ['id' => 1], 'coefficient' => 6],
                ],
                'apcSaeApprentissageCritiques' => [
                    ['apprentissageCritique' => ['code' => 'AC11.01']],
                ],
            ],
        ]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($apiService, $em);

        $typeBut = new TypeDiplome();
        $typeBut->setLibelleCourt('BUT');

        $formation = new Formation(null);
        $formation->setTypeDiplome($typeBut);
        $formation->setSigle('INFO');

        $campagne = new CampagneCollecte();

        $options = new ButSyncOptions(
            syncParcours: true,
            syncCompetences: true,
            syncMaquette: true,
        );

        $result = $service->synchroniserWithOptions($formation, $campagne, $options);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->ressourcesCount);
        $this->assertSame(1, $result->saesCount);
        $this->assertSame(2, $result->ecsCount); // 1 EC Ressource + 1 EC SAE dans l'UE S1
        $this->assertStringContainsString('1 ressources', $result->getSummary());
        $this->assertStringContainsString('1 SAÉ', $result->getSummary());
    }

    public function testSelectiveParcoursAndYear2027(): void
    {
        $apiService = $this->createMock(OrebutApiService::class);
        $apiService->expects($this->once())
            ->method('getDepartement')
            ->with('info', $this->isInstanceOf(Formation::class), 2027)
            ->willReturn([
                'sigle' => 'INFO',
                'libelle' => 'Informatique 2027',
                'typeStructure' => 'type2',
                'apcParcours' => [
                    ['id' => 1, 'code' => 'A', 'libelle' => 'Parcours A'],
                    ['id' => 2, 'code' => 'B', 'libelle' => 'Parcours B'],
                ],
            ]);

        $apiService->expects($this->once())
            ->method('getCompetences')
            ->with('info', $this->isInstanceOf(Formation::class), 2027)
            ->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->createService($apiService, $em);

        $typeBut = new TypeDiplome();
        $typeBut->setLibelleCourt('BUT');

        $formation = new Formation(null);
        $formation->setTypeDiplome($typeBut);
        $formation->setSigle('INFO');

        $campagne = new CampagneCollecte();

        // On ne sélectionne QUE le parcours A
        $options = new ButSyncOptions(
            syncParcours: true,
            syncCompetences: true,
            syncMaquette: false,
            selectedParcoursCodes: ['A'],
            annee: 2027,
        );

        $result = $service->synchroniserWithOptions($formation, $campagne, $options);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->parcoursCreated);
        $this->assertCount(1, $formation->getParcours());
        $this->assertSame('A', $formation->getParcours()->first()->getSigle());
    }
}

