<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/FicheMatiere/FicheMatiereFieldUpdater.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/02/2026 22:01
 */

namespace App\Service\FicheMatiere;

use App\Entity\FicheMatiere;
use App\Repository\ComposanteRepository;
use App\Repository\LangueRepository;
use App\Repository\RythmeFicheMatiereRepository;
use App\Repository\UserRepository;
use App\Repository\VilleRepository;
use App\Service\AbstractFieldUpdater;

final class FicheMatiereFieldUpdater extends AbstractFieldUpdater
{
    public function __construct(
        private readonly UserRepository   $userRepo,
        private readonly LangueRepository $langueRepo,
    )
    {
    }

    public function applyForTab(FicheMatiere $formation, string $tabKey, string $field, mixed $value): void
    {
        $allowed = $this->allowedFieldsByTab();
        if (!\in_array($field, $allowed[$tabKey] ?? [], true)) {
            throw new \InvalidArgumentException("Field not allowed for tab $tabKey: $field");
        }

        $handlers = $this->handlers();
        if (!isset($handlers[$field])) {
            throw new \InvalidArgumentException('Field not supported for autosave: ' . $field);
        }

        $handlers[$field]($formation, $value);
    }

    /** @return array<string, string[]> */
    private function allowedFieldsByTab(): array
    {
        return [
            'identite' => [
                'fiche_matiere_step1[responsableFicheMatiere]',
                'fiche_matiere_step1[sigle]',
                'fiche_matiere_step1[libelle]',
                'fiche_matiere_step1[libelleAnglais]',
            ],
            'presentation' => [
                'fiche_matiere_step2[description]',
                'fiche_matiere_step2[objectifs]',
                'fiche_matiere_step2[langueDispense][]',
                'fiche_matiere_step2[langueSupport][]'
            ],
            'mutualisation' => [
                'fiche_matiere_step1b[enseignementMutualise]'
            ],
            'volumes_horaires' => [
                'volumesHorairesImpose',
                'fiche_matiere_step4_hd[volumeCmPresentiel]',
                'fiche_matiere_step4_hd[volumeTdPresentiel]',
                'fiche_matiere_step4_hd[volumeTpPresentiel]',
                'fiche_matiere_step4_hd[volumeCmDistanciel]',
                'fiche_matiere_step4_hd[volumeTdDistanciel]',
                'fiche_matiere_step4_hd[volumeTpDistanciel]',
                'fiche_matiere_step4_hd[volumeTe]',
            ],
            'mccc' => []
        ];
    }

    /** @return array<string, \Closure(FicheMatiere, mixed): void> */
    private function handlers(): array
    {
        return [
            // ----------------- STEP 1 (identite) -----------------
            'fiche_matiere_step1[responsableFicheMatiere]' => function (FicheMatiere $p, $v): void {
                $p->setResponsableFicheMatiere($this->toEntity($this->userRepo, $v));
            },
            'fiche_matiere_step1[sigle]' => function (FicheMatiere $p, $v): void {
                $p->setSigle($this->toString($v));
            },
            'fiche_matiere_step1[libelle]' => function (FicheMatiere $p, $v): void {
                $p->setLibelle($this->toString($v));
            },
            'fiche_matiere_step1[libelleAnglais]' => function (FicheMatiere $p, $v): void {
                $p->setLibelleAnglais($this->toString($v));
            },


            // ----------------- STEP 2 (présentation) -----------------
            'fiche_matiere_step2[description]' => function (FicheMatiere $p, $v): void {
                $p->setDescription($this->toString($v));
            },
            'fiche_matiere_step2[objectifs]' => function (FicheMatiere $p, $v): void {
                $p->setObjectifs($this->toString($v));
            },
            'fiche_matiere_step2[langueDispense][]' => function (FicheMatiere $f, $v): void {
                $this->syncCollection(
                    $f->getLangueDispense(),
                    $this->langueRepo,
                    $v,
                    fn($item) => $f->addLangueDispense($item),
                    fn($item) => $f->removeLangueDispense($item)
                );
            },
            'fiche_matiere_step2[langueSupport][]' => function (FicheMatiere $f, $v): void {
                $this->syncCollection(
                    $f->getLangueSupport(),
                    $this->langueRepo,
                    $v,
                    fn($item) => $f->addLangueSupport($item),
                    fn($item) => $f->removeLangueSupport($item)
                );
            },


            // ----------------- STEP 3 (Mutualisation) -----------------
            'fiche_matiere_step1b[enseignementMutualise]' => function (FicheMatiere $p, $v): void {
                $p->setEnseignementMutualise($this->toBoolOrNull($v));
            },

            // ----------------- STEP 4 (Volumes horaires) -----------------
            'volumesHorairesImpose' => function (FicheMatiere $p, $v): void {
                $p->setVolumesHorairesImpose($this->toBoolOrNull($v));
            },
            'fiche_matiere_step4_hd[volumeCmPresentiel]' => function (FicheMatiere $p, $v): void {
                $p->setVolumeCmPresentiel($this->toFloatOrNull($v));
            },
            'fiche_matiere_step4_hd[volumeTdPresentiel]' => function (FicheMatiere $p, $v): void {
                $p->setVolumeTdPresentiel($this->toFloatOrNull($v));
            },
            'fiche_matiere_step4_hd[volumeTpPresentiel]' => function (FicheMatiere $p, $v): void {
                $p->setVolumeTpPresentiel($this->toFloatOrNull($v));
            },
            'fiche_matiere_step4_hd[volumeCmDistanciel]' => function (FicheMatiere $p, $v): void {
                $p->setVolumeCmDistanciel($this->toFloatOrNull($v));
            },
            'fiche_matiere_step4_hd[volumeTdDistanciel]' => function (FicheMatiere $p, $v): void {
                $p->setVolumeTdDistanciel($this->toFloatOrNull($v));
            },
            'fiche_matiere_step4_hd[volumeTpDistanciel]' => function (FicheMatiere $p, $v): void {
                $p->setVolumeTpDistanciel($this->toFloatOrNull($v));
            },
            'fiche_matiere_step4_hd[volumeTe]' => function (FicheMatiere $p, $v): void {
                $p->setVolumeTe($this->toFloatOrNull($v));
            },
        ];
    }
}
