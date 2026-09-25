<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Etablissement;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Etablissement::class)]
final class EtablissementDataTable extends AbstractAppDataTable
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
            TextColumn::new('libelle', 'Libellé de l\'établissement'),
            TextColumn::new('adresse', 'Adresse')
                ->html(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_etablissement_show', static fn(Etablissement $e): array => ['id' => $e->getId()]))
            ->add($this->createEditAction('app_etablissement_edit', static fn(Etablissement $e): array => ['id' => $e->getId()], modal: false))
            ->add($this->createDeleteAction(
                'app_etablissement_delete',
                static fn(Etablissement $e): array => ['id' => $e->getId()],
                csrfToken: static fn(Etablissement $e): string => 'delete-etablissement-' . $e->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
