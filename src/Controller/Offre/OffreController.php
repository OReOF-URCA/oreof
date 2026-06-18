<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Formation;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\DpeParcoursRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Service\ParcoursComparaisonService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OffreController extends BaseController
{
    #[Route('/offrev2', name: 'app_offre_index')]
    public function index(
        Request               $request,
        DpeParcoursRepository $dpeParcoursRepository,
    ): Response
    {
        $tabStatistiques = [
            'nbFormations' => 0,
            'nbParcours' => 0,
            'nbParcoursOuvert' => 0,
            'capacite' => 0,
            'capaciteTotale' => 0
        ];

        // récupérer l'ensemble des formations et des parcours associés, pour chacun l'état du DPE courant
        $allParcours = $dpeParcoursRepository->findByCampagneCollecte($this->getCampagneCollecte());
        $tFormations = [];
        foreach ($allParcours as $parcours) {
            $formation = $parcours->getParcours()?->getFormation();
            $idFormation = $formation?->getId();
            if ($idFormation === null) {
                continue;
            }
            if (!array_key_exists($idFormation, $tFormations)) {
                $tFormations[$idFormation]['formation'] = $formation;
                $tFormations[$idFormation]['dpeParcours'] = [];
            }
            $tFormations[$idFormation]['dpeParcours'][] = $parcours;
            $tabStatistiques['nbParcoursOuvert'] += $parcours->getEtatReconduction() === TypeModificationDpeEnum::OUVERT ? 1 : 0;
        }

        // Construire les listes de filtres disponibles à partir des données
        $types = [];
        $composantes = [];
        $villes = [];
        foreach ($tFormations as $row) {
            $tabStatistiques['capaciteTotale'] += $row['formation']->getCapacite();
            $f = $row['formation'];
            $types[$f->getTypeDiplome()?->getLibelle() ?? ''] = true;
            $composantes[$f->getComposantePorteuse()?->getLibelle() ?? ''] = true;
            if ($f->isHasParcours() === false) {
                $loc = $f->getLocalisationMention()->first();
                $ville = is_object($loc) ? $loc->getLibelle() : null;
                $villes[$ville ?? ''] = true;
            } else {
                // si RF, les villes sont gérées au niveau des parcours; on ne les agrège pas ici pour simplifier le POC
            }
        }
        $types = array_values(array_filter(array_keys($types)));
        sort($types);
        $composantes = array_values(array_filter(array_keys($composantes)));
        sort($composantes);
        $villes = array_values(array_filter(array_keys($villes)));
        sort($villes);

        // Lire les filtres de la requête
        $q = trim((string)$request->query->get('q', ''));
        $type = (string)$request->query->get('type', '');
        $comp = (string)$request->query->get('comp', '');
        $ville = (string)$request->query->get('ville', '');

        // Appliquer les filtres côté PHP sur le tableau tFormations
        if ($q || $type || $comp || $ville) {
            $tFormations = array_filter($tFormations, static function (array $row) use ($q, $type, $comp, $ville): bool {
                $f = $row['formation'];
                // Libellé
                if ($q !== '') {
                    $lib = (string)($f->getDisplayLong() ?? '');
                    if (mb_stripos($lib, $q) === false) {
                        return false;
                    }
                }
                // Type diplôme
                if ($type !== '') {
                    if (((string)($f->getTypeDiplome()?->getLibelle() ?? '')) !== $type) {
                        return false;
                    }
                }
                // Composante
                if ($comp !== '') {
                    if (((string)($f->getComposantePorteuse()?->getLibelle() ?? '')) !== $comp) {
                        return false;
                    }
                }
                // Ville (seulement lorsque pas de parcours)
                if ($ville !== '' && $f->isHasParcours() === false) {
                    $v = (string)(($f->getLocalisationMention()->first())?->getLibelle() ?? '');
                    if ($v !== $ville) {
                        return false;
                    }
                }

                return true;
            });
        }

        foreach ($tFormations as $row) {
            $tabStatistiques['nbFormations']++;
            $tabStatistiques['nbParcours'] += count($row['dpeParcours']);
            $tabStatistiques['capacite'] += $row['formation']->getCapacite();
        }

        // Déterminer si on ne doit renvoyer que le fragment du frame
        $frameId = $request->headers->get('Turbo-Frame');
        $isFrame = $frameId === 'offre_table';

        $params = [
            'tFormations' => $tFormations,
            'filters' => [
                'q' => $q,
                'type' => $type,
                'comp' => $comp,
                'ville' => $ville,
            ],
            'choices' => [
                'types' => $types,
                'composantes' => $composantes,
                'villes' => $villes,
            ],
            'tabStatistiques' => $tabStatistiques,
        ];
        //        if ($isFrame) {
        //            // Répondre avec un markup contenant le <turbo-frame id="offre_table"> attendu
        //            return $this->render('offre/_table_frame.html.twig', $params);
        //        }


        return $this->render('offre_v2/index.html.twig', $params);
    }

    #[Route('/offre/{slug}/configurer', name: 'offre_v2_configurer')]
    public function configurer(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation                  $formation,
        ParcoursComparaisonService $comparaisonService,
    ): Response
    {
        // Récupérer le type de diplôme
        $typeDiplome = $formation->getTypeDiplome();
        $tabStatistiques = [];
        $tabStatistiques['nbFormations'] = 1;
        $tabStatistiques['parcours'] = [];
        $tabStatistiques['nbParcours'] = $formation->getParcours()->count();
        $tabStatistiques['nbParcoursOuvert'] = 0;
        $tabStatistiques['capacite'] = 0;

        foreach ($formation->getParcours() as $parcours) {
            $tableau[$parcours->getId()] = $comparaisonService->construireTableauComparaison($parcours);
            if ($parcours->isOuvert() === true) {
                $tabStatistiques['parcours'][$parcours->getId()]['nbAnnees'] = $parcours->getAnnees()->count();
                $tabStatistiques['parcours'][$parcours->getId()]['nbAnneesOuvertes'] = 0;
                $tabStatistiques['parcours'][$parcours->getId()]['capacite'] = 0;
                $tabStatistiques['nbParcoursOuvert']++;
                foreach ($parcours->getAnnees() as $annee) {
                    if ($annee->isOuvert() === true) {
                        $tabStatistiques['parcours'][$parcours->getId()]['nbAnneesOuvertes']++;
                        $tabStatistiques['parcours'][$parcours->getId()]['capacite'] += $annee->getCapaciteAccueil();
                        $tabStatistiques['capacite'] += $annee->getCapaciteAccueil();
                    }
                }
            }
        }


        // Récupérer les plateformes d'admission associées au type de diplôme pour la campagne active
        $campagne = $this->getCampagneCollecte();
        $plateformes = [];

        if ($typeDiplome) {
            foreach ($typeDiplome->getTypeDiplomePlateformeAdmissions() as $tpa) {
                if ($tpa->getCampagne() === $campagne && $tpa->getPlateforme()?->getActive()) {
                    $plateforme = $tpa->getPlateforme();
                    $plateformes[] = [
                        'id' => $plateforme->getId(),
                        'libelle' => $plateforme->getLibelle(),
                        'code' => $plateforme->getCode(),
                        'color' => $plateforme->getColor(),
                        'definitionChamps' => $plateforme->getDefinitionChamps(),
                        'annees' => array_values($tpa->getAnnees()),

                    ];
                }
            }
        }

        return $this->render('offre_v2/configurer.html.twig', [
            'formation' => $formation,
            'plateformes' => $plateformes,
            'campagne' => $campagne,
            'tabStatistiques' => $tabStatistiques,
            'comparaison' => $tableau,
        ]);
    }
}
