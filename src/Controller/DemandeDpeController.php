<?php

namespace App\Controller;

use App\Classes\Excel\ExcelWriter;
use App\Classes\ValidationProcess;
use App\DTO\TranslatableKey;
use App\Entity\Composante;
use App\Entity\DpeDemande;
use App\Form\DpeDemandeTexteType;
use App\Service\DataTableBuilder;
use App\Repository\ComposanteRepository;
use App\Repository\DpeDemandeRepository;
use App\Repository\MentionRepository;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DemandeDpeController extends BaseController
{
    #[Route('/demande/dpe', name: 'app_demande_dpe')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        DataTableBuilder $builder,
        ValidationProcess $validationProcess,
        MentionRepository $mentionRepository,
        ComposanteRepository $composanteRepository,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('demande_dpe/index.html.twig', [
            'type' => 'ses',
            'table' => $this->buildTable(
                $builder,
                $validationProcess,
                $mentionRepository,
                $composanteRepository,
                'ses'
            ),
        ]);
    }

    //    #[Route('/demande/dpe/liste/{type}', name: 'app_dpe_demande_liste')]
    //    public function liste(
    //        ComposanteRepository $composanteRepository,
    //        Request $request,
    //        ?string $type = null
    //    ): Response
    //    {
    //        // Route legacy conservée pour compatibilité des anciens liens/stimulus.
    //        if ($type === 'composante') {
    //            $composanteId = $request->query->getInt('composante');
    //            if ($composanteId > 0) {
    //                $composante = $composanteRepository->find($composanteId);
    //                if ($composante !== null) {
    //                    return $this->redirectToRoute('app_demande_dpe_composante', ['composante' => $composante->getId()]);
    //                }
    //            }
    //        }
    //
    //        return $this->redirectToRoute('app_demande_dpe');
    //    }

    #[Route('/demande/dpe/composante/{composante}', name: 'app_demande_dpe_composante')]
    public function dpeComposante(
        Composante $composante,
        DataTableBuilder     $builder,
        ValidationProcess    $validationProcess,
        MentionRepository    $mentionRepository,
        ComposanteRepository $composanteRepository,
    ): Response
    {
        $this->denyAccessUnlessGranted('SHOW', [
            'route' => 'app_composante',
            'subject' => $composante
        ]);

        return $this->render('demande_dpe/index.html.twig', [
            'type' => 'composante',
            'composante' => $composante,
            'table' => $this->buildTable(
                $builder,
                $validationProcess,
                $mentionRepository,
                $composanteRepository,
                'composante',
                $composante
            ),
        ]);
    }

    private function buildTable(
        DataTableBuilder     $builder,
        ValidationProcess    $validationProcess,
        MentionRepository    $mentionRepository,
        ComposanteRepository $composanteRepository,
        string               $type,
        ?Composante          $composante = null,
    ): array
    {
        $campagneCollecte = $this->getCampagneCollecte();

        if ($campagneCollecte === null) {
            $builder->addBaseWhere('1 = 0');
        } else {
            $builder
                ->addBaseWhere('IDENTITY(e.campagneCollecte) = :campagneCollecteId')
                ->addBaseParameter('campagneCollecteId', $campagneCollecte->getId());
        }

        if ($composante !== null) {
            $builder
                ->addBaseJoin('left', 'e.formation', 'f')
                ->addBaseWhere('IDENTITY(f.composantePorteuse) = :composanteId')
                ->addBaseParameter('composanteId', $composante->getId());
        }

        $mentions = $composante !== null
            ? $mentionRepository->findByComposante($composante)
            : $mentionRepository->findBy([], ['libelle' => 'ASC']);

        $mentionChoices = [];
        foreach ($mentions as $mention) {
            $mentionChoices[(string)$mention->getId()] = $mention->getLibelle();
        }

        $composanteChoices = [];
        foreach ($composanteRepository->findPorteuse() as $composanteItem) {
            $composanteChoices[(string)$composanteItem->getId()] = $composanteItem->getLibelle();
        }

        $etatChoices = [];
        foreach ($validationProcess->getProcessAll() as $value => $process) {
            $etatChoices[$value] = (string)($process['label'] ?? $value);
        }

        $builder
            ->setEntity(DpeDemande::class)
            ->setPerPage(20)
            ->setDefaultSort('dateDemande', 'desc');

        if ($type === 'ses') {
            $builder->addColumn('formation.composantePorteuse.libelle', [
                'label' => 'Composante',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'type' => 'select',
                'choices' => $composanteChoices,
                'filter_expression' => 'IDENTITY(formation_0.composantePorteuse)',
                'class' => 'min-w-52',
            ]);
        }

        return $builder
            ->addColumn('formation.mention.libelle', [
                'label' => 'Mention',
                'sortable' => true,
                'filterable' => true,
                'type' => 'select',
                'choices' => $mentionChoices,
                'filter_expression' => 'IDENTITY(formation_0.mention)',
                'template' => 'demande_dpe/_datatable_mention.html.twig',
                'class' => 'min-w-[18rem]',
            ])
            ->addColumn('parcours.libelle', [
                'label' => 'Parcours / Niveau',
                'sortable' => true,
                'filterable' => false,
                'template' => 'demande_dpe/_datatable_cible.html.twig',
                'class' => 'min-w-[14rem]',
            ])
            ->addColumn('dateDemande', [
                'label' => 'Date demande',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'type' => 'date',
                'format' => 'date',
            ])
            ->addColumn('niveauModification', [
                'label' => 'Niveau',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'type' => 'select',
                'choices' => DpeDemande::getListeNiveauModification(),
                'template' => 'demande_dpe/_datatable_niveau.html.twig',
                'class' => 'min-w-[12rem]',
            ])
            ->addColumn('etatDemande', [
                'label' => 'Etat',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'type' => 'select',
                'choices' => $etatChoices,
                'template' => 'demande_dpe/_datatable_etat.html.twig',
                'class' => 'min-w-[12rem]',
            ])
            ->addColumn('argumentaireDemande', [
                'label' => 'Commentaire',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true,
                'template' => 'demande_dpe/_datatable_commentaire.html.twig',
                'class' => 'min-w-[20rem]',
            ])
            ->addColumn('id', [
                'label' => 'Actions',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'demande_dpe/_datatable_actions.html.twig',
                'class' => 'min-w-[11rem] text-right',
            ])
            ->build();
    }

    #[Route('/demande/dpe/{id}/edit', name: 'app_demande_dpe_edit', methods: ['GET', 'POST'])]
    public function edit(
        TurboStreamResponseFactory $turboStreamResponseFactory,
        EntityManagerInterface $entityManager,
        Request                    $request,
        DpeDemande                 $dpeDemande
    ): Response
    {


        $form = $this->createForm(DpeDemandeTexteType::class, $dpeDemande, [
            'action' => $this->generateUrl('app_demande_dpe_edit', ['id' => $dpeDemande->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (trim($dpeDemande->getArgumentaireDemande()) === '') {
                $this->addFlash('error', 'Le champ "Argumentaire de la demande" ne peut pas être vide.');
                return $this->json(false);
            }
            $entityManager->flush();

            return $this->json(true);
        }

        return $turboStreamResponseFactory->streamOpenModalFromTemplates(
            new TranslatableKey('demande_dpe.edit.titre'),
            new TranslatableKey('demande_dpe.edit.description'),
            '_ui/_modal_new_generic.html.twig',
            [
                'dpeDemande' => $dpeDemande,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
        );
    }

    #[Route('/demande/dpe/export/{type}', name: 'app_demande_dpe_export')]
    public function dpeExport(
        ExcelWriter          $excelWriter,
        DpeDemandeRepository $dpeDemandeRepository,
        ComposanteRepository $composanteRepository,
        Request $request,
        string               $type,
    ): Response
    {
        if ($type === 'composante') {
            $composante = $composanteRepository->find($request->query->get('composante'));
            if ($composante === null) {
                throw $this->createNotFoundException('Composante non trouvée');
            }

            $this->denyAccessUnlessGranted('MANAGE', [
                'route' => 'app_composante',
                'subject' => $composante
            ]);

            $demandes = $dpeDemandeRepository->findByComposante($composante);
        } else {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $demandes = $dpeDemandeRepository->findAll();
        }

        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $filename = 'demandes_dpe_' . date('Y-m-d_H-i-s') . '.xlsx';
        $excelWriter->nouveauFichier('Export Demande DPE');
        $excelWriter->setActiveSheetIndex(0);
        $excelWriter->writeCellName('A1', 'Composante');
        $excelWriter->writeCellName('B1', 'Type Diplôme');
        $excelWriter->writeCellName('C1', 'Mention');
        $excelWriter->writeCellName('D1', 'Parcours');
        $excelWriter->writeCellName('E1', 'Demande de ?');
        $excelWriter->writeCellName('F1', 'Date demande');
        $excelWriter->writeCellName('G1', 'Date clôture');
        $excelWriter->writeCellName('H1', 'Niveau demande');
        $excelWriter->writeCellName('I1', 'Etat');
        $excelWriter->writeCellName('J1', 'Commentaire');
        if ($isAdmin) {
            $excelWriter->writeCellName('K1', 'Id Parcours');
            $excelWriter->writeCellName('L1', 'Id Mention');
        }
        $ligne = 2;
        foreach ($demandes as $demande) {
            if ($demande->getNiveauDemande() === 'F') {
                $formation = $demande->getFormation();
            } else {
                $parcours = $demande->getParcours();
                $formation = $parcours?->getFormation();
            }
            $composante = $formation?->getComposantePorteuse();

            $excelWriter->writeCellName('A' . $ligne, $composante->getLibelle());
            $excelWriter->writeCellName('B' . $ligne, $formation?->getTypeDiplome()?->getLibelle() ?? 'Inconnu');
            $excelWriter->writeCellName('C' . $ligne, $formation?->getDisplay());

            if ($demande->getNiveauDemande() === 'G') {
                $excelWriter->writeCellName('D' . $ligne, 'Niveau Mention');
            } else {
                $excelWriter->writeCellName('D' . $ligne, $parcours?->getDisplay());
            }

            $excelWriter->writeCellName('E' . $ligne, $demande->getAuteur() ? $demande->getAuteur()->getDisplay() : '');
            $excelWriter->writeCellName('F' . $ligne, $demande->getDateDemande()?->format('d/m/Y'));
            $excelWriter->writeCellName('G' . $ligne, $demande->getDateCloture() ? $demande->getDateCloture()->format('d/m/Y') : '');
            $excelWriter->writeCellName('H' . $ligne, $demande->getNiveauModification() ? $demande->getNiveauModification()->getLibelle() : '');
            $excelWriter->writeCellName('I' . $ligne, $demande->getEtatDemande()?->getLibelle());
            $excelWriter->writeCellName('J' . $ligne, $demande->getArgumentaireDemande());
            if ($isAdmin) {
                $excelWriter->writeCellName('K' . $ligne, $demande->getParcours()?->getId());
                $excelWriter->writeCellName('L' . $ligne, $demande->getFormation()?->getId());
            }
            $ligne++;
        }

        $excelWriter->getColumnsAutoSize('A', 'L');

        return $excelWriter->genereFichier($filename);
    }

    #[Route('/demande/dpe/{id}', name: 'app_demande_dpe_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        EntityManagerInterface $entityManager,
        Request                $request,
        DpeDemande             $dpeDemande
    ): Response
    {
        if ($this->isCsrfTokenValid(
            'delete' . $dpeDemande->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $entityManager->remove($dpeDemande);
            $entityManager->flush();

            return $this->json(true);
        }

        return $this->json(false, 400);
    }
}
