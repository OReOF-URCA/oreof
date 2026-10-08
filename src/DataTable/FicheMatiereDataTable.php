<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Classes\DataUserSession;
use App\DataTable\Column\SearchableTemplateColumn;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDataTable(FicheMatiere::class)]
class FicheMatiereDataTable extends AbstractAppDataTable
{
    public function __construct(
        protected readonly Security $security,
        protected readonly DataUserSession $dataUserSession,
    ) {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return parent::configureDataTable($table)
            ->pageLength(50)
            ->order([['name' => 'libelle', 'dir' => 'asc']]);
    }

    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $rootAlias = $qb->getRootAliases()[0];

        $qb->andWhere(sprintf('%s.campagneCollecte = :campagne', $rootAlias))
            ->andWhere(sprintf('(%s.horsDiplome = 0 OR %s.horsDiplome IS NULL)', $rootAlias, $rootAlias))
            ->setParameter('campagne', $this->dataUserSession->getCampagneCollecte());

        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            // Admin : pas de restriction
        } elseif ($user instanceof User && $user->getComposanteResponsableDpe()->count() > 0) {
            // Responsable de DPE : voir les fiches de sa composante
            $qb->innerJoin(sprintf('%s.parcours', $rootAlias), 'dpe_parcours')
                ->innerJoin('dpe_parcours.formation', 'dpe_formation')
                ->innerJoin('dpe_formation.composantePorteuse', 'dpe_composante')
                ->andWhere('dpe_composante IN (:dpeComposantes)')
                ->setParameter('dpeComposantes', $user->getComposanteResponsableDpe());
        } else {
            // Responsable pédagogique : voir ses propres fiches
            $qb->andWhere(sprintf('%s.responsableFicheMatiere = :responsable', $rootAlias))
                ->setParameter('responsable', $user);
        }

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('libelle')->label('Fiche matière'))
            ->add(
                ChoiceFilter::new('parcours')
                    ->label('Parcours')
                    ->entity(
                        class: Parcours::class,
                        label: 'libelle',
                        orderBy: ['libelle' => 'ASC']
                    )
            )
            ->add(
                ChoiceFilter::new('responsableFicheMatiere')
                    ->label('Référent')
                    ->entity(
                        class: User::class,
                        label: 'display',
                        orderBy: ['nom' => 'ASC', 'prenom' => 'ASC']
                    )
            )
            ->add(
                TernaryFilter::new('utilise')
                    ->label('Utilisé ?')
                    ->trueLabel('Oui')
                    ->falseLabel('Non')
                    ->query(static function (QueryBuilder $qb, mixed $value): void {
                        $rootAlias = $qb->getRootAliases()[0];
                        if ($value === '1' || $value === true || $value === 'true') {
                            $qb->andWhere(sprintf('(SELECT COUNT(ec.id) FROM %s ec WHERE ec.ficheMatiere = %s) > 0', ElementConstitutif::class, $rootAlias));
                        } elseif ($value === '0' || $value === false || $value === 'false') {
                            $qb->andWhere(sprintf('(SELECT COUNT(ec.id) FROM %s ec WHERE ec.ficheMatiere = %s) = 0', ElementConstitutif::class, $rootAlias));
                        }
                    })
            )
            ->add(
                ChoiceFilter::new('remplissage')
                    ->label('Remplissage')
                    ->options([
                        'Non complété' => '0',
                        'Complet' => '100',
                    ])
                    ->query(static function (QueryBuilder $qb, mixed $value): void {
                        $rootAlias = $qb->getRootAliases()[0];
                        if ($value === '0') {
                            $qb->andWhere(sprintf('JSON_EXTRACT(%s.remplissage, \'$.pourcentage\') = 0 OR %s.remplissage IS NULL', $rootAlias, $rootAlias));
                        } elseif ($value === '100') {
                            $qb->andWhere(sprintf('JSON_EXTRACT(%s.remplissage, \'$.pourcentage\') = 100', $rootAlias));
                        }
                    })
            );
    }

    public function configureColumns(): iterable
    {
        return [
            SearchableTemplateColumn::new('libelle', 'Fiche matière')
                ->setField('libelle')
                ->setSearchField('libelle')
                ->setOrderable(true)
                ->setSearchable(true)
                ->setTemplate('structure/fiche_matiere/_column_libelle.html.twig'),
            TextColumn::new('parcours', 'Parcours')
                ->setField('parcours.libelle')
                ->setSearchField('parcours.libelle'),
            SearchableTemplateColumn::new('etatFiche', 'État')
                ->setTemplate('structure/fiche_matiere/_column_etat.html.twig'),
            SearchableTemplateColumn::new('utilise', 'Utilisé ?')
                ->setTemplate('structure/fiche_matiere/_column_utilise.html.twig'),
            SearchableTemplateColumn::new('responsableFicheMatiere', 'Référent')
                ->setField('responsableFicheMatiere.nom')
                ->setSearchField('responsableFicheMatiere.nom')
                ->setOrderable(true)
                ->setSearchable(true)
                ->setTemplate('structure/fiche_matiere/_column_referent.html.twig'),
            SearchableTemplateColumn::new('remplissage', 'Remplissage')
                ->setTemplate('structure/fiche_matiere/_column_remplissage.html.twig'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $voirAction = Action::new('show', 'Voir', self::BTN_SHOW_CLASS)
            ->linkToRoute('fiche_matiere_v2_voir', static fn(FicheMatiere $fm): array => ['slug' => $fm->getSlug()])
            ->icon(Icon::Eye)
            ->htmlAttributes([
                'target' => '_blank',
                'data-turbo-prefetch' => 'false',
                'data-turbo-preload' => 'false',
            ]);

        $editAction = Action::edit('Modifier', self::BTN_EDIT_CLASS)
            ->linkToRoute('fiche_matiere_v2_modifier', static fn(FicheMatiere $fm): array => ['slug' => $fm->getSlug()])
            ->icon(Icon::Pencil)
            ->htmlAttributes([
                'target' => '_blank',
                'data-turbo-prefetch' => 'false',
                'data-turbo-preload' => 'false',
            ]);

        return $actions
            ->add($voirAction)
            ->add($editAction)
            ->add($this->createDuplicateAction('app_fiche_matiere_dupliquer', static fn(FicheMatiere $fm): array => ['slug' => $fm->getSlug()]))
            ->add($this->createDeleteAction(
                'app_fiche_matiere_delete',
                static fn(FicheMatiere $fm): array => ['slug' => $fm->getSlug()],
                confirm: "Cette action supprimera définitivement la fiche matière si elle n'est pas rattachée à un parcours.",
                csrfToken: static fn(FicheMatiere $fm): string => 'delete' . $fm->getId()
            ))
            ->alignment(ActionsAlignment::Right);
    }
}
