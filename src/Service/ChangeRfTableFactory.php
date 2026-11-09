<?php

namespace App\Service;

use App\Entity\CampagneCollecte;
use App\Entity\ChangeRf;
use App\Entity\Composante;
use App\Enums\TypeRfEnum;

/**
 * Construit la table des demandes de changement RF / Co-RF pour une étape de validation.
 * Partagée par les contrôleurs de validation (admin et composante).
 */
class ChangeRfTableFactory
{
    public function __construct(private readonly DataTableBuilder $builder)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(CampagneCollecte $campagneCollecte, string $etat, ?Composante $composante): array
    {
        $this->builder
            ->addBaseWhere('IDENTITY(e.campagneCollecte) = :campagneCollecteId')
            ->addBaseParameter('campagneCollecteId', $campagneCollecte->getId())
            ->addBaseWhere('JSON_CONTAINS(e.etatDemande, :etatDpe) = 1')
            ->addBaseParameter('etatDpe', json_encode([$etat => 1]));

        if ($composante !== null) {
            $this->builder
                ->addBaseWhere('IDENTITY(formation_0.composantePorteuse) = :composanteId')
                ->addBaseParameter('composanteId', $composante->getId());
        }

        return $this->builder
            ->setEntity(ChangeRf::class)
            ->setPerPage(20)
            ->setDefaultSort('dateDemande', 'desc')
            ->enableCheckboxSelection([
                'name' => 'demandes[]',
                'valueField' => 'id',
                'inputClass' => 'validation-change-rf-item',
                'allId' => 'validation-change-rf-all',
            ])
            ->addColumn('formation.composantePorteuse.libelle', [
                'label' => 'Composante',
                'sortable' => true,
                'filterable' => true,
                'sort_expression' => 'composantePorteuse_1.libelle',
                'filter_expression' => 'composantePorteuse_1.libelle',
                'class' => 'min-w-[12rem]',
            ])
            ->addColumn('formation.libelle', [
                'label' => 'Formation',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'template' => 'validation/datatable/_formation_change_rf.html.twig',
                'class' => 'min-w-[18rem]',
            ])
            ->addColumn('typeRf', [
                'label' => 'Co/RF',
                'sortable' => true,
                'filterable' => true,
                'type' => 'select',
                'choices' => [
                    TypeRfEnum::RF->value => 'Responsable',
                    TypeRfEnum::CORF->value => 'Co-responsable',
                ],
                'template' => 'validation/datatable/_type_rf_change_rf.html.twig',
            ])
            ->addColumn('ancienResponsable.display', [
                'label' => 'Actuel Co/RF',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
            ])
            ->addColumn('nouveauResponsable.display', [
                'label' => 'Nouveau Co/RF',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
            ])
            ->addColumn('dateDemande', [
                'label' => 'Date demande',
                'sortable' => true,
                'filterable' => true,
                'type' => 'date',
                'format' => 'datetime',
            ])
            ->addColumn('etatDemande', [
                'label' => 'Suivi / Etat',
                'sortable' => false,
                'filterable' => true,
                'template' => 'validation/datatable/_suivi_change_rf.html.twig',
                'class' => 'min-w-[15rem]',
            ])
            ->addColumn('id', [
                'label' => 'Actions',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'validation/datatable/_actions_change_rf.html.twig',
                'class' => 'text-right min-w-[11rem]',
            ])
            ->build();
    }
}
