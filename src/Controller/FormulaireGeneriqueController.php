<?php

namespace App\Controller;


use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Mention;
use App\Entity\Parcours;
use App\Entity\UserProfil;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Events\AddCentreParcoursEvent;
use App\Form\FormulaireGeneriqueType;
use App\Repository\MentionRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ProfilRepository;
use App\Repository\TypeDiplomeRepository;
use App\Repository\DomaineRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/formulaire-generique')]
final class FormulaireGeneriqueController extends BaseController
{
    public function __construct(
        private readonly WorkflowInterface $dpeParcoursWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/new', name: 'app_formulaire_generique_new', methods: ['GET', 'POST'])]
    public function new(
        Request                  $request,
        ParcoursRepository       $parcoursRepository,
        ProfilRepository         $profilRepository,
        MentionRepository        $mentionRepository,
        TypeDiplomeRepository    $typeDiplomeRepository,
        FormationRepository      $formationRepository,
        DomaineRepository        $domaineRepository,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        $form = $this->createForm(FormulaireGeneriqueType::class, null, [
            'action' => $this->generateUrl('app_formulaire_generique_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $mentionId = $form->get('mentionExistante')->getData();
            if (empty($mentionId)) {
                return $this->render('formulaire_generique/new.html.twig', [
                    'form'         => $form->createView(),
                    'domaines'     => $domaineRepository->findBy([], ['libelle' => 'ASC']),
                    'mentionError' => 'Veuillez sélectionner ou créer une mention.',
                ]);
            }

            $mention = $mentionRepository->find($mentionId);
            if ($mention === null) {
                return $this->render('formulaire_generique/new.html.twig', [
                    'form' => $form->createView(),
                    'mentionError' => 'Mention introuvable.',
                ]);
            }

            try {
                $formationId = $form->get('formationExistante')->getData();
                $formation = null;

                if (!empty($formationId)) {
                    $formation = $formationRepository->find($formationId);
                }

                if ($formation === null) {
                    $formation = new Formation($this->getCampagneCollecte());
                    $formation->setTypeDiplome($data['typeDiplome']);
                    $formation->setMention($mention);
                    $formation->setComposantePorteuse($data['composantePorteuse'] ?? null);
                    if (!empty($data['composantePorteuse'])) {
                        $formation->addComposantesInscription($data['composantePorteuse']);
                    }
                    $formation->setResponsableMention($this->getUser());
                    $formation->setHasParcours(true);
                    $formation->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_PARCOURS);
                    $formation->setNiveauEntree($data['niveauEntree']);
                    $formation->setNiveauSortie($data['niveauSortie']);
                    if (!empty($data['localisationMention'])) {
                        foreach ($data['localisationMention'] as $ville) {
                            $formation->addLocalisationMention($ville);
                        }
                    }
                    if (!empty($data['composantesInscription'])) {
                        foreach ($data['composantesInscription'] as $composante) {
                            $formation->addComposantesInscription($composante);
                        }
                    }
                    if (!empty($data['regimeInscription'])) {
                        $formation->setRegimeInscription($data['regimeInscription']);
                    }
                    $this->entityManager->persist($formation);
                }

                // Create the parcours
                $parcours = new Parcours($formation);
                $parcours->setLibelle($data['libelle']);
                $parcours->setRythmeFormation($data['rythmeFormation'] ?? null);
                $parcours->setRythmeFormationTexte($data['rythmeFormationTexte'] ?? null);
                $parcours->setRespParcours($this->getUser());
                $parcours->setModalitesEnseignement(null);
                $parcours->setDureeParcours($data['dureeParcours'] ?? null);
                $parcours->setDureeParcoursUnite($data['dureeParcoursUnite'] ?? null);

                $this->entityManager->persist($parcours);

                // Create DpeParcours
                $dpeParcours = new DpeParcours();
                $dpeParcours->setParcours($parcours);
                $dpeParcours->setFormation($formation);
                $dpeParcours->setCampagneCollecte($this->getCampagneCollecte());
                $dpeParcours->setVersion('0.1');
                $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE);
                $this->dpeParcoursWorkflow->apply($dpeParcours, 'initialiser');
                $this->dpeParcoursWorkflow->apply($dpeParcours, 'autoriser');
                $parcours->addDpeParcour($dpeParcours);

                $this->entityManager->persist($dpeParcours);

                // Create DpeDemande
                $dpeDemande = new DpeDemande();
                $dpeDemande->setParcours($parcours);
                $dpeDemande->setFormation($formation);
                $dpeDemande->setCampagneCollecte($this->getCampagneCollecte());
                $dpeDemande->setNiveauDemande('P');
                $dpeDemande->setEtatDemande(EtatDpeEnum::en_cours_redaction);
                $dpeDemande->setArgumentaireDemande('Création d\'un nouveau parcours');
                $dpeDemande->setNiveauModification(TypeModificationDpeEnum::CREATION);
                $dpeDemande->setAuteur($this->getUser());

                $this->entityManager->persist($dpeDemande);

                // UserProfil for responsable formation
                $profilRespFormation = $profilRepository->findOneBy(['code' => 'ROLE_RESP_FORMATION']);
                if ($profilRespFormation !== null) {
                    $uc = new UserProfil();
                    $uc->setUser($this->getUser());
                    $uc->setCampagneCollecte($this->getCampagneCollecte());
                    $uc->setFormation($formation);
                    $uc->setProfil($profilRespFormation);
                    $this->entityManager->persist($uc);
                }

                $this->entityManager->flush();

                $parcoursRepository->save($parcours, true);

                // Dispatch centre events
                $respParcoursProfil = $profilRepository->findOneBy(['code' => 'ROLE_RESP_PARCOURS']);
                if ($respParcoursProfil !== null) {
                    $event = new AddCentreParcoursEvent(
                        $parcours,
                        $this->getUser(),
                        $respParcoursProfil,
                        $this->getCampagneCollecte()
                    );
                    $eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);
                }

                $this->addFlashBag('success', 'Le parcours a été créé avec succès.');

                return $this->redirectToRoute('app_parcours_show', ['id' => $parcours->getId()]);

            } catch (\Throwable $e) {
                $this->entityManager->clear();
                $this->addFlashBag('danger', 'Une erreur est survenue lors de la création du parcours. Veuillez réessayer.');
            }
        }

        return $this->render('formulaire_generique/new.html.twig', [
            'form'     => $form->createView(),
            'domaines' => $domaineRepository->findBy([], ['libelle' => 'ASC']),
        ]);
    }

