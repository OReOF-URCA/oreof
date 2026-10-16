<?php

declare(strict_types=1);

namespace App\DataTable;

use App\DataTable\Column\SearchableTemplateColumn;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\Filters;

#[AsDataTable(FicheMatiere::class)]
final class FicheMatiereHorsDiplomeDataTable extends FicheMatiereDataTable
{
    protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $rootAlias = $qb->getRootAliases()[0];

        $qb->andWhere(sprintf('%s.campagneCollecte = :campagne', $rootAlias))
            ->andWhere(sprintf('%s.horsDiplome = 1', $rootAlias))
            ->setParameter('campagne', $this->dataUserSession->getCampagneCollecte());

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('libelle')->label('Fiche matière'))
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
}
