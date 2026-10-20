<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Referentiel;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\Actions;

#[AsDataTable(Referentiel::class)]
final class ReferentielDataTable extends AbstractAppDataTable
{
    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Référentiel'),
            TextColumn::new('code', 'Code'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('admin_referentiel_valeurs', static fn (Referentiel $r): array => ['id' => $r->getId()], 'Valeurs', false))
            ->add($this->createEditAction('admin_referentiel_edit', static fn (Referentiel $r): array => ['id' => $r->getId()]));
    }
}
