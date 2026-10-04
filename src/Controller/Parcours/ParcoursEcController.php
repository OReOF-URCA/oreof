<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursV2Controller.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/01/2026 22:28
 */

namespace App\Controller\Parcours;

use App\Classes\EcOrdre;
use App\Classes\GetElementConstitutif;
use App\Controller\BaseController;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\Entity\TypeEc;
use App\Entity\Ue;
use App\Form\EcStep4Type;
use App\Form\ElementConstitutifType;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\NatureUeEcRepository;
use App\Repository\TypeEcRepository;
use App\Service\EC\Heures;
use App\Service\Validation\ValidationDirtyMarker;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parcours/v2/ec', name: 'parcours_ec')]
class ParcoursEcController extends BaseController
{

    #[Route('/{semestreParcours}/ajouter-ec/{ue}', name: '_ajouter_ec_ue', methods: ['GET', 'POST'])]
    public function new(
        TypeDiplomeResolver $typeDiplomeResolver,
        TurboStreamResponseFactory   $turboStream,
        EcOrdre                      $ecOrdre,
        Request                      $request,
        ElementConstitutifRepository $elementConstitutifRepository,
        Ue                           $ue,
        SemestreParcours    $semestreParcours
    ): Response
    {
        $parcours = $semestreParcours->getParcours();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $elementConstitutif = new ElementConstitutif();
        $elementConstitutif->setFicheMatiere(null);
        $elementConstitutif->setParcours($parcours);

        $elementConstitutif->setModaliteEnseignement($parcours->getModalitesEnseignement());
        $elementConstitutif->setUe($ue);

        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        // récupération des fiches matières

        $form = $this->createForm(ElementConstitutifType::class, $elementConstitutif, [
            'action' => $this->generateUrl(
                'parcours_ec_ajouter_ec_ue',
                ['ue' => $ue->getId(), 'semestreParcours' => $semestreParcours->getId(), 'element' => $request->query->get('element')]
            ),
            'typeDiplome' => $typeDiplome,
            'parcours' => $parcours,
            'campagneCollecte' => $this->getCampagneCollecte(),
            'isAdmin' => $isAdmin
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($elementConstitutif->getNatureUeEc()->isChoix() === false && $elementConstitutif->getNatureUeEc()->isLibre() === false) {

                $lastEc = $ecOrdre->getOrdreSuivant($ue, $request);
                $elementConstitutif->setOrdre($lastEc);
                $elementConstitutif->genereCode();
                $elementConstitutifRepository->save($elementConstitutif, true);
            } elseif ($elementConstitutif->getNatureUeEc()->isChoix() === true) {
                $lastEc = $ecOrdre->getOrdreSuivant($ue, $request);
                $elementConstitutif->setLibelle($form->get('libelleChoix')->getData());
                $elementConstitutif->setFicheMatiere(null);
                $elementConstitutif->setOrdre($lastEc);
                $elementConstitutif->genereCode();
                $elementConstitutifRepository->save($elementConstitutif, true);
            } else {
                $lastEc = $ecOrdre->getOrdreSuivant($ue, $request);
                $elementConstitutif->setLibelle($form->get('libelleLibre')->getData());
                $elementConstitutif->setOrdre($lastEc);
                $elementConstitutif->genereCode();
                $elementConstitutifRepository->save($elementConstitutif, true);
            }

            $typeD = $typeDiplomeResolver->fromParcours($parcours);
            $dtoSemestre = $typeD->calculStructureSemestre($semestreParcours, $parcours);

            //rechargement de l'UE
            return $turboStream->stream('parcours_v2/turbo/add_ec_success.stream.html.twig', [
                'parcours' => $parcours,
                'ue' => $dtoSemestre->getUe($ue->getOrdre()), //todo: tester, car si UE enfant, ca ne marche pas
                'toastMessage' => 'EC ajouté avec succès',
                'semestreParcours' => $semestreParcours,
                'semestre' => $ue->getSemestre(),
                'newEcId' => $elementConstitutif->getId(),
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            'Ajouter une EC',
            'Dans l\UE ' . $ue->display(),
            'parcours_v2/ec/_new.html.twig',
            [
                'elementConstitutif' => $elementConstitutif,
                'form' => $form->createView(),
                'ue' => $ue,
                'parcours' => $parcours,
                'isAdmin' => $isAdmin,
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Créer l\'EC',
            ]
        );


    }


    #[Route('/{semestreParcours}/modifier-ec/{elementConstitutif}', name: '_modifier_ec_ue', methods: ['GET', 'POST'])]
    public function modifier(
        TurboStreamResponseFactory   $turboStream,
        EcOrdre                      $ecOrdre,
        Request                      $request,
        TypeDiplomeResolver $typeDiplomeResolver,
        ElementConstitutifRepository $elementConstitutifRepository,
        ElementConstitutif           $elementConstitutif,
        SemestreParcours $semestreParcours
    ): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $parcours = $semestreParcours->getParcours();
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();
        $ue = $elementConstitutif->getUe();

        $form = $this->createForm(ElementConstitutifType::class, $elementConstitutif, [
            'action' => $this->generateUrl(
                'parcours_ec_modifier_ec_ue',
                ['semestreParcours' => $semestreParcours->getId(), 'elementConstitutif' => $elementConstitutif->getId()]
            ),
            'typeDiplome' => $typeDiplome,
            'parcours' => $parcours,
            'campagneCollecte' => $this->getCampagneCollecte(),
            'isAdmin' => $isAdmin
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($elementConstitutif->getNatureUeEc()->isChoix() === false && $elementConstitutif->getNatureUeEc()->isLibre() === false) {
                $lastEc = $ecOrdre->getOrdreSuivant($ue, $request);
                $elementConstitutif->setOrdre($lastEc);
                $elementConstitutif->genereCode();
                $elementConstitutifRepository->save($elementConstitutif, true);
            } elseif ($elementConstitutif->getNatureUeEc()->isChoix() === true) {
                $lastEc = $ecOrdre->getOrdreSuivant($ue, $request);
                $elementConstitutif->setLibelle($form->get('libelleChoix')->getData());
                $elementConstitutif->setFicheMatiere(null);
                $elementConstitutif->setOrdre($lastEc);
                $elementConstitutif->genereCode();
                $elementConstitutifRepository->save($elementConstitutif, true);
            } else {
                $lastEc = $ecOrdre->getOrdreSuivant($ue, $request);
                $elementConstitutif->setLibelle($form->get('libelleLibre')->getData());
                $elementConstitutif->setOrdre($lastEc);
                $elementConstitutif->genereCode();
                $elementConstitutifRepository->save($elementConstitutif, true);
            }

            $typeD = $typeDiplomeResolver->fromParcours($parcours);
            $dtoSemestre = $typeD->calculStructureSemestre($semestreParcours, $parcours);

            return $turboStream->stream('parcours_v2/turbo/add_ec_success.stream.html.twig', [
                'parcours' => $parcours,
                'ue' => $dtoSemestre->getUe($ue->getOrdre()), //todo: tester, car si UE enfant, ca ne marche pas
                'toastMessage' => 'EC modifié avec succès',
                'semestreParcours' => $semestreParcours,
                'semestre' => $ue->getSemestre(),
                'newEcId' => $elementConstitutif->getId(),
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            'Modifier une EC',
            'EC :  ' . $elementConstitutif->display(),
            'parcours_v2/ec/_new.html.twig',
            [
                'elementConstitutif' => $elementConstitutif,
                'form' => $form->createView(),
                'ue' => $ue,
                'parcours' => $parcours,
                'isAdmin' => $isAdmin,
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Modifier l\'EC',
            ]
        );


    }

    #[Route('/{parcours}/saisie-heure/{id}', name: '_saisir_heures', methods: ['GET', 'POST'])]
    public function saisirHeures(
        TurboStreamResponseFactory $turboStream,
        Heures                     $heures,
        ValidationDirtyMarker      $dirtyMarker,
        Request                    $request,
        Parcours                   $parcours, ElementConstitutif $elementConstitutif): Response
    {
        $isParcoursProprietaire = $elementConstitutif->getFicheMatiere()?->getParcours()?->getId() === $parcours->getId();

        $getElement = new GetElementConstitutif($elementConstitutif, $parcours);
        $ecHeures = $getElement->getFicheMatiereHeures();

        $form = $this->createForm(EcStep4Type::class, $ecHeures, [
            'isModal' => true,
            'data_class' => $ecHeures::class,
            'modalite' => $parcours->getModalitesEnseignement(),
            'action' => $this->generateUrl(
                'parcours_ec_saisir_heures',
                [
                    'id' => $elementConstitutif->getId(),
                    'parcours' => $parcours->getId()
                ]
            ),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $dirtyMarker->markEcDirty($elementConstitutif);

            //sauvegarde des heures
            $heures->saveHeures($elementConstitutif, $parcours, $ecHeures, $request->request);


            return $turboStream->stream('parcours_v2/turbo/heures_ec_success.stream.html.twig', [
                'ec' => $elementConstitutif,
                'parcours' => $parcours,
            ]);
        }

        $body = $this->renderView('parcours_v2/ec/_heures_ec.html.twig', [
            'form' => $form->createView(),
            'ec' => $elementConstitutif,
            'parcours' => $parcours,
            'isParcoursProprietaire' => $isParcoursProprietaire,
            'isMutualisee' => $elementConstitutif->getFicheMatiere()?->getElementConstitutifs()->count() > 0, //todo: mais plus large ca peut venir de l'UE ou du semestre
            'modalite' => $parcours->getModalitesEnseignement()
        ]);

        $footer = $this->renderView('_ui/_footer_submit_cancel.html.twig', [
            'submitLabel' => 'Enregistrer les heures de l\'EC',
        ]);

        return new Response(
            $this->renderView('_ui/open.stream.html.twig', [
                'title' => 'Modifier les heures de l\'EC',
                'subtitle' => 'Dans : EC ' . $elementConstitutif->display(),
                'body' => $body,
                'footer' => $footer,
            ]),
            200,
            ['Content-Type' => 'text/vnd.turbo-stream.html']
        );
    }

//    #[Route('/{parcours}/saisie-heure/{id}', name: '_saisir_heures_post', methods: ['POST'])]
//    public function ecPost(
//        ValidationDirtyMarker $dirtyMarker,
//        Request               $request,
//        Parcours              $parcours,
//        ElementConstitutif    $id
//    ): Response
//    {
//        // Turbo Stream si Turbo le demande (Accept: text/vnd.turbo-stream.html)
//        $accept = $request->headers->get('Accept', '');
//        $wantsTurboStream = str_contains($accept, 'text/vnd.turbo-stream.html');
//
//        //todo: ?? où
//    }

    #[Route('/{semestreParcours}/ue/{ue}/{ec}/pre-delete-ec', name: '_pre_delete')]
    public function preDeleteEc(
        TurboStreamResponseFactory $turboStream,
        CsrfTokenManagerInterface  $csrfTokenManager,
        SemestreParcours           $semestreParcours,
        ElementConstitutif         $ec,
        Ue                         $ue
    ): Response
    {

        return $turboStream->streamOpenModalFromTemplates(
            'Supprimer un EC',
            'Dans : ' . $ue->display() . ', semestre ' . $semestreParcours->getSemestre()?->getOrdre(),
            'parcours_v2/ec/_delete.html.twig',
            [],
            '_ui/_footer_delete_cancel.html.twig',
            [
                'submitLabel' => 'Supprimer l\'EC',
                'url' => $this->generateUrl('parcours_ec_delete', [
                    'semestreParcours' => $semestreParcours->getId(),
                    'ue' => $ue->getId(),
                    'ec' => $ec->getId()
                ]),
                'csrf_token' => $csrfTokenManager->getToken('delete' . $ec->getId()),
            ]
        );
    }

    #[Route('/{semestreParcours}/ue/{ue}/{ec}/delete-ec', name: '_delete')]
    public function deleteEc(
        TurboStreamResponseFactory $turboStream,
        Request                    $request,
        SemestreParcours           $semestreParcours,
        ElementConstitutif         $ec,
        Ue                         $ue,
        TypeDiplomeResolver        $typeDiplomeResolver
    ): Response
    {
        if ($this->isCsrfTokenValid(
            'delete' . $ec->getId(),
            $request->query->get('csrf_token')
        )) {
            $parcours = $semestreParcours->getParcours();
            $typeD = $typeDiplomeResolver->fromParcours($parcours);
            $dtoSemestre = $typeD->calculStructureSemestre($semestreParcours, $parcours);

            //todo: supprimer l'EC, les EC enfants, renuméroter
            //faire un service avec renumérotation en option, pourra servir pour la suppression d'un semestre ou d'un aprcours

            return $turboStream->stream('parcours_v2/turbo/add_ec_success.stream.html.twig', [
                'parcours' => $parcours,
                'ue' => $dtoSemestre->getUe($ue->getOrdre()), //todo: tester, car si UE enfant, ca ne marche pas
                'toastMessage' => 'EC supprimé avec succès',
                'semestreParcours' => $semestreParcours,
                'semestre' => $ue->getSemestre()
            ]);
        }

        return $turboStream->streamToastError(
            'Erreur lors de la suppression de l\'UE',
        );
    }
}
