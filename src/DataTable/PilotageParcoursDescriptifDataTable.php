<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Classes\DataUserSession;
use App\DataTable\Column\SearchableTemplateColumn;
use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Parcours;
use App\Repository\CampagneCollecteRepository;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Parcours::class)]
final class PilotageParcoursDescriptifDataTable extends AbstractAppDataTable
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
                ChoiceFilter::new('typeDescriptif')
                    ->label('Type de descriptif')
                    ->options([
                        'Tous avec texte' => 'all',
                        'Descriptif manuel uniquement' => 'manuel_only',
                        'Descriptif automatique uniquement' => 'auto_only',
                        'Les deux descriptifs renseignés' => 'both',
                    ])
                    ->query(static function (QueryBuilder $qb, mixed $value): void {
                        $rootAlias = $qb->getRootAliases()[0];
                        if ($value === 'manuel_only') {
                            $qb->andWhere(sprintf("%s.descriptifHautPage IS NOT NULL AND TRIM(%s.descriptifHautPage) != ''", $rootAlias, $rootAlias))
                                ->andWhere(sprintf("(%s.descriptifHautPageAutomatique IS NULL OR TRIM(%s.descriptifHautPageAutomatique) = '')", $rootAlias, $rootAlias));
                        } elseif ($value === 'auto_only') {
                            $qb->andWhere(sprintf("%s.descriptifHautPageAutomatique IS NOT NULL AND TRIM(%s.descriptifHautPageAutomatique) != ''", $rootAlias, $rootAlias))
                                ->andWhere(sprintf("(%s.descriptifHautPage IS NULL OR TRIM(%s.descriptifHautPage) = '')", $rootAlias, $rootAlias));
                        } elseif ($value === 'both') {
                            $qb->andWhere(sprintf("%s.descriptifHautPage IS NOT NULL AND TRIM(%s.descriptifHautPage) != ''", $rootAlias, $rootAlias))
                                ->andWhere(sprintf("%s.descriptifHautPageAutomatique IS NOT NULL AND TRIM(%s.descriptifHautPageAutomatique) != ''", $rootAlias, $rootAlias));
                        }
                    })
            )
            ->add(
                TextFilter::new('formation.libelle')->label('Formation')
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
                ->setTemplate('admin/pilotage/descriptifs/column/_parcours.html.twig'),
            SearchableTemplateColumn::new('formation', 'Formation & Composante')
                ->setSearchField('formation.libelle')
                ->setTemplate('admin/pilotage/descriptifs/column/_formation.html.twig'),
            SearchableTemplateColumn::new('descriptifHautPageAutomatique', 'Descriptif automatique')
                ->setField('descriptifHautPageAutomatique')
                ->setSearchField('descriptifHautPageAutomatique')
                ->setTemplate('admin/pilotage/descriptifs/column/_descriptif_auto.html.twig'),
            SearchableTemplateColumn::new('descriptifHautPage', 'Descriptif manuel')
                ->setField('descriptifHautPage')
                ->setSearchField('descriptifHautPage')
                ->setTemplate('admin/pilotage/descriptifs/column/_descriptif_manuel.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(
                $this->createEditAction(
                    'app_admin_pilotage_descriptifs_edit',
                    static fn(Parcours $p): array => ['id' => $p->getId()],
                    label: 'Éditer',
                    modal: true,
                    icon: Icon::Pencil
                )
            )
            ->add(
                $this->createShowAction(
                    'parcours_v2_voir',
                    static fn(Parcours $p): array => ['parcours' => $p->getId()],
                    label: 'Voir parcours',
                    modal: false,
                    icon: Icon::Eye
                )->htmlAttributes([
                    'target' => '_blank',
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
            ->andWhere('filter_dp.campagneCollecte = :filterCampagne OR filter_form.dpe = :filterCampagne')
            ->setParameter('filterCampagne', $campagne);

        $qb->andWhere(sprintf(
            "(%s.descriptifHautPageAutomatique IS NOT NULL AND TRIM(%s.descriptifHautPageAutomatique) != '') OR (%s.descriptifHautPage IS NOT NULL AND TRIM(%s.descriptifHautPage) != '')",
            $rootAlias,
            $rootAlias,
            $rootAlias,
            $rootAlias
        ));

        $qb->distinct();

        return $qb;
    }
}
