<?php

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\GetHistorique;
use App\Classes\JsonReponse;
use App\Classes\Mailer;
use App\Classes\Process\ChangeRfProcess;
use App\Entity\ChangeRf;
use App\Entity\HistoriqueFormation;
use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use App\Exception\FileUploadException;
use App\Service\SecureUploadService;
use App\Utils\Tools;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class PvConseilController extends BaseController
{
    #[Route('/pv/conseil/{parcours}', name: 'app_deposer_pv_conseil')]
    public function index(
        GetHistorique $getHistorique,
        Mailer $myMailer,
        SecureUploadService $secureUploadService,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        Request $request,
        Parcours $parcours,
    ): Response {
        if ($request->isMethod('POST')) {
            $dpeParcours = GetDpeParcours::getFromParcours($parcours);
            if ($dpeParcours !== null) {
                $conseilLaisserPasser = $getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'soumis_conseil');
                if ($conseilLaisserPasser !== null && $conseilLaisserPasser->getEtat() === 'laisserPasser') {
                    $histo = $conseilLaisserPasser;
                } else {
                    $histo = new HistoriqueParcours();
                    $histo->setParcours($parcours);
                    $histo->setUser($this->getUser());
                    $histo->setEtape('soumis_conseil');

                }
                $histo->setEtat('valide');
                if ($request->request->has('dateconseil') && $request->request->get('dateconseil') !== null) {
                    $histo->setDate(Tools::convertDate($request->request->get('dateconseil')));
                }

                //upload
                if ($request->files->has('file') && $request->files->get('file') !== null) {
                    try {
                        $upload = $secureUploadService->uploadFromRequest($request, 'file', 'conseils');
                    } catch (FileUploadException $exception) {
                        return JsonReponse::error($exception->getPublicMessage());
                    }

                    if ($upload !== null) {
                        $tab['fichier'] = $upload->getStoredFilename();
                        $tab['fichier_original'] = $upload->getOriginalFilename();
                    }
                } else {
                    return JsonReponse::success($translator->trans('deposer.pv.flash.error', [], 'process'));
                }

                $histo->setComplements($tab ?? []);
                $entityManager->persist($histo);
                $entityManager->flush();

                //todo:  mail à la CFVU, avec un event ? ou workflow sur laisserPasser
                $myMailer->initEmail();
                $myMailer->setTemplate(
                    'mails/workflow/dpeParcours/conseil_pv_depose.html.twig',
                    ['parcours' => $parcours]
                ); //todo: revoir le texte du mail si avec ou sans parcours
                $myMailer->sendMessage(
                    [$this->getEtablissement()?->getEmailCentral()],
                    '[ORéOF]  Le PV de conseil a été déposé pour le parcours ' . $parcours->getLibelle()
                );

                return JsonReponse::success($translator->trans('deposer.pv.flash.success', [], 'process'));
            }

            return JsonReponse::error('Pas de DPE associé au parcours');
        }
        return $this->render('pv_conseil/_index.html.twig', [
            'parcours' => $parcours,
        ]);
    }

    #[Route('/pv/conseil/change-rf/{changeRf}', name: 'app_deposer_pv_conseil_change_rf')]
    public function changeRfPv(
        ChangeRfProcess $changeRfProcess,
        GetHistorique $getHistorique,
        Mailer $myMailer,
        SecureUploadService $secureUploadService,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        Request $request,
        ChangeRf $changeRf,
    ): Response {
        if ($request->isMethod('POST')) {
            if ($changeRf !== null) {
                $conseilLaisserPasser = $getHistorique->getHistoriqueChangeRfLastStep($changeRf, 'changeRf.soumis_conseil');
                if ($conseilLaisserPasser !== null && $conseilLaisserPasser->getEtat() === 'laisserPasser') {
                    $histo = $conseilLaisserPasser;
                } else {
                    $histo = new HistoriqueFormation();
                    $histo->setChangeRf($changeRf);
                    $histo->setUser($this->getUser());
                    $histo->setEtape('changeRf.soumis_conseil');

                }
                $histo->setEtat('valide');
                $histo->setCreated(new DateTime());
                if ($request->request->has('dateconseil') && $request->request->get('dateconseil') !== null) {
                    $histo->setDate(Tools::convertDate($request->request->get('dateconseil')));
                }

                //upload
                if ($request->files->has('file') && $request->files->get('file') !== null) {
                    try {
                        $upload = $secureUploadService->uploadFromRequest($request, 'file', 'conseils');
                    } catch (FileUploadException $exception) {
                        return JsonReponse::error($exception->getPublicMessage());
                    }

                    if ($upload !== null) {
                        $tab['fichier'] = $upload->getStoredFilename();
                        $tab['fichier_original'] = $upload->getOriginalFilename();
                    }
                } else {
                    return JsonReponse::error($translator->trans('deposer.pv.flash.error', [], 'process'));
                }

                $histo->setComplements($tab ?? []);
                $entityManager->persist($histo);
                $entityManager->flush();

                //si déjà validé CFVU "sous réserve" de PV appliquer le changement
//                if (true) {
//                    $changeRfProcess->valideChangeRf($changeRf, $this->getUser(), $transition, $request, $fileName);
//                }

                $myMailer->initEmail();
                $myMailer->setTemplate(
                    'mails/workflow/changerf/deposer_pv.html.twig',
                    ['demande' => $changeRf,
                        'formation' => $changeRf->getFormation(),]
                );
                $myMailer->sendMessage(
                    [$this->getEtablissement()?->getEmailCentral()],
                    '[ORéOF]  Le PV de conseil a été déposé pour le changement de RF : ' . $changeRf->getFormation()?->getDisplay()
                );

                return JsonReponse::success($translator->trans('deposer.pv.flash.success', [], 'process'));
            }

            return JsonReponse::error('Pas de DPE associé au parcours');
        }
        return $this->render('pv_conseil/_index.html.twig', [
            'changeRf' => $changeRf,
            'type' => 'change_rf',
        ]);
    }
}
