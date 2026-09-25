<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\YesNoBadgeColumn;
use App\Entity\PlateformeAdmission;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(PlateformeAdmission::class)]
final class PlateformeAdmissionDataTable extends AbstractAppDataTable
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
            ->add(TextFilter::new('code')->label('Code/Sigle'))
            ->add(
                TernaryFilter::new('active')
                    ->label('Active ?')
                    ->values(true, false)
                    ->trueLabel('Oui')
                    ->falseLabel('Non')
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé de la plateforme d\'admission'),
            TextColumn::new('code', 'Code/Sigle'),
            YesNoBadgeColumn::new('active', 'Active ?'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_plateforme_adminission_show', static fn(PlateformeAdmission $p): array => ['id' => $p->getId()]))
            ->add($this->createEditAction('app_plateforme_adminission_edit', static fn(PlateformeAdmission $p): array => ['id' => $p->getId()], modal: false))
            ->add($this->createDuplicateAction('app_plateforme_adminission_duplicate', static fn(PlateformeAdmission $p): array => ['id' => $p->getId()]))
            ->add($this->createDeleteAction(
                'app_plateforme_adminission_delete',
                static fn(PlateformeAdmission $p): array => ['id' => $p->getId()],
                csrfToken: static fn(PlateformeAdmission $p): string => 'delete-plateformeadmission-' . $p->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
