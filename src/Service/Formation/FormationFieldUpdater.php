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
                'formation_step1[responsableMention]',
                'formation_step1[coResponsable]',
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
            // ----------------- STEP 1 (localisation) -----------------
            'formation_step1[responsableMention]' => function (Formation $p, $v) {
                $p->setResponsableMention($this->toEntity($this->userRepo, $v));
            },
            'formation_step1[coResponsable]' => function (Formation $p, $v) {
                $p->setCoResponsable($this->toEntity($this->userRepo, $v));
            },
            'formation_step1[sigle]' => function (Formation $p, $v) {
                $p->setSigle($this->toStringOrNull($v));
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
                $regimes = \App\Referentiel\RegimeInscription::normalize($this->toArray($v));
                $p->setRegimeInscription($regimes);
                if ($p->isHasParcours() === false) {
                    $premierParcours = $p->getParcours()->first();
                    if ($premierParcours instanceof Parcours) {
                        $premierParcours->setRegimeInscription($regimes);
                    }
                }
            },
            'formation_step1[modalitesAlternance]' => function (Formation $p, $v) {
                $texte = $this->toString($v);
                $p->setModalitesAlternance($texte);
                if ($p->isHasParcours() === false) {
                    $premierParcours = $p->getParcours()->first();
                    if ($premierParcours instanceof Parcours) {
                        $premierParcours->setModalitesAlternance($texte);
                    }
                }
            },
            // ----------------- STEP 2 (presentation) -----------------
            // EnumType : si ton setter attend un enum, adapte via toEnum()
            'formation_step2[objectifsFormation]' => function (Formation $p, $v) {
                $texte = $this->toString($v);
                $p->setObjectifsFormation($texte);
                if ($p->isHasParcours() === false) {
                    $premierParcours = $p->getParcours()->first();
                    if ($premierParcours instanceof Parcours) {
                        $premierParcours->setObjectifsParcours($texte);
                    }
                }
            },
            'formation_step2[resultatsAttendus]' => function (Formation $p, $v) {
                $texte = $this->toString($v);
                $p->setResultatsAttendus($texte);
                if ($p->isHasParcours() === false) {
                    $premierParcours = $p->getParcours()->first();
                    if ($premierParcours instanceof Parcours) {
                        $premierParcours->setResultatsAttendus($texte);
                    }
                }
            },
            'formation_step2[contenuFormation]' => function (Formation $p, $v) {
                $texte = $this->toString($v);
                $p->setContenuFormation($texte);
                if ($p->isHasParcours() === false) {
                    $premierParcours = $p->getParcours()->first();
                    if ($premierParcours instanceof Parcours) {
                        $premierParcours->setContenuFormation($texte);
                    }
                }
            },
            'formation_step2[rythmeFormation]' => function (Formation $p, $v) {
                $rythme = $this->toEntity($this->rythmeRepo, $v);
                $p->setRythmeFormation($rythme);
                if ($p->isHasParcours() === false) {
                    $premierParcours = $p->getParcours()->first();
                    if ($premierParcours instanceof Parcours) {
                        $premierParcours->setRythmeFormation($rythme);
                    }
                }
            },
            'formation_step2[rythmeFormationTexte]' => function (Formation $p, $v) {
                $texte = $this->toString($v);
                $p->setRythmeFormationTexte($texte);
                if ($p->isHasParcours() === false) {
                    $premierParcours = $p->getParcours()->first();
                    if ($premierParcours instanceof Parcours) {
                        $premierParcours->setRythmeFormationTexte($texte);
                    }
                }
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
