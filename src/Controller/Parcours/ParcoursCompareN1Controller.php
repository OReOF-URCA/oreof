<?php

declare(strict_types=1);

namespace App\Controller\Parcours;

use App\Controller\BaseController;
use App\Entity\Parcours;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Navigation\Breadcrumb\Breadcrumb as BreadcrumbService;
use App\Service\Parcours\ParcoursN1ComparatorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parcours/v2/{parcours}/comparer-n1', name: 'app_parcours_comparer_n1', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
class ParcoursCompareN1Controller extends BaseController
{
    #[Breadcrumb(menuKey: 'offre.detail_mentions')]
    public function __invoke(
        Parcours $parcours,
        Request $request,
        ParcoursN1ComparatorService $comparatorService,
        BreadcrumbService $breadcrumb,
    ): Response {
        $formation = $parcours->getFormation();
        if ($formation === null) {
            throw $this->createNotFoundException('Formation introuvable.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('RELATED_TO_PARCOURS', $parcours)) {
            throw $this->createAccessDeniedException();
        }

        if ($formation->getSlug() !== null) {
            $breadcrumb->add(
                $formation->getDisplay() ?? 'Formation',
                'formation_v2_voir',
                ['slug' => $formation->getSlug()]
            );
        }

        $breadcrumb->add(
            $parcours->getDisplay(),
            'parcours_v2_voir',
            ['parcours' => $parcours->getId()]
        );

        $breadcrumb->add('Comparaison N-1 => N');

        $scope = $request->query->getString('scope', 'parcours');
        if (!in_array($scope, ['parcours', 'formation'], true)) {
            $scope = 'parcours';
        }

        $result = $comparatorService->compare($parcours, scope: $scope);
        $onlyDiff = $request->query->getBoolean('only_diff', false);
        $activeTab = $request->query->getString('tab', 'synthese');

        return $this->render('parcours_v2/comparer_n1.html.twig', [
            'parcours' => $parcours,
            'formation' => $formation,
            'result' => $result,
            'onlyDiff' => $onlyDiff,
            'activeTab' => $activeTab,
            'scope' => $scope,
        ]);
    }
}
