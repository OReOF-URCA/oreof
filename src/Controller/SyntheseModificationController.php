<?php

namespace App\Controller;

use App\Classes\Export\ExportSyntheseModification;
use App\Classes\GetDpeParcours;
use App\DataTable\ConseilDocumentsDpeParcoursDataTable;
use App\DTO\TranslatableKey;
use App\Entity\Composante;
use App\Form\CfvuExportDateType;
use App\Message\Export;
use App\Repository\ComposanteRepository;
use App\Repository\DpeDemandeRepository;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class SyntheseModificationController extends BaseController
{
    #[Route('/synthese/modifications', name: 'app_synthese_modification_export_pdf', methods: ['GET', 'POST'])]
    public function exportSynthese(
        Request $request,
        DpeDemandeRepository $dpeParcoursRepository,
        ComposanteRepository $composanteRepository,
        ConseilDocumentsDpeParcoursDataTable $documentsTable,
    ): Response {
        $tab = (string) $request->query->get('tab', 'modifications');
        if (!in_array($tab, ['modifications', 'documents'], true)) {
            $tab = 'modifications';
        }

        $composantes = [];
        $comp = [];
        if ($tab === 'modifications') {
            $dpes = $dpeParcoursRepository->findByCampagneWithModification($this->getCampagneCollecte());
            foreach ($dpes as $dpe) {
                $composanteId = $dpe->getFormation()?->getComposantePorteuse()?->getId();
                if ($composanteId !== null) {
                    if (!array_key_exists($composanteId, $comp)) {
                        $comp[$composanteId] = 0;
                    }
                    $comp[$composanteId]++;
                }
            }
            $composantes = $composanteRepository->findPorteuse();
        }

        return $this->render('synthese_modification/index.html.twig', [
            'tab' => $tab,
            'composantes' => $composantes,
            'comp' => $comp,
            'documentsTable' => $documentsTable,
        ]);
    }

    #[Route('/synthese/modifications/all', name: 'app_synthese_modification_export_all', methods: ['GET', 'POST'])]
    public function exportSyntheseAll(
        Request $request,
        DpeDemandeRepository $dpeParcoursRepository,
        MessageBusInterface $messageBus,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $form = $this->createForm(CfvuExportDateType::class, null, [
            'action' => $this->generateUrl('app_synthese_modification_export_all'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $turboStream->streamOpenModalFromTemplates(
                new TranslatableKey('export.modal_cfvu_date.title', [], 'modal'),
                new TranslatableKey('export.modal_cfvu_date.subtitle_synthese_all', [], 'modal'),
                'export/_modal_cfvu_date.html.twig',
                [
                    'form' => $form->createView(),
                ],
                '_ui/_footer_submit_cancel.html.twig',
                [
                    'submitLabel' => 'export.modal_cfvu_date.submit_generate',
                ],
                $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
            );
        }

        /** @var ?\DateTimeInterface $dateCfvu */
        $dateCfvu = $form->get('dateCfvu')->getData();

        // récupèrer les formations qui sont ouvertes avec CFVU
        $dpes = $dpeParcoursRepository->findByCampagneWithModification($this->getCampagneCollecte());
        $formations = [];
        foreach ($dpes as $dpe) {
            $formation = $dpe->getFormation();
            if ($formation !== null) {
                $formationId = $formation->getId();
                if (!array_key_exists($formationId, $formations)) {
                    $formations[$formationId]['parcours'] = [];
                    $formations[$formationId]['hasModif'] = false;
                    $formations[$formationId]['formation'] = $formation;
                    $formations[$formationId]['dpeDemande'] = $dpe;
                    $formations[$formationId]['composante'] = $formation->getComposantePorteuse();
                }

                if ($dpe->getParcours() !== null) {
                    $parcours = $dpe->getParcours();
                    $dpeParcours = GetDpeParcours::getFromParcours($parcours);
                    if ($dpeParcours !== null &&
                        (array_key_exists('soumis_cfvu', $dpeParcours->getEtatValidation())
                            || array_key_exists('non_ouverture_cfvu', $dpeParcours->getEtatValidation()))) {
                        $formations[$formationId]['parcours'][$parcours->getId()]['parcours'] = $parcours;
                        $formations[$formationId]['parcours'][$parcours->getId()]['dpeDemande'] = $dpe;
                        $formations[$formationId]['hasModif'] = true;
                    }
                }
            }
        }

        $user = $this->getUser();
        $messageBus->dispatch(
            new Export(
                $user instanceof \App\Entity\User ? $user->getId() : null,
                'pdf-synthese_modification',
                $formations,
                $this->getCampagneCollecte(),
                $dateCfvu
            )
        );

        if ($this->isTurboFrameRequest()) {
            return $turboStream->streamToastSuccess(
                'Les documents sont en cours de génération, un mail vous sera envoyé une fois les documents prêts.',
                true,
            );
        }

        $this->addFlashBag('success', 'Les documents sont en cours de génération, un mail vous sera envoyé une fois les documents prêts.');

        return $this->redirectToRoute('app_synthese_modification_export_pdf');
    }

    #[Route('/synthese/modifications/composante/{composante}', name: 'app_synthese_modification_export_composante', methods: ['GET', 'POST'])]
    public function exportSyntheseComposante(
        Request $request,
        ExportSyntheseModification $exportSyntheseModification,
        MessageBusInterface $messageBus,
        DpeDemandeRepository $dpeParcoursRepository,
        Composante          $composante,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $form = $this->createForm(CfvuExportDateType::class, null, [
            'action' => $this->generateUrl('app_synthese_modification_export_composante', [
                'composante' => $composante->getId(),
            ]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $turboStream->streamOpenModalFromTemplates(
                new TranslatableKey('export.modal_cfvu_date.title', [], 'modal'),
                new TranslatableKey('export.modal_cfvu_date.subtitle_synthese_composante', ['%composante%' => (string) $composante->getLibelle()], 'modal'),
                'export/_modal_cfvu_date.html.twig',
                [
                    'form' => $form->createView(),
                ],
                '_ui/_footer_submit_cancel.html.twig',
                [
                    'submitLabel' => 'export.modal_cfvu_date.submit_generate',
                ],
                $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
            );
        }

        /** @var ?\DateTimeInterface $dateCfvu */
        $dateCfvu = $form->get('dateCfvu')->getData();

        //récupère toutes les formations de la composante qui sont ouvertes en CFVU
        $dpes = $dpeParcoursRepository->findParcoursByComposante($this->getCampagneCollecte(), $composante);

        $formations = [];
        foreach ($dpes as $dpe) {
            $formation = $dpe->getFormation();
            if ($formation !== null) {
                $formationId = $formation->getId();
                if (!array_key_exists($formationId, $formations)) {
                    $formations[$formationId]['parcours'] = [];
                    $formations[$formationId]['formation'] = $formation;
                    $formations[$formationId]['dpeDemande'] = null;
                    $formations[$formationId]['hasModif'] = false;
                    $formations[$formationId]['composante'] = $composante;
                }

                if ($dpe->getParcours() === null) {
                    //dpe si c'est une formation
                    $formations[$formationId]['dpeDemande'] = $dpe;
                } else {
                    //dpe si c'est un parcours
                    $parcours = $dpe->getParcours();
                    $dpeParcours = GetDpeParcours::getFromParcours($parcours);
                    if ($dpeParcours !== null &&
                        (array_key_exists('soumis_central', $dpeParcours->getEtatValidation()) || array_key_exists('soumis_cfvu', $dpeParcours->getEtatValidation()) || array_key_exists('non_ouverture_cfvu', $dpeParcours->getEtatValidation()))) {
                        $formations[$formationId]['hasModif'] = true;
                        $formations[$formationId]['parcours'][$parcours->getId()]['parcours'] = $parcours;
                        $formations[$formationId]['parcours'][$parcours->getId()]['dpeDemande'] = $dpe;
                    }
                }
            }
        }

        $user = $this->getUser();
        $messageBus->dispatch(
            new Export(
                $user instanceof \App\Entity\User ? $user->getId() : null,
                'pdf-synthese_modification',
                $formations,
                $this->getCampagneCollecte(),
                $dateCfvu
            )
        );

        if ($this->isTurboFrameRequest()) {
            return $turboStream->streamToastSuccess(
                'Les documents sont en cours de génération, un mail vous sera envoyé une fois les documents prêts.',
                true,
            );
        }

        $this->addFlashBag('success', 'Les documents sont en cours de génération, un mail vous sera envoyé une fois les documents prêts.');

        return $this->redirectToRoute('app_synthese_modification_export_pdf');
    }


//    #[Route('/synthese/modification/pdf/old', name: 'app_synthese_modification_export_pdf_old')]
//    public function pdf(
//        KernelInterface              $kernel,
//        ParcoursVersioningRepository $parcoursVersioningRepository,
//        ParcoursRepository           $parcoursRepository,
//        VersioningParcours           $versioningParcours,
//        ComposanteRepository         $composanteRepository,
//        MyGotenbergPdf               $myGotenbergPdf
//    ): Response {
//        $dir = $kernel->getProjectDir() . '/public/';
//        $composantes = $composanteRepository->findAllId();
//        foreach ($composantes as $composante) {
//            $tDemandes[$composante['id']] = [];
//        }
//
//        $patterns = [
//            '\/heuresEctsFormation\/',
//            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/heuresEctsEc\/',
//
//            '\/semestres\/\d+\/heuresEctsSemestre\/',
//            '\/semestres\/\d+\/ues\/\d+\/uesEnfants\/\d+',
//            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementsConstitutifsEnfants\/\d+\/elementConstitutif\/ficheMatiere\/',
//            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementsConstitutifsEnfants\/\d+\/heuresEctsEc\/',
//            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementConstitutif\/natureUeEc\/',
//            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/',
//            '\/heuresEctsFormation\/',
//        ];
//
//        $patternsAIgnorer = [
//            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/mcccs\/\d+\/', //todo: temporairement le temps de gérer l'affichage propre des MCCC
//            '\/semestres\/\d+\/ues\/\d+\/heuresEctsUe\/',
//            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementConstitutif\/mcccs\/\d+\/',
//            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/elementRaccroche\/mcccs\/\d+\/',
//            '\/semestres\/\d+\/ues\/\d+\/uesEnfants\/\d+\/elementConstitutifs\/\d+\/heuresEctsEc\/',
//            '\/semestres\/\d+\/ues\/\d+\/uesEnfants\/\d+\/heuresEctsUe\/',
//            '\/semestres\/\d+\/ues\/\d+\/heuresEctsUeEnfants\/\d+\/',
//            '\/semestres\/\d+\/ues\/\d+\/elementConstitutifs\/\d+\/heuresEctsEcEnfants\/',
//        ];
//
//        foreach ($composantes as $cmp) {
//            $allparcours = $parcoursRepository->findByTypeValidationAttenteCfvuAndComposante($this->getDpe(), 'soumis_central', $cmp['id']); //soumis_cfvu
//
//            foreach ($allparcours as $parcours) {
//                //créer la sauvegarde JSON
//                //récupère JSON CFVU
//                $lastVersion = $parcoursVersioningRepository->findLastVersion($parcours);
//                $lastVersion = count($lastVersion) > 0 ? $lastVersion[0] : null;
//
//                //on fait une copie de la version courante en json
//                $jsonCourant = $versioningParcours->saveVersionOfParcoursCourant($parcours);
//                $jsonCfvu = $versioningParcours->loadJsonCfvu($parcours, $lastVersion);
//
//                $comp = $parcours->getFormation()?->getComposantePorteuse();
//                if ($comp !== null) {
//                    $idP = $parcours->getId();
//                    $idComp = $comp->getId();
//                    $tDemandes[$comp->getId()][$idP] = [];
//
//                    $r = new JsonDiff(json_decode($jsonCfvu), json_decode($jsonCourant));
//                    $tDemandes[$idComp][$idP]['parcours']['display'] = $parcours->getLibelle();
//                    $tDemandes[$idComp][$idP]['parcours']['formation'] = $parcours->getFormation()?->getDisplayLong();
//                    $tDemandes[$idComp][$idP]['nbDiff'] = $r->getDiffCnt();
//                    $result['modified'] = [];
//                    $result['added'] = [];
//                    $result['removed'] = [];
//                    $hasPatch = false;
//                    foreach ($r->getPatch()->jsonSerialize() as $patch) {
//                        if (ExtractTextFromJsonPatch::getLastItem($patch->path)) {
//                            $key = $this->extractPatternsFromString($patterns, $patch->path, $patternsAIgnorer);
//                            if ($key !== null) {
//                                switch ($patch->op) {
//                                    case 'test':
//                                        $hasPatch = true;
//                                        $result['modified'][$key['path']][$key['key']]['libelle'] = ExtractTextFromJsonPatch::getLibelle($patch->path, $jsonCourant);
//                                        $result['modified'][$key['path']][$key['key']]['texte'] = ExtractTextFromJsonPatch::getTextFromPath($patch);
//                                        $result['modified'][$key['path']][$key['key']]['original'] = ExtractTextFromJsonPatch::getOriginalValueFromPatch($patch);
//                                        break;
//                                    case 'replace':
//                                        $hasPatch = true;
//                                        $result['modified'][$key['path']][$key['key']]['nouveau'] = ExtractTextFromJsonPatch::getNewValueFromPatch($patch);
//                                        break;
//                                    case 'add':
//                                        $hasPatch = true;
//                                        $result['added'][$key['path']][$key['key']]['nouveau'] = ExtractTextFromJsonPatch::getLibelle($patch->path, $jsonCourant);
//                                        $result['added'][$key['path']][$key['key']]['libelle'] = ExtractTextFromJsonPatch::getTextFromPath($patch);
//                                        break;
//                                    case 'remove':
//                                        $hasPatch = true;
//                                        $result['removed'][$key['path']][$key['key']]['origine'] = ExtractTextFromJsonPatch::getLibelle($patch->path, $jsonCfvu);
//                                        $result['removed'][$key['path']][$key['key']]['libelle'] = ExtractTextFromJsonPatch::getTextFromPath($patch);
//                                        break;
//                                }
//                            }
//                        }
//                    }
//                    $tDemandes[$idComp][$idP]['patch'] = $result;
//                }
//            }
//            if ($hasPatch) {
//                $myGotenbergPdf->renderAndSave(
//                    'pdf/synthese_modifications_parcours.html.twig',
//                    'uploads/syntheses/',
//                    [
//                        'titre' => 'Liste des demandes de changement MCCC et maquettes',
//                        'demandes' => $tDemandes,
//                        'composante' => $cmp,
//                        'dpe' => $this->getDpe(),
//                    ],
//                    'synthese_changement_cfvu_' . $cmp['sigle'] . '_' . (new DateTime())->format('d-m-Y_H-i-s')
//                );
//            }
//
//        }
//
//
//        //        return $this->render('pdf/synthese_modifications_parcours.html.twig', [
//        //            'titre' => 'Liste des demandes de changement MCCC et maquettes',
//        //            'demandes' => $tDemandes,
//        //            'composantes' => $composantes,
//        //            'dpe' => $this->getDpe(),
//        //        ]);
//
//    }

//    private function extractPatternsFromString(array $patterns, string $string, array $patternsAIgnorer = []): ?array
//    {
//        // on regarde si c'est un pattern à ignorer
//        foreach ($patternsAIgnorer as $pattern) {
//            preg_match("/^(" . $pattern . ")/", $string, $matches);
//            if (!empty($matches[0])) {
//                return null;
//            }
//        }
//
//
//        foreach ($patterns as $pattern) {
//            preg_match("/^(" . $pattern . ")/", $string, $matches);
//            if (!empty($matches[0])) {
//                return ['path' => $matches[0],
//                    'key' => substr($string, strlen($matches[0]))
//                ];
//            }
//        }
//
//        return null;
//    }
}
