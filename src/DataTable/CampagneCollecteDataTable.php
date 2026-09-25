<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\CampagneCollecte;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TemplateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(CampagneCollecte::class)]
final class CampagneCollecteDataTable extends AbstractAppDataTable
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
            ->add(TextFilter::new('anneeUniversitaire.libelle')->label('Année Universitaire'));
    }

    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('libelle', 'Libellé de la campagne de collecte'),
            TextColumn::new('anneeUniversitaire', 'Année Universitaire')->setSearchField('anneeUniversitaire.libelle'),
            TemplateColumn::new('timelineDates', 'Dates')
                ->setTemplate('config/campagne_collecte/_datatable_timeline.html.twig'),
            TemplateColumn::new('defaut', 'Collecte DPE active ?')
                ->setTemplate('config/campagne_collecte/_datatable_defaut.html.twig'),
            TemplateColumn::new('enablePublication', 'Configuration de la publication')
                ->setTemplate('config/campagne_collecte/_datatable_publication.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $configurePublicationAction = Action::new('configure_publication', 'Paramétrer')
            ->linkToRoute('app_campagne_collecte_configure_publication', static fn(CampagneCollecte $c): array => ['id' => $c->getId()])
            ->htmlAttributes([
                'data-turbo' => 'true',
                'data-action' => 'click->modalturbo#open',
                'title' => 'Paramétrer les options de publication',
            ])
            ->icon(Icon::Wrench)
            ->setClassName('inline-flex items-center gap-1 rounded-md border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-100');

        return $actions
            ->add($this->createShowAction('app_campagne_collecte_show', static fn(CampagneCollecte $c): array => ['id' => $c->getId()]))
            ->add($this->createEditAction('app_campagne_collecte_edit', static fn(CampagneCollecte $c): array => ['id' => $c->getId()]))
            ->add($this->createDuplicateAction('app_campagne_collecte_duplicate', static fn(CampagneCollecte $c): array => ['id' => $c->getId()]))
            ->add($configurePublicationAction)
            ->add($this->createDeleteAction(
                'app_campagne_collecte_delete',
                static fn(CampagneCollecte $c): array => ['id' => $c->getId()],
                csrfToken: static fn(CampagneCollecte $c): string => 'delete-campagnecollecte-' . $c->getId()
            ))
            
            ->alignment(ActionsAlignment::Right);
    }
}
