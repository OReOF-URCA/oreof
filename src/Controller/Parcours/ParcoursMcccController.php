<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursV2Controller.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/01/2026 22:28
 */

namespace App\Controller\Parcours;

use App\Classes\GetElementConstitutif;
use App\Controller\BaseController;
use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use App\Form\EcStep4Type;
use App\Service\VersioningParcours;
use App\TypeDiplome\McccDisplayInterface;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parcours/v2/ec', name: 'parcours_mccc')]
class ParcoursMcccController extends BaseController
{
    #[Route('/{parcours}/mccc/{id}/voir', name: '_voir', methods: ['GET'])]
    public function voir(
        TypeDiplomeResolver        $typeDiplomeResolver,
        TurboStreamResponseFactory $turboStream,
        Parcours                   $parcours,
        ElementConstitutif         $elementConstitutif,
        VersioningParcours         $versioningParcours
    ): Response {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();
        $typeD = $typeDiplomeResolver->fromParcours($parcours);
        $getElement = new GetElementConstitutif($elementConstitutif, $parcours);
        $typeMccc = $getElement->getTypeMcccFromFicheMatiere();
        $ects = $getElement->getFicheMatiereEcts();

        $lastVersion = $versioningParcours->getLastVersionOrLastYearCfvu($parcours);

        $typeMcccLibelle = [
            'ct' => 'Contrôle Terminal',
            'cc' => 'Contrôle Continu',
            'cci' => 'Contrôle Continu Intégral',
            'cc_ct' => 'Contrôle Continu + Contrôle Terminal',
        ];

        $mcccs = $typeD instanceof McccDisplayInterface
            ? $typeD->getDisplayMccc($getElement->getMcccsFromFicheMatiere($typeD), $typeMccc ?? '')
            : $typeD->getMcccs($elementConstitutif);

        $template = 'typeDiplome/' . $typeD->getTemplateFolder() . '/mccc-non-editable/' . constant($typeD::class . '::TEMPLATE_FORM_MCCC');

        return $turboStream->streamOpenModalFromTemplates(
            'Modalités de Contrôle des Connaissances et des Compétences',
            'Dans l\'EC ' . $elementConstitutif->display(),
            $template,
            [
                'isMcccImpose' => $elementConstitutif->getFicheMatiere()?->isMcccImpose(),
                'isEctsImpose' => $elementConstitutif->getFicheMatiere()?->isEctsImpose(),
                'typeMccc' => $typeMccc,
                'typeEpreuves' => $typeD->getTypeEpreuves(),
                'typeMcccLibelle' => $typeMcccLibelle,
                'ec' => $elementConstitutif,
                'ects' => $ects,
                'typeDiplome' => $typeD,
                'templateForm' => $typeD->getMcccTemplate(),
                'mcccs' => $mcccs,
                'isFromVersioning' => 'false',
                'lastVersion' => $lastVersion,
                'libelleQuelleVersion' => 'Version actuellement saisie en attente de validation',
                'parcoursId' => $parcours->getId(),
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }
    #[Route('/{parcours}/mccc/{id}', name: '_saisir', methods: ['GET'])]
    public function saisir(
        TypeDiplomeResolver        $typeDiplomeResolver,
        TurboStreamResponseFactory $turboStream,
        Parcours                   $parcours, ElementConstitutif $elementConstitutif): Response
    {
        $isParcoursProprietaire = $elementConstitutif->getFicheMatiere()?->getParcours()?->getId() === $parcours->getId();

        $typeDiplome = $typeDiplomeResolver->fromParcours($parcours);
        $getElement = new GetElementConstitutif($elementConstitutif, $parcours);
        $typeMccc = $getElement->getTypeMcccFromFicheMatiere();
        $elementConstitutif->settypeMccc($typeMccc);
        return $turboStream->streamOpenModalFromTemplates(
            'Modifier les MCCC de l\'EC',
            'Dans l\'EC ' . $elementConstitutif->display(),
            'typeDiplome/' . $typeDiplome->getTemplateFolder() . '/mccc/_mccc.html.twig',
            [
                'form' => $typeDiplome->createFormMccc($elementConstitutif)->createView(),
                'ec' => $elementConstitutif,
                'parcours' => $parcours,
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Enregistrer les MCCC de l\'EC',
            ]
        );
    }

    #[Route('/{parcours}/mccc/{id}', name: '_saisir_post', methods: ['POST'])]
    public function saisirPost(
        Request            $request,
        Parcours           $parcours,
        ElementConstitutif $id
    ): Response
    {
        //        $user = new User();
        //        $form = $this->createForm(UserType::class, $user, [
        //            'action' => $this->generateUrl('user_create_modal'),
        //            'method' => 'POST',
        //        ]);
        //        $form->handleRequest($request);
        //
        //        if (!$form->isSubmitted() || !$form->isValid()) {
        // 422 conseillé avec Turbo : Turbo garde le contexte et affiche les erreurs
        //            return $this->render('user/modal_form.html.twig', [
        //                'form' => $form,
        //                'title' => 'Créer un utilisateur',
        //            ], new Response(status: 422));
        //        }

        //        $em->persist($user);
        //        $em->flush();

        // Turbo Stream si Turbo le demande (Accept: text/vnd.turbo-stream.html)
        $accept = $request->headers->get('Accept', '');
        $wantsTurboStream = str_contains($accept, 'text/vnd.turbo-stream.html');

        if ($wantsTurboStream) {
            return $this->render('parcours_v2/ec/_heures_ec_valide.html.twig', [
                'ec' => $id,
                'parcours' => $parcours,
            ], new Response(headers: ['Content-Type' => 'text/vnd.turbo-stream.html']));
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
