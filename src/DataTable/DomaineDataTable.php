<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Domaine;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Domaine::class)]
final class DomaineDataTable extends AbstractAppDataTable
{
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('sigle')->label('Sigle'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé de la mention'),
            TextColumn::new('sigle', 'Sigle'),
            NumberColumn::new('nbMentions', 'Nombre de mentions')
                ->setOrderable(false)
                ->setSearchable(false),
            TextColumn::new('codeApogee', 'Code Apogée'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_domaine_show', static fn (Domaine $d): array => ['id' => $d->getId()]))
            ->add($this->createEditAction('app_domaine_edit', static fn (Domaine $d): array => ['id' => $d->getId()]))
            ->add($this->createDuplicateAction('app_domaine_duplicate', static fn (Domaine $d): array => ['id' => $d->getId()]));
    }
}
