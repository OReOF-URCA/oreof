<?php

namespace App\Controller;

use App\Classes\Codification\CodificationFormation;
use App\Classes\Export\ExportCodification;
use App\Classes\GetFormations;
use App\Classes\JsonReponse;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Repository\ComposanteRepository;
use App\Repository\DomaineRepository;
use App\Repository\FormationRepository;
use App\Repository\MentionRepository;
use App\Repository\ParcoursRepository;
use App\Repository\TypeDiplomeRepository;
use App\Repository\VilleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CodificationController extends BaseController
{
    #[Route('/codification/liste', name: 'app_codification_liste')]
    public function liste(
        TypeDiplomeRepository $typeDiplomeRepository,
    ): Response {
        return $this->render('codification/liste.html.twig', [
            'typeDiplomes' => $typeDiplomeRepository->findAll(),
        ]);
    }

    #[Route('/codification/liste-inter', name: 'app_codification_liste_inter')]
    public function listeInter(
        TypeDiplomeRepository $typeDiplomeRepository,
        Request               $request,
    ): Response {
        $typeDiplome = $typeDiplomeRepository->find($request->query->get('step'));

        if ($typeDiplome === null) {
            throw new Exception('Type de diplôme non trouvé');
        }

        return $this->render('codification/_liste-inter.html.twig', [
            'typeDiplome' => $typeDiplome,
        ]);
    }

    #[Route('/codification/liste/type_diplome/{typeDiplome}', name: 'app_codification_liste_type_diplome')]
    public function listeTypeDiplome(
        FormationRepository   $formationRepository,
        VilleRepository       $villeRepository,
        ComposanteRepository  $composanteRepository,
        MentionRepository     $mentionRepository,
        DomaineRepository     $domaineRepository,
        GetFormations         $getFormations,
        Request               $request,
        TypeDiplome           $typeDiplome
    ): Response {
        if ($typeDiplome === null) {
            throw new Exception('Type de diplôme non trouvé');
        }

        $filtres = $request->query->all();
        $filtres['typeDiplome'] = $typeDiplome->getId();

        if ($this->isGranted('ROLE_ADMIN') ||
            $this->isGranted('CAN_COMPOSANTE_SHOW_ALL', $this->getUser()) ||
            $this->isGranted('CAN_COMPOSANTE_SCOLARITE_ALL', $this->getUser()) ||
            $this->isGranted('CAN_ETABLISSEMENT_SHOW_ALL', $this->getUser()) ||
            $this->isGranted('CAN_ETABLISSEMENT_SCOLARITE_ALL', $this->getUser()) ||
            $this->isGranted('CAN_FORMATION_SHOW_ALL', $this->getUser())) {
            $tFormations = $getFormations->getFormations(
                $this->getUser(),
                $this->getCampagneCollecte(),
                $filtres
            );
        } else {
            $formations = [];
            $centres = $this->getUser()?->getUserCentres();
            foreach ($centres as $centre) {
                //todo: gérer avec un voter
                if ($centre->getComposante() !== null && (
                    in_array('Gestionnaire', $centre->getDroits()) ||
                    in_array('Invité', $centre->getDroits()) ||
                    in_array('ROLE_SCOL', $centre->getDroits()) ||
                    in_array('Directeur', $centre->getDroits())
                )) {
                    $formations[] = $formationRepository->findByComposante(
                        $centre->getComposante(),
                        $this->getCampagneCollecte()
                    );
                }
            }

            $formations[] = $formationRepository->findByComposanteDpe(
                $this->getUser(),
                $this->getCampagneCollecte()
            );
            $formations[] = $formationRepository->findByResponsableOuCoResponsable(
                $this->getUser(),
                $this->getCampagneCollecte()
            );
            $formations[] = $formationRepository->findByResponsableOuCoResponsableParcours(
                $this->getUser(),
                $this->getCampagneCollecte(),
                []
            );
            $formations = array_merge(...$formations);

            //filtrer par type de diplome les formations
            $tFormations = [];
            foreach ($formations as $formation) {
                if ($formation->getTypeDiplome() === $typeDiplome) {
                    $tFormations[$formation->getId()] = $formation;
                }
            }
        }

        return $this->render('codification/_liste.html.twig', [
            'formations' => $tFormations,
            'typeDiplome' => $typeDiplome,
            'params' => $request->query->all(),
            'composantes' => $composanteRepository->findAll(),
            'mentions' => $mentionRepository->findAll(),
            'domaines' => $domaineRepository->findAll(),
            'villes' => $villeRepository->findAll(),
        ]);
    }


    #[Route('/codification/export', name: 'app_codification_export')]
    public function export(
        ExportCodification  $export,
        TypeDiplomeRepository $typeDiplomeRepository,
        FormationRepository $formationRepository,
    ): Response {
        if ($this->isGranted('ROLE_ADMIN') ||
            $this->isGranted('CAN_COMPOSANTE_SHOW_ALL', $this->getUser()) ||
            $this->isGranted('CAN_COMPOSANTE_SCOLARITE_ALL', $this->getUser()) ||
            $this->isGranted('CAN_ETABLISSEMENT_SHOW_ALL', $this->getUser()) ||
            $this->isGranted('CAN_ETABLISSEMENT_SCOLARITE_ALL', $this->getUser()) ||
            $this->isGranted('CAN_FORMATION_SHOW_ALL', $this->getUser())) {
            $formations = $formationRepository->findBySearch('', $this->getCampagneCollecte());

            return $export->exportFormations($formations);
        }

        $formations = [];
        //gérer le cas ou l'utilisateur dispose des droits pour lire la composante
        $centres = $this->getUser()?->getUserCentres();
        foreach ($centres as $centre) {
            //todo: gérer avec un voter
            if ($centre->getComposante() !== null && (
                in_array('Gestionnaire', $centre->getDroits()) ||
                in_array('Invité', $centre->getDroits()) ||
                in_array('ROLE_SCOL', $centre->getDroits()) ||
                in_array('Directeur', $centre->getDroits())
            )) {
                //todo: il faudrait pouvoir filtrer par ce que contient le rôle et pas juste le nom
                $formations[] = $formationRepository->findByComposante(
                    $centre->getComposante(),
                    $this->getCampagneCollecte()
                );
            }
        }

        $formations[] = $formationRepository->findByComposanteDpe(
            $this->getUser(),
            $this->getCampagneCollecte()
        );
        $formations[] = $formationRepository->findByResponsableOuCoResponsable(
            $this->getUser(),
            $this->getCampagneCollecte()
        );
        $formations[] = $formationRepository->findByResponsableOuCoResponsableParcours(
            $this->getUser(),
            $this->getCampagneCollecte(),
            []
        );
        $formations = array_merge(...$formations);

        $tFormations = [];
        foreach ($formations as $formation) {
            $tFormations[$formation->getId()] = $formation;
        }

        return $this->render('codification/liste.html.twig', [
            'formations' => $tFormations,
            'typeDiplomes' => $typeDiplomeRepository->findAll(),
        ]);
    }

    #[Route('/codification/{formation}', name: 'app_codification_index')]
    public function index(
        Formation $formation,
        Request $request
    ): Response {

        $selectedParcours = $request->query->get('parcours');

        return $this->render('codification/index.html.twig', [
            'formation' => $formation,
            'step' => $selectedParcours ?? $formation->getParcours()->first()->getId(),
        ]);
    }

    #[Route('/codification/modifier/{parcours}/{annee}', name: 'app_codification_edit')]
    public function modifier(
        EntityManagerInterface $em,
        Request  $request,
        Parcours $parcours,
        int      $annee
    ): Response {
        $formation = $parcours->getFormation();

        $form = $this->createFormBuilder(null, [
            'action' => $this->generateUrl('app_codification_edit', [
                'parcours' => $parcours->getId(),
                'annee' => $annee,
            ]),
        ])
            ->add('codeMentionApogee', null, [
                'label' => 'Lettre Mention',
                'data' => $parcours->getCodeMentionApogee(),
            ])
            ->add('codeDiplome', null, [
                'label' => 'Code diplôme',
                'data' => $parcours->getCodeDiplome($annee),
            ])
            ->add('codeVersionDiplome', null, [
                'label' => 'Version diplôme',
                'data' => $parcours->getCodeVersionDiplome($annee),
            ])
            ->add('codeEtape', null, [
                'label' => 'Code étape',
                'data' => $parcours->getCodeEtape($annee),
            ])
            ->add('codeVersionEtape', null, [
                'label' => 'Version étape',
                'data' => $parcours->getCodeVersionEtape($annee),
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $semParcours = $parcours->getSemestrePourAnnee($annee);
            $parcours->setCodeMentionApogee($form->get('codeMentionApogee')->getData());
            foreach ($semParcours as $sem) {
                $sem->setCodeApogeeDiplome($form->get('codeDiplome')->getData(), $annee);
                $sem->setCodeApogeeVersionDiplome($form->get('codeVersionDiplome')->getData(), $annee);
                $sem->setCodeApogeeEtapeAnnee($form->get('codeEtape')->getData(), $annee);
                $sem->setCodeApogeeEtapeVersion($form->get('codeVersionEtape')->getData(), $annee);
            }

            $em->flush();

            return $this->json(true);
        }

        return $this->render('codification/_edit.html.twig', [
            'formation' => $formation,
            'parcours' => $parcours,
            'annee' => $annee,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/codification/parcours/{formation}', name: 'app_codification_wizard')]
    public function parcoursWizard(
        Request             $request,
        ParcoursRepository  $parcoursRepository,
        Formation           $formation
    ): Response {
        $idParcours = $request->query->get('step');
        $parcours = $parcoursRepository->find($idParcours);

        if ($parcours === null) {
            throw new NotFoundHttpException('Parcours non trouvé');
        }

        $typeD = $this->typeDiplomeResolver->fromParcours($parcours);
        $tParcours = $typeD->calculStructureParcours($parcours);

        return $this->render('codification/_parcours.html.twig', [
            'formation' => $formation,
            'typeDiplome' => $formation->getTypeDiplome(),
            'dto' => $tParcours,
            'parcours' => $parcours,
            'typeD' => $typeD,
        ]);
    }

    #[Route('/codification/modal-genere/{formation}', name: 'app_codification_modal')]
    public function genereModal(
        Formation             $formation
    ): Response {
        return $this->render('codification/_modal.html.twig', [
            'formation' => $formation,
        ]);
    }

    #[Route('/codification/genere/{formation}', name: 'app_codification')]
    public function genereCodifBasse(
        CodificationFormation $codificationFormation,
        Formation             $formation
    ): Response {
        $codificationFormation->setCodificationBasse($formation);
        return JsonReponse::success('Codification mise à jour');
    }
}
