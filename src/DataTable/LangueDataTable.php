<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Langue;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;


#[AsDataTable(Langue::class)]
final class LangueDataTable extends AbstractAppDataTable
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
            ->add(TextFilter::new('codeIso')->label('Code ISO'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé de la langue'),
            TextColumn::new('codeIso', 'Code ISO'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_langue_show', static fn(Langue $l): array => ['id' => $l->getId()]))
            ->add($this->createEditAction('app_langue_edit', static fn(Langue $l): array => ['id' => $l->getId()]))
            ->add($this->createDuplicateAction('app_langue_duplicate', static fn(Langue $l): array => ['id' => $l->getId()]))
            ->add($this->createDeleteAction(
                'app_langue_delete',
                static fn(Langue $l): array => ['id' => $l->getId()],
                csrfToken: static fn(Langue $l): string => 'delete-langue-' . $l->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
