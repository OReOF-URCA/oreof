<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\YesNoBadgeColumn;
use App\Entity\NatureUeEc;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(NatureUeEc::class)]
final class NatureUeEcDataTable extends AbstractAppDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'libelle', 'dir' => 'asc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('libelle')->label('Libellé'))
            ->add(
                TernaryFilter::new('choix')
                    ->label('Choix ?')
                    ->values(true, false)
                    ->trueLabel('Oui')
                    ->falseLabel('Non')
            )
            ->add(
                TernaryFilter::new('libre')
                    ->label('Libre ?')
                    ->values(true, false)
                    ->trueLabel('Oui')
                    ->falseLabel('Non')
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé'),
            YesNoBadgeColumn::new('choix', 'Choix ?'),
            YesNoBadgeColumn::new('libre', 'Libre ?'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_nature_ue_ec_show', static fn(NatureUeEc $n): array => ['id' => $n->getId()]))
            ->add($this->createEditAction('app_nature_ue_ec_edit', static fn(NatureUeEc $n): array => ['id' => $n->getId()]))
            ->add($this->createDuplicateAction('app_nature_ue_ec_duplicate', static fn(NatureUeEc $n): array => ['id' => $n->getId()]))
            ->add($this->createDeleteAction(
                'app_nature_ue_ec_delete',
                static fn(NatureUeEc $n): array => ['id' => $n->getId()],
                csrfToken: static fn(NatureUeEc $n): string => 'delete-natureueec-' . $n->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
