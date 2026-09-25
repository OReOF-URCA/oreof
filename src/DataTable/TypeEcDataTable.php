<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\TypeDiplome;
use App\Entity\TypeEc;
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

#[AsDataTable(TypeEc::class)]
final class TypeEcDataTable extends AbstractAppDataTable
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
            TextColumn::new('libelle', 'Libellé du type d\'EC'),
            TemplateColumn::new('typeDiplomes', 'Type(s) de diplôme')
                ->setTemplate('config/type_ec/_column_type_diplomes.html.twig'),
            TemplateColumn::new('formation', 'Formation')
                ->setTemplate('config/type_ec/_column_formation.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_type_ec_show', static fn(TypeEc $t): array => ['id' => $t->getId()]))
            ->add($this->createEditAction('app_type_ec_edit', static fn(TypeEc $t): array => ['id' => $t->getId()]))
            ->add($this->createDuplicateAction('app_type_ec_duplicate', static fn(TypeEc $t): array => ['id' => $t->getId()]))
            ->add($this->createDeleteAction(
                'app_type_ec_delete',
                static fn(TypeEc $t): array => ['id' => $t->getId()],
                csrfToken: static fn(TypeEc $t): string => 'delete-typeec-' . $t->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
