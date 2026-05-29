<?php

namespace App\Service;

use App\Classes\GetHistorique;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Enums\CampagnePublicationTagEnum;
use App\Enums\TypeModificationDpeEnum;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApiJsonExport
{

    private EntityManagerInterface $entityManager;

    private GetHistorique $getHistorique;

    private VersioningParcours $versioningParcours;

    private UrlGeneratorInterface $router;

    public function __construct(
        EntityManagerInterface $entityManager,
        GetHistorique $getHistorique,
        VersioningParcours $versioningParcours,
        UrlGeneratorInterface $router,
    )
    {
        $this->entityManager = $entityManager;
        $this->getHistorique = $getHistorique;
        $this->versioningParcours = $versioningParcours;
        $this->router = $router;
    }

    public function generateApiVersioning(
        string $hostname,
        SymfonyStyle $io,
        LheoXML $lheoXmlService
    ): array
    {
        $dataJSON = [];
        $urlPrefix = "https://" . $hostname;

        $countParcoursCampagneActuelle = 0;

        $countParcoursCampagneSuivante = 0;

        $campagneCourante = $this->entityManager->getRepository(CampagneCollecte::class)
            ->findOneBy(['publicationTag' => CampagnePublicationTagEnum::ANNEE_COURANTE->value]);

        $campagneSuivante = $this->entityManager->getRepository(CampagneCollecte::class)
            ->findOneBy(['publicationTag' => CampagnePublicationTagEnum::ANNEE_SUIVANTE->value]);

        $etatReconductionCampagneSuivante = [
            TypeModificationDpeEnum::OUVERT,
            TypeModificationDpeEnum::NON_OUVERTURE_CFVU,
            TypeModificationDpeEnum::NON_OUVERTURE_SES,
            TypeModificationDpeEnum::FERMETURE_DEFINITIVE,
            TypeModificationDpeEnum::MODIFICATION_TEXTE,
            TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE
        ];

        /**
         * Filtrage sur la campagne de collecte en cours (ANNEE COURANTE) (N)
         */
        if ($campagneCourante && $campagneCourante->isEnablePublication()) {
            $formationArray = $this->entityManager->getRepository(Formation::class)->findAll();
            $formationArray = array_filter($formationArray, function ($f) use ($campagneCourante) {
                $parcoursCampagneActuelle = array_filter(
                    $f->getParcours()->toArray(),
                    function ($p) use ($campagneCourante) {
                        $dpeParcours = $p->getDpeParcours()?->last();
                        if ($dpeParcours instanceof DpeParcours) {
                            return $dpeParcours->getCampagneCollecte()?->getId() === $campagneCourante->getId();
                        }
                        return false;
                    }
                );
                return count($parcoursCampagneActuelle) > 0;
            });

            $io?->writeln('Génération de l\'année en cours (N)...');
            $io?->progressStart(count($formationArray));
            foreach ($formationArray as $formation) {
                $dateValidationFormation = [];
                $tParcours = [];
                /** @var Parcours $parcours */
                foreach ($formation->getParcours() as $parcours) {
                    $lastVersion = $this->entityManager->getRepository(ParcoursVersioning::class)
                        ->findLastCfvuVersion($parcours);
                    if (count($lastVersion) > 0 && $lheoXmlService->isValidLHEO($parcours)) {
                        $lastVersionData = $this->versioningParcours->loadParcoursFromVersion($lastVersion[0]);
                        $tParcours[] = [
                            'id_old' => $parcours->getParcoursOrigineCopie()?->getId(),
                            'id' => $parcours->getId(),
                            'libelle' => $lastVersionData['parcours']->getDisplay(),
                            'url' => $urlPrefix . $this->router->generate(
                                    'app_parcours_export_json_urca_cfvu_valid',
                                    ['parcours' => $lastVersion[0]->getParcours()->getId()]
                            )
                        ];
                        $dateValideCfvu = $this->getHistorique
                            ->getHistoriqueParcoursLastStep($parcours->getDpeParcours()->last(), 'valide_cfvu')
                            ?->getDate();

                        $dateValideAPublier = $this->getHistorique
                            ->getHistoriqueParcoursLastStep($parcours->getDpeParcours()->last(), 'valide_a_publier')
                            ?->getDate();

                        if ($dateValideCfvu !== null) {
                            $dateValidationFormation[] = $dateValideCfvu;
                        }
                        if ($dateValideAPublier !== null) {
                            $dateValidationFormation[] = $dateValideAPublier;
                        }
                        ++$countParcoursCampagneActuelle;

                    }
                }
                // Date de validation : la plus récente des dates de publication de parcours
                if (count($dateValidationFormation) > 0) {
                    rsort($dateValidationFormation);
                    $dateValidationFormation = $dateValidationFormation[0];
                } else {
                    $dateValidationFormation = null;
                }

                if (count($tParcours) > 0) {
                    $dataJSON[] = [
                        'id_old' => $formation->getFormationOrigineCopie()?->getId(),
                        'id' => $formation->getId(),
                        'libelle' => $formation->getDisplayLong(),
                        'parcours' => $tParcours,
                        'dateValidation' => $dateValidationFormation?->format('Y-m-d H:i:s') ?? null
                    ];
                }

                $io?->progressAdvance();
            }
            $io?->progressFinish();
        }

        /**
         * Filtrage sur la campagne de collecte suivante (ANNEE SUIVANTE) (N + 1)
         */
        if ($campagneSuivante && $campagneSuivante->isEnablePublication()) {
            $formationArray = $this->entityManager->getRepository(Formation::class)->findAll();
            $formationArray = array_filter(
                $formationArray,
                function ($f) use ($campagneSuivante, $etatReconductionCampagneSuivante) {
                    $parcoursCampagneSuivante = array_filter(
                        $f->getParcours()->toArray(),
                        function ($p) use ($campagneSuivante, $etatReconductionCampagneSuivante) {
                            $dpeParcours = $p->getDpeParcours()?->last();
                            if ($dpeParcours instanceof DpeParcours) {
                                return $dpeParcours->getCampagneCollecte()?->getId() === $campagneSuivante->getId()
                                    && in_array($dpeParcours->getEtatReconduction(), $etatReconductionCampagneSuivante);
                            }
                        }
                    );

                    return count($parcoursCampagneSuivante) > 0;
                }
            );

            $io?->writeln('Génération de l\'année suivante (N + 1)...');
            $io?->progressStart(count($formationArray));
            foreach ($formationArray as $formationAnneeSuivante) {
                $addedParcours = [];
                foreach ($formationAnneeSuivante->getParcours() as $parcoursAnneeSuivante) {
                    if ($lheoXmlService->isValidLHEO($parcoursAnneeSuivante)) {
                        $dpeParcoursToAdd = $parcoursAnneeSuivante->getDpeParcours()?->last();
                        if ($dpeParcoursToAdd instanceof DpeParcours) {
                            if (in_array($dpeParcoursToAdd->getEtatReconduction(), $etatReconductionCampagneSuivante)) {
                                $addedParcours[] = [
                                    'id_old' => $parcoursAnneeSuivante->getParcoursOrigineCopie()?->getId(),
                                    'id' => $parcoursAnneeSuivante->getId(),
                                    'libelle' => $parcoursAnneeSuivante->getDisplay(),
                                    'url' => $urlPrefix . $this->router->generate(
                                        'app_parcours_export_json_urca_annee_suivante_light',
                                        ['parcours' => $parcoursAnneeSuivante->getId()]
                                    )
                                ];
                            }
                        }

                        ++$countParcoursCampagneSuivante;
                    }
                }
                if (count($addedParcours) > 0) {
                    $dataJSON[] = [
                        'id_old' => $formationAnneeSuivante->getFormationOrigineCopie()?->getId(),
                        'id' => $formationAnneeSuivante->getId(),
                        'libelle' => $formationAnneeSuivante->getDisplayLong(),
                        'parcours' => $addedParcours,
                        'dateValidation' => (new DateTime('2025-12-15'))->format('Y-m-d H:i:s')
                    ];
                }

                $io?->progressAdvance();
            }
            $io?->progressFinish();
        }

        if ($campagneCourante && !$campagneCourante?->isEnablePublication()) {
            $io?->writeln("La publication n'est pas activée pour l'année courante (N).");
        }
        if ($campagneSuivante && !$campagneSuivante?->isEnablePublication()) {
            $io?->writeln("La publication n'est pas activée pour l'année suivante (N + 1).");
        }
        $io?->writeln($countParcoursCampagneActuelle . ' Parcours de la Campagne Actuelle (N) ont été ajoutés à l\'API');
        $io?->writeln($countParcoursCampagneSuivante . ' Parcours de la Campagne Suivante (N + 1) ont été ajoutés à l\'API');
        if ($campagneCourante === null) {
            $io?->writeln('Aucune campagne courante (N) n\'a été trouvée en base de données.');
        }
        if ($campagneSuivante === null) {
            $io?->writeln('Aucune campagne suivante (N + 1) n\'a été trouvée en base de données.');
        }

        return $dataJSON;
    }
}
