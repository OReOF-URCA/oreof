<?php

namespace App\Controller;

use App\Repository\FormationRepository;
use App\Service\FormationDiffBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/formation_fusion', name: 'app_formation_fusion_')]
#[IsGranted('ROLE_ADMIN')]
class FormationFusionController extends BaseController
{
    public function __construct(
        private readonly FormationRepository $formationRepository,
        private readonly EntityManagerInterface $em,
        private readonly FormationDiffBuilder $diffBuilder,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $formations = $this->formationRepository->findByCampagneCollecte($this->getCampagneCollecte());

        return $this->render('admin/formation_fusion/index.html.twig', [
            'formations' => $formations,
        ]);
    }

    #[Route('/diff', name: 'diff', methods: ['GET', 'POST'])]
    public function diff(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_formation_fusion_index');
        }

        $idA = (int) $request->request->get('formation_a');
        $idB = (int) $request->request->get('formation_b');

        $formationA = $this->formationRepository->find($idA);
        $formationB = $this->formationRepository->find($idB);

        if (!$formationA || !$formationB) {
            $this->toast('danger', 'Formations introuvables.');
            return $this->redirectToRoute('app_formation_fusion_index');
        }

        if ($idA === $idB) {
            $this->toast('danger', 'Veuillez sélectionner deux formations différentes.');
            return $this->redirectToRoute('app_formation_fusion_index');
        }

        if ($formationA->getTypeDiplome()?->getId() !== $formationB->getTypeDiplome()?->getId()) {
            $this->toast('danger', sprintf(
                'Les deux formations doivent avoir le même type de diplôme ("%s" ≠ "%s").',
                $formationA->getTypeDiplome()?->getLibelle() ?? '—',
                $formationB->getTypeDiplome()?->getLibelle() ?? '—',
            ));
            return $this->redirectToRoute('app_formation_fusion_index');
        }

        $fields = $this->diffBuilder->buildDiffFields($formationA, $formationB);
        $differentKeys = array_values(array_map(
            fn($f) => $f['key'],
            array_filter($fields, fn($f) => $f['isDifferent'])
        ));

