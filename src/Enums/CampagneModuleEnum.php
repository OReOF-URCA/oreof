<?php

namespace App\Enums;

enum CampagneModuleEnum: string
{
    case OFFRE_FORMATION = 'offre_formation';
    case MAQUETTES = 'maquettes';
    case DESCRIPTIFS = 'descriptifs';
    case DPE = 'dpe';
    case MCCC = 'mccc';

    public function getLabel(): string
    {
        return match ($this) {
            self::OFFRE_FORMATION => 'Offre de formation (saisie, modification, validation)',
            self::MAQUETTES => 'Maquettes & Structures (semestres, UE, EC, volumes horaires)',
            self::DESCRIPTIFS => 'Descriptifs & Fiches matières (objectifs, contenus, compétences)',
            self::DPE => 'Demandes de modification / DPE (création, réouverture)',
            self::MCCC => 'Modalités de Contrôle des Connaissances (MCCC)',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::OFFRE_FORMATION => 'Permet aux responsables et composantes de modifier et soumettre les mentions et parcours de l\'offre.',
            self::MAQUETTES => 'Permet d\'éditer la maquette pédagogique, les UE, les EC et les volumes horaires des parcours.',
            self::DESCRIPTIFS => 'Permet aux enseignants et responsables de rédiger et compléter les fiches matières et cours.',
            self::DPE => 'Permet de créer, modifier et soumettre les demandes de modification de parcours (DPE).',
            self::MCCC => 'Permet de configurer et éditer les modalités de contrôle des connaissances et compétences.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::OFFRE_FORMATION => 'icon:book-open',
            self::MAQUETTES => 'icon:layers',
            self::DESCRIPTIFS => 'icon:file-text',
            self::DPE => 'icon:git-pull-request',
            self::MCCC => 'icon:award',
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
