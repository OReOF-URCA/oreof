<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\DpeFormation;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use App\Entity\User;
use App\Enums\RegimeInscriptionEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Enums\TypeParcoursEnum;
use App\Service\Offre\RemplissageSuspension;
use App\Service\Parcours\GenereStructureParcours;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OffreParcoursController extends BaseController
{
    use OffreAccessTrait;
  
    #[Route('/offre/{slug}/parcours/modal-add', name: 'offre_v2_parcours_modal_add', methods: ['GET'])]
    public function modalAddParcours(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation $formation,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        $parcours = new Parcours($formation);

        return $turboStream->streamOpenModalFromTemplates(
            'Ajouter un parcours',
            $formation->getDisplayLong(),
            'offre_v2/_modal_edit_parcours.html.twig',
            [
                'parcours' => $parcours,
                'formation' => $formation,
                'parcoursOrigine' => null,
                'dpeParcours' => null,
                'isAdd' => true,
                'regimesInscription' => RegimeInscriptionEnum::cases(),
                'typesParcours' => TypeParcoursEnum::cases(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Ajouter',
            ]
        );
    }

    #[Route('/offre/{slug}/parcours/modal-add/sauvegarder', name: 'offre_v2_parcours_modal_add_sauvegarder', methods: ['POST'])]
    public function modalSauvegarderAddParcours(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation $formation,
        Request $request,
        EntityManagerInterface $em,
        TurboStreamResponseFactory $turboStream,
        GenereStructureParcours $genereStructureParcours,
        RemplissageSuspension $remplissageSuspension,
    ): Response {
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        $csrfToken = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('parcours_add_' . $formation->getId(), $csrfToken)) {
            return $turboStream->streamToastError('Token CSRF invalide.');
        }

        $campagne = $this->getCampagneCollecte();

        $isSesOrAdmin = $this->isGranted('ROLE_SES') || $this->isGranted('ROLE_ADMIN');
        if (!$isSesOrAdmin) {
            if (!$campagne->isPeriodActive()) {
                return $turboStream->streamToastError('La campagne de collecte est fermée. Ajout impossible.');
            }

            $dpeFormation = $em->getRepository(DpeFormation::class)->findOneBy([
                'formation' => $formation,
                'campagneCollecte' => $campagne,
            ]);

            $isBrouillon = $dpeFormation === null || array_key_exists('brouillon', $dpeFormation->getEtatValidation());
            if (!$isBrouillon) {
                return $turboStream->streamToastError('L\'offre a déjà été transmise pour validation. Ajout impossible.');
            }
        }

        $libelle = trim((string)$request->request->get('libelle', ''));
        if ($libelle === '') {
            return $turboStream->streamToastError('Le libellé du parcours ne peut pas être vide.');
        }

        $parcours = new Parcours($formation);
        $parcours->setLibelle($libelle);

        $typeParcoursRaw = (string)$request->request->get('typeParcours');
        if ($typeParcoursRaw !== '') {
            $typeEnum = TypeParcoursEnum::tryFrom($typeParcoursRaw);
            if ($typeEnum !== null) {
                $parcours->setTypeParcours($typeEnum);
            }
        }

        $regimesRaw = $request->request->all('regimeInscription');
        $regimes = [];
        foreach ($regimesRaw as $raw) {
            if ($raw === '' || $raw === null) {
                continue;
            }
            $enum = RegimeInscriptionEnum::tryFrom((string)$raw);
            if ($enum !== null) {
                $regimes[] = $enum;
            }
        }
        $parcours->setRegimeInscription($regimes);

        if ($formation->getResponsableMention() !== null) {
            $parcours->setRespParcours($formation->getResponsableMention());
        }

        $em->persist($parcours);
        $formation->addParcour($parcours);

        // Structure du parcours (années et semestres)
        $genereStructureParcours->genereStructureParcours($parcours);

        // DPE Parcours
        $dpeParcours = new DpeParcours();
        $dpeParcours->setParcours($parcours);
        $dpeParcours->setCampagneCollecte($campagne);
        $dpeParcours->setFormation($formation);
        $dpeParcours->setVersion('0.1');
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::CREATION);
        $em->persist($dpeParcours);
        $parcours->addDpeParcour($dpeParcours);

        // Historique
        $histo = new HistoriqueParcours();
        $histo->setParcours($parcours);
        $histo->setCreated(new \DateTime());
        $histo->setEtat('valide');
        $histo->setEtape('creation');
        $histo->setUser($this->getUser() instanceof User ? $this->getUser() : null);
        $em->persist($histo);

        $remplissageSuspension->suspendre();
        $em->flush();

        $this->addFlashBag('success', 'Le parcours a été ajouté avec succès.');

        return $turboStream->stream('offre_v2/_parcours_add_stream.html.twig', [
            'formation' => $formation,
            'parcours' => $parcours,
        ]);
    }

    #[Route('/offre/parcours/{parcours}/modal-edit', name: 'offre_v2_parcours_modal_edit', methods: ['GET'])]
    public function modalEditParcours(
        Parcours $parcours,
        TurboStreamResponseFactory $turboStream,
        EntityManagerInterface $em,
    ): Response {
        $campagne = $this->getCampagneCollecte();
        $formation = $parcours->getFormation();
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        $dpeParcours = $em->getRepository(DpeParcours::class)->findOneBy([
            'parcours' => $parcours,
            'campagneCollecte' => $campagne,
        ]);

        $parcoursOrigine = $parcours->getParcoursOrigine() ?? $parcours->getParcoursOrigineCopie();

        return $turboStream->streamOpenModalFromTemplates(
            'Modifier le parcours',
            $parcours->getLibelle(),
            'offre_v2/_modal_edit_parcours.html.twig',
            [
                'parcours' => $parcours,
                'formation' => $formation,
                'parcoursOrigine' => $parcoursOrigine,
                'dpeParcours' => $dpeParcours,
                'regimesInscription' => RegimeInscriptionEnum::cases(),
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
        RemplissageSuspension $remplissageSuspension,
    ): Response {
        $this->denyAccessUnlessCanConfigurerOffre($parcours->getFormation());

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

        $regimesRaw = $request->request->all('regimeInscription');
        $regimes = [];
        foreach ($regimesRaw as $raw) {
            if ($raw === '' || $raw === null) {
                continue;
            }
            $enum = RegimeInscriptionEnum::tryFrom((string)$raw);
            if ($enum !== null) {
                $regimes[] = $enum;
            }
        }
        $parcours->setRegimeInscription($regimes);

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

        $parcoursOrigine = $parcours->getParcoursOrigine() ?? $parcours->getParcoursOrigineCopie();
        if ($dpeParcours->getEtatReconduction() !== TypeModificationDpeEnum::CREATION) {
            if ($parcoursOrigine && $parcoursOrigine->getLibelle() !== $parcours->getLibelle()) {
                $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_INTITULE);
            } elseif ($dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_INTITULE) {
                $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
            }
        }

        $remplissageSuspension->suspendre();
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
