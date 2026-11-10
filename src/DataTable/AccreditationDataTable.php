<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\YesNoBadgeColumn;
use App\Entity\Accreditation;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Accreditation::class)]
final class AccreditationDataTable extends AbstractAppDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'anneeDebut', 'dir' => 'desc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('libelle')->label('Libellé'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Période d\'accréditation'),
            NumberColumn::new('anneeDebut', 'Année début'),
            NumberColumn::new('anneeFin', 'Année fin'),
            NumberColumn::new('nbMentions', 'Nombre de mentions')
                ->setOrderable(false)
                ->setSearchable(false),
            YesNoBadgeColumn::new('actif', 'Active ?'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createEditAction('app_accreditation_edit', static fn (Accreditation $a): array => ['id' => $a->getId()]))
            ->add($this->createDeleteAction(
                'app_accreditation_delete',
                static fn (Accreditation $a): array => ['id' => $a->getId()],
                csrfToken: static fn (Accreditation $a): string => 'delete-accreditation-' . $a->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
