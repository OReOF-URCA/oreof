<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\YesNoBadgeColumn;
use App\Entity\TypeEpreuve;
use App\Entity\TypeDiplome;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(TypeEpreuve::class)]
final class TypeEpreuveDataTable extends AbstractAppDataTable
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
            ->add(TextFilter::new('sigle')->label('Code'))
            ->add(
                TernaryFilter::new('hasDuree')
                    ->label('Avec durée ?')
                    ->values(true, false)
                    ->trueLabel('Oui')
                    ->falseLabel('Non')
            )
            ->add(
                TernaryFilter::new('hasJustification')
                    ->label('Avec justification ?')
                    ->values(true, false)
                    ->trueLabel('Oui')
                    ->falseLabel('Non')
            )
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
            TextColumn::new('libelle', 'Libellé du type d\'épreuve'),
            TextColumn::new('sigle', 'Code'),
            TemplateColumn::new('typeDiplomes', 'Type(s) de diplôme')
                ->setTemplate('config/type_epreuve/_column_type_diplomes.html.twig'),
            YesNoBadgeColumn::new('hasDuree', 'Avec durée ?'),
            YesNoBadgeColumn::new('hasJustification', 'Avec justification ?'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_type_epreuve_show', static fn(TypeEpreuve $t): array => ['id' => $t->getId()]))
            ->add($this->createEditAction('app_type_epreuve_edit', static fn(TypeEpreuve $t): array => ['id' => $t->getId()]))
            ->add($this->createDuplicateAction('app_type_epreuve_duplicate', static fn(TypeEpreuve $t): array => ['id' => $t->getId()]))
            ->add($this->createDeleteAction(
                'app_type_epreuve_delete',
                static fn(TypeEpreuve $t): array => ['id' => $t->getId()],
                csrfToken: static fn(TypeEpreuve $t): string => 'delete-typeepreuve-' . $t->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
