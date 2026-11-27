<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Classes\DataUserSession;
use App\DataTable\Column\SearchableTemplateColumn;
use App\Entity\CampagneCollecte;
use App\Entity\CoherenceAnalyse;
use App\Entity\Composante;
use App\Entity\Parcours;
use App\Repository\CampagneCollecteRepository;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Parcours::class)]
final class PilotageCoherenceDataTable extends AbstractAppDataTable
{
    public function __construct(
        private readonly DataUserSession            $dataUserSession,
        private readonly CampagneCollecteRepository $campagneCollecteRepository,
    )
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->pageLength(25)
            ->order([['name' => 'libelle', 'dir' => 'asc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                TextFilter::new('libelle')->label('Parcours')
            )
            ->add(
                TextFilter::new('formation.libelle')->label('Formation')
            )
            ->add(
                ChoiceFilter::new('campagne')
                    ->label('Campagne de collecte')
                    ->entity(
                        class: CampagneCollecte::class,
                        label: 'libelle',
                        orderBy: ['id' => 'DESC']
                    )
                    ->query(static function (QueryBuilder $qb, mixed $value): void {
                        if ($value) {
                            $qb->setParameter('filterCampagne', $value);
                        }
                    })
            )
            ->add(
                ChoiceFilter::new('formation.composantePorteuse')
                    ->label('Composante')
                    ->entity(
                        class: Composante::class,
                        label: 'libelle',
                        orderBy: ['libelle' => 'ASC']
                    )
            )
            ->add(
                ChoiceFilter::new('coherenceStatus')
                    ->label('Statut cohérence')
                    ->options([
                        'Anomalies (Alertes + À vérifier)' => 'all_issues',
                        'Alertes critiques uniquement' => CoherenceAnalyse::STATUS_ALERT,
                        'À vérifier uniquement' => CoherenceAnalyse::STATUS_WARNING,
                        'Cohérent' => CoherenceAnalyse::STATUS_OK,
                        'Non analysé (non persisté)' => 'not_analyzed',
                        'Analysé par IA' => 'ai_only',
                    ])
                    ->query(static function (QueryBuilder $qb, mixed $value): void {
                        if ($value === 'all_issues') {
                            $qb->andWhere('ca_filter.status IN (:issueStatuses)')
                                ->setParameter('issueStatuses', [
                                    CoherenceAnalyse::STATUS_ALERT,
                                    CoherenceAnalyse::STATUS_WARNING,
                                ]);
                        } elseif (in_array($value, [
                            CoherenceAnalyse::STATUS_ALERT,
                            CoherenceAnalyse::STATUS_WARNING,
                            CoherenceAnalyse::STATUS_OK,
                        ], true)) {
                            $qb->andWhere('ca_filter.status = :coherenceStatus')
                                ->setParameter('coherenceStatus', $value);
                        } elseif ($value === 'not_analyzed') {
                            $qb->andWhere('ca_filter.id IS NULL');
                        } elseif ($value === 'ai_only') {
                            $qb->andWhere('ca_filter.id IS NOT NULL AND ca_filter.isAiGenerated = true');
                        }
                    })
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('id', 'ID')->setOrderable(true),
            SearchableTemplateColumn::new('libelle', 'Parcours')
                ->setField('libelle')
                ->setSearchField('libelle')
                ->setOrderable(true)
                ->setTemplate('admin/pilotage/coherence/column/_parcours.html.twig'),
            SearchableTemplateColumn::new('formation', 'Formation & Composante')
                ->setSearchField('formation.libelle')
                ->setTemplate('admin/pilotage/coherence/column/_formation.html.twig'),
            TemplateColumn::new('coherenceAnalyse', 'Cohérence')
                ->setOrderable(false)
                ->setTemplate('admin/pilotage/coherence/column/_coherence.html.twig'),
            TemplateColumn::new('analyzedAt', 'Dernière analyse')
                ->setOrderable(false)
                ->setTemplate('admin/pilotage/coherence/column/_analyzed_at.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $diagnosticAction = Action::new('diagnostic', 'Diagnostic', self::BTN_EDIT_CLASS)
            ->linkToRoute('app_coherence_parcours_modal', static fn(Parcours $p): array => ['parcours' => $p->getId()])
            ->icon(Icon::WandSparkles)
            ->htmlAttributes([
                'data-action' => 'click->modalturbo#open',
                'data-turbo' => 'true',
                'data-turbo-prefetch' => 'false',
                'data-turbo-preload' => 'false',
            ]);

        return $actions
            ->add($diagnosticAction)
            ->add(
                $this->createShowAction(
                    'parcours_v2_voir',
                    static fn(Parcours $p): array => ['parcours' => $p->getId()],
                    label: 'Voir parcours',
                    modal: false,
                    icon: Icon::Eye
                )->htmlAttributes([
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                ])
            )
            ->alignment(ActionsAlignment::Right);
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $rootAlias = $qb->getRootAliases()[0];

        $campagneFilter = $request->filters['campagne'] ?? null;
        $campagne = null;
        if ($campagneFilter) {
            $campagne = $this->campagneCollecteRepository->find((int)$campagneFilter);
        }
        if ($campagne === null) {
            $campagne = $this->dataUserSession->getCampagneCollecte();
        }

        $qb->leftJoin(sprintf('%s.dpeParcours', $rootAlias), 'filter_dp')
            ->leftJoin(sprintf('%s.formation', $rootAlias), 'filter_form')
            ->leftJoin(sprintf('%s.coherenceAnalyse', $rootAlias), 'ca_filter')
            ->andWhere('filter_dp.campagneCollecte = :filterCampagne OR filter_form.dpe = :filterCampagne')
            ->setParameter('filterCampagne', $campagne);

        $qb->distinct();

        return $qb;
    }
}
