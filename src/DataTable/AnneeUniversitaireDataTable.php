<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\AnneeUniversitaire;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(AnneeUniversitaire::class)]
final class AnneeUniversitaireDataTable extends AbstractAppDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'annee', 'dir' => 'desc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('libelle')->label('Libellé'))
            ->add(TextFilter::new('annee')->label('Année'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé'),
            NumberColumn::new('annee', 'Année'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_annee_universitaire_show', static fn(AnneeUniversitaire $a): array => ['id' => $a->getId()]))
            ->add($this->createEditAction('app_annee_universitaire_edit', static fn(AnneeUniversitaire $a): array => ['id' => $a->getId()]))
            ->add($this->createDuplicateAction('app_annee_universitaire_duplicate', static fn(AnneeUniversitaire $a): array => ['id' => $a->getId()]))
            ->add($this->createDeleteAction(
                'app_annee_universitaire_delete',
                static fn(AnneeUniversitaire $a): array => ['id' => $a->getId()],
                csrfToken: static fn(AnneeUniversitaire $a): string => 'delete-anneeuniversitaire-' . $a->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
