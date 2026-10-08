<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\SearchableTemplateColumn;
use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereVersioning;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(FicheMatiere::class)]
final class VersioningFicheMatiereDataTable extends AbstractAppDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'id', 'dir' => 'desc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('libelle')->label('Fiche Matière'))
            ->add(TextFilter::new('sigle')->label('Code / Sigle'))
            ->add(
                ChoiceFilter::new('campagneCollecte')
                    ->label('Campagne de collecte')
                    ->entity(
                        class: CampagneCollecte::class,
                        label: 'libelle',
                        orderBy: ['libelle' => 'DESC']
                    )
            )
            ->add(
                ChoiceFilter::new('composante')
                    ->label('Composante')
                    ->entity(
                        class: Composante::class,
                        label: 'libelle',
                        orderBy: ['libelle' => 'ASC']
                    )
                    ->query(static function (QueryBuilder $qb, mixed $value): void {
                        $rootAlias = $qb->getRootAliases()[0];
                        if ($value) {
                            $qb->andWhere(sprintf(':filterComposante MEMBER OF %s.composante', $rootAlias))
                                ->setParameter('filterComposante', $value);
                        }
                    })
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
                            $qb->andWhere(sprintf('(SELECT COUNT(fmv.id) FROM %s fmv WHERE fmv.ficheMatiere = %s) = 0', FicheMatiereVersioning::class, $rootAlias));
                        } elseif ($value === 'has_version') {
                            $qb->andWhere(sprintf('(SELECT COUNT(fmv.id) FROM %s fmv WHERE fmv.ficheMatiere = %s) > 0', FicheMatiereVersioning::class, $rootAlias));
                        }
                    })
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('id', 'ID')->setOrderable(true),
            SearchableTemplateColumn::new('libelle', 'Fiche Matière')
                ->setField('libelle')
                ->setSearchField('libelle')
                ->setOrderable(true)
                ->setTemplate('admin/versioning/column/_fiche_libelle.html.twig'),
            SearchableTemplateColumn::new('campagne', 'Campagne')
                ->setTemplate('admin/versioning/column/_fiche_campagne.html.twig'),
            SearchableTemplateColumn::new('composante', 'Composante')
                ->setTemplate('admin/versioning/column/_fiche_composante.html.twig'),
            SearchableTemplateColumn::new('status', 'Statut')
                ->setTemplate('admin/versioning/column/_fiche_status.html.twig'),
            SearchableTemplateColumn::new('derniereVersion', 'Dernière version')
                ->setTemplate('admin/versioning/column/_fiche_derniere_version.html.twig'),
            SearchableTemplateColumn::new('fichiers', 'Fichiers JSON')
                ->setTemplate('admin/versioning/column/_fiche_files.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $generateAction = Action::new('generate', 'Générer version')
            ->linkToRoute('app_admin_versioning_generate_version', static fn(FicheMatiere $f): array => ['type' => 'fiche', 'id' => $f->getId()])
            ->asAjaxRequest(method: 'POST')
            ->icon(Icon::RefreshCw)
            ->askConfirmation('Générer une nouvelle version JSON pour cette fiche matière ?')
            ->setClassName('inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300');

        return $actions
            ->add($generateAction)
            ->alignment(ActionsAlignment::Right);
    }
}


