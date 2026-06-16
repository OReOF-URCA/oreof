<?php

namespace App\Controller;


use App\Entity\Constantes;
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
        // Seuls les utilisateurs disposant de tous les droits (admin) peuvent désigner
        // une autre personne comme responsable du parcours. Les autres ne peuvent
        // s'assigner qu'eux-mêmes (sinon ils perdraient l'accès au parcours créé).
        $canChooseResponsable = $this->isGranted('ROLE_ADMIN');

        $form = $this->createForm(FormulaireGeneriqueType::class, null, [
            'action' => $this->generateUrl('app_formulaire_generique_new'),
            'can_choose_responsable' => $canChooseResponsable,
            'current_user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $mentionId = $form->get('mentionExistante')->getData();
            if (empty($mentionId)) {
                return $this->render('formulaire_generique/new.html.twig', [
                    'form'         => $form->createView(),
                    'domaines'     => $domaineRepository->findBy([], ['libelle' => 'ASC']),
                    'mentionError' => 'Veuillez sélectionner ou créer un intitulé de formation.',
                ]);
            }

            $mention = $mentionRepository->find($mentionId);
            if ($mention === null) {
                return $this->render('formulaire_generique/new.html.twig', [
                    'form' => $form->createView(),
                    'mentionError' => 'Intitulé de formation introuvable.',
                ]);
            }

            try {
                $formationId = $form->get('formationExistante')->getData();
                $formation = null;

                if (!empty($formationId)) {
                    $formation = $formationRepository->find($formationId);
                }

                // Filet de sécurité : si le champ caché n'a pas été renseigné (ex. retour
                // arrière du navigateur sans relance du JS), on vérifie nous-mêmes qu'une
                // formation n'existe pas déjà pour ce couple mention / type de diplôme,
                // afin de ne pas en créer un doublon.
                if ($formation === null) {
                    $formation = $formationRepository->findOneBy([
                        'mention' => $mention,
                        'typeDiplome' => $data['typeDiplome'],
                        'dpe' => $this->getCampagneCollecte(),
                    ]);
                }

                $formationCreee = $formation === null;

                // Case « Cette formation aura plusieurs parcours » (proposée uniquement
                // pour une formation neuve). Mono = formation neuve sans la case cochée :
                // la formation EST son parcours unique (champs nommés « … de formation »).
                $plusieursParcours = (bool) $form->get('plusieursParcours')->getData();
                $estMono = $formationCreee && !$plusieursParcours;

                if ($formation === null) {
                    $formation = new Formation($this->getCampagneCollecte());
                    $formation->setTypeDiplome($data['typeDiplome']);
                    $formation->setMention($mention);
                    $formation->setComposantePorteuse($data['composantePorteuse'] ?? null);
                    if (!empty($data['composantePorteuse'])) {
                        $formation->addComposantesInscription($data['composantePorteuse']);
                    }
                    // Même garde-fou que pour le responsable du parcours : seul un admin
                    // peut désigner quelqu'un d'autre, sinon c'est l'utilisateur courant.
                    $responsableMention = $canChooseResponsable ? ($data['responsableMention'] ?? $this->getUser()) : $this->getUser();
                    $formation->setResponsableMention($responsableMention);
                    // Mono (case décochée) → affichage sans parcours ; multi (case cochée) → avec parcours.
                    $formation->setHasParcours($plusieursParcours);
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
                } else {
                    // Ajout d'un parcours à une formation existante → elle devient multiparcours.
                    $formation->setHasParcours(true);
                }

                // Create the parcours
                $parcours = new Parcours($formation);
                if ($estMono) {
                    // Mono : le parcours unique reprend l'intitulé et le responsable de la formation.
                    $libelleParcours = $mention->getLibelle();
                    $respParcours = $responsableMention;
                } else {
                    // Multi / formation existante : libellé saisi (ou intitulé de la formation si vide),
                    // responsable choisi (garde-fou : un non-admin reste responsable de son parcours).
                    $libelleParcours = trim((string) ($data['libelle'] ?? '')) ?: $mention->getLibelle();
                    $respParcours = $canChooseResponsable ? ($data['respParcours'] ?? $this->getUser()) : $this->getUser();
                }
                $parcours->setLibelle($libelleParcours);
                $parcours->setRythmeFormation($data['rythmeFormation'] ?? null);
                $parcours->setRythmeFormationTexte($data['rythmeFormationTexte'] ?? null);
                $parcours->setRespParcours($respParcours);
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
                if ($data['typeDiplome']->isPassageCfvu()) {
                    $this->dpeParcoursWorkflow->apply($dpeParcours, 'autoriser');
                } else {
                    // Type de diplôme sans passage CFVU → initialisation directe dans l'état
                    // d'édition « sans CFVU » (même mécanisme que ProcessReouvertureController).
                    $dpeParcours->setEtatValidation(['en_cours_redaction_ss_cfvu' => 1]);
                }
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

                $this->addFlashBag(
                    Constantes::FLASHBAG_SUCCESS,
                    $formationCreee
                        ? 'La formation et son parcours ont été créés avec succès.'
                        : 'Le parcours a été créé avec succès.'
                );

                // Nouvelle formation (elle n'avait aucun parcours) → on affiche la formation ;
                // ajout d'un parcours à une formation existante → on affiche le parcours.
                if ($formationCreee) {
                    return $this->redirectToRoute('app_formation_show', ['slug' => $formation->getSlug()]);
                }

                return $this->redirectToRoute('app_parcours_show', ['id' => $parcours->getId()]);

            } catch (\Throwable $e) {
                $this->entityManager->clear();
                $this->addFlashBag(Constantes::FLASHBAG_ERROR, 'Une erreur est survenue lors de la création du parcours. Veuillez réessayer.');
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
        // Domaine(s) : au moins un est obligatoire.
        $domaineIds = $request->request->all('domaineIds');
        $domainesAjoutes = 0;
        foreach ($domaineIds as $domaineId) {
            $domaine = $domaineRepository->find((int) $domaineId);
            if ($domaine !== null) {
                $mention->addDomaine($domaine);
                $domainesAjoutes++;
            }
        }
        if ($domainesAjoutes === 0) {
            return $this->json(['success' => false, 'error' => 'Veuillez sélectionner au moins un domaine.'], Response::HTTP_BAD_REQUEST);
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