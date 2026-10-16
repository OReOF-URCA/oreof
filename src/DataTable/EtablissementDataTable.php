<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Etablissement;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\Action;
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
        $apiKeysAction = Action::new('api_keys', 'Clés API')
            ->linkToRoute('app_etablissement_api_keys', static fn(Etablissement $e): array => ['id' => $e->getId()])
            ->icon(Icon::Key)
            ->htmlAttributes([
                'title' => 'Gérer les clés d\'API tierces',
                'data-turbo-prefetch' => 'false',
                'data-turbo-preload' => 'false',
            ])
            ->setClassName('inline-flex items-center gap-1 rounded-md border border-purple-300 bg-purple-50 px-2.5 py-1 text-xs font-semibold text-purple-700 transition hover:bg-purple-100 dark:border-purple-500 dark:bg-purple-900 dark:text-purple-300 dark:hover:border-purple-300 dark:hover:text-purple-100');

        return $actions
            ->add($this->createShowAction('app_etablissement_show', static fn(Etablissement $e): array => ['id' => $e->getId()]))
            ->add($this->createEditAction('app_etablissement_edit', static fn(Etablissement $e): array => ['id' => $e->getId()], modal: false))
            ->add($apiKeysAction)
            ->add($this->createDeleteAction(
                'app_etablissement_delete',
                static fn(Etablissement $e): array => ['id' => $e->getId()],
                csrfToken: static fn(Etablissement $e): string => 'delete-etablissement-' . $e->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
