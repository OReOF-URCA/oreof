<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\YesNoBadgeColumn;
use App\Entity\Actualite;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;

#[AsDataTable(Actualite::class)]
final class ActualiteDataTable extends AbstractAppDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'datePublication', 'dir' => 'desc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('titre')->label('Titre'))
            ->add(
                TernaryFilter::new('affiche')
                    ->label('Publié ?')
                    ->values(true, false)
                    ->trueLabel('Oui')
                    ->falseLabel('Non')
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('titre', 'Titre'),
            DateColumn::new('datePublication', 'Date')
                ->setFormat('d/m/Y H:i'),
            YesNoBadgeColumn::new('affiche', 'Publié ?'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_actualite_show', static fn(Actualite $a): array => ['id' => $a->getId()]))
            ->add($this->createEditAction('app_actualite_edit', static fn(Actualite $a): array => ['id' => $a->getId()]))
            ->add($this->createDuplicateAction('app_actualite_duplicate', static fn(Actualite $a): array => ['id' => $a->getId()]))
            ->add($this->createDeleteAction(
                'app_actualite_delete',
                static fn(Actualite $a): array => ['id' => $a->getId()],
                csrfToken: static fn(Actualite $a): string => 'delete-actualite-' . $a->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
