<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\ReferentielValeur;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Model\Actions;

#[AsDataTable(ReferentielValeur::class)]
final class ReferentielValeurDataTable extends AbstractAppDataTable
{
    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('code', 'Code'),
            TextColumn::new('libelle', 'Libellé'),
            NumberColumn::new('ordre', 'Ordre'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add($this->createEditAction('admin_referentiel_edit_value', static fn (ReferentielValeur $v): array => ['id' => $v->getId()]));
    }
}
