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
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parcours/v2/ec', name: 'parcours_mccc')]
class ParcoursMcccController extends BaseController
{
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
