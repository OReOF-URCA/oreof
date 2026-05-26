<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/StructureParcours.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 01/10/2023 08:43
 */

namespace App\DTO;

use App\Entity\Annee;
use App\Entity\Parcours;

use Symfony\Component\Serializer\Annotation\Groups;
use App\Enums\DureeParcoursUniteEnum;
class StructureParcours
{
    #[Groups(['DTO_json_versioning'])]
    public Parcours $parcours;

    /** @var StructureSemestre[] $semestres */
    #[Groups(['DTO_json_versioning'])]
    public array $semestres = [];

    public array $annees = [];

    #[Groups(['DTO_json_versioning'])]
    public HeuresEctsFormation $heuresEctsFormation;

    public ?StatsFichesMatieresParcours $statsFichesMatieresParcours = null;

    public ?float $dureeParcours = null;
    public ?DureeParcoursUniteEnum $dureeParcoursUnite = null;

    public function __construct(
        private $withEcts = true,
        private $withBcc = true,
    )
    {
    }

    // Factory statique : crée un DTO à partir d'une Entity (pas autowiré)
    public static function fromEntity(Parcours $parcours, bool $withEcts = true, bool $withBcc = true): self
    {
        $self = new self($withEcts, $withBcc);
        $self->parcours = $parcours;
        $self->statsFichesMatieresParcours = new StatsFichesMatieresParcours($parcours); //todo: a déplacer ? hors de ce DTO

        if ($withEcts) {
            $self->heuresEctsFormation = new HeuresEctsFormation();
        }

        /** @var Annee $annee */
        foreach ($parcours->getAnnees() as $annee) {
            $self->annees[$annee->getOrdre()]['heuresEctsAnnee'] = new HeuresEctsAnnee();
            $self->annees[$annee->getOrdre()]['annee'] = $annee;
            $self->annees[$annee->getOrdre()]['semestres'] = [];
        }

        return $self;
    }

//    public function setParcours(Parcours $parcours): void
//    {
//        $this->statsFichesMatieresParcours = new StatsFichesMatieresParcours($parcours);
//        $this->parcours = $parcours;
//        if ($this->withEcts) {
//            $this->heuresEctsFormation = new HeuresEctsFormation();
//        }
//
//    }

    public function addSemestre(int $ordre, StructureSemestre $structureSemestre): void
    {
        $this->semestres[$ordre] = $structureSemestre;
        $this->annees[$structureSemestre->semestreParcours?->getAnnee()?->getOrdre()]['semestres'][$ordre] = $structureSemestre;
        $this->annees[$structureSemestre->semestreParcours?->getAnnee()?->getOrdre()]['heuresEctsAnnee']->addSemestre($structureSemestre->heuresEctsSemestre);
        if ($this->withEcts) {
            $this->heuresEctsFormation->addSemestre($structureSemestre->heuresEctsSemestre);
        }
    }

    public function getTabAnnee(): array
    {
        $tab = [];
        foreach ($this->semestres as $semestre) {
            $tab[$semestre->getAnnee()][$semestre->ordre] = $semestre;
        }

        return $tab;
    }
}
