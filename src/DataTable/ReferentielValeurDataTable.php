<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\YesNoBadgeColumn;
use App\Entity\ReferentielValeur;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDataTable(ReferentielValeur::class)]
final class ReferentielValeurDataTable extends AbstractAppDataTable
{
    public const SESSION_KEY = 'admin_referentiel_valeurs_id';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'ordre', 'dir' => 'asc']]);
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $id = $this->requestStack->getCurrentRequest()?->getSession()->get(self::SESSION_KEY);
        $alias = $qb->getRootAliases()[0];

        // Never return values from another referential when no context is selected.
        $qb->andWhere(sprintf('%s.referentiel = :selectedReferentiel', $alias))
            ->setParameter('selectedReferentiel', is_numeric($id) ? (int) $id : -1);

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('code')->label('Code'))
            ->add(TextFilter::new('libelle')->label('Libellé'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('code', 'Code'),
            TextColumn::new('libelle', 'Libellé'),
            TextColumn::new('legacyValue', 'Valeur historique'),
            NumberColumn::new('ordre', 'Ordre'),
            new YesNoBadgeColumn('actif', 'Actif'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createEditAction(
                'admin_referentiel_edit_value',
                static fn (ReferentielValeur $v): array => ['id' => $v->getId()]
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