    #[Route('/mention/new', name: 'app_formulaire_generique_mention_new', methods: ['POST'])]
    public function newMention(
        Request               $request,
        TypeDiplomeRepository $typeDiplomeRepository,
        DomaineRepository     $domaineRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('mention_creation', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $typeDiplome = $typeDiplomeRepository->find((int) $request->request->get('typeDiplomeId', 0));
        $libelle     = trim($request->request->get('libelle', ''));

        if ($typeDiplome === null || $libelle === '') {
            return $this->json(['success' => false, 'error' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $mention = new Mention();
        $mention->setLibelle($libelle);
        $mention->setTypeDiplome($typeDiplome);

        $sigle = trim($request->request->get('sigle', ''));
        if ($sigle !== '') {
            $mention->setSigle($sigle);
        }
        $codeApogee = trim($request->request->get('codeApogee', ''));
        if ($codeApogee !== '') {
            $mention->setCodeApogee($codeApogee);
        }
        $domaineId = (int) $request->request->get('domaineId', 0);
        if ($domaineId > 0) {
            $domaine = $domaineRepository->find($domaineId);
            if ($domaine !== null) {
                $mention->addDomaine($domaine);
            }
        }

        $this->entityManager->persist($mention);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'id' => $mention->getId(), 'libelle' => $mention->getLibelle()]);
    }

    #[Route('/mentions-by-type-diplome/{typeDiplome}', name: 'app_formulaire_generique_mentions_by_type_diplome', methods: ['GET'])]
    public function mentionsByTypeDiplome(
        int                   $typeDiplome,
        MentionRepository     $mentionRepository,
        TypeDiplomeRepository $typeDiplomeRepository,
    ): Response {
        $typeDiplomeEntity = $typeDiplomeRepository->find($typeDiplome);
        if ($typeDiplomeEntity === null) {
            return $this->json([]);
        }

        $mentions = $mentionRepository->findBy(
            ['typeDiplome' => $typeDiplomeEntity],
            ['libelle' => 'ASC']
        );

        return $this->json(array_map(
            fn(Mention $m) => ['id' => $m->getId(), 'libelle' => $m->getLibelle()],
            $mentions
        ));
    }

    #[Route('/save/{parcours}', name: 'app_formulaire_generique_save', methods: ['POST'])]
    public function save(
        Parcours           $parcours,
        Request            $request,
        ParcoursRepository $parcoursRepository,
    ): Response {
        $data   = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $action = $data['action'] ?? null;
        $value  = $data['value'] ?? null;
        $field  = $data['field'] ?? null;

        match ($action) {
            'text' => match ($field) {
                'libelle'  => $parcours->setLibelle($value),
                'sigle'    => $parcours->setSigle($value),
                'codeRNCP' => $parcours->setCodeRNCP($value),
                default    => null,
            },
            'float' => match ($field) {
                'dureeParcours'        => $parcours->setDureeParcours($value !== '' ? (float)$value : null),
                'nbHeuresStages'       => $parcours->setNbHeuresStages($value !== '' ? (float)$value : null),
                'nbHeuresProjet'       => $parcours->setNbHeuresProjet($value !== '' ? (float)$value : null),
                'nbHeuresSituationPro' => $parcours->setNbHeuresSituationPro($value !== '' ? (float)$value : null),
                default                => null,
            },
            'dureeParcoursUnite' => $parcours->setDureeParcoursUnite(
                $value !== '' ? \App\Enums\DureeParcoursUniteEnum::from($value) : null
            ),
            'textarea' => match ($field) {
                'contenuFormation'              => $parcours->setContenuFormation($value),
                'objectifsParcours'             => $parcours->setObjectifsParcours($value),
                'resultatsAttendus'             => $parcours->setResultatsAttendus($value),
                'rythmeFormationTexte'          => $parcours->setRythmeFormationTexte($value),
                'motsCles'                      => $parcours->setMotsCles($value),
                'stageText'                     => $parcours->setStageText($value),
                'projetText'                    => $parcours->setProjetText($value),
                'memoireText'                   => $parcours->setMemoireText($value),
                'situationProText'              => $parcours->setSituationProText($value),
                'prerequis'                     => $parcours->setPrerequis($value),
                'modalitesAlternance'           => $parcours->setModalitesAlternance($value),
                'coordSecretariat'              => $parcours->setCoordSecretariat($value),
                'modalitesAdmission'            => $parcours->setModalitesAdmission($value),
                'poursuitesEtudes'              => $parcours->setPoursuitesEtudes($value),
                'debouches'                     => $parcours->setDebouches($value),
                'descriptifHautPage'            => $parcours->setDescriptifHautPage($value),
                'descriptifHautPageAutomatique' => $parcours->setDescriptifHautPageAutomatique($value),
                'descriptifBasPage'             => $parcours->setDescriptifBasPage($value),
                default                         => null,
            },
            'yesNo' => match ($field) {
                'hasStage'        => $parcours->setHasStage((bool)(int)$value),
                'hasProjet'       => $parcours->setHasProjet((bool)(int)$value),
                'hasMemoire'      => $parcours->setHasMemoire((bool)(int)$value),
                'hasSituationPro' => $parcours->setHasSituationPro((bool)(int)$value),
                default           => null,
            },
            'modalitesEnseignement' => $parcours->setModalitesEnseignement(
                $value !== '' ? \App\Enums\ModaliteEnseignementEnum::from((int)$value) : null
            ),
            'niveauFrancais' => $parcours->setNiveauFrancais(
                $value !== '' ? \App\Enums\NiveauLangueEnum::from($value) : null
            ),
            'typeParcours' => $parcours->setTypeParcours(
                $value !== '' ? \App\Enums\TypeParcoursEnum::from($value) : null
            ),
            default => null,
        };

        $parcoursRepository->save($parcours, true);

        return $this->json(true);
    }

    #[Route('/formation-by-mention/{mention}/{typeDiplome}', name: 'app_formulaire_generique_formation_by_mention', methods: ['GET'])]
    public function formationByMention(
        int                  $mention,
        int                  $typeDiplome,
        FormationRepository  $formationRepository,
        TypeDiplomeRepository $typeDiplomeRepository,
        MentionRepository    $mentionRepository,
    ): Response {
        $mentionEntity = $mentionRepository->find($mention);
        $typeDiplomeEntity = $typeDiplomeRepository->find($typeDiplome);

        if ($mentionEntity === null || $typeDiplomeEntity === null) {
            return $this->json(null);
        }

        $formation = $formationRepository->findOneBy([
            'mention' => $mentionEntity,
            'typeDiplome' => $typeDiplomeEntity,
            'dpe' => $this->getCampagneCollecte(),
        ]);

        if ($formation === null) {
            return $this->json(null);
        }

        return $this->json([
            'id' => $formation->getId(),
            'display' => $formation->getDisplayLong(),
        ]);
    }
}