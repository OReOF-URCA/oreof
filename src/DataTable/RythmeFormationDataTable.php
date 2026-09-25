<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\RythmeFormation;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(RythmeFormation::class)]
final class RythmeFormationDataTable extends AbstractAppDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'libelle', 'dir' => 'asc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('libelle')->label('Libellé'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé du rythme de formation'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_rythme_formation_show', static fn(RythmeFormation $r): array => ['id' => $r->getId()]))
            ->add($this->createEditAction('app_rythme_formation_edit', static fn(RythmeFormation $r): array => ['id' => $r->getId()]))
            ->add($this->createDuplicateAction('app_rythme_formation_duplicate', static fn(RythmeFormation $r): array => ['id' => $r->getId()]))
            ->add($this->createDeleteAction(
                'app_rythme_formation_delete',
                static fn(RythmeFormation $r): array => ['id' => $r->getId()],
                csrfToken: static fn(RythmeFormation $r): string => 'delete-rythmeformation-' . $r->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
