<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Ville;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;

#[AsDataTable(Ville::class)]
final class VilleDataTable extends AbstractAppDataTable
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
            ->add(TextFilter::new('codeApogee')->label('Code Apogée'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé de la ville'),
            TextColumn::new('codeApogee', 'Code Apogée'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_ville_show', static fn(Ville $v): array => ['id' => $v->getId()]))
            ->add($this->createEditAction('app_ville_edit', static fn(Ville $v): array => ['id' => $v->getId()]))
            ->add($this->createDuplicateAction('app_ville_duplicate', static fn(Ville $v): array => ['id' => $v->getId()]))
            ->add($this->createDeleteAction(
                'app_ville_delete',
                static fn(Ville $v): array => ['id' => $v->getId()],
                csrfToken: static fn(Ville $v): string => 'delete-ville-' . $v->getId()
            ))            
            ->alignment(ActionsAlignment::Right);

    }
}
