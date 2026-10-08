<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Classes\DataUserSession;
use App\DataTable\Column\SearchableTemplateColumn;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\HistoriqueFormation;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(HistoriqueFormation::class)]
final class ConseilDocumentsDpeFormationDataTable extends AbstractAppDataTable
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

        $qb->andWhere(sprintf('(%s.dpeFormation IS NOT NULL OR %s.changeRf IS NULL) AND (%s.etape NOT LIKE :changeRfPrefix OR %s.etape IS NULL)', $rootAlias, $rootAlias, $rootAlias, $rootAlias))
            ->setParameter('changeRfPrefix', 'changeRf.%')
            ->leftJoin(sprintf('%s.formation', $rootAlias), 'formation')
            ->leftJoin(sprintf('%s.dpeFormation', $rootAlias), 'dpeFormation')
            ->andWhere('(formation.dpe = :campagneCollecte OR dpeFormation.campagneCollecte = :campagneCollecte)')
            ->setParameter('campagneCollecte', $this->dataUserSession->getCampagneCollecte());

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
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
                ChoiceFilter::new('formation')
                    ->label('Formation')
                    ->entity(
                        class: Formation::class,
                        label: 'display',
                        orderBy: ['sigle' => 'ASC']
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
                            $qb->andWhere(sprintf('(%s.complements LIKE :hasPvKey OR %s.documentPv IS NOT NULL)', $alias, $alias))
                                ->setParameter('hasPvKey', '%"fichier"%');
                        } elseif ($value === '0' || $value === false || $value === 'false') {
                            $qb->andWhere(sprintf('((%s.complements IS NULL OR %s.complements NOT LIKE :hasPvKey) AND %s.documentPv IS NULL)', $alias, $alias, $alias))
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
                            $qb->andWhere(sprintf('(%s.complements LIKE :hasJustificationKey OR %s.documentNote IS NOT NULL)', $alias, $alias))
                                ->setParameter('hasJustificationKey', '%"fichier_note"%');
                        } elseif ($value === '0' || $value === false || $value === 'false') {
                            $qb->andWhere(sprintf('((%s.complements IS NULL OR %s.complements NOT LIKE :hasJustificationKey) AND %s.documentNote IS NULL)', $alias, $alias, $alias))
                                ->setParameter('hasJustificationKey', '%"fichier_note"%');
                        }
                    })
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('composante', 'Composante')
                ->setField('formation.composantePorteuse.libelle')
                ->setSearchField('formation.composantePorteuse.libelle')
                ->setOrderable(true)
                ->setSearchable(true),
            SearchableTemplateColumn::new('formation', 'Formation')
                ->setField('formation.libelle')
                ->setSearchField('formation.libelle')
                ->setTemplate('conseils/documents/_datatable_formation.html.twig'),
            TextColumn::new('etape', 'Étape')
                ->setOrderable(true)
                ->setSearchable(true),
            DateColumn::new('created', 'Date de création')
                ->setFormat('d/m/Y H:i')
                ->setOrderable(true),
            SearchableTemplateColumn::new('pv', 'PV')
                ->setTemplate('conseils/documents/_datatable_pv.html.twig'),
            SearchableTemplateColumn::new('justification', 'Justificatif')
                ->setTemplate('conseils/documents/_datatable_justification.html.twig'),
        ];
    }
}
