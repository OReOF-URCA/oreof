<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\DpeFormation;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
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
                'anneesOrdres' => $formation->getOrdresAnneesPossibles(),
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

        // Années sélectionnées pour le parcours (ou toutes les années autorisées par défaut)
        $anneesOrdresRaw = $request->request->all('anneesOrdres');
        $anneesOrdres = array_filter(array_map('intval', $anneesOrdresRaw), static fn($v) => $v > 0);
        $ordresAutorises = $parcours->getOrdresAnneesPossibles();
        if (empty($anneesOrdres) && !$request->request->has('anneesOrdres')) {
            $anneesOrdres = $ordresAutorises;
        } else {
            $anneesOrdres = array_values(array_intersect($anneesOrdres, $ordresAutorises));
        }

        foreach ($anneesOrdres as $ordre) {
            $annee = new Annee();
            $annee->setParcours($parcours);
            $annee->setOrdre($ordre);
            $annee->setIsOuvert(true);
            $annee->setCapaciteAccueil(0);
            $annee->setHasCapacite(true);
            $annee->setIsProposeRecrutement(true);
            $annee->setRegimeInscription($parcours->getRegimeInscription());
            $em->persist($annee);
            $parcours->addAnnee($annee);

            for ($s = 2 * $ordre - 1; $s <= 2 * $ordre; $s++) {
                $semestre = new Semestre();
                $semestre->setOrdre($s);
                $em->persist($semestre);

                $sp = new SemestreParcours($semestre, $parcours);
                $sp->setAnnee($annee);
                $annee->addParcoursSemestre($sp);
                $parcours->addSemestreParcour($sp);
                $semestre->addSemestreParcour($sp);
                $em->persist($sp);
            }
        }

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

    #[Route('/offre/parcours/{parcours}/annee/modal-add', name: 'offre_v2_annee_modal_add', methods: ['GET'])]
    public function modalAddAnnee(
        Parcours $parcours,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $formation = $parcours->getFormation();
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        $anneesExistantes = [];
        foreach ($parcours->getAnnees() as $annee) {
            if ($annee->getOrdre() !== null) {
                $anneesExistantes[] = $annee->getOrdre();
            }
        }
        sort($anneesExistantes);

        $ordresPossibles = $parcours->getOrdresAnneesPossibles();
        $ordresDisponibles = $parcours->getOrdresAnneesDisponibles();

        $footerTemplate = empty($ordresDisponibles)
            ? '_ui/_footer_cancel.html.twig'
            : '_ui/_footer_submit_cancel.html.twig';

        return $turboStream->streamOpenModalFromTemplates(
            'Ajouter une année',
            $parcours->getLibelle() ?? '',
            'offre_v2/_modal_add_annee.html.twig',
            [
                'parcours' => $parcours,
                'formation' => $formation,
                'typeDiplome' => $formation?->getTypeDiplome(),
                'anneesExistantes' => $anneesExistantes,
                'ordresPossibles' => $ordresPossibles,
                'ordresDisponibles' => $ordresDisponibles,
            ],
            $footerTemplate,
            [
                'submitLabel' => 'Ajouter',
            ]
        );
    }

    #[Route('/offre/parcours/{parcours}/annee/modal-add/sauvegarder', name: 'offre_v2_annee_modal_add_sauvegarder', methods: ['POST'])]
    public function modalSauvegarderAddAnnee(
        Parcours $parcours,
        Request $request,
        EntityManagerInterface $em,
        TurboStreamResponseFactory $turboStream,
        RemplissageSuspension $remplissageSuspension,
    ): Response {
        $formation = $parcours->getFormation();
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        $csrfToken = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('annee_add_' . $parcours->getId(), $csrfToken)) {
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

        $ordre = (int)$request->request->get('ordre');
        if ($ordre <= 0) {
            return $turboStream->streamToastError('L\'ordre de l\'année doit être un entier positif.');
        }

        if (!$parcours->isOrdreAnneeValide($ordre)) {
            $ordresPossibles = $parcours->getOrdresAnneesPossibles();
            $sigle = $formation?->getTypeDiplome()?->getLibelleCourt() ?? 'ce type de diplôme';
            $min = !empty($ordresPossibles) ? min($ordresPossibles) : 1;
            $max = !empty($ordresPossibles) ? max($ordresPossibles) : 1;

            return $turboStream->streamToastError(
                sprintf(
                    'L\'année %d est incohérente pour %s (années autorisées : %s %d à %d).',
                    $ordre,
                    $sigle,
                    $sigle,
                    $min,
                    $max
                )
            );
        }

        foreach ($parcours->getAnnees() as $existingAnnee) {
            if ($existingAnnee->getOrdre() === $ordre) {
                return $turboStream->streamToastError(sprintf('L\'année %d existe déjà pour ce parcours.', $ordre));
            }
        }

        $annee = new Annee();
        $annee->setParcours($parcours);
        $annee->setOrdre($ordre);
        $annee->setIsOuvert(true);
        $annee->setCapaciteAccueil(0);
        $annee->setHasCapacite(true);
        $annee->setIsProposeRecrutement(true);
        $annee->setRegimeInscription($parcours->getRegimeInscription());
        $parcours->addAnnee($annee);
        $em->persist($annee);

        // Initialiser les 2 semestres associés à cette année
        for ($s = 2 * $ordre - 1; $s <= 2 * $ordre; $s++) {
            $semestre = new Semestre();
            $semestre->setOrdre($s);
            $em->persist($semestre);

            $sp = new SemestreParcours($semestre, $parcours);
            $sp->setAnnee($annee);
            $annee->addParcoursSemestre($sp);
            $parcours->addSemestreParcour($sp);
            $semestre->addSemestreParcour($sp);
            $em->persist($sp);
        }

        $remplissageSuspension->suspendre();
        $em->flush();

        $this->addFlashBag('success', sprintf('L\'année %d a été ajoutée avec succès au parcours.', $ordre));

        return $turboStream->stream('offre_v2/_parcours_reload_stream.html.twig', [
            'formation' => $formation,
            'parcours' => $parcours,
            'toastMessage' => sprintf('L\'année %d a été ajoutée avec succès.', $ordre),
        ]);
    }

    #[Route('/offre/annee/{annee}/modal-delete', name: 'offre_v2_annee_modal_delete', methods: ['GET'])]
    public function modalDeleteAnnee(
        Annee $annee,
        TurboStreamResponseFactory $turboStream,
        EntityManagerInterface $em,
    ): Response {
        $parcours = $annee->getParcours();
        $formation = $parcours?->getFormation();
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        $campagne = $this->getCampagneCollecte();
        if ($parcours === null || !$this->isParcoursNouvellementCree($parcours, $campagne, $em)) {
            return $turboStream->streamToastError('Seules les années des parcours nouvellement créés peuvent être supprimées.');
        }

        $typeCourt = $formation?->getTypeDiplome()?->getLibelleCourt() ?? 'Année';

        return $turboStream->streamOpenModalFromTemplates(
            sprintf('Supprimer l\'année %s %d', $typeCourt, $annee->getOrdre() ?? 0),
            $parcours->getLibelle() ?? '',
            'offre_v2/_modal_delete_annee.html.twig',
            [
                'annee' => $annee,
                'parcours' => $parcours,
                'formation' => $formation,
                'typeDiplome' => $formation?->getTypeDiplome(),
            ],
            '_ui/_footer_delete_cancel.html.twig',
            [
                'url' => $this->generateUrl('offre_v2_annee_supprimer', ['annee' => $annee->getId()]),
                'csrf_token' => 'annee_delete_' . $annee->getId(),
                'submitLabel' => 'Supprimer l\'année',
            ]
        );
    }

    #[Route('/offre/annee/{annee}/supprimer', name: 'offre_v2_annee_supprimer', methods: ['POST', 'DELETE'])]
    public function supprimerAnnee(
        Annee $annee,
        Request $request,
        EntityManagerInterface $em,
        TurboStreamResponseFactory $turboStream,
        RemplissageSuspension $remplissageSuspension,
    ): Response {
        $parcours = $annee->getParcours();
        $formation = $parcours?->getFormation();
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        $campagne = $this->getCampagneCollecte();
        if ($parcours === null || !$this->isParcoursNouvellementCree($parcours, $campagne, $em)) {
            return $turboStream->streamToastError('Seules les années des parcours nouvellement créés peuvent être supprimées.');
        }

        $csrfToken = (string)$request->request->get('csrf_token', $request->request->get('_token', ''));
        if (!$this->isCsrfTokenValid('annee_delete_' . $annee->getId(), $csrfToken)) {
            return $turboStream->streamToastError('Token CSRF invalide.');
        }

        $isSesOrAdmin = $this->isGranted('ROLE_SES') || $this->isGranted('ROLE_ADMIN');
        if (!$isSesOrAdmin) {
            if (!$campagne->isPeriodActive()) {
                return $turboStream->streamToastError('La campagne de collecte est fermée. Suppression impossible.');
            }

            $dpeFormation = $em->getRepository(DpeFormation::class)->findOneBy([
                'formation' => $formation,
                'campagneCollecte' => $campagne,
            ]);

            $isBrouillon = $dpeFormation === null || array_key_exists('brouillon', $dpeFormation->getEtatValidation());
            if (!$isBrouillon) {
                return $turboStream->streamToastError('L\'offre a déjà été transmise pour validation. Suppression impossible.');
            }
        }

        $ordre = $annee->getOrdre();

        // Supprimer les paramètres de plateformes liés à cette année
        foreach ($annee->getAdmissionPlateformeParametres() as $param) {
            $em->remove($param);
        }

        // Supprimer les SemestreParcours et Semestre associés
        foreach ($annee->getParcoursSemestre() as $sp) {
            $semestre = $sp->getSemestre();
            $parcours->removeSemestreParcour($sp);
            $annee->removeParcoursSemestre($sp);
            $em->remove($sp);
            if ($semestre !== null && $semestre->getSemestreParcours()->count() <= 1) {
                $em->remove($semestre);
            }
        }

        $parcours->removeAnnee($annee);
        $em->remove($annee);

        $remplissageSuspension->suspendre();
        $em->flush();

        $this->addFlashBag('success', sprintf('L\'année %d a été supprimée avec succès.', $ordre));

        return $turboStream->stream('offre_v2/_parcours_reload_stream.html.twig', [
            'formation' => $formation,
            'parcours' => $parcours,
            'toastMessage' => sprintf('L\'année %d a été supprimée avec succès.', $ordre),
        ]);
    }

    private function isParcoursNouvellementCree(Parcours $parcours, CampagneCollecte $campagne, EntityManagerInterface $em): bool
    {
        $dpeParcours = $em->getRepository(DpeParcours::class)->findOneBy([
            'parcours' => $parcours,
            'campagneCollecte' => $campagne,
        ]);

        if ($dpeParcours !== null && $dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::CREATION) {
            return true;
        }

        return $parcours->getParcoursOrigine() === null && $parcours->getParcoursOrigineCopie() === null;
    }
}
