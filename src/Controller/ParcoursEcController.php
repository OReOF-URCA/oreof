<?php

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\JsonReponse;
use App\Entity\Parcours;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\TypeEcRepository;
use App\Repository\UeRepository;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\Tools;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParcoursEcController extends AbstractController
{
    #[Route('/parcours/ec/liste/{parcours}', name: 'app_parcours_ec')]
    public function index(
        TypeDiplomeResolver $typeDiplomeResolver,
        TypeEcRepository             $typeEcRepository,
        Parcours                     $parcours
    ): Response {
        $typeD = $typeDiplomeResolver->fromParcours($parcours);
        $dto = $typeD->calculStructureParcours($parcours);

        return $this->render('parcours_ec/index.html.twig', [
            'typeD' => $typeD,
            'dto' => $dto,
            'parcours' => $parcours,
            'dpeParcours' => GetDpeParcours::getFromParcours($parcours),
            'typesEc' => $typeEcRepository->findByTypeDiplome($parcours->getFormation()?->getTypeDiplome())
        ]);
    }

    #[Route('/parcours/ressources-sae/{parcours}', name: 'app_parcours_ressources_sae_but')]
    public function ressourcesSae(
        FicheMatiereRepository $ficheMatiereRepository,
        Parcours               $parcours
    ): Response {
        $fichesMatieres = [];
        foreach ($parcours->getSemestreParcours() as $semP) {
            if ($semP->getSemestre()?->getSemestreRaccroche() !== null) {
                $sem = $semP->getSemestre()?->getSemestreRaccroche()?->getSemestre();
            } else {
                $sem = $semP->getSemestre();
            }
            if ($sem !== null) {
                foreach ($sem->getUes() as $ue) {
                    if ($ue !== null) {
                        if ($ue->getUeRaccrochee() !== null && $ue->getUeRaccrochee()->getUe() !== null) {
                            $ue = $ue->getUeRaccrochee()->getUe();
                        }

                        foreach ($ue->getElementConstitutifs() as $ec) {
                            $fichesMatieres[] = $ec->getFicheMatiere();
                        }
                    }
                }
            }
        }


        $tabEcs = [];
        foreach ($fichesMatieres as $ficheMatiere) {
            if ($ficheMatiere->getElementConstitutifs()->count() > 0) {
                $sem = $ficheMatiere->getElementConstitutifs()?->first()->getUe()?->getSemestre();
                if ($sem !== null) {
                    if (array_key_exists($sem->getOrdre(), $tabEcs) === false) {
                        $tabEcs[$sem->getOrdre()] = [];
                    }
                    if ($ficheMatiere !== null) {
                        $tabEcs[$sem->getOrdre()][$ficheMatiere->getSigle()] = $ficheMatiere;
                    }
                }
            }
        }

        foreach ($tabEcs as $key => $tabEc) {
            ksort($tabEcs[$key]);
        }

        return $this->render('parcours_ec/ressources_saes.html.twig', [
            'parcours' => $parcours,
            'tabEcs' => $tabEcs,
        ]);
    }

    //todo: devrait être dans type diplôme
    #[Route('/parcours/ressources-sae/{parcours}/coeff', name: 'app_parcours_ressources_sae_but_coeff')]
    public function ressourcesSaeCoeff(
        Parcours $parcours
    ): Response
    {
        $tabEcs = [];
        $tabEcUes = [];
        $tabUes = [];
        foreach ($parcours->getSemestreParcours() as $semParc) {
            if ($semParc->getSemestre()?->getSemestreRaccroche() !== null) {
                $semParc = $semParc->getSemestre()?->getSemestreRaccroche()?->getSemestre();
            } else {
                $semParc = $semParc->getSemestre();
            }

            if ($semParc !== null) {
                $tabEcs[$semParc->getOrdre()] = [];
                foreach ($semParc->getUes() as $ue) {
                    if ($ue->getUeRaccrochee() !== null) {
                        $ue = $ue->getUeRaccrochee();
                    }

                    $tabEcUes[$semParc->getOrdre()][$ue->getId()] = [];
                    $tabUes[$semParc->getOrdre()][$ue->getId()] = $ue;
                    foreach ($ue->getElementConstitutifs() as $ec) {
                        if ($ec->getFicheMatiere() !== null) {
                            $tabEcUes[$semParc->getOrdre()][$ue->getId()][$ec->getFicheMatiere()?->getSigle()] = $ec;
                            $tabEcs[$semParc->getOrdre()][$ec->getFicheMatiere()?->getSigle()] = $ec;
                        }
                    }
                }

                ksort($tabEcs[$semParc->getOrdre()]);
            }
        }

        return $this->render('parcours_ec/ressources_saes_coeff.html.twig', [
            'parcours' => $parcours,
            'tabEcs' => $tabEcs,
            'tabEcUes' => $tabEcUes,
            'tabUes' => $tabUes,
        ]);
    }

    #[Route('/parcours/m2e-ec/{parcours}/coeff', name: 'app_parcours_ec_m2e_coeff')]
    public function m2eEcCoeff(
        Parcours $parcours
    ): Response
    {
        $tabEcs = [];
        $tabEcUes = [];
        $tabUes = [];
        foreach ($parcours->getSemestreParcours() as $semParc) {
            if ($semParc->getSemestre()?->getSemestreRaccroche() !== null) {
                $semParc = $semParc->getSemestre()?->getSemestreRaccroche()?->getSemestre();
            } else {
                $semParc = $semParc->getSemestre();
            }

            if ($semParc !== null) {
                $tabEcs[$semParc->getOrdre()] = [];
                foreach ($semParc->getUes() as $ue) {
                    if ($ue->getUeRaccrochee() !== null) {
                        $ue = $ue->getUeRaccrochee();
                    }

                    $tabEcUes[$semParc->getOrdre()][$ue->getId()] = [];
                    $tabUes[$semParc->getOrdre()][$ue->getId()] = $ue;
                    foreach ($ue->getElementConstitutifs() as $ec) {
                        if ($ec->getFicheMatiere() !== null) {
                            $tabEcUes[$semParc->getOrdre()][$ue->getId()][$ec->getId()] = $ec;
                            $tabEcs[$semParc->getOrdre()][$ec->getId()] = $ec;
                        }
                    }
                }

                ksort($tabEcs[$semParc->getOrdre()]);
            }
        }

        return $this->render('parcours_ec/m2e_coeff.html.twig', [
            'parcours' => $parcours,
            'tabEcs' => $tabEcs,
            'tabEcUes' => $tabEcUes,
            'tabUes' => $tabUes,
        ]);
    }

    #[Route('/parcours/ec/update', name: 'app_parcours_ec_update')]
    public function updateParcoursEc(
        UeRepository                 $ueRepository,
        ElementConstitutifRepository $ecRepository,
        TypeEcRepository             $typeEcRepository,
        Request                      $request,
    ): Response {
        $field = $request->request->get('field');


        switch ($field) {
            case 'synchroBcc':
                $ec = $ecRepository->find($request->request->get('ec'));

                if ($ec === null) {
                    return JsonReponse::error('EC introuvable');
                }

                $ec->setSynchroBcc($request->request->get('value') === 'true');
                $ecRepository->save($ec, true);
                return JsonReponse::success('EC mis à jour, BCC raccrochés');
            case 'ectsSpecifiques':
                $ec = $ecRepository->find($request->request->get('ec'));

                if ($ec === null) {
                    return JsonReponse::error('EC introuvable');
                }

                $ec->setEctsSpecifiques($request->request->get('value') === 'true');
                $ecRepository->save($ec, true);
                return JsonReponse::success('EC mis à jour, ECTS spécifiques');
            case 'controleAssiduite':
                $ec = $ecRepository->find($request->request->get('ec'));

                if ($ec === null) {
                    return JsonReponse::error('EC introuvable');
                }

                $ec->setControleAssiduite($request->request->get('value') === 'true');

                if ($ec->isControleAssiduite() === true) {
                    $ec->setTypeMccc(null);
                    //supprimer les MCCC associées
                    foreach ($ec->getMcccs() as $mccc) {
                        $ec->removeMccc($mccc);
                    }
                }

                $ecRepository->save($ec, true);
                return JsonReponse::success('EC mis à jour, Contrôle assiduité enregistré');
            case 'mcccSpecifiques':
                $ec = $ecRepository->find($request->request->get('ec'));

                if ($ec === null) {
                    return JsonReponse::error('EC introuvable');
                }

                $ec->setMcccSpecifiques($request->request->get('value') === 'true');
                $ecRepository->save($ec, true);
                return JsonReponse::success('EC mis à jour, MCCC spécifiques');
            case 'heuresSpecifiques':
                $ec = $ecRepository->find($request->request->get('ec'));

                if ($ec === null) {
                    return JsonReponse::error('EC introuvable');
                }

                $ec->setHeuresSpecifiques($request->request->get('value') === 'true');
                $ecRepository->save($ec, true);
                return JsonReponse::success('EC mis à jour, Heures spécifiques');
            case 'typeEc':
                $ec = $ecRepository->find($request->request->get('ec'));

                if ($ec === null) {
                    return JsonReponse::error('EC introuvable');
                }
                $typeEc = $typeEcRepository->find($request->request->get('value'));
                if ($typeEc === null) {
                    return JsonReponse::error('Type EC introuvable');
                }
                $ec->setTypeEc($typeEc);
                $ecRepository->save($ec, true);
                return JsonReponse::success('Type EC mis à jour');
            case 'ectsUE':
                $ue = $ueRepository->find($request->request->get('ue'));

                if ($ue === null) {
                    return JsonReponse::error('UE introuvable');
                }

                $ue->setEcts(Tools::convertToFloat($request->request->get('value')));
                $ueRepository->save($ue, true);
                return JsonReponse::success('ECTS de l\'UE mis à jour');
            case 'coeffEc':
                $ec = $ecRepository->find($request->request->get('ec'));

                if ($ec === null) {
                    return JsonReponse::error('EC introuvable');
                }

                $ec->setEcts(Tools::convertToFloat($request->request->get('value')));
                $ecRepository->save($ec, true);
                return JsonReponse::success('ECTS de l\'EC mis à jour');
        }
    }
}
