<?php

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\JsonReponse;
use App\Classes\Process\ParcoursProcess;
use App\Classes\ValidationProcess;
use App\Entity\DpeDemande;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Events\DpeDemandeEvent;
use App\Events\HistoriqueFormationEvent;
use App\Events\HistoriqueParcoursEvent;
use App\Repository\DpeDemandeRepository;
use App\Repository\DpeParcoursRepository;
use App\Service\VersioningFormation;
use App\Service\VersioningParcours;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProcessReouvertureController extends BaseController
{
//todo: a voir ce qui est encore utile
    public function __construct(
        private readonly EventDispatcherInterface      $eventDispatcher,
        private readonly EntityManagerInterface        $entityManager,
        private readonly ValidationProcess             $validationProcess,
        private readonly ParcoursProcess               $parcoursProcess,
        KernelInterface                                $kernel
    ) {
    }

    #[Route('/demande/non-ouverture/{parcours}', name: 'app_validation_demande_non_ouverture')]
    public function demandeNonOuverture(
        Parcours           $parcours,
        VersioningParcours $versioningParcours,
        Request            $request
    ): Response
    {
        if ($parcours === null) {
            return JsonReponse::error('Parcours non trouvé');
        }
        $formation = $parcours->getFormation();
        $typeDpe = 'P';

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $dpe = GetDpeParcours::getFromParcours($parcours);
            if ($dpe === null) {
                return JsonReponse::error('DPE non trouvé');
            }

            //réouverture directe sans sauvegarde ou avec sauvegarde selon le choix
            if ($data['demandeNonOuverture'] === 'NON_OUVERTURE') {
                $etatTypeModification = TypeModificationDpeEnum::NON_OUVERTURE_SES;
                $dpe->setEtatReconduction($etatTypeModification);
                $dpe->setEtatValidation(['non_ouverture_ses' => 1]); //un état de processus différent pour connaitre le branchement ensuite

                $now = new DateTimeImmutable('now');
                $histoEvent = new HistoriqueParcoursEvent($parcours, $this->getUser(), 'non_ouverture_ses', 'valide', $request);
                $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);
                $this->entityManager->flush();


                $demande = new DpeDemande();
                $demande->setFormation($formation);
                $demande->setCampagneCollecte($this->getCampagneCollecte());
                $demande->setParcours($parcours);
                $demande->setAuteur($this->getUser());
                $demande->setEtatDemande(EtatDpeEnum::non_ouverture_ses);
                $demande->setNiveauDemande($typeDpe);
                $demande->setArgumentaireDemande(array_key_exists('argumentaire_demande_non_ouverture', $data) ? $data['argumentaire_demande_non_ouverture'] : '');
                $demande->setNiveauModification($etatTypeModification);
                $this->entityManager->persist($demande);
                $this->entityManager->flush();

                //mail au SES
                $dpeDemandeEvent = new DpeDemandeEvent($demande, $this->getUser());
                $this->eventDispatcher->dispatch($dpeDemandeEvent, DpeDemandeEvent::DPE_DEMANDE_OPENED);

                return JsonReponse::success('Demande de non ouverture envoyée');
            }
            return JsonReponse::error('Erreur lors de la demande de non ouverture');
        }

        return $this->render('process_validation/_demande_non_ouverture.html.twig', [
            'parcours' => $parcours
        ]);
    }

    #[Route('/demande/reouverture/{parcours}', name: 'app_validation_demande_reouverture')]
    public function demandeReouverture(
        Parcours  $parcours,
        VersioningParcours  $versioningParcours,
        Request             $request
    ): Response {
        if ($parcours === null) {
            return JsonReponse::error('Parcours non trouvé');
        }
        $formation = $parcours->getFormation();
        $typeDpe = 'P';

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $dpe = GetDpeParcours::getFromParcours($parcours);
            if ($dpe === null) {
                return JsonReponse::error('DPE non trouvé');
            }

            //réouverture directe sans sauvegarde ou avec sauvegarde selon le choix
            if ($data['demandeReouverture'] === 'MODIFICATION_TEXTE') {
                $etatTypeModification = TypeModificationDpeEnum::MODIFICATION_TEXTE;
                $dpe->setEtatValidation(['en_cours_redaction_ss_cfvu' => 1]); //un état de processus différent pour connaitre le branchement ensuite
                $dpe->setEtatReconduction($etatTypeModification);
                $now = new DateTimeImmutable('now');
                $versioningParcours->saveVersionOfParcours($parcours, $now, true);
                $histoEvent = new HistoriqueParcoursEvent($parcours, $this->getUser(), 'en_cours_redaction_ss_cfvu', 'valide', $request);
                $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);
                $this->entityManager->flush();
            } elseif ($data['demandeReouverture'] === 'MODIFICATION_MCCC_TEXTE') {
                $etatTypeModification = TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE;
                $dpe->setEtatValidation(['en_cours_redaction' => 1]);
                $dpe->setEtatReconduction($etatTypeModification);
                $now = new DateTimeImmutable('now');
                $versioningParcours->saveVersionOfParcours($parcours, $now, true);
                $histoEvent = new HistoriqueParcoursEvent($parcours, $this->getUser(), 'en_cours_redaction', 'valide', $request);
                $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);
                $this->entityManager->flush();
            }

            $demande = new DpeDemande();
            $demande->setFormation($formation);
            $demande->setParcours($parcours);
            $demande->setCampagneCollecte($this->getCampagneCollecte());
            $demande->setAuteur($this->getUser());
            $demande->setNiveauDemande($typeDpe);
            $demande->setArgumentaireDemande(array_key_exists('argumentaire_demande_reouverture', $data) ? $data['argumentaire_demande_reouverture'] : '');
            $demande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
            $demande->setNiveauModification($etatTypeModification);
            $this->entityManager->persist($demande);
            $this->entityManager->flush();

            //mail au SES
            $dpeDemandeEvent = new DpeDemandeEvent($demande, $this->getUser());
            $this->eventDispatcher->dispatch($dpeDemandeEvent, DpeDemandeEvent::DPE_DEMANDE_OPENED);

            return JsonReponse::success('DPE ouvert');
        }

        return $this->render('process_validation/_demande_reouverture.html.twig', [
            'parcours' => $parcours
        ]);
    }

    #[Route('/demande/switch/{parcours}', name: 'app_validation_demande_switch')]
    public function demandeSwitch(
        DpeDemandeRepository $dpeDemandeRepository,
        Parcours             $parcours,
        Request              $request
    ): Response
    {
        if ($parcours === null) {
            return JsonReponse::error('Parcours non trouvé');
        }

        $demande = $dpeDemandeRepository->findLastOpenedDemande($parcours, EtatDpeEnum::en_cours_redaction, TypeModificationDpeEnum::MODIFICATION_TEXTE);

        if ($request->isMethod('POST')) {
            if ($demande === null) {
                // on créé une demande pour switcher vers modification MCCC + texte
                $demande = new DpeDemande();
                $demande->setFormation($parcours->getFormation());
                $demande->setParcours($parcours);
                $demande->setCampagneCollecte($this->getCampagneCollecte());
                $demande->setAuteur($this->getUser());
                $demande->setNiveauDemande('P');
                $demande->setniveauModification(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);
                $this->entityManager->persist($demande);
            }

            $data = $request->request->all();

            $dpe = GetDpeParcours::getFromParcours($parcours);
            if ($dpe === null) {
                return JsonReponse::error('DPE non trouvé');
            }

            //réouverture directe sans sauvegarde ou avec sauvegarde selon le choix

            $dpe->setEtatValidation(['en_cours_redaction' => 1]);
            $dpe->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);
            $this->entityManager->flush();


            $demande->setArgumentaireDemande(array_key_exists('argumentaire_demande_reouverture', $data) ? $data['argumentaire_demande_reouverture'] : '');
            $demande->setEtatDemande(EtatDpeEnum::en_cours_redaction);

            $this->entityManager->flush();

            $histoEvent = new HistoriqueParcoursEvent($parcours, $this->getUser(), 'en_cours_redaction', 'valide', $request);
            $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);

            //mail au SES
            $dpeDemandeEvent = new DpeDemandeEvent($demande, $this->getUser());
            $this->eventDispatcher->dispatch($dpeDemandeEvent, DpeDemandeEvent::DPE_DEMANDE_OPENED);


            return JsonReponse::success('DPE ouvert avec modification de la structure');
        }

        return $this->render('process_validation/_demande_switch.html.twig', [
            'parcours' => $parcours,
            'demande' => $demande ?? null,
        ]);
    }

    #[Route('/demande/reouverture-mention/{formation}', name: 'app_validation_demande_reouverture_mention')]
    public function demandeReouvertureMention(
        VersioningFormation $versioningFormation,
        Formation          $formation,
        Request            $request
    ): Response {
        $typeDpe = 'F';

        if ($formation === null) {
            return JsonReponse::error('Formation non trouvée');
        }


        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            if ($data['demandeReouverture'] === 'MODIFICATION_TEXTE') {
                $etat = TypeModificationDpeEnum::MODIFICATION_TEXTE;
                $texte = 'DPE ouvert pour modification des textes';

            } elseif ($data['demandeReouverture'] === 'MODIFICATION_PARCOURS') {
                $etat = TypeModificationDpeEnum::MODIFICATION_PARCOURS;
                $texte = 'DPE ouvert pour modification de la structure de la mention';
            }

            //todo: vérifier si l'évent est OK ?
            $versioningFormation->saveVersionOfFormation($formation, new DateTimeImmutable('now'), true);
            $histoEvent = new HistoriqueFormationEvent($formation, $this->getUser(), 'en_cours_redaction', 'valide', $request);
            $this->eventDispatcher->dispatch($histoEvent, HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION);
            $this->entityManager->flush();

            $formation->setEtatReconduction($etat);
            $this->entityManager->flush();

            //on applique directement l'ouverture, mais on historise la demande

            $demande = new DpeDemande();
            $demande->setFormation($formation);
            $demande->setParcours(null);
            $demande->setAuteur($this->getUser());
            $demande->setCampagneCollecte($this->getCampagneCollecte());
            $demande->setNiveauDemande($typeDpe);
            $demande->setArgumentaireDemande(array_key_exists('argumentaire_demande_reouverture', $data) ? $data['argumentaire_demande_reouverture'] : '');
            $demande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
            $demande->setNiveauModification($etat);
            $this->entityManager->persist($demande);
            $this->entityManager->flush();

            //mail au SES
            $dpeDemandeEvent = new DpeDemandeEvent($demande, $this->getUser());
            $this->eventDispatcher->dispatch($dpeDemandeEvent, DpeDemandeEvent::DPE_DEMANDE_OPENED);

            $formation->setEtatReconduction($etat);
            $this->entityManager->flush();

            return JsonReponse::success($texte);
        }

        return $this->render('process_validation/_demande_reouverture_formation.html.twig', [
            'formation' => $formation,
            ]);
    }

    #[Route('/demande/reouverture/cloture/{parcours}', name: 'app_validation_demande_reouverture_cloture')]
    //autorisation de la cloture si pas de différence entre les versions
    public function demandeReouvertureCloture(
        DpeDemandeRepository  $dpeDemandeRepository,
        DpeParcoursRepository $dpeParcoursRepository,
        Parcours $parcours,
        VersioningParcours    $versioningParcours,
        Request               $request
    ): Response {

        $dpeParcours = $dpeParcoursRepository->findLastDpeForParcours($parcours);

        if ($parcours === null || $dpeParcours === null) {
            return JsonReponse::error('Parcours non trouvé');
        }

        //on récupère la demande la plus récente et ouverte
        $demande = $dpeDemandeRepository->findLastOpenedDemande($parcours, EtatDpeEnum::en_cours_redaction);

        if ($demande === null) {
            return JsonReponse::error('Demande non trouvée');
        }

        //todo: gérer le cas du parcours par défaut...
        $textDifferencesParcours = $versioningParcours->getDifferencesBetweenParcoursAndLastVersion($parcours);
        $version = $versioningParcours->hasLastVersion($parcours);

        //parcourir toutes les clés de $textDifferencesParcours pour vérifier si il y a des différences (valeur non vide)
        $hasDifferences = false;
        foreach ($textDifferencesParcours as $key => $value) {
            if ($value !== '') {
                $hasDifferences = true;
                break;
            }
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $dpe = GetDpeParcours::getFromParcours($parcours);

            if ($dpe === null) {
                return JsonReponse::error('DPE non trouvé');
            }
            //réouverture directe sans sauvegarde ou avec sauvegarde selon le choix
            if ($dpe->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_TEXTE) {
                //todo: tester si pas de diff, et si oui revenir au contenu de départ
                if ($hasDifferences === true) {
                    //rollback ds modifs depuis la dernière version
                    $versioningParcours->rollbackToLastVersion($parcours);
                }
                $dpe->setEtatValidation(['publie' => 1]); //un état de processus différent pour connaitre le branchement ensuite
                $parcours->getDpeParcours()->first()->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

                $histoEvent = new HistoriqueParcoursEvent($parcours, $this->getUser(), 'cloture_ses_ss_cfvu', 'valide', $request);
                $this->eventDispatcher->dispatch($histoEvent, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);

                $this->entityManager->flush();
            } elseif ($dpe->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC || $dpe->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE) {
                $process = $this->validationProcess->getEtape('ses');
                $this->parcoursProcess->etatParcours($dpe, $process);
                $dpe->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
                $this->parcoursProcess->valideParcours($dpe, $this->getUser(), $process, $request);

                //                    $parcours->getDpeParcours()?->first()->setEtatValidation(['central' => 1]); //un état de processus différent pour connaitre le branchement ensuite
                //                    $formation->getDpe()?->getDpeParcours()->first()->setEtatValidation(['soumis_central' => 1]);
                //processus de passage en cfvu

            }

            $demande->setDateCloture(new DateTimeImmutable('now'));
            $demande->setEtatDemande(EtatDpeEnum::publie);
            $demande->setNiveauModification(TypeModificationDpeEnum::ANNULATION_REOUVERTURE);

            $this->entityManager->flush();

            return JsonReponse::success('DPE cloturé');

        }


        return $this->render('process_validation/_demande_reouverture_cloture.html.twig', [
            'parcours' => $parcours,
            'hasDifferences' => $hasDifferences,
            'type_modif' => $dpeParcours->getEtatReconduction()->value,
            'stringDifferencesParcours' => $textDifferencesParcours,
            'hasLastVersion' => $version,
        ]);
    }

    #[Route('/demande/reouverture-mention/cloture/{formation}', name: 'app_validation_demande_reouverture_cloture_mention')]
    public function demandeReouvertureClotureMention(
        DpeDemandeRepository $dpeDemandeRepository,
        Formation $formation,
        VersioningFormation $versioningFormation,
        Request             $request
    ): Response {


        if ($formation === null) {
            return JsonReponse::error('Parcours non trouvé');
        }

        //on récupère la demande la plus récente et ouverte
        $demande = $dpeDemandeRepository->findLastOpenedDemandeMention($formation, EtatDpeEnum::en_cours_redaction);

        if ($demande === null) {
            return JsonReponse::error('Demande non trouvée');
        }

        //todo: gérer le cas du parcours par défaut...
        $textDifferencesParcours = $versioningFormation->getDifferencesBetweenFormationAndLastVersion($formation);
        $version = $versioningFormation->hasLastVersion($formation);

        //parcourir toutes les clés de $textDifferencesParcours pour vérifier si il y a des différences (valeur non vide)
        $hasDifferences = false;
        foreach ($textDifferencesParcours as $key => $value) {
            if ($value !== '') {
                $hasDifferences = true;
                break;
            }
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            //réouverture directe sans sauvegarde ou avec sauvegarde selon le choix
            if ($formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_TEXTE) {
                //todo: tester si pas de diff, et si oui revenir au contenu de départ
                if ($hasDifferences === true) {
                    //rollback ds modifs depuis la dernière version
                    $versioningFormation->rollbackToLastVersion($formation);
                }
            } elseif ($formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE) {


                //                    $parcours->getDpeParcours()?->first()->setEtatValidation(['central' => 1]); //un état de processus différent pour connaitre le branchement ensuite
                //                    $formation->getDpe()?->getDpeParcours()->first()->setEtatValidation(['soumis_central' => 1]);
                //processus de passage en cfvu

            }
            $formation->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
            $demande->setDateCloture(new DateTimeImmutable('now'));
            $demande->setEtatDemande(EtatDpeEnum::publie);
            $demande->setNiveauModification(TypeModificationDpeEnum::ANNULATION_REOUVERTURE);

            $this->entityManager->flush();

            return JsonReponse::success('DPE cloturé');

        }

        return $this->render('process_validation/_demande_reouverture_formation_cloture.html.twig', [
            'formation' => $formation,
            'hasDifferences' => $hasDifferences,
            'type_modif' => $formation->getEtatReconduction()->value,
            'stringDifferencesParcours' => $textDifferencesParcours,
            'hasLastVersion' => $version,
        ]);
    }

    #[Route('/validation/ouverture/reserve-lot/{etape}', name: 'app_validation_reserve_ouverture_lot')]
    public function reserveLot(
        DpeParcoursRepository $dpeParcoursRepository,
        string                $etape,
        Request               $request
    ): Response {
        if ($request->isMethod('POST')) {
            $sParcours = $request->request->get('parcours');
        } else {
            $sParcours = $request->query->get('parcours');
        }
        $allParcours = explode(',', $sParcours);

        $process = $this->validationProcess->getEtape($etape);
        $meta = $this->validationProcess->getMetaFromTransition($transition);
        $tParcours = [];
        foreach ($allParcours as $id) {
            $dpe = $dpeParcoursRepository->find($id);
            if ($dpe === null) {
                return JsonReponse::error('Parcours non trouvé');
            }
            $tParcours[] = $dpe;
            $processData = $this->parcoursProcess->etatParcours($dpe, $process);

            if ($request->isMethod('POST')) {
                $this->parcoursProcess->reserveParcours($dpe, $this->getUser(), $transition, $request);
            }
        }

        if ($request->isMethod('POST')) {
            $this->toast('success', 'Formations marquées avec des réserves');
            return $this->redirectToRoute('app_validation_dpe_index', ['etape' => $etape]);
        }

        return $this->render('process_validation/_reserve_lot.html.twig', [
            'formations' => $tParcours,
            'sParcours' => $sParcours,
            'process' => $process,
            'meta' => $meta,
            'transition' => $transition,
            'objet' => $dpe,
            'processData' => $processData ?? null,
            'type' => 'lot',
            'id' => $id,
            'etape' => $etape,
        ]);
    }

    #[Route('/validation/ouverture/valide-lot/{etape}', name: 'app_validation_valide_ouverture_lot')]
    public function valideLot(
        EntityManagerInterface $entityManager,
        DpeParcoursRepository $dpeParcoursRepository,
        string                $etape,
        Request               $request
    ): Response {
        if ($request->isMethod('POST')) {
            $sParcours = $request->request->get('parcours');
            $nextStep = match($etape) {
                'NON_OUVERTURE_SES' => TypeModificationDpeEnum::NON_OUVERTURE_CFVU,
                'OUVERTURE_SES' => TypeModificationDpeEnum::OUVERTURE_CFVU,
                'NON_OUVERTURE_CFVU' => TypeModificationDpeEnum::NON_OUVERTURE,
                'OUVERTURE_CFVU' => TypeModificationDpeEnum::OUVERT
            };
        } else {
            $sParcours = $request->query->get('parcours');
        }

        $allParcours = explode(',', $sParcours);
        $tParcours = [];

        foreach ($allParcours as $id) {
            $dpe = $dpeParcoursRepository->find($id);
            if ($dpe === null) {
                return JsonReponse::error('Parcours non trouvé');
            }
            $tParcours[] = $dpe;

            if ($request->isMethod('POST')) {
                $dpe->setEtatReconduction($nextStep);
                if ($nextStep === TypeModificationDpeEnum::NON_OUVERTURE) {
                    $dpe->setEtatValidation(['non_ouvert' => 1]);
                    if ($dpe->getParcours()?->isParcoursDefaut() === true) {
                        $dpe->getParcours()?->setDescriptifHautPageAutomatique('Cette formation ne sera pas proposée pour la campagne ' . $this->getCampagneCollecte()->getLibelle() . '.');
                    } else {
                        $dpe->getParcours()?->setDescriptifHautPageAutomatique('Ce parcours ne sera pas proposé pour la campagne ' . $this->getCampagneCollecte()->getLibelle() . '.');
                    }
                }

                if ($nextStep === TypeModificationDpeEnum::OUVERT) {
                    $dpe->setEtatValidation(['soumis_ses' => 1]);
                    $dpe->getParcours()?->setDescriptifHautPageAutomatique(null);
                }
            }
        }

        if ($request->isMethod('POST')) {
            $entityManager->flush();
            $this->toast('success', 'Parcours validés');

            return $this->redirectToRoute('app_validation_dpe_index', [
                'step' => 'ouverture',
                'typeValidation' => $etape
            ]);
        }


        return $this->render('process_validation/_valide_ouverture_lot.html.twig', [
            'formations' => $tParcours,
            'sParcours' => $sParcours,
            'type' => 'lot',
            'etape' => $etape,
        ]);
    }
}
