<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\TypeDiplome;
use App\Entity\TypeUe;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(TypeUe::class)]
final class TypeUeDataTable extends AbstractAppDataTable
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
            ->add(
                ChoiceFilter::new('typeDiplomes')
                    ->label('Type de diplôme')
                    ->entity(TypeDiplome::class, 'libelle')
                    ->query(static function (QueryBuilder $qb, mixed $value): void {
                        if ($value) {
                            $qb->innerJoin('e.typeDiplomes', 'filter_td')
                                ->andWhere('filter_td.id = :filter_td_id')
                                ->setParameter('filter_td_id', $value);
                        }
                    })
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé du type d\'UE'),
            TemplateColumn::new('typeDiplomes', 'Type(s) de diplôme')
                ->setTemplate('config/type_ue/_column_type_diplomes.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_type_ue_show', static fn(TypeUe $t): array => ['id' => $t->getId()]))
            ->add($this->createEditAction('app_type_ue_edit', static fn(TypeUe $t): array => ['id' => $t->getId()]))
            ->add($this->createDuplicateAction('app_type_ue_duplicate', static fn(TypeUe $t): array => ['id' => $t->getId()]))
            ->add($this->createDeleteAction(
                'app_type_ue_delete',
                static fn(TypeUe $t): array => ['id' => $t->getId()],
                csrfToken: static fn(TypeUe $t): string => 'delete-typeue-' . $t->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
