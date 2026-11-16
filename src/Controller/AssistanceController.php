<?php

namespace App\Controller;

use App\Entity\DemandeAssistance;
use App\Entity\Formation;
use App\Entity\MessageAssistance;
use App\Entity\Parcours;
use App\Entity\PieceJointeAssistance;
use App\Entity\User;
use App\Enums\SensMessageAssistanceEnum;
use App\Enums\StatutDemandeAssistanceEnum;
use App\Repository\DemandeAssistanceRepository;
use App\Repository\FormationRepository;
use App\Repository\UserRepository;
use App\Service\Assistance\FilMessagesAssistance;
use App\Service\Assistance\FusionDemandesAssistance;
use App\Service\Assistance\IntegrationMailAssistance;
use App\Service\Assistance\ReponseAssistance;
use App\Service\Assistance\Source\ImapSource;
use App\Service\Assistance\StockagePieceJointe;
use App\Service\Assistance\VerrouReleveAssistance;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * Suivi des demandes d'assistance reçues par mail.
 */
#[Route('/administration/assistance', name: 'app_assistance_')]
#[IsGranted('ROLE_ADMIN')]
class AssistanceController extends AbstractController
{
    private const PAR_PAGE = 30;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, DemandeAssistanceRepository $repository, UserRepository $userRepository, FilMessagesAssistance $fil): Response
    {
        $statut = $request->query->getString('statut', 'ouvertes');
        $filtreResponsable = $request->query->getString('responsable');
        $recherche = $request->query->getString('q');
        $page = max(1, $request->query->getInt('page', 1));

        $responsable = match (true) {
            $filtreResponsable === 'moi' => $this->getUser() instanceof User ? $this->getUser() : null,
            ctype_digit($filtreResponsable) => $userRepository->find((int) $filtreResponsable),
            default => null,
        };

        $demandes = $repository->rechercher($statut, $responsable, $recherche, $page, self::PAR_PAGE);

        return $this->render('assistance/index.html.twig', [
            'demandes' => $demandes,
            'fil' => $fil,
            'total' => count($demandes),
            'page' => $page,
            'pages' => max(1, (int) ceil(count($demandes) / self::PAR_PAGE)),
            'ouvertes' => $repository->compterOuvertes(),
            'filtres' => ['statut' => $statut, 'responsable' => $filtreResponsable, 'q' => $recherche],
            'statuts' => StatutDemandeAssistanceEnum::cases(),
            'equipe' => $userRepository->findByRole('ROLE_ADMIN'),
        ]);
    }

    #[Route('/relever', name: 'relever', methods: ['POST'])]
    public function relever(Request $request, ImapSource $source, IntegrationMailAssistance $integration, VerrouReleveAssistance $verrou): Response
    {
        if (!$this->isCsrfTokenValid('assistance_relever', $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_assistance_index');
        }

        if (!$source->estConfiguree()) {
            $this->addFlash('warning', 'La boîte mail d\'assistance n\'est pas encore configurée.');

            return $this->redirectToRoute('app_assistance_index');
        }

        if (!$verrou->acquerir()) {
            $this->addFlash('info', 'Une relève est déjà en cours, réessayez dans quelques instants.');

            return $this->redirectToRoute('app_assistance_index');
        }

        try {
            // fenêtre courte : la relève complète (7 jours) est faite par le cron, hors requête web
            $nombre = $integration->integrerTout($source, new DateTimeImmutable('-1 day'));
            $this->addFlash('success', sprintf('%d nouveau(x) mail(s) relevé(s).', $nombre));
        } catch (Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        } finally {
            $verrou->liberer();
        }

        return $this->redirectToRoute('app_assistance_index');
    }

    #[Route('/piece-jointe/{id}', name: 'piece_jointe', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function pieceJointe(PieceJointeAssistance $piece, StockagePieceJointe $stockage): Response
    {
        $chemin = $stockage->cheminAbsolu($piece);
        if (!is_file($chemin)) {
            throw $this->createNotFoundException('Pièce jointe introuvable.');
        }

        $reponse = new BinaryFileResponse($chemin);
        $reponse->headers->set('Content-Type', $piece->getMimeType());
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $piece->getNomOriginal(), 'piece-jointe');

        return $reponse;
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        DemandeAssistance $demande,
        DemandeAssistanceRepository $repository,
        UserRepository $userRepository,
        FormationRepository $formationRepository,
        ReponseAssistance $reponse,
    ): Response {
        return $this->render('assistance/show.html.twig', [
            'demande' => $demande,
            'statuts' => StatutDemandeAssistanceEnum::cases(),
            'equipe' => $userRepository->findByRole('ROLE_ADMIN'),
            'formations' => $formationRepository->findBy([], ['id' => 'DESC']),
            'candidatesFusion' => $repository->candidatesFusion($demande),
            'destinatairesProposes' => $reponse->destinatairesProposes($demande),
        ]);
    }

    #[Route('/{id}/repondre', name: 'repondre', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function repondre(Request $request, DemandeAssistance $demande, ReponseAssistance $reponse): Response
    {
        $auteur = $this->getUser();
        if (!$this->jetonValide($request, $demande) || !$auteur instanceof User) {
            return $this->retourDemande($demande);
        }

        $destinataires = preg_split('/[\s,;]+/', $request->request->getString('destinataires')) ?: [];

        try {
            $reponse->repondre($demande, $auteur, $request->request->getString('corps'), $destinataires, $request->request->getBoolean('resoudre'));
            $this->addFlash('success', 'Réponse envoyée.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('warning', $e->getMessage());
        } catch (Throwable $e) {
            $this->addFlash('error', 'Envoi impossible : ' . $e->getMessage());
        }

        return $this->retourDemande($demande);
    }

    #[Route('/{id}/statut', name: 'statut', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function statut(Request $request, DemandeAssistance $demande): Response
    {
        $statut = StatutDemandeAssistanceEnum::tryFrom($request->request->getString('statut'));
        if ($this->jetonValide($request, $demande) && $statut !== null) {
            $demande->setStatut($statut);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Statut : %s.', $statut->getLibelle()));
        }

        return $this->retourDemande($demande);
    }

    #[Route('/{id}/responsable', name: 'responsable', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function responsable(Request $request, DemandeAssistance $demande, UserRepository $userRepository): Response
    {
        if ($this->jetonValide($request, $demande)) {
            $id = $request->request->getString('responsable');
            $responsable = ctype_digit($id) ? $userRepository->find((int) $id) : null;
            $demande->setResponsable($responsable);
            if ($responsable !== null && $demande->getStatut() === StatutDemandeAssistanceEnum::NOUVELLE) {
                $demande->setStatut(StatutDemandeAssistanceEnum::EN_COURS);
            }
            $this->entityManager->flush();
        }

        return $this->retourDemande($demande);
    }

    #[Route('/{id}/note', name: 'note', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function note(Request $request, DemandeAssistance $demande): Response
    {
        $contenu = trim($request->request->getString('contenu'));
        if ($this->jetonValide($request, $demande) && $contenu !== '') {
            $auteur = $this->getUser() instanceof User ? $this->getUser() : null;
            $note = (new MessageAssistance())
                ->setSens(SensMessageAssistanceEnum::NOTE)
                ->setAuteur($auteur)
                ->setExpediteurEmail((string) $auteur?->getEmail())
                ->setExpediteurNom($auteur?->getDisplay())
                ->setSujet('Note interne')
                ->setCorpsTexte($contenu)
                ->setDateEnvoi(new DateTimeImmutable());
            $demande->addMessage($note);
            $this->entityManager->flush();
        }

        return $this->retourDemande($demande);
    }

    #[Route('/{id}/rattacher', name: 'rattacher', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rattacher(Request $request, DemandeAssistance $demande): Response
    {
        if ($this->jetonValide($request, $demande)) {
            $formationId = $request->request->getString('formation');
            $parcoursId = $request->request->getString('parcours');
            $formation = ctype_digit($formationId) ? $this->entityManager->find(Formation::class, (int) $formationId) : null;
            $parcours = ctype_digit($parcoursId) ? $this->entityManager->find(Parcours::class, (int) $parcoursId) : null;
            if ($parcours !== null && $parcours->getFormation() !== $formation) {
                $parcours = null;
            }
            $demande->setFormation($formation)->setParcours($parcours);
            $this->entityManager->flush();
        }

        return $this->retourDemande($demande);
    }

    #[Route('/{id}/fusionner', name: 'fusionner', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function fusionner(Request $request, DemandeAssistance $demande, DemandeAssistanceRepository $repository, FusionDemandesAssistance $fusion): Response
    {
        $id = $request->request->getString('source');
        $source = ctype_digit($id) ? $repository->find((int) $id) : null;
        if ($this->jetonValide($request, $demande) && $source !== null && $source !== $demande) {
            // les messages vont sur la demande la plus ancienne
            [$cible, $absorbee] = $source->getCreated() < $demande->getCreated() ? [$source, $demande] : [$demande, $source];
            $fusion->fusionner($cible, $absorbee);
            $this->addFlash('success', 'Demandes fusionnées.');

            return $this->retourDemande($cible);
        }

        return $this->retourDemande($demande);
    }

    private function jetonValide(Request $request, DemandeAssistance $demande): bool
    {
        return $this->isCsrfTokenValid('assistance_' . $demande->getId(), $request->request->getString('_token'));
    }

    private function retourDemande(DemandeAssistance $demande): Response
    {
        return $this->redirectToRoute('app_assistance_show', ['id' => $demande->getId()]);
    }
}