        return $this->render('admin/formation_fusion/diff.html.twig', [
            'formationA'    => $formationA,
            'formationB'    => $formationB,
            'fields'        => $fields,
            'differentKeys' => $differentKeys,
            'hashA'         => $this->diffBuilder->hashFormation($formationA),
            'hashB'         => $this->diffBuilder->hashFormation($formationB),
        ]);
    }

    #[Route('/execute', name: 'execute', methods: ['POST'])]
    public function execute(Request $request): Response
    {
        $idA     = (int) $request->request->get('formation_a');
        $idB     = (int) $request->request->get('formation_b');
        $choices = $request->request->all('fields');
        $hashA   = $request->request->get('hash_a');
        $hashB   = $request->request->get('hash_b');

        if (!$this->isCsrfTokenValid('formation_fusion_execute', $request->request->get('_token'))) {
            $this->toast('danger', 'Token CSRF invalide. Veuillez recharger la page et réessayer.');
            return $this->redirectToRoute('app_formation_fusion_index');
        }

        $formationA = $this->formationRepository->find($idA);
        $formationB = $this->formationRepository->find($idB);

        if (!$formationA || !$formationB) {
            $this->toast('danger', 'Formations introuvables.');
            return $this->redirectToRoute('app_formation_fusion_index');
        }

        if ($this->diffBuilder->hashFormation($formationA) !== $hashA) {
            $this->toast('danger', 'La formation A a été modifiée pendant la comparaison. Veuillez relancer la fusion pour voir les données à jour.');
            return $this->redirectToRoute('app_formation_fusion_index');
        }

        if ($this->diffBuilder->hashFormation($formationB) !== $hashB) {
            $this->toast('danger', 'La formation B a été modifiée pendant la comparaison. Veuillez relancer la fusion pour voir les données à jour.');
            return $this->redirectToRoute('app_formation_fusion_index');
        }

        // Save display names before any data is modified or entity removed
        $displayA = $formationA->getDisplay();
        $displayB = $formationB->getDisplay();

        // Save localisation IDs before applying choices (needed to detect ville/localisation mismatches after change)
        $oldLocalisationIds = $formationA->getLocalisationMention()->map(fn($v) => $v->getId())->toArray();

        // Collect all parcours from both formations before reassignment
        $allParcours = array_merge(
            $formationA->getParcours()->toArray(),
            $formationB->getParcours()->toArray()
        );

        // Apply chosen field values (only fields where admin chose B need to be set on A)
        foreach ($choices as $field => $choice) {
            if ($choice === 'B') {
                $this->diffBuilder->applyField($formationA, $formationB, $field);
            }
        }

        // Reassign all OneToMany collections from B → A
        foreach ($formationB->getParcours()->toArray() as $item) {
            $item->setFormation($formationA);
        }
        foreach ($formationB->getBlocCompetences()->toArray() as $item) {
            $item->setFormation($formationA);
        }
        foreach ($formationB->getTypeEcs()->toArray() as $item) {
            $item->setFormation($formationA);
        }
        foreach ($formationB->getButCompetences()->toArray() as $item) {
            $item->setFormation($formationA);
        }
        foreach ($formationB->getDpeParcours()->toArray() as $item) {
            $item->setFormation($formationA);
        }
        $existingUserProfilKeys = array_map(
            fn($up) => $up->getUser()?->getId() . '_' . $up->getProfil()?->getId() . '_' . $up->getCampagneCollecte()?->getId(),
            $formationA->getUserProfils()->toArray()
        );
        foreach ($formationB->getUserProfils()->toArray() as $item) {
            $key = $item->getUser()?->getId() . '_' . $item->getProfil()?->getId() . '_' . $item->getCampagneCollecte()?->getId();
            if (!in_array($key, $existingUserProfilKeys, true)) {
                $item->setFormation($formationA);
            } else {
                $this->em->remove($item);
            }
        }
        foreach ($formationB->getDpeDemandes()->toArray() as $item) {
            $item->setFormation($formationA);
        }
        foreach ($formationB->getHistoriqueFormations()->toArray() as $item) {
            $item->setFormation($formationA);
        }
        foreach ($formationB->getCommentaires()->toArray() as $item) {
            $item->setFormation($formationA);
        }
        foreach ($formationB->getFormationVersionings()->toArray() as $item) {
            $item->setFormation($formationA);
        }
        foreach ($formationB->getChangeRves()->toArray() as $item) {
            $item->setFormation($formationA);
        }

        // Cascade: composantePorteuse → parcours.composanteInscription
        if (($choices['composantePorteuse'] ?? 'A') === 'B') {
            $newComposante = $formationA->getComposantePorteuse();
            foreach ($allParcours as $parcours) {
                $parcours->setComposanteInscription($newComposante);
            }
        }

        // Cascade: localisationMention → parcours.ville and parcours.localisation
        if (($choices['localisationMention'] ?? 'A') === 'B') {
            $newLocalisationIds = $formationA->getLocalisationMention()->map(fn($v) => $v->getId())->toArray();
            foreach ($allParcours as $parcours) {
                if ($parcours->getVille() !== null && !in_array($parcours->getVille()->getId(), $newLocalisationIds)) {
                    $parcours->setVille(null);
                }
                if ($parcours->getLocalisation() !== null && !in_array($parcours->getLocalisation()->getId(), $newLocalisationIds)) {
                    $parcours->setLocalisation(null);
                }
            }
        }

        // Cascade: rythmeFormation → parcours.rythmeFormation
        if (($choices['rythmeFormation'] ?? 'A') === 'B') {
            $newRythme = $formationA->getRythmeFormation();
            foreach ($allParcours as $parcours) {
                $parcours->setRythmeFormation($newRythme);
            }
        }

        // Cascade: rythmeFormationTexte → parcours.rythmeFormationTexte
        if (($choices['rythmeFormationTexte'] ?? 'A') === 'B') {
            $newRythmeTexte = $formationA->getRythmeFormationTexte();
            foreach ($allParcours as $parcours) {
                $parcours->setRythmeFormationTexte($newRythmeTexte);
            }
        }

        // Cascade: regimeInscription → parcours.regimeInscription
        if (($choices['regimeInscription'] ?? 'A') === 'B') {
            $newRegime = $formationA->getRegimeInscription();
            foreach ($allParcours as $parcours) {
                $parcours->setRegimeInscription($newRegime);
            }
        }

        // Propagate hasParcours flag
        if ($formationB->isHasParcours() === true) {
            $formationA->setHasParcours(true);
        }

        // Clear ManyToMany on B to avoid FK conflicts on deletion
        foreach ($formationB->getLocalisationMention()->toArray() as $item) {
            $formationB->removeLocalisationMention($item);
        }
        foreach ($formationB->getComposantesInscription()->toArray() as $item) {
            $formationB->removeComposantesInscription($item);
        }

        // Disconnect OneToOne cascade relations to avoid unintended cascade deletes
        if ($formationB->getFormationOrigineCopie() !== null) {
            $formationB->setFormationOrigineCopie(null);
        }
        if ($formationB->getFormationCopieAnneeUniversitaire() !== null) {
            $formationB->getFormationCopieAnneeUniversitaire()->setFormationOrigineCopie(null);
        }

        $this->em->flush();
        $this->em->remove($formationB);
        $this->em->flush();

        $this->toast('success', sprintf(
            'La formation "%s" a été fusionnée dans "%s" avec succès.',
            $displayB,
            $displayA
        ));

        return $this->redirectToRoute('app_formation_fusion_index');
    }
}
