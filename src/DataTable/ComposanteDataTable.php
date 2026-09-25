<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Composante;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Composante::class)]
final class ComposanteDataTable extends AbstractAppDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->order([['name' => 'libelle', 'dir' => 'asc']]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('libelle')->label('Nom'))
            ->add(TextFilter::new('sigle')->label('Sigle'))
            ->add(TextFilter::new('codeComposante')->label('Code compo.'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Nom de la composante'),
            TextColumn::new('sigle', 'Sigle'),
            TextColumn::new('codeComposante', 'Code compo.'),
            TextColumn::new('codeApogee', 'Code CIP (Apogée)'),
            TextColumn::new('directeur', 'Directeur')->setSearchField('directeur.display'),
            TextColumn::new('responsableDpe', 'Responsable DPE')->setSearchField('responsableDpe.display'),
        ];
    } 

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_composante_show', static fn(Composante $c): array => ['id' => $c->getId()]))
            ->add($this->createEditAction('app_composante_edit', static fn(Composante $c): array => ['id' => $c->getId()]))
            ->add($this->createDeleteAction(
                'app_composante_delete',
                static fn(Composante $c): array => ['id' => $c->getId()],
                csrfToken: static fn(Composante $c): string => 'delete-composante-' . $c->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
