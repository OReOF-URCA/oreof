<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\YesNoBadgeColumn;
use App\Entity\TypeDiplome;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(TypeDiplome::class)]
final class TypeDiplomeDataTable extends AbstractAppDataTable
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
            ->add(TextFilter::new('libelleCourt')->label('Sigle'))
            ->add(TextFilter::new('codeApogee')->label('Code Apogée'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé du type de diplôme'),
            TextColumn::new('libelleCourt', 'Sigle'),
            TextColumn::new('codeApogee', 'Code Apogée'),
            YesNoBadgeColumn::new('hasMemoire', 'Mémoire ?'),
            YesNoBadgeColumn::new('hasStage', 'Stage ?'),
            YesNoBadgeColumn::new('hasProjet', 'Projet ?'),
            YesNoBadgeColumn::new('hasSituationPro', 'Situation Pro. ?'),
            YesNoBadgeColumn::new('ectsObligatoireSurEc', 'ECTS Obli. ?'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_type_diplome_show', static fn(TypeDiplome $t): array => ['id' => $t->getId()]))
            ->add($this->createEditAction('app_type_diplome_edit', static fn(TypeDiplome $t): array => ['id' => $t->getId()], modal: false))
            ->add($this->createDuplicateAction('app_type_diplome_duplicate', static fn(TypeDiplome $t): array => ['id' => $t->getId()]))
            ->add($this->createDeleteAction(
                'app_type_diplome_delete',
                static fn(TypeDiplome $t): array => ['id' => $t->getId()],
                csrfToken: static fn(TypeDiplome $t): string => 'delete-typediplome-' . $t->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
