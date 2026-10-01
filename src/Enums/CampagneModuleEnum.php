<?php

namespace App\Enums;

enum CampagneModuleEnum: string
{
    case OFFRE_FORMATION = 'offre_formation';
    case MAQUETTES = 'maquettes';
    case DESCRIPTIFS = 'descriptifs';
    case MCCC = 'mccc';

    public function getLabel(): string
    {
        return match ($this) {
            self::OFFRE_FORMATION => 'Offre de formation (ouverture, capacités, ...)',
            self::MAQUETTES => 'Maquettes & Structures (semestres, UE, EC, volumes horaires)',
            self::DESCRIPTIFS => 'Descriptifs de formation & Fiches matières (objectifs, contenus, compétences)',
            self::MCCC => 'Modalités de Contrôle des Connaissances (MCCC)'
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::OFFRE_FORMATION => 'Permet aux responsables et composantes de modifier et soumettre les mentions et parcours de l\'offre ainsi que les capacités.',
            self::MAQUETTES => 'Permet d\'éditer la maquette pédagogique, les UE, les EC et les volumes horaires des parcours.',
            self::DESCRIPTIFS => 'Permet aux enseignants et responsables de rédiger et compléter les descriptifs des formations et des fiches matières.',
            self::MCCC => 'Permet de configurer et éditer les modalités de contrôle des connaissances et compétences.'
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::OFFRE_FORMATION => 'icon:book-open',
            self::MAQUETTES => 'icon:layers',
            self::DESCRIPTIFS => 'icon:file-text',
            self::MCCC => 'icon:award'
        };
    }

    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }

        return $choices;
    }
}
