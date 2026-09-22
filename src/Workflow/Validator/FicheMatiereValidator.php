<?php

declare(strict_types=1);

namespace App\Workflow\Validator;

use App\Classes\verif\AbstractValide;
use App\Classes\verif\FicheMatiereValide;
use App\Entity\FicheMatiere;

final class FicheMatiereValidator
{
    public function validate(FicheMatiere $ficheMatiere): ValidationResult
    {
        $validator = (new FicheMatiereValide(
            $ficheMatiere,
            $ficheMatiere->getParcours()?->getFormation()?->getTypeDiplome(),
        ))->valideFicheMatiere();

        $groups = [
            'identity' => [
                'label' => 'Identification de la fiche matière',
                'fields' => ['libelle', 'libelleAnglais', 'mutualise'],
            ],
            'content' => [
                'label' => 'Description et objectifs pédagogiques',
                'fields' => ['description', 'objectifs'],
            ],
            'languages' => [
                'label' => 'Langues d’enseignement et de support',
                'fields' => ['langueDispense', 'langueSupport'],
            ],
        ];

        $fieldLabels = [
            'libelle' => 'Le libellé de la fiche matière est obligatoire.',
            'libelleAnglais' => 'Le libellé anglais de la fiche matière est obligatoire.',
            'mutualise' => 'Le caractère mutualisé de l’enseignement doit être renseigné.',
            'description' => 'La description doit comporter au moins 12 caractères.',
            'objectifs' => 'Les objectifs doivent comporter au moins 12 caractères.',
            'langueDispense' => 'Au moins une langue d’enseignement doit être renseignée.',
            'langueSupport' => 'Au moins une langue de support doit être renseignée.',
        ];

        $errors = [];
        $checks = [];

        foreach ($groups as $code => $group) {
            $groupErrors = [];
            foreach ($group['fields'] as $field) {
                if (($validator->etat[$field] ?? AbstractValide::VIDE) === AbstractValide::COMPLET) {
                    continue;
                }

                $groupErrors[] = ValidationError::forField(
                    $field,
                    sprintf('fiche_matiere.%s.incomplete', $field),
                    $fieldLabels[$field],
                );
            }

            $errors = array_merge($errors, $groupErrors);
            $checks[] = ValidationCheck::fromResult($code, $group['label'], [
                'errors' => $groupErrors,
                'warnings' => [],
            ]);
        }

        return [] === $errors
            ? ValidationResult::success([], $checks)
            : ValidationResult::failure($errors, [], $checks);
    }
}
