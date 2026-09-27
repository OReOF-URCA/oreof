<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Parcours::class)]
final class VersioningParcoursDataTable extends AbstractAppDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'id', 'dir' => 'desc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('libelle')->label('Parcours'))
            ->add(
                ChoiceFilter::new('campagne')
                    ->label('Campagne de collecte')
                    ->entity(
                        class: CampagneCollecte::class,
                        label: 'libelle',
                        orderBy: ['libelle' => 'DESC']
                    )
                    ->query(static function (QueryBuilder $qb, mixed $value): void {
                        $rootAlias = $qb->getRootAliases()[0];
                        if ($value) {
                            $qb->leftJoin(sprintf('%s.dpeParcours', $rootAlias), 'filter_dp')
                                ->leftJoin(sprintf('%s.formation', $rootAlias), 'filter_form')
                                ->andWhere('filter_dp.campagneCollecte = :filterCampagne OR filter_form.dpe = :filterCampagne')
                                ->setParameter('filterCampagne', $value);
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
                ChoiceFilter::new('statusVersioning')
                    ->label('Statut versioning')
                    ->options([
                        '⚠️ Aucune version générée' => 'no_version',
                        '✔️ Avec versions enregistrées' => 'has_version',
                    ])
                    ->query(static function (QueryBuilder $qb, mixed $value): void {
                        $rootAlias = $qb->getRootAliases()[0];
                        if ($value === 'no_version') {
                            $qb->andWhere(sprintf('(SELECT COUNT(pv.id) FROM %s pv WHERE pv.parcours = %s) = 0', ParcoursVersioning::class, $rootAlias));
                        } elseif ($value === 'has_version') {
                            $qb->andWhere(sprintf('(SELECT COUNT(pv.id) FROM %s pv WHERE pv.parcours = %s) > 0', ParcoursVersioning::class, $rootAlias));
                        }
                    })
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('id', 'ID')->setOrderable(true),
            TemplateColumn::new('libelle', 'Parcours')
                ->setField('libelle')
                ->setSearchField('libelle')
                ->setOrderable(true)
                ->setTemplate('admin/versioning/column/_parcours_libelle.html.twig'),
            TemplateColumn::new('campagne', 'Campagne')
                ->setTemplate('admin/versioning/column/_parcours_campagne.html.twig'),
            TemplateColumn::new('formation', 'Formation')
                ->setTemplate('admin/versioning/column/_parcours_formation.html.twig'),
            TemplateColumn::new('status', 'Statut')
                ->setTemplate('admin/versioning/column/_parcours_status.html.twig'),
            TemplateColumn::new('derniereVersion', 'Dernière version')
                ->setTemplate('admin/versioning/column/_parcours_derniere_version.html.twig'),
            TemplateColumn::new('fichiers', 'Fichiers JSON (Parcours / DTO)')
                ->setTemplate('admin/versioning/column/_parcours_files.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $generateAction = Action::new('generate', 'Générer version')
            ->linkToRoute('app_admin_versioning_generate_version', static fn(Parcours $p): array => ['type' => 'parcours', 'id' => $p->getId()])
            ->asAjaxRequest(method: 'POST')
            ->icon(Icon::RefreshCw)
            ->askConfirmation('Générer une nouvelle version JSON pour ce parcours ?')
            ->setClassName('inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300');

        return $actions
            ->add($generateAction)
            ->alignment(ActionsAlignment::Right);
    }
}


