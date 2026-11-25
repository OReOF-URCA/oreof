<?php

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Classes\Process\FicheMatiereProcess;
use App\Entity\FicheMatiere;
use App\Entity\User;
use App\Service\VersioningFicheMatiere;
use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FicheMatiereOuvertureController extends BaseController
{
    #[Route('/fiche/matiere/demande/ouverture/{id}', name: 'app_fiche_matiere_validation_demande_reouverture')]
    public function ouverture(
        FicheMatiereProcess $ficheMatiereProcess,
        EventDispatcherInterface $eventDispatcher,
        Request $request,
        FicheMatiere $ficheMatiere,
        VersioningFicheMatiere $versioningFiche
    ): Response {
        $user = $this->getUser();
        $isAuthorOrResp = $user instanceof User && (
                $ficheMatiere->getResponsableFicheMatiere()?->getId() === $user->getId()
                || ($ficheMatiere->getParcours() !== null && (
                        $ficheMatiere->getParcours()->getRespParcours()?->getId() === $user->getId()
                        || $ficheMatiere->getParcours()->getCoResponsable()?->getId() === $user->getId()
                        || ($ficheMatiere->getParcours()->getFormation() !== null && (
                                $ficheMatiere->getParcours()->getFormation()->getResponsableMention()?->getId() === $user->getId()
                                || $ficheMatiere->getParcours()->getFormation()->getCoResponsable()?->getId() === $user->getId()
                            ))
                    ))
            );

        if (!$this->isGranted('ROLE_ADMIN') && !$isAuthorOrResp) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour réouvrir cette fiche matière.');
        }

        if ($request->isMethod('POST')) {
            $ficheMatiereProcess->ouvertureFicheMatiere($ficheMatiere, $this->getUser(), $request);

            // Sauvegarde de la fiche lors de la réouverture
            $now = new DateTimeImmutable('now');
            $versioningFiche->saveFicheMatiereVersion($ficheMatiere, $now, withFlush: true, isVersionValide: true);

            return JsonReponse::success('La fiche matière est réouverte', [
                'redirect' => $this->generateUrl('fiche_matiere_v2_modifier', ['id' => $ficheMatiere->getId()]),
            ]);
        }
        return $this->render('fiche_matiere_ouverture/_reouverture.html.twig', [
            'ficheMatiere' => $ficheMatiere,
        ]);
    }
}
