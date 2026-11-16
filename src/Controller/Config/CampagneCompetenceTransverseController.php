<?php

declare(strict_types=1);

namespace App\Controller\Config;

use App\DTO\TranslatableKey;
use App\Entity\BlocCompetence;
use App\Entity\CampagneCollecte;
use App\Entity\Competence;
use App\Form\BlocCompetenceType;
use App\Form\CompetenceType;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\BlocCompetenceRepository;
use App\Repository\CampagneCollecteRepository;
use App\Repository\CompetenceRepository;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/campagne-collecte')]
#[IsGranted('ROLE_ADMIN')]
class CampagneCompetenceTransverseController extends AbstractController
{
    #[Route('/{id}/competences-transverses', name: 'app_campagne_collecte_competences_transverses', methods: ['GET'])]
    #[Breadcrumb(menuKey: 'administration.campagne_collecte')]
    #[Breadcrumb(label: 'Compétences transverses')]
    public function index(
        CampagneCollecte           $campagne,
        BlocCompetenceRepository   $blocCompetenceRepository,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response
    {
        $blocs = $blocCompetenceRepository->findTransversesByCampagne($campagne);

        // Recherche automatique de la campagne n-1 (par année ou précédente par id)
        $sourceCampagne = null;
        if ($campagne->getAnnee() !== null) {
            $sourceCampagne = $campagneCollecteRepository->findOneBy(['annee' => $campagne->getAnnee() - 1]);
        }
        if ($sourceCampagne === null) {
            $sourceCampagne = $campagneCollecteRepository->createQueryBuilder('c')
                ->andWhere('c.id < :id')
                ->setParameter('id', $campagne->getId())
                ->orderBy('c.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        $allCampagnes = $campagneCollecteRepository->findBy([], ['annee' => 'DESC', 'id' => 'DESC']);

        return $this->render('config/campagne_collecte/competences_transverses.html.twig', [
            'campagne' => $campagne,
            'blocs' => $blocs,
            'sourceCampagneN1' => $sourceCampagne,
            'allCampagnes' => $allCampagnes,
        ]);
    }

    #[Route('/{id}/competences-transverses/import-n1', name: 'app_campagne_collecte_competences_transverses_import_n1', methods: ['POST'])]
    public function importN1(
        CampagneCollecte           $campagne,
        Request                    $request,
        BlocCompetenceRepository   $blocCompetenceRepository,
        CampagneCollecteRepository $campagneCollecteRepository,
        EntityManagerInterface     $em
    ): Response
    {
        if (!$this->isCsrfTokenValid('import-transverse-' . $campagne->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $sourceId = $request->request->getInt('source_campagne_id');
        $sourceCampagne = $sourceId > 0 ? $campagneCollecteRepository->find($sourceId) : null;

        if ($sourceCampagne === null) {
            if ($campagne->getAnnee() !== null) {
                $sourceCampagne = $campagneCollecteRepository->findOneBy(['annee' => $campagne->getAnnee() - 1]);
            }
            if ($sourceCampagne === null) {
                $sourceCampagne = $campagneCollecteRepository->createQueryBuilder('c')
                    ->andWhere('c.id < :id')
                    ->setParameter('id', $campagne->getId())
                    ->orderBy('c.id', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
            }
        }

        if ($sourceCampagne === null) {
            $this->addFlash('danger', 'Aucune campagne source n\'a été trouvée pour la reprise.');
            return $this->redirectToRoute('app_campagne_collecte_competences_transverses', ['id' => $campagne->getId()]);
        }

        if ($sourceCampagne->getId() === $campagne->getId()) {
            $this->addFlash('warning', 'La campagne source ne peut pas être la campagne actuelle.');
            return $this->redirectToRoute('app_campagne_collecte_competences_transverses', ['id' => $campagne->getId()]);
        }

        $sourceBlocs = $blocCompetenceRepository->findTransversesByCampagne($sourceCampagne);
        if (empty($sourceBlocs)) {
            $this->addFlash('warning', sprintf('Aucune compétence transversale trouvée dans la campagne source "%s".', $sourceCampagne->getLibelle()));
            return $this->redirectToRoute('app_campagne_collecte_competences_transverses', ['id' => $campagne->getId()]);
        }

        $currentMaxOrdre = $blocCompetenceRepository->getMaxOrdreCampagneCollecte($campagne) ?? 0;
        $nbBlocs = 0;
        $nbCompetences = 0;

        foreach ($sourceBlocs as $sourceBloc) {
            $currentMaxOrdre++;
            $cloneBloc = clone $sourceBloc;
            $cloneBloc->setParcours(null);
            $cloneBloc->setFormation(null);
            $cloneBloc->setCampagneCollecte($campagne);
            $cloneBloc->setBlocCompetenceOrigineCopie($sourceBloc);
            $cloneBloc->setOrdre($currentMaxOrdre);

            $em->persist($cloneBloc);
            $nbBlocs++;

            foreach ($sourceBloc->getCompetences() as $comp) {
                $cloneComp = clone $comp;
                $cloneComp->setBlocCompetence($cloneBloc);
                $cloneComp->setCompetenceOrigineCopie($comp);

                $em->persist($cloneComp);
                $nbCompetences++;
            }
        }

        $em->flush();

        $this->addFlash('toast', [
            'type' => 'success',
            'text' => sprintf(
                '%d bloc(s) et %d compétence(s) transversales importés avec succès depuis la campagne "%s".',
                $nbBlocs,
                $nbCompetences,
                $sourceCampagne->getLibelle()
            ),
        ]);

        return $this->redirectToRoute('app_campagne_collecte_competences_transverses', ['id' => $campagne->getId()]);
    }

    #[Route('/{id}/competences-transverses/bloc/new', name: 'app_campagne_collecte_competence_transverse_bloc_new', methods: ['GET', 'POST'])]
    public function blocNew(
        CampagneCollecte           $campagne,
        Request                    $request,
        BlocCompetenceRepository   $blocCompetenceRepository,
        TurboStreamResponseFactory $turboStream,
        EntityManagerInterface     $em
    ): Response
    {
        $bloc = new BlocCompetence();
        $bloc->setCampagneCollecte($campagne);
        $bloc->setParcours(null);
        $bloc->setFormation(null);

        $form = $this->createForm(BlocCompetenceType::class, $bloc, [
            'action' => $this->generateUrl('app_campagne_collecte_competence_transverse_bloc_new', ['id' => $campagne->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $maxOrdre = ($blocCompetenceRepository->getMaxOrdreCampagneCollecte($campagne) ?? 0) + 1;
            $bloc->setOrdre($maxOrdre);

            if ($bloc->getCode() === null || trim($bloc->getCode()) === '') {
                $bloc->genereCode();
            }

            $em->persist($bloc);
            $em->flush();

            return $turboStream->stream('config/campagne_collecte/turbo/update_competences_transverses.stream.html.twig', [
                'campagne' => $campagne,
                'blocs' => $blocCompetenceRepository->findTransversesByCampagne($campagne),
                'toastMessage' => 'Bloc de compétences transversales créé avec succès',
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('Ajouter un bloc de compétences transversales', [], 'modal'),
            'Campagne : ' . $campagne->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            ['form' => $form->createView()],
            '_ui/_footer_submit_cancel.html.twig',
            ['submitLabel' => 'Créer le bloc']
        );
    }

    #[Route('/competences-transverses/bloc/{id}/edit', name: 'app_campagne_collecte_competence_transverse_bloc_edit', methods: ['GET', 'POST'])]
    public function blocEdit(
        BlocCompetence             $blocCompetence,
        Request                    $request,
        BlocCompetenceRepository   $blocCompetenceRepository,
        TurboStreamResponseFactory $turboStream,
        EntityManagerInterface     $em
    ): Response
    {
        $campagne = $blocCompetence->getCampagneCollecte();
        $form = $this->createForm(BlocCompetenceType::class, $blocCompetence, [
            'action' => $this->generateUrl('app_campagne_collecte_competence_transverse_bloc_edit', ['id' => $blocCompetence->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($blocCompetence->getCode() === null || trim($blocCompetence->getCode()) === '') {
                $blocCompetence->genereCode();
            }

            $em->flush();

            return $turboStream->stream('config/campagne_collecte/turbo/update_competences_transverses.stream.html.twig', [
                'campagne' => $campagne,
                'blocs' => $blocCompetenceRepository->findTransversesByCampagne($campagne),
                'toastMessage' => 'Bloc de compétences modifié avec succès',
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('Modifier le bloc de compétences', [], 'modal'),
            $blocCompetence->display(),
            '_ui/_modal_new_generic.html.twig',
            ['form' => $form->createView()],
            '_ui/_footer_submit_cancel.html.twig',
            ['submitLabel' => 'Enregistrer']
        );
    }

    #[Route('/competences-transverses/bloc/{id}/delete', name: 'app_campagne_collecte_competence_transverse_bloc_delete', methods: ['POST', 'DELETE'])]
    public function blocDelete(
        BlocCompetence         $blocCompetence,
        Request                $request,
        EntityManagerInterface $em
    ): Response
    {
        if (!$this->isCsrfTokenValid('delete-bloccompetence-' . $blocCompetence->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $campagne = $blocCompetence->getCampagneCollecte();
        $em->remove($blocCompetence);
        $em->flush();

        $this->addFlash('toast', [
            'type' => 'success',
            'text' => 'Bloc de compétences supprimé avec succès.',
        ]);

        return $this->redirectToRoute('app_campagne_collecte_competences_transverses', ['id' => $campagne?->getId()]);
    }

    #[Route('/competences-transverses/bloc/{id}/competence/new', name: 'app_campagne_collecte_competence_transverse_competence_new', methods: ['GET', 'POST'])]
    public function competenceNew(
        BlocCompetence             $blocCompetence,
        Request                    $request,
        BlocCompetenceRepository   $blocCompetenceRepository,
        CompetenceRepository       $competenceRepository,
        TurboStreamResponseFactory $turboStream,
        EntityManagerInterface     $em
    ): Response
    {
        $campagne = $blocCompetence->getCampagneCollecte();
        $competence = new Competence($blocCompetence);

        $form = $this->createForm(CompetenceType::class, $competence, [
            'action' => $this->generateUrl('app_campagne_collecte_competence_transverse_competence_new', ['id' => $blocCompetence->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $maxOrdre = ($competenceRepository->getMaxOrdreBlocCompetence($blocCompetence) ?? 0) + 1;
            $competence->setOrdre($maxOrdre);

            if ($competence->getCode() === null || trim($competence->getCode()) === '') {
                $competence->genereCode();
            }

            $em->persist($competence);
            $em->flush();

            return $turboStream->stream('config/campagne_collecte/turbo/update_competences_transverses.stream.html.twig', [
                'campagne' => $campagne,
                'blocs' => $blocCompetenceRepository->findTransversesByCampagne($campagne),
                'toastMessage' => 'Compétence ajoutée avec succès',
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('Ajouter une compétence', [], 'modal'),
            'Bloc : ' . $blocCompetence->display(),
            '_ui/_modal_new_generic.html.twig',
            ['form' => $form->createView()],
            '_ui/_footer_submit_cancel.html.twig',
            ['submitLabel' => 'Ajouter la compétence']
        );
    }

    #[Route('/competences-transverses/competence/{id}/edit', name: 'app_campagne_collecte_competence_transverse_competence_edit', methods: ['GET', 'POST'])]
    public function competenceEdit(
        Competence                 $competence,
        Request                    $request,
        BlocCompetenceRepository   $blocCompetenceRepository,
        TurboStreamResponseFactory $turboStream,
        EntityManagerInterface     $em
    ): Response
    {
        $bloc = $competence->getBlocCompetence();
        $campagne = $bloc?->getCampagneCollecte();

        $form = $this->createForm(CompetenceType::class, $competence, [
            'action' => $this->generateUrl('app_campagne_collecte_competence_transverse_competence_edit', ['id' => $competence->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($competence->getCode() === null || trim($competence->getCode()) === '') {
                $competence->genereCode();
            }

            $em->flush();

            return $turboStream->stream('config/campagne_collecte/turbo/update_competences_transverses.stream.html.twig', [
                'campagne' => $campagne,
                'blocs' => $blocCompetenceRepository->findTransversesByCampagne($campagne),
                'toastMessage' => 'Compétence modifiée avec succès',
            ]);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('Modifier la compétence', [], 'modal'),
            $competence->display(),
            '_ui/_modal_new_generic.html.twig',
            ['form' => $form->createView()],
            '_ui/_footer_submit_cancel.html.twig',
            ['submitLabel' => 'Enregistrer']
        );
    }

    #[Route('/competences-transverses/competence/{id}/delete', name: 'app_campagne_collecte_competence_transverse_competence_delete', methods: ['POST', 'DELETE'])]
    public function competenceDelete(
        Competence             $competence,
        Request                $request,
        EntityManagerInterface $em
    ): Response
    {
        if (!$this->isCsrfTokenValid('delete-competence-' . $competence->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $campagne = $competence->getBlocCompetence()?->getCampagneCollecte();
        $em->remove($competence);
        $em->flush();

        $this->addFlash('toast', [
            'type' => 'success',
            'text' => 'Compétence supprimée avec succès.',
        ]);

        return $this->redirectToRoute('app_campagne_collecte_competences_transverses', ['id' => $campagne?->getId()]);
    }
}
