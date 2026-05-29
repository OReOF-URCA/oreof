<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ParcoursWizardController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\Bcc;
use App\Classes\JsonReponse;
use App\Entity\DpeParcours;
use App\Entity\Parcours;
use App\Form\ParcoursStep1Type;
use App\Form\ParcoursStep2Type;
use App\Form\ParcoursStep5Type;
use App\Form\ParcoursStep6Type;
use App\Form\ParcoursStep7Type;
use App\Repository\ComposanteRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\Utils\Access;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/parcours/step')]
class ParcoursWizardController extends BaseController
{
    #[Route('/', name: 'app_parcours_wizard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('formation/index.html.twig');
    }

    #[Route('/{dpeParcours}/1', name: 'app_parcours_wizard_step_1', methods: ['GET'])]
    public function step1(DpeParcours $dpeParcours): Response
    {
        $parcours = $dpeParcours->getParcours();

        $form = $this->createForm(ParcoursStep1Type::class, $parcours);

        return $this->render('parcours_wizard/_step1.html.twig', [
            'form' => $form->createView(),
            'parcours' => $parcours,
        ]);
    }

    /**
     * @throws TypeDiplomeNotFoundException
     */
    #[Route('/{dpeParcours}/2', name: 'app_parcours_wizard_step_2', methods: ['GET'])]
    public function step2(
        DpeParcours $dpeParcours
    ): Response {

        $parcours = $dpeParcours->getParcours();

        $form = $this->createForm(ParcoursStep2Type::class, $parcours, [
            'typeDiplome' => $parcours->getTypeDiplome(),
        ]);

        return $this->render('parcours_wizard/_step2.html.twig', [
            'form' => $form->createView(),
            'typeDiplome' => $parcours->getTypeDiplome(),
            'parcours' => $parcours,
        ]);
    }

    #[Route('/{dpeParcours}/3', name: 'app_parcours_wizard_step_3', methods: ['GET'])]
    public function step3(
        ParcoursRepository $parcoursRepository,
        DpeParcours $dpeParcours
    ): Response {
        if (!Access::isAccessible($dpeParcours)) {
            return $this->render('parcours_wizard/_access_denied.html.twig');
        }

        $parcours = $dpeParcours->getParcours();

        if ($parcours === null) {
            throw new Exception('Le parcours n\'existe pas.');
        }

        if ($parcours->getFormation() === null) {
            throw new Exception('Le parcours n\'est pas lié à une formation.');
        }

        $typeDiplome = $this->typeDiplomeResolver->fromTypeDiplome($parcours->getFormation()->getTypeDiplome());
        $listeParcours = $parcoursRepository->findBy(['formation' => $parcours->getFormation()]);
        return $this->render('parcours_wizard/_step3.html.twig', [
            'parcours' => $parcours,
            'blocCompetences' => $parcours->getBlocCompetences(),
            'listeParcours' => $listeParcours,
            'typeDiplome' => $typeDiplome,
        ]);
    }

    #[Route('/{dpeParcours}/4', name: 'app_parcours_wizard_step_4', methods: ['GET'])]
    public function step4(
        Request $request,
        ParcoursRepository $parcoursRepository, DpeParcours $dpeParcours): Response
    {
        if (!Access::isAccessible($dpeParcours)) {
            return $this->render('parcours_wizard/_access_denied.html.twig');
        }

        $parcours = $dpeParcours->getParcours();

        $listeParcours = $parcoursRepository->findBy(['formation' => $parcours->getFormation()]);
        return $this->render('parcours_wizard/_step4.html.twig', [
            'parcours' => $parcours,
            'listeParcours' => $listeParcours,
            'semestreAffiche' => $request->getSession()->get('semestreAffiche') ?? null,
            'ueAffichee' => $request->getSession()->get('ueAffichee') ?? null,
        ]);
    }

    #[Route('/{parcours}/recopie/hors-formation', name: 'app_recopie_bcc_autre_formation', methods: ['GET'])]
    public function recopieHorsParcours(
        ComposanteRepository $composanteRepository,
        Parcours $parcours,
    ): Response {

        $composantes = $composanteRepository->findAll();

        return $this->render('parcours_wizard/_recopieHorsFormation.html.twig', [
            'parcours' => $parcours,
            'composantes' => $composantes,
        ]);
    }

    #[Route('/{parcours}/recopie/hors-formation/ajax', name: 'app_recopie_bcc_autre_formation_ajax', methods: [
        'POST',
        'DELETE'
    ])]
    public function recopieHorsParcoursAjax(
        BCC $bcc,
        EntityManagerInterface $entityManager,
        Request $request,
        FormationRepository $formationRepository,
        ParcoursRepository $parcoursRepository,
        Parcours $parcours,
    ): Response {
        $data = JsonRequest::getFromRequest($request);
        $t = [];
        switch ($data['field']) {
            case 'formation':
                $formations = $formationRepository->findBy(['composantePorteuse' => $data['value']]);
                foreach ($formations as $formation) {
                    $t[] = [
                        'id' => $formation->getId(),
                        'libelle' => $formation->getDisplayLong()
                    ];
                }
                break;
            case 'parcours':
                $allParcours = $parcoursRepository->findBy(['formation' => $data['value']]);
                foreach ($allParcours as $parcour) {
                    $t[] = [
                        'id' => $parcour->getId(),
                        'libelle' => $parcour->getLibelle()
                    ];
                }
                break;
            case 'save':
                if (isset($data['parcours']) && $data['parcours'] !== '') {
                    $bcc->recopieBcc($parcours, $data['parcours']);
                    return JsonReponse::success('Recopie effectuée');
                }

                return JsonReponse::error('Erreur lors de la recopie des BCC');
        }

        return $this->json($t);
    }

    #[Route('/{dpeParcours}/5', name: 'app_parcours_wizard_step_5', methods: ['GET'])]
    public function step5(DpeParcours $dpeParcours): Response
    {


        $parcours = $dpeParcours->getParcours();

        $form = $this->createForm(ParcoursStep5Type::class, $parcours);

        return $this->render('parcours_wizard/_step5.html.twig', [
            'form' => $form->createView(),
            'parcours' => $parcours,
        ]);
    }

    #[Route('/{dpeParcours}/6', name: 'app_parcours_wizard_step_6', methods: ['GET'])]
    public function step6(DpeParcours $dpeParcours): Response
    {


        $parcours = $dpeParcours->getParcours();

        $form = $this->createForm(ParcoursStep6Type::class, $parcours);

        return $this->render('parcours_wizard/_step6.html.twig', [
            'form' => $form->createView(),
            'parcours' => $parcours,
        ]);
    }

    #[Route('/{dpeParcours}/7', name: 'app_parcours_wizard_step_7', methods: ['GET'])]
    public function step7(DpeParcours $dpeParcours): Response
    {
        $parcours = $dpeParcours->getParcours();

        $form = $this->createForm(ParcoursStep7Type::class, $parcours);

        return $this->render('parcours_wizard/_step7.html.twig', [
            'form' => $form->createView(),
            'parcours' => $parcours,
        ]);
    }

    #[Route('/{parcours}/code-rome', name: 'app_parcours_wizard_step_6_code_rome', methods: ['GET'])]
    public function codeRome(Parcours $parcours): Response
    {
        $codes = $parcours->getCodesRome();

        return $this->render('parcours_wizard/_codeRome.html.twig', [
            'parcours' => $parcours,
            'codes' => $codes,
        ]);
    }

    #[Route('/{parcours}/code-rome/gere', name: 'app_parcours_wizard_step_6_code_rome_gere', methods: ['POST'])]
    public function codeRomeGere(
        ParcoursRepository $parcoursRepository,
        Request $request,
        Parcours $parcours
    ): Response {
        $action = JsonRequest::getValueFromRequest($request, 'action');
        $code = JsonRequest::getValueFromRequest($request, 'code');

        switch ($action) {
            case 'ADD':
                $codes = $parcours->getCodesRome();
                $codes[] = ['code' => $code];
                $parcours->setCodesRome($codes);

                $parcoursRepository->save($parcours, true);
                return $this->json(true);
            case 'DELETE':
                $codes = $parcours->getCodesRome();
                foreach ($codes as $key => $value) {
                    if ((string)$value['code'] === (string)$code) {
                        unset($codes[$key]);
                    }
                }
                $parcours->setCodesRome($codes);

                $parcoursRepository->save($parcours, true);
                return $this->json(true);
        }


        return $this->json(false);
    }
}
