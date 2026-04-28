<?php

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\JsonReponse;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\AnneeRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OffreController extends BaseController
{
    #[Route('/offre', name: 'app_offre')]
    public function index(
        Request               $request,
        DpeParcoursRepository $dpeParcoursRepository,
    ): Response
    {
        $tabStatistiques = [
            'nbFormations' => 0,
            'nbParcours' => 0,
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
        if ($isFrame) {
            // Répondre avec un markup contenant le <turbo-frame id="offre_table"> attendu
            return $this->render('offre/_table_frame.html.twig', $params);
        }

        return $this->render('offre/index.html.twig', $params);
    }

    #[Route('/offre/update', name: 'app_offre_update')]
    public function update(
        ParcoursRepository $parcoursRepository,
        AnneeRepository        $anneeRepository,
        EntityManagerInterface $entityManager,
        DpeParcoursRepository  $dpeParcoursRepository,
        FormationRepository    $formationRepository,
        Request                $request
    ): Response
    {
        $data = JsonRequest::getFromRequest($request);
        switch ($data['action']) {
            case 'changeOuverture':
                $t = explode('_', $data['id']);
                if ($t[0] === 'formation') {
                    $formation = $formationRepository->find($t[1]);
                    if ($formation === null) {
                        return JsonReponse::error('Pas de formation trouvée');
                    }

                    // on parcours tous les parcours et on les fermes
                    foreach ($formation->getParcours() as $parcours) {
                        $dpe = GetDpeParcours::getFromParcours($parcours);
                        if ($dpe !== null) {
                            $dpe->setEtatReconduction($this->convertValue($data['value']));
                            // todo: modifier le texte de descriptif haut selon le choix de fermeture ajouter/retirer
                        }
                    }
                    $formation->setEtatReconduction($this->convertValue($data['value']));
                    $entityManager->flush();
                } elseif ($t[0] === 'parcours') {
                    $dpe = $dpeParcoursRepository->find($t[1]);
                    if ($dpe === null) {
                        return JsonReponse::error('Pas de DPE pour le parcours');
                    }

                    $dpe->setEtatReconduction($this->convertValue($data['value']));
                    $entityManager->flush();
                } else {
                    return JsonReponse::error('Action inconnue');
                }
                break;
            case 'changeCapacite':
                $annee = $anneeRepository->find($data['id']);
                if ($annee === null) {
                    return JsonReponse::error('Pas d\'année trouvée');
                }

                $annee->setCapaciteAccueil((int)$data['value']);
                $entityManager->flush();
                break;
            case 'changeCapaciteParcours':
                $parcours = $parcoursRepository->find($data['id']);
                if ($parcours === null) {
                    return JsonReponse::error('Pas de parcours trouvé');
                }

                $parcours->setCapaciteAccueil((int)$data['value']);
                $entityManager->flush();
                break;
            case 'changeCapaciteMention':
                $formation = $formationRepository->find($data['id']);
                if ($formation === null) {
                    return JsonReponse::error('Pas de formation trouvée');
                }

                $formation->setCapaciteAccueil((int)$data['value']);
                $entityManager->flush();
                break;
            case 'changeOuvertureAnnee':
                $annee = $anneeRepository->find($data['id']);
                if ($annee === null) {
                    return JsonReponse::error('Pas d\'année trouvée');
                }

                // on parcours tous les parcours et on les fermes
                $annee->setIsOuvert(!$annee->isOuvert());

                $addTexte = false;
                foreach ($annee->getParcoursSemestre() as $ps) {
                    $ps->setOuvert($annee->isOuvert());
                    if ($addTexte === false) {
                        $anneeTexte = 'Année ' . $annee->getOrdre() . ' non ouverte.';
                        if ($annee->isOuvert()) {
                            //retirer le texte "Année xx non ouverte"
                            $ps->getParcours()?->setDescriptifHautPageAutomatique(
                                str_replace($anneeTexte, '', $ps->getParcours()?->getDescriptifHautPageAutomatique() ?? '')
                            );
                        } else {
                            $ps->getParcours()?->setDescriptifHautPageAutomatique(($ps->getParcours()?->getDescriptifHautPageAutomatique() ?? '') . ' ' . $anneeTexte);
                        }

                        $addTexte = true;
                    }

                }


                $entityManager->flush();
                break;
            case 'changeRecrutementAnnee':
                $annee = $anneeRepository->find($data['id']);
                if ($annee === null) {
                    return JsonReponse::error('Pas d\'année trouvée');
                }

                // on parcours tous les parcours et on les fermes
                $annee->setIsProposeRecrutement(!$annee->isProposeRecrutement());
                $entityManager->flush();
                break;
            case 'changeHasCapacite':
                $annee = $anneeRepository->find($data['id']);
                if ($annee === null) {
                    return JsonReponse::error('Pas d\'année trouvée');
                }

                $annee->setHasCapacite(!$annee->hasCapacite());
                $entityManager->flush();
        }

        return JsonReponse::success('Mise à jour effectuée');
    }

    private function convertValue(mixed $value): TypeModificationDpeEnum
    {
        return match ($value) {
            'OUVERT' => TypeModificationDpeEnum::OUVERT,
            'NON_OUVERTURE' => TypeModificationDpeEnum::NON_OUVERTURE_CFVU,
            'FERMETURE_DEFINITIVE' => TypeModificationDpeEnum::FERMETURE_DEFINITIVE,
        };
    }


}
