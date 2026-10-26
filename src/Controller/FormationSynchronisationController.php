<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Formation;
use App\Repository\FicheMatiereRepository;
use App\Service\Api\OrebutApiService;
use App\TypeDiplome\Synchronisation\But;
use App\TypeDiplome\Synchronisation\ButSyncOptions;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class FormationSynchronisationController extends AbstractController
{
    /**
     * Modal et traitement de synchronisation avec l'API ORéBUT (B.U.T.).
     */
    #[Route('/formation/{formation}/synchronisation-orebut-modal', name: 'app_formation_synchronisation_orebut_modal', methods: ['GET', 'POST'])]
    public function synchronisationOrebutModal(
        Request $request,
        Formation $formation,
        But $butSynchronisation,
        OrebutApiService $orebutApiService,
        TurboStreamResponseFactory $turboStreamResponseFactory,
    ): Response {
        $typeD = $formation->getTypeDiplome();
        if ($typeD === null || strtoupper(trim($typeD->getLibelleCourt())) !== 'BUT') {
            throw $this->createAccessDeniedException('La synchronisation ORéBUT est réservée aux formations B.U.T.');
        }

        $detectedSigle = $butSynchronisation->resolveSigle($formation);

        if ($request->isMethod('POST')) {
            $isTurbo = $request->getPreferredFormat() === 'turbo-stream'
                || str_contains((string) $request->headers->get('Accept'), 'text/vnd.turbo-stream.html');
            $redirectUrl = $this->generateUrl('formation_v2_modifier', ['slug' => $formation->getSlug()]);

            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('orebut_sync_' . $formation->getId(), $token)) {
                $errorMsg = 'Jeton CSRF invalide.';
                if ($isTurbo) {
                    return $turboStreamResponseFactory->stream('formation/synchronisation/success.stream.html.twig', [
                        'redirectUrl' => $redirectUrl,
                        'toastMessage' => $errorMsg,
                        'success' => false,
                    ]);
                }
                $this->addFlash('danger', $errorMsg);
                return $this->redirectToRoute('formation_v2_modifier', ['slug' => $formation->getSlug()], Response::HTTP_SEE_OTHER);
            }

            $sigle = (string) $request->request->get('sigle', $detectedSigle);
            $annee = $request->request->getInt('annee', 2021);
            if (!in_array($annee, [2021, 2027], true)) {
                $annee = 2021;
            }

            $syncParcours = $request->request->getBoolean('sync_parcours');
            $syncCompetences = $request->request->getBoolean('sync_competences');
            $syncMaquette = $request->request->getBoolean('sync_maquette');
            $selectedParcours = (array) $request->request->all('parcours_codes');

            $options = new ButSyncOptions(
                syncParcours: $syncParcours,
                syncCompetences: $syncCompetences,
                syncMaquette: $syncMaquette,
                sigleDepartement: $sigle,
                selectedParcoursCodes: $selectedParcours,
                annee: $annee,
            );

            $dpeParcours = $formation->getDpeParcours()->first();
            $campagneCollecte = $dpeParcours ? $dpeParcours->getCampagneCollecte() : $formation->getDpe();

            $result = $butSynchronisation->synchroniserWithOptions($formation, $campagneCollecte, $options);
            $flashMsg = $result->success ? $result->getSummary() : ('La synchronisation a échoué : ' . implode(' ', $result->errors));

            if ($isTurbo) {
                return $turboStreamResponseFactory->stream('formation/synchronisation/success.stream.html.twig', [
                    'redirectUrl' => $redirectUrl,
                    'toastMessage' => $flashMsg,
                    'success' => $result->success,
                ]);
            }

            $this->addFlash($result->success ? 'success' : 'danger', $flashMsg);

            return $this->redirectToRoute('formation_v2_modifier', [
                'slug' => $formation->getSlug(),
            ], Response::HTTP_SEE_OTHER);
        }

        // Requête GET : affichage ou actualisation de la modale
        $sigle = (string) $request->query->get('sigle', $detectedSigle);
        $annee = $request->query->getInt('annee', 2021);
        if (!in_array($annee, [2021, 2027], true)) {
            $annee = 2021;
        }

        $deptInfo = $orebutApiService->getDepartement($sigle, $formation, $annee);
        $allDepartements = $orebutApiService->getDepartements($formation);
        $apiUrl = $orebutApiService->getApiUrl($formation);
        $hasCustomConfig = $orebutApiService->isConfigured($formation);

        $existingParcoursSigles = [];
        foreach ($formation->getParcours() as $p) {
            if ($p->getSigle() !== null && $p->getSigle() !== '') {
                $existingParcoursSigles[] = (string) $p->getSigle();
            }
        }

        return $turboStreamResponseFactory->streamOpenModalFromTemplates(
            title: 'Synchroniser avec ORéBUT',
            subtitle: $formation->getDisplay() . ' (Version ' . $annee . ')',
            bodyTemplate: 'formation/synchronisation/modal_orebut.html.twig',
            bodyContext: [
                'formation' => $formation,
                'detectedSigle' => $sigle,
                'selectedAnnee' => $annee,
                'deptInfo' => $deptInfo,
                'allDepartements' => $allDepartements,
                'apiUrl' => $apiUrl,
                'hasCustomConfig' => $hasCustomConfig,
                'existingParcoursSigles' => $existingParcoursSigles,
            ],
            footerTemplate: 'formation/synchronisation/_modal_orebut_footer.html.twig',
            footerContext: [
                'formation' => $formation,
            ]
        );
    }

    /**
     * Route historique de synchronisation globale complète (maintenue pour rétrocompatibilité).
     */
    #[Route('/formation/synchronisation/{formation}', name: 'app_formation_synchronisation', methods: ['GET', 'POST'])]
    public function index(
        But $butSynchronisation,
        Formation $formation
    ): Response {
        $dpeParcours = $formation->getDpeParcours()->first();
        $campagneCollecte = $dpeParcours ? $dpeParcours->getCampagneCollecte() : $formation->getDpe();
        if ($campagneCollecte === null) {
            $this->addFlash('danger', 'Aucune campagne de collecte trouvée.');
            return $this->redirectToRoute('formation_v2_modifier', ['slug' => $formation->getSlug()]);
        }

        $result = $butSynchronisation->synchroniserWithOptions($formation, $campagneCollecte, new ButSyncOptions());

        if ($result->success) {
            $this->addFlash('success', $result->getSummary());
        } else {
            $this->addFlash('danger', 'La synchronisation a échoué : ' . implode(' ', $result->errors));
        }

        return $this->redirectToRoute('formation_v2_modifier', [
            'slug' => $formation->getSlug(),
        ]);
    }

    #[Route('/formation/synchronisation-acs/{formation}', name: 'app_formation_synchronisation_acs', methods: ['GET', 'POST'])]
    public function synchroAcs(
        Formation $formation,
        But $butSynchronisation,
    ): Response {
        $dpeParcours = $formation->getDpeParcours()->first();
        $campagneCollecte = $dpeParcours ? $dpeParcours->getCampagneCollecte() : $formation->getDpe();

        $options = new ButSyncOptions(syncParcours: false, syncCompetences: true, syncMaquette: true);
        $result = $butSynchronisation->synchroniserWithOptions($formation, $campagneCollecte, $options);

        if ($result->success) {
            $this->addFlash('success', 'Rattachement des apprentissages critiques synchronisé.');
        } else {
            $this->addFlash('danger', 'Échec de synchronisation des apprentissages critiques.');
        }

        return $this->redirectToRoute('formation_v2_modifier', [
            'slug' => $formation->getSlug(),
        ]);
    }

    #[Route('/formation/synchronisation-mccc/{formation}', name: 'app_formation_synchronisation_mccc', methods: ['GET', 'POST'])]
    public function synchronisationMccc(
        But $butSynchronisation,
        Formation $formation
    ): Response {
        $butSynchronisation->synchroniserMccc($formation);
        $this->addFlash('success', 'Les MCCC ont été synchronisées depuis le fichier XLSX.');

        return $this->redirectToRoute('formation_v2_modifier', [
            'slug' => $formation->getSlug(),
        ]);
    }
}
