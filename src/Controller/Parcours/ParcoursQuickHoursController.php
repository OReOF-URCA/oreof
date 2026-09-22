<?php

namespace App\Controller\Parcours;

use App\Classes\GetElementConstitutif;
use App\Controller\BaseController;
use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Navigation\Breadcrumb\Breadcrumb as BreadcrumbService;
use App\Repository\ElementConstitutifRepository;
use App\Service\Parcours\ParcoursHoursComparator;
use App\Service\Validation\ValidationDirtyMarker;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parcours/v2', name: 'parcours_v2_')]
class ParcoursQuickHoursController extends BaseController
{
    //todo: gérer les mutualisés non éditable sauf coche dédiée. Les enfants (ECTS imposés par le parent...)
    #[Route('/{parcours}/saisie-rapide-heures', name: 'saisie_rapide_heures', methods: ['GET'])]
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function index(
        Request $request,
        Parcours $parcours,
        TypeDiplomeResolver $typeDiplomeResolver,
        ParcoursHoursComparator $hoursComparator,
        BreadcrumbService $breadcrumb
    ): Response {
        $formation = $parcours->getFormation();
        if ($formation !== null) {
            $breadcrumb->add(
                $formation->getDisplay(),
                'formation_v2_voir',
                ['slug' => $formation->getSlug()]
            );
        }
        $breadcrumb->add(
            $parcours->getDisplay(),
            'parcours_v2_modifier',
            ['parcours' => $parcours->getId()]
        );
        $breadcrumb->add('Saisie rapide des heures');

        $typeD = $typeDiplomeResolver->fromParcours($parcours);
        $dto = $typeD->calculStructureParcours($parcours);

        $selectedRef = $request->query->get('ref');
        $referenceOptions = $hoursComparator->getAvailableReferences($parcours);
        $referenceData = $hoursComparator->getReferenceData($parcours, $selectedRef);

        return $this->render('parcours_v2/saisie_rapide_heures.html.twig', [
            'parcours' => $parcours,
            'formation' => $formation,
            'typeDiplome' => $formation?->getTypeDiplome(),
            'dto' => $dto,
            'referenceOptions' => $referenceOptions,
            'selectedRef' => $selectedRef,
            'referenceData' => $referenceData,
            'modalite' => $parcours->getModalitesEnseignement(),
        ]);
    }

