<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\DpeFormation;
use App\Entity\DpeParcours;
use App\Entity\Parcours;
use App\Entity\RythmeFormation;
use App\Enums\TypeModificationDpeEnum;
use App\Enums\TypeParcoursEnum;
use App\Repository\RythmeFormationRepository;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OffreParcoursController extends BaseController
{
    #[Route('/offre/parcours/{parcours}/modal-edit', name: 'offre_v2_parcours_modal_edit', methods: ['GET'])]
    public function modalEditParcours(
        RythmeFormationRepository $rythmeFormationRepository,
        Parcours $parcours,
        TurboStreamResponseFactory $turboStream,
        EntityManagerInterface $em,
    ): Response {
        $campagne = $this->getCampagneCollecte();
        $formation = $parcours->getFormation();

        $dpeParcours = $em->getRepository(DpeParcours::class)->findOneBy([
            'parcours' => $parcours,
            'campagneCollecte' => $campagne,
        ]);

        $parcoursOrigine = $parcours->getParcoursOrigine() ?? $parcours->getParcoursOrigineCopie();

        $isModifieN1 = ($dpeParcours?->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_INTITULE);
        if (!$isModifieN1 && $parcoursOrigine && $parcoursOrigine->getLibelle() !== $parcours->getLibelle()) {
            $isModifieN1 = true;
        }

        return $turboStream->streamOpenModalFromTemplates(
            'Modifier le parcours',
            $parcours->getLibelle(),
            'offre_v2/_modal_edit_parcours.html.twig',
            [
                'parcours' => $parcours,
                'formation' => $formation,
                'parcoursOrigine' => $parcoursOrigine,
                'dpeParcours' => $dpeParcours,
                'isModifieN1' => $isModifieN1,
                'rythmesFormation' => $rythmeFormationRepository->findBy([], ['libelle' => 'ASC']),
                'typesParcours' => TypeParcoursEnum::cases(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Enregistrer',
            ]
        );
    }

    #[Route('/offre/parcours/{parcours}/modal-edit/sauvegarder', name: 'offre_v2_parcours_modal_sauvegarder', methods: ['POST'])]
    public function modalSauvegarderParcours(
        Parcours $parcours,
        Request $request,
        EntityManagerInterface $em,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $csrfToken = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('parcours_edit_' . $parcours->getId(), $csrfToken)) {
            return $turboStream->streamToastError('Token CSRF invalide.');
        }

        $campagne = $this->getCampagneCollecte();
        $formation = $parcours->getFormation();

        $isSesOrAdmin = $this->isGranted('ROLE_SES') || $this->isGranted('ROLE_ADMIN');
        if (!$isSesOrAdmin) {
            if (!$campagne->isPeriodActive()) {
                return $turboStream->streamToastError('La campagne de collecte est fermée. Modification impossible.');
            }

            $dpeFormation = $em->getRepository(DpeFormation::class)->findOneBy([
                'formation' => $formation,
                'campagneCollecte' => $campagne,
            ]);

            $isBrouillon = $dpeFormation === null || array_key_exists('brouillon', $dpeFormation->getEtatValidation());
            if (!$isBrouillon) {
                return $turboStream->streamToastError('L\'offre a déjà été transmise pour validation. Modification impossible.');
            }
        }

        $libelle = trim((string)$request->request->get('libelle', ''));
        if ($libelle === '') {
            return $turboStream->streamToastError('Le libellé du parcours ne peut pas être vide.');
        }

        $parcours->setLibelle($libelle);

        $typeParcoursRaw = (string)$request->request->get('typeParcours');
        if ($typeParcoursRaw !== '') {
            $typeEnum = TypeParcoursEnum::tryFrom($typeParcoursRaw);
            if ($typeEnum !== null) {
                $parcours->setTypeParcours($typeEnum);
            }
        }

        $rythmeRaw = $request->request->all('rythmeFormation');
        $rythmeId = !empty($rythmeRaw) ? (int)reset($rythmeRaw) : (int)$request->request->get('rythmeFormation');
        if ($rythmeId > 0) {
            $rythme = $em->getRepository(RythmeFormation::class)->find($rythmeId);
            $parcours->setRythmeFormation($rythme);
        } else {
            $parcours->setRythmeFormation(null);
        }

        $dpeParcours = $em->getRepository(DpeParcours::class)->findOneBy([
            'parcours' => $parcours,
            'campagneCollecte' => $campagne,
        ]);

        if ($dpeParcours === null) {
            $dpeParcours = new DpeParcours();
            $dpeParcours->setParcours($parcours);
            $dpeParcours->setCampagneCollecte($campagne);
            $dpeParcours->setFormation($formation);
            $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
            $em->persist($dpeParcours);
        }

        $isModifieN1 = $request->request->getBoolean('isModifieN1');
        if ($isModifieN1) {
            $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_INTITULE);
        } else {
            if ($dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_INTITULE) {
                $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
            }
        }

        $em->flush();

        $dpeFormation = $em->getRepository(DpeFormation::class)->findOneBy([
            'formation' => $formation,
            'campagneCollecte' => $campagne,
        ]);
        $isBrouillon = $dpeFormation === null || array_key_exists('brouillon', $dpeFormation->getEtatValidation());
        $canEdit = $isSesOrAdmin || $isBrouillon;

        return $turboStream->stream('offre_v2/_parcours_save_stream.html.twig', [
            'parcours' => $parcours,
            'campagne' => $campagne,
            'can_edit' => $canEdit,
        ]);
    }
}
