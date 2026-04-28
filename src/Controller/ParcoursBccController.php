<?php

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Entity\Parcours;
use App\Repository\ButApprentissageCritiqueRepository;
use App\Repository\CompetenceRepository;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereRepository;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParcoursBccController extends BaseController
{
    #[Route('/parcours/bcc/{parcours}', name: 'app_parcours_bcc')]
    public function index(
        Parcours $parcours): Response
    {
        $typeD = $this->typeDiplomeResolver->fromTypeDiplome($parcours->getFormation()?->getTypeDiplome());
        $dto = $typeD->calculStructureParcours($parcours, new OptionsCalculStructure(false, true, false));
        return $this->render('parcours_bcc/index.html.twig', [
            'parcours' => $parcours,
            'dto' => $dto,
            'editable' => true //todo: déterminer selon l'état du process ...
        ]);
    }

    #[Route('/parcours/bcc-but/{parcours}', name: 'app_parcours_bcc_but')]
    public function bccBut(
        Parcours $parcours
    ): Response
    {
        $competences = $parcours->getFormation()?->getButCompetences();
        $niveaux = [];

        foreach ($competences as $competence) {
            foreach ($competence->getButNiveaux() as $niveau) {
                if ($niveau->getAnnee() === 'BUT1') {
                    $niveaux[$competence->getId()][1] = [];
                    $niveaux[$competence->getId()][2] = [];
                    foreach ($niveau->getButApprentissageCritiques() as $bac) {
                        $niveaux[$competence->getId()][1][] = $bac;
                        $niveaux[$competence->getId()][2][] = $bac;
                    }
                } elseif ($niveau->getAnnee() === 'BUT2') {
                    $niveaux[$competence->getId()][3] = [];
                    $niveaux[$competence->getId()][4] = [];
                    foreach ($niveau->getButApprentissageCritiques() as $bac) {
                        $niveaux[$competence->getId()][3][] = $bac;
                        $niveaux[$competence->getId()][4][] = $bac;
                    }
                } elseif ($niveau->getAnnee() === 'BUT3') {
                    $niveaux[$competence->getId()][5] = [];
                    $niveaux[$competence->getId()][6] = [];
                    foreach ($niveau->getButApprentissageCritiques() as $bac) {
                        $niveaux[$competence->getId()][5][] = $bac;
                        $niveaux[$competence->getId()][6][] = $bac;
                    }
                }
            }
        }

        $tabFichesMatieres = [];
        $apprentissagesCritiques = [];
        $tabUeEc = [];

        foreach ($parcours->getSemestreParcours() as $semParc) {
            foreach ($semParc->getSemestre()?->getUes() as $ue) {
                foreach ($ue->getElementConstitutifs() as $ec) {
                    if ($ec->getFicheMatiere() !== null) {
                        $tabUeEc[$semParc->getOrdre()][$ue->getOrdre()][$ec->getFicheMatiere()->getId()] = 'x';
                        $tabFichesMatieres[$semParc->getOrdre()][$ec->getFicheMatiere()->getId()] = $ec->getFicheMatiere();
                        foreach ($ec->getFicheMatiere()->getApprentissagesCritiques() as $ac) {
                            $apprentissagesCritiques[$ec->getFicheMatiere()->getId()][$ac->getId()] = 'ok';
                        }
                    }
                }
                ksort($tabFichesMatieres[$semParc->getOrdre()]);
            }
        }

        return $this->render('parcours_bcc/but.html.twig', [
            'parcours' => $parcours,
            'tabFichesMatieres' => $tabFichesMatieres,
            'competences' => $competences,
            'niveaux' => $niveaux,
            'apprentissagesCritiques' => $apprentissagesCritiques,
            'tabUeEc' => $tabUeEc,
        ]);
    }

    #[Route('/parcours/bcc/{parcours}/update-competence', name: 'app_parcours_bcc_update_competence', methods: ['POST'])]
    public function updateCompetence(
        EntityManagerInterface       $em,
        ElementConstitutifRepository $ecRepository,
        CompetenceRepository         $competenceRepository,
        Request                      $request,
        Parcours                     $parcours
    ): Response {
        $ec = $ecRepository->find($request->request->get('ec'));
        $competence = $competenceRepository->find($request->request->get('competence'));
        if ($competence === null || $ec === null) {
            return JsonReponse::error('EC ou compétence introuvable');
        }

        if ($ec->getFicheMatiere() === null) {
            return JsonReponse::error('EC sans fiche matière');
        }
        if ($request->request->get('checked') === 'true') {
            // on ajoute
            if ($ec->getFicheMatiere()->getParcours() === $parcours) {
                $ec->getFicheMatiere()->addCompetence($competence);
            } else {
                $ec->addCompetence($competence);
            }
        } else {

            if ($ec->getFicheMatiere()->getParcours() === $parcours) {
                $ec->getFicheMatiere()->removeCompetence($competence);
            } else {
                $ec->removeCompetence($competence);
            }
        }

        $em->flush();
        return JsonReponse::success('Lien compétence/EC mis à jour');
    }

    #[Route('/parcours/bcc-but/{parcours}/update-competence', name: 'app_parcours_bcc_but_update_competence', methods: ['POST'])]
    public function updateButCompetence(
        EntityManagerInterface             $em,
        FicheMatiereRepository             $ficheMatiereRepository,
        ButApprentissageCritiqueRepository $butApprentissageCritiqueRepository,
        Request                            $request,
    ): Response {
        $fiche = $ficheMatiereRepository->find($request->request->get('ec'));
        $ac = $butApprentissageCritiqueRepository->find($request->request->get('competence'));
        if ($ac === null || $fiche === null) {
            return JsonReponse::error('EC ou apprentissage critique introuvable');
        }

        if ($request->request->get('checked') === 'true') {
            // on ajoute
            $fiche->addApprentissagesCritique($ac);
        } else {
            $fiche->removeApprentissagesCritique($ac);
        }

        $em->flush();
        return JsonReponse::success('Lien Apprentissage critique/Ressource ou SAE mis à jour');
    }
}
