<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\YesNoBadgeColumn;
use App\Entity\Mention;
use App\Entity\TypeDiplome;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(Mention::class)]
final class MentionDataTable extends AbstractAppDataTable
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
            ->add(TextFilter::new('sigle')->label('Sigle'))
            ->add(TextFilter::new('codeApogee')->label('Code Apogée'))
            ->add(
                ChoiceFilter::new('typeDiplome')
                    ->label('Type de diplôme')
                    ->entity(TypeDiplome::class, 'libelle')
            );
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé de la mention'),
            TextColumn::new('sigle', 'Sigle'),
            TextColumn::new('codeApogee', 'Code Apogée'),
            TextColumn::new('typeDiplome', 'Type de diplôme')
                ->setSearchField('typeDiplome.libelle'),
            TemplateColumn::new('domaines', 'Domaine(s)')
                ->setTemplate('config/mention/_column_domaines.html.twig'),
            YesNoBadgeColumn::new('utilise', 'Utilisé ?'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add($this->createShowAction('app_mention_show', static fn(Mention $m): array => ['id' => $m->getId()]))
            ->add($this->createEditAction('app_mention_edit', static fn(Mention $m): array => ['id' => $m->getId()]))
            ->add($this->createDuplicateAction('app_mention_duplicate', static fn(Mention $m): array => ['id' => $m->getId()]))
            ->add($this->createDeleteAction(
                'app_mention_delete',
                static fn(Mention $m): array => ['id' => $m->getId()],
                csrfToken: static fn(Mention $m): string => 'delete-mention-' . $m->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
