<?php

namespace App\Service\Formation;

use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\RegimeInscriptionEnum;
use App\Repository\ComposanteRepository;
use App\Repository\RythmeFormationRepository;
use App\Repository\UserRepository;
use App\Repository\VilleRepository;
use App\Service\AbstractFieldUpdater;

final class FormationFieldUpdater extends AbstractFieldUpdater
{
    public function __construct(
        private readonly UserRepository            $userRepo,
        private readonly VilleRepository           $villeRepo,
        private readonly RythmeFormationRepository $rythmeRepo,
        private readonly ComposanteRepository      $composanteRepo,
    )
    {
    }

    public function applyForTab(Formation $formation, string $tabKey, string $field, mixed $value): void
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
            'localisation' => [
                'formation_step1[sigle]',
                'formation_step1[localisationMention][]',
                'formation_step1[composantesInscription][]',
                'formation_step1[regimeInscription][]',
                'formation_step1[modalitesAlternance]',
            ],
            'presentation' => [
                'formation_step2[objectifsFormation]',
                'formation_step2[resultatsAttendus]',
                'formation_step2[contenuFormation]',
                'formation_step2[rythmeFormation]',
                'formation_step2[rythmeFormationTexte]'
            ],
            'structure' => [
                'formation_step3[hasParcours]'
            ]
        ];
    }

    /** @return array<string, callable(Formation, mixed): void> */
    private function handlers(): array
    {
        return [
            // ----------------- STEP 1 (presentation) -----------------
            'formation_step1[sigle]' => function (Formation $p, $v) {
                $p->setResponsableMention($this->toEntity($this->userRepo, $v));
            },
            'formation_step1[localisationMention][]' => function (Formation $f, $v) {
                $this->syncCollection(
                    $f->getLocalisationMention(), // Getter de la collection
                    $this->villeRepo,
                    $v,
                    fn($item) => $f->addLocalisationMention($item), // Adder
                    fn($item) => $f->removeLocalisationMention($item) // Remover
                );
            },

            // Exemple pour une collection de Composantes
            'formation_step1[composantesInscription][]' => function (Formation $f, $v) {
                $this->syncCollection(
                    $f->getComposantesInscription(),
                    $this->composanteRepo,
                    $v,
                    fn($item) => $f->addComposantesInscription($item),
                    fn($item) => $f->removeComposantesInscription($item)
                );
            },
            'formation_step1[regimeInscription][]' => function (Formation $p, $v) {
                $p->setRegimeInscription(\App\Referentiel\RegimeInscription::normalize((array) $v));
            },
            'formation_step1[modalitesAlternance]' => function (Formation $p, $v) {
                $p->setModalitesAlternance($this->toString($v));
            },
            // ----------------- STEP 2 (descriptif) -----------------
            // EnumType : si ton setter attend un enum, adapte via toEnum()
            'formation_step2[objectifsFormation]' => function (Formation $p, $v) {
                $p->setObjectifsFormation($this->toString($v));
            },
            'formation_step2[resultatsAttendus]' => function (Formation $p, $v) {
                $p->setResultatsAttendus($this->toString($v));
            },
            'formation_step2[contenuFormation]' => function (Formation $p, $v) {
                $p->setContenuFormation($this->toString($v));
            },
            'formation_step2[rythmeFormation]' => function (Formation $p, $v) {
                $p->setRythmeFormation($this->toEntity($this->rythmeRepo, $v));
            },
            'formation_step2[rythmeFormationTexte]' => function (Formation $p, $v) {
                $p->setRythmeFormationTexte($this->toString($v));
            },

            // ----------------- STEP 3 (Structure maquette) -----------------
            'formation_step3[hasParcours]' => function (Formation $f, $v) {
                $hasParcours = $this->toBoolOrNull($v);
                if ($hasParcours === false) {
                    if (\count($f->getParcours()) > 1) {
                        throw new \LogicException("Il y a plus d'un parcours");
                    }
                    $premierParcours = $f->getParcours()->first();
                    if ($premierParcours instanceof Parcours) {
                        $premierParcours->setLibelle(Parcours::PARCOURS_DEFAUT);
                    }
                } elseif ($hasParcours === true) {
                    foreach ($f->getParcours() as $parcours) {
                        if ($parcours->isParcoursDefaut()) {
                            $parcours->setLibelle('[A renomer] ' . $parcours->getLibelle());
                        }
                    }
                }
                $f->setHasParcours($hasParcours);
            },
        ];
    }
}
