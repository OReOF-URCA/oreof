<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Classes\DataUserSession;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(HistoriqueParcours::class)]
final class ConseilDocumentsDpeParcoursDataTable extends AbstractAppDataTable
{
    public function __construct(
        private readonly DataUserSession $dataUserSession,
    ) {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'created', 'dir' => 'desc']]);
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $rootAlias = $qb->getRootAliases()[0];

        $qb->andWhere(sprintf('%s.parcours IS NOT NULL', $rootAlias))
            ->innerJoin(sprintf('%s.parcours', $rootAlias), 'parcours')
            ->innerJoin('parcours.formation', 'formation')
            ->andWhere('formation.dpe = :campagneCollecte')
            ->setParameter('campagneCollecte', $this->dataUserSession->getCampagneCollecte());

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                ChoiceFilter::new('parcours.formation.composantePorteuse')
                    ->label('Composante')
                    ->entity(
                        class: Composante::class,
                        label: 'libelle',
                        orderBy: ['libelle' => 'ASC']
                    )
            )
            ->add(
                ChoiceFilter::new('parcours.formation')
                    ->label('Formation')
                    ->entity(
                        class: Formation::class,
                        label: 'display',
                        orderBy: ['sigle' => 'ASC']
                    )
            )
            ->add(
                ChoiceFilter::new('parcours')
                    ->label('Parcours')
                    ->entity(
                        class: Parcours::class,
                        label: 'display',
                        orderBy: ['libelle' => 'ASC']
                    )
            )
            ->add(TextFilter::new('etape')->label('Étape'))
            ->add(
                TernaryFilter::new('hasPv')
                    ->label('PV')
                    ->trueLabel('Avec PV')
                    ->falseLabel('Sans PV')
                    ->query(static function (QueryBuilder $qb, mixed $value, string $alias): void {
                        if ($value === '1' || $value === true || $value === 'true') {
                            $qb->andWhere(sprintf('%s.complements LIKE :hasPvKey', $alias))
                                ->setParameter('hasPvKey', '%"fichier"%');
                        } elseif ($value === '0' || $value === false || $value === 'false') {
                            $qb->andWhere(sprintf('(%s.complements IS NULL OR %s.complements NOT LIKE :hasPvKey)', $alias, $alias))
                                ->setParameter('hasPvKey', '%"fichier"%');
                        }
                    })
            )
            ->add(
                TernaryFilter::new('hasJustification')
                    ->label('Justificatif')
                    ->trueLabel('Avec justificatif')
                    ->falseLabel('Sans justificatif')
                    ->query(static function (QueryBuilder $qb, mixed $value, string $alias): void {
                        if ($value === '1' || $value === true || $value === 'true') {
                            $qb->andWhere(sprintf('%s.complements LIKE :hasJustificationKey', $alias))
                                ->setParameter('hasJustificationKey', '%"fichier_note"%');
                        } elseif ($value === '0' || $value === false || $value === 'false') {
                            $qb->andWhere(sprintf('(%s.complements IS NULL OR %s.complements NOT LIKE :hasJustificationKey)', $alias, $alias))
                                ->setParameter('hasJustificationKey', '%"fichier_note"%');
                        }
                    })
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('composante', 'Composante')
                ->setField('parcours.formation.composantePorteuse.libelle')
                ->setSearchField('parcours.formation.composantePorteuse.libelle')
                ->setOrderable(true)
                ->setSearchable(true),
            TemplateColumn::new('formation', 'Formation')
                ->setField('parcours.formation.libelle')
                ->setSearchField('parcours.formation.libelle')
                ->setTemplate('conseils/documents/_datatable_formation.html.twig'),
            TemplateColumn::new('parcours', 'Parcours')
                ->setField('parcours.libelle')
                ->setSearchField('parcours.libelle')
                ->setTemplate('conseils/documents/_datatable_parcours.html.twig'),
            TextColumn::new('etape', 'Étape')
                ->setOrderable(true)
                ->setSearchable(true),
            DateColumn::new('created', 'Date de création')
                ->setFormat('d/m/Y H:i')
                ->setOrderable(true),
            TemplateColumn::new('pv', 'PV')
                ->setTemplate('conseils/documents/_datatable_pv.html.twig'),
            TemplateColumn::new('justification', 'Justificatif')
                ->setTemplate('conseils/documents/_datatable_justification.html.twig'),
        ];
    }
}
