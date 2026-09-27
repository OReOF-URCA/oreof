<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\FormationVersioning;
use App\Entity\TypeDiplome;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Formation::class)]
final class VersioningFormationDataTable extends AbstractAppDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'id', 'dir' => 'desc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('sigle')->label('Sigle / Libellé'))
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
                            $qb->leftJoin(sprintf('%s.dpeFormations', $rootAlias), 'filter_df')
                                ->andWhere('filter_df.campagneCollecte = :filterCampagne OR ' . sprintf('%s.dpe = :filterCampagne', $rootAlias))
                                ->setParameter('filterCampagne', $value);
                        }
                    })
            )
            ->add(
                ChoiceFilter::new('composantePorteuse')
                    ->label('Composante')
                    ->entity(
                        class: Composante::class,
                        label: 'libelle',
                        orderBy: ['libelle' => 'ASC']
                    )
            )
            ->add(
                ChoiceFilter::new('typeDiplome')
                    ->label('Type de diplôme')
                    ->entity(
                        class: TypeDiplome::class,
                        label: 'libelle_court',
                        orderBy: ['libelle_court' => 'ASC']
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
                            $qb->andWhere(sprintf('(SELECT COUNT(fv.id) FROM %s fv WHERE fv.formation = %s) = 0', FormationVersioning::class, $rootAlias));
                        } elseif ($value === 'has_version') {
                            $qb->andWhere(sprintf('(SELECT COUNT(fv.id) FROM %s fv WHERE fv.formation = %s) > 0', FormationVersioning::class, $rootAlias));
                        }
                    })
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('id', 'ID')->setOrderable(true),
            TemplateColumn::new('libelle', 'Formation')
                ->setField('sigle')
                ->setSearchField('sigle')
                ->setOrderable(true)
                ->setTemplate('admin/versioning/column/_formation_libelle.html.twig'),
            TemplateColumn::new('campagne', 'Campagne')
                ->setTemplate('admin/versioning/column/_formation_campagne.html.twig'),
            TemplateColumn::new('composante', 'Composante & Diplôme')
                ->setTemplate('admin/versioning/column/_formation_composante.html.twig'),
            TemplateColumn::new('status', 'Statut Versioning')
                ->setTemplate('admin/versioning/column/_formation_status.html.twig'),
            TemplateColumn::new('derniereVersion', 'Dernière version')
                ->setTemplate('admin/versioning/column/_formation_derniere_version.html.twig'),
            TemplateColumn::new('fichiers', 'Fichiers JSON')
                ->setTemplate('admin/versioning/column/_formation_files.html.twig'),
        ];
    }
}