    #[Route('/{parcours}/quick-heures/ec/{elementConstitutif}', name: 'quick_save_ec_heures', methods: ['POST'])]
    public function quickSaveEc(
        Request $request,
        Parcours $parcours,
        ElementConstitutif $elementConstitutif,
        ValidationDirtyMarker $dirtyMarker,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? $request->request->all();

        $ficheMatiere = $elementConstitutif->getFicheMatiere();
        $isParcoursProprietaire = $ficheMatiere === null || $ficheMatiere->getParcours() === null || $ficheMatiere->getParcours()->getId() === $parcours->getId();
        $isVolumesImposes = $ficheMatiere?->isVolumesHorairesImpose() ?? false;
        $isEctsImpose = $ficheMatiere?->isEctsImpose() ?? false;

        // Toggle heures spécifiques
        if (array_key_exists('heuresSpecifiques', $data)) {
            $elementConstitutif->setHeuresSpecifiques((bool)$data['heuresSpecifiques']);
        }

        // Toggle sans heure
        $sansHeure = array_key_exists('sansHeure', $data) ? (bool)$data['sansHeure'] : $elementConstitutif->isSansHeure();
        $elementConstitutif->setSansHeure($sansHeure);

        if ($sansHeure) {
            $elementConstitutif->setVolumeCmPresentiel(0.0);
            $elementConstitutif->setVolumeTdPresentiel(0.0);
            $elementConstitutif->setVolumeTpPresentiel(0.0);
            $elementConstitutif->setVolumeTe(0.0);
            $elementConstitutif->setVolumeCmDistanciel(0.0);
            $elementConstitutif->setVolumeTdDistanciel(0.0);
            $elementConstitutif->setVolumeTpDistanciel(0.0);

            if ($ficheMatiere !== null && $isParcoursProprietaire && !$isVolumesImposes && !$elementConstitutif->isHeuresSpecifiques()) {
                $ficheMatiere->setVolumeCmPresentiel(0.0);
                $ficheMatiere->setVolumeTdPresentiel(0.0);
                $ficheMatiere->setVolumeTpPresentiel(0.0);
                $ficheMatiere->setVolumeTe(0.0);
                $ficheMatiere->setVolumeCmDistanciel(0.0);
                $ficheMatiere->setVolumeTdDistanciel(0.0);
                $ficheMatiere->setVolumeTpDistanciel(0.0);
            }
        } elseif (!$isVolumesImposes) {
            $cmPres = array_key_exists('cmPres', $data) ? max(0.0, (float)$data['cmPres']) : $elementConstitutif->getVolumeCmPresentiel();
            $tdPres = array_key_exists('tdPres', $data) ? max(0.0, (float)$data['tdPres']) : $elementConstitutif->getVolumeTdPresentiel();
            $tpPres = array_key_exists('tpPres', $data) ? max(0.0, (float)$data['tpPres']) : $elementConstitutif->getVolumeTpPresentiel();
            $tePres = array_key_exists('tePres', $data) ? max(0.0, (float)$data['tePres']) : $elementConstitutif->getVolumeTe();
            $cmDist = array_key_exists('cmDist', $data) ? max(0.0, (float)$data['cmDist']) : $elementConstitutif->getVolumeCmDistanciel();
            $tdDist = array_key_exists('tdDist', $data) ? max(0.0, (float)$data['tdDist']) : $elementConstitutif->getVolumeTdDistanciel();
            $tpDist = array_key_exists('tpDist', $data) ? max(0.0, (float)$data['tpDist']) : $elementConstitutif->getVolumeTpDistanciel();

            $elementConstitutif->setVolumeCmPresentiel($cmPres);
            $elementConstitutif->setVolumeTdPresentiel($tdPres);
            $elementConstitutif->setVolumeTpPresentiel($tpPres);
            $elementConstitutif->setVolumeTe($tePres);
            $elementConstitutif->setVolumeCmDistanciel($cmDist);
            $elementConstitutif->setVolumeTdDistanciel($tdDist);
            $elementConstitutif->setVolumeTpDistanciel($tpDist);

            if ($ficheMatiere !== null && $isParcoursProprietaire && !$elementConstitutif->isHeuresSpecifiques()) {
                $ficheMatiere->setVolumeCmPresentiel($cmPres);
                $ficheMatiere->setVolumeTdPresentiel($tdPres);
                $ficheMatiere->setVolumeTpPresentiel($tpPres);
                $ficheMatiere->setVolumeTe($tePres);
                $ficheMatiere->setVolumeCmDistanciel($cmDist);
                $ficheMatiere->setVolumeTdDistanciel($tdDist);
                $ficheMatiere->setVolumeTpDistanciel($tpDist);
            }
        }

        // ECTS
        if (array_key_exists('ects', $data) && !$isEctsImpose) {
            $ects = max(0.0, (float)$data['ects']);
            $elementConstitutif->setEcts($ects);
            if ($ficheMatiere !== null && $isParcoursProprietaire) {
                $ficheMatiere->setEcts($ects);
            }
        }

        $dirtyMarker->markEcDirty($elementConstitutif);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'ecId' => $elementConstitutif->getId(),
            'code' => $elementConstitutif->getCode(),
            'ects' => (float)$elementConstitutif->getEcts(),
            'heures' => [
                'cmPres' => (float)$elementConstitutif->getVolumeCmPresentiel(),
                'tdPres' => (float)$elementConstitutif->getVolumeTdPresentiel(),
                'tpPres' => (float)$elementConstitutif->getVolumeTpPresentiel(),
                'tePres' => (float)$elementConstitutif->getVolumeTe(),
                'cmDist' => (float)$elementConstitutif->getVolumeCmDistanciel(),
                'tdDist' => (float)$elementConstitutif->getVolumeTdDistanciel(),
                'tpDist' => (float)$elementConstitutif->getVolumeTpDistanciel(),
            ],
            'sansHeure' => (bool)$elementConstitutif->isSansHeure(),
            'heuresSpecifiques' => (bool)$elementConstitutif->isHeuresSpecifiques(),
        ]);
    }
}
