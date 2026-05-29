<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/TypeDiplome/LicenceController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/04/2023 22:19
 */

namespace App\Controller\TypeDiplome;

use App\Classes\GetElementConstitutif;
use App\Classes\JsonReponse;
use App\Controller\BaseController;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Repository\ElementConstitutifRepository;
use App\Repository\TypeDiplomeRepository;
use App\Repository\TypeEpreuveRepository;
use App\Service\TypeDiplomeResolver;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LicenceController extends BaseController
{

    private TypeDiplomeHandlerInterface $typeDiplomeHandler;
    private TypeDiplome $typeDiplome;

    public function __construct(
        TypeDiplomeResolver   $typeDiplomeResolver,
        TypeDiplomeRepository $typeDiplomeRepository)
    {
        $this->typeDiplome = $typeDiplomeRepository->findOneBy(['libelle_court' => 'L']);
        if ($this->typeDiplome === null) {
            throw new Exception('Type de diplome Licence non trouvé');
        }

        $this->typeDiplomeHandler = $typeDiplomeResolver->get($this->typeDiplome);
    }



    #[Route('/type_diplome/change/licence/hd/{ficheMatiere}', name: 'type_diplome_licence_change_hd')]
    public function changeTypeHd(
        Request $request,
        EntityManagerInterface $entityManager,
        TypeEpreuveRepository $typeEpreuveRepository,
        FicheMatiere $ficheMatiere,
    ): Response
    {
        $typeEpreuves = $typeEpreuveRepository->findByTypeDiplome($this->typeDiplome);

        if ($request->query->get('type') !== $ficheMatiere->getTypeMccc()) {
            $ficheMatiere->setTypeMccc($request->query->get('type'));
            $this->typeDiplomeHandler->clearMcccs($ficheMatiere);
            $entityManager->flush();
        }

        switch ($request->query->get('type')) {
            case 'cc':
                if ($this->typeDiplome->getLibelleCourt() !== 'L') {
                    //seul cas particulier, pour les autres mêmes formulaires
                    return $this->render('typeDiplome/mccc/licence/_cc_autres_diplomes.html.twig', [
                        'mcccs' => $this->typeDiplomeHandler->getMcccs($ficheMatiere),
                        'typeEpreuves' => $typeEpreuves,
                        'disabled' => false,
                    ]);
                }

                return $this->render('typeDiplome/mccc/licence/_cc.html.twig', [
                    'mcccs' => $this->typeDiplomeHandler->getMcccs($ficheMatiere),
                    'typeEpreuves' => $typeEpreuves,
                    'disabled' => false,
                ]);

            case 'cci':
                return $this->render('typeDiplome/mccc/licence/_cci.html.twig', [
                    'mcccs' => $this->typeDiplomeHandler->getMcccs($ficheMatiere),
                    'typeEpreuves' => $typeEpreuves,
                    'disabled' => false,
                ]);
            case 'cc_ct':
                return $this->render('typeDiplome/mccc/licence/_cc_ct.html.twig', [
                    'mcccs' => $this->typeDiplomeHandler->getMcccs($ficheMatiere),
                    'typeEpreuves' => $typeEpreuves,
                    'disabled' => false,
                ]);
            case 'ct':
                return $this->render('typeDiplome/mccc/licence/_ct.html.twig', [
                    'mcccs' => $this->typeDiplomeHandler->getMcccs($ficheMatiere),
                    'typeEpreuves' => $typeEpreuves,
                    'disabled' => false,
                ]);
        }

        return $this->render('typeDiplome/mccc/licence/_vide.html.twig');
    }

    #[Route('/type_diplome/change/licence/{elementConstitutif}/{parcours}', name: 'type_diplome_licence_change')]
    public function changeType(
        Request $request,
        EntityManagerInterface $entityManager,
        TypeEpreuveRepository $typeEpreuveRepository,
        ElementConstitutif $elementConstitutif,
        Parcours $parcours
    ): Response
    {
        if ($elementConstitutif->getFicheMatiere()?->isHorsDiplome() === true) {
            $typeDiplome = $this->typeDiplome;
            $typeDiplomeHandler = $this->typeDiplomeHandler;
        } else {
            $typeDiplome = $elementConstitutif->getFicheMatiere()?->getParcours()?->getFormation()?->getTypeDiplome();
            if ($typeDiplome === null) {
                $typeDiplome = $this->typeDiplome;
            }
            $typeDiplomeHandler = $this->typeDiplomeResolver->get($typeDiplome);

        }

        $typeEpreuves = $typeEpreuveRepository->findByTypeDiplome($typeDiplome ?? $this->typeDiplome);

        $raccroche = !($elementConstitutif->getNatureUeEc()->isChoix() || $elementConstitutif->getNatureUeEc()->isLibre()) && $elementConstitutif->getFicheMatiere()?->getParcours()?->getId() !== $parcours->getId();
        $getElement = new GetElementConstitutif($elementConstitutif, $parcours);
        $disabled = ($elementConstitutif->isMcccSpecifiques() === false && $raccroche) || ($elementConstitutif->getFicheMatiere()?->isMcccImpose());
        $owner = $getElement->getElementConstitutif();

        if ($request->query->get('type') !== $owner->getTypeMccc()) {
            $owner->setTypeMccc($request->query->get('type'));
            $typeDiplomeHandler->clearMcccs($elementConstitutif);
            $entityManager->flush();
            $entityManager->refresh($elementConstitutif);
        }

        if ($disabled === true) {
            $folder = 'mccc-non-editable';
        } else {
            $folder = 'mccc';
        }

        switch ($request->query->get('type')) {
            case 'cc':
                if ($typeDiplome->getLibelleCourt() !== 'L') {

                    //seul cas particulier, pour les autres mêmes formulaires
                    return $this->render('typeDiplome/'.$folder.'/licence/_cc_autres_diplomes.html.twig', [
                        'mcccs' => $getElement->getMcccsFromFicheMatiere($typeDiplomeHandler),
                        'typeEpreuves' => $typeEpreuves,
                        'elementConstitutif' => $elementConstitutif,
                    ]);
                }

                return $this->render('typeDiplome/'.$folder.'/licence/_cc.html.twig', [
                    'mcccs' => $getElement->getMcccsFromFicheMatiere($typeDiplomeHandler),
                    'typeEpreuves' => $typeEpreuves,
                    'elementConstitutif' => $elementConstitutif,
                ]);

            case 'cci':
                return $this->render('typeDiplome/'.$folder.'/licence/_cci.html.twig', [
                    'mcccs' => $getElement->getMcccsFromFicheMatiere($typeDiplomeHandler),
                    'typeEpreuves' => $typeEpreuves,
                    'elementConstitutif' => $elementConstitutif,
                ]);
            case 'cc_ct':
                return $this->render('typeDiplome/'.$folder.'/licence/_cc_ct.html.twig', [
                    'mcccs' => $getElement->getMcccsFromFicheMatiere($typeDiplomeHandler),
                    'typeEpreuves' => $typeEpreuves,
                    'elementConstitutif' => $elementConstitutif,
                ]);
            case 'ct':
                return $this->render('typeDiplome/'.$folder.'/licence/_ct.html.twig', [
                    'mcccs' => $getElement->getMcccsFromFicheMatiere($typeDiplomeHandler),
                    'typeEpreuves' => $typeEpreuves,
                    'elementConstitutif' => $elementConstitutif,
                ]);
        }

        return $this->render('typeDiplome/mccc/licence/_vide.html.twig');
    }



    #[Route('/type_diplome/save/mccc/hd/{ficheMatiere}', name: 'app_fiche_matiere_mccc_hors_diplome')]
    public function saveMcccHorsDiplome(
        Request $request,
        FicheMatiere $ficheMatiere,
    ): Response {
        if ($request->isMethod('POST')) {
            $this->typeDiplomeHandler->saveMcccs($ficheMatiere, $request->request);
            return JsonReponse::success('MCCCs enregistrés');
        }

        return JsonReponse::error('Erreur lors de l\'enregistrement des MCCCs');
    }
}
