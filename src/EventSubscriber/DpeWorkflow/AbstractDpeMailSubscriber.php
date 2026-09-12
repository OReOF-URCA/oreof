<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/DpeWorkflow/AbstractDpeMailSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 06/10/2023 08:37
 */

namespace App\EventSubscriber\DpeWorkflow;

use App\Entity\ChangeRf;
use App\Entity\DpeParcours;
use App\Entity\Etablissement;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\User;

class AbstractDpeMailSubscriber
{
    protected ?DpeParcours $dpeParcours;
    protected ?Parcours $parcours;
    protected ?Formation $formation;
    protected ?ChangeRf $demande;
    protected ?User $responsableDpe;
    protected ?bool $hasParcours = false;

    protected function getDataFromEvent($event): ?bool
    {
        $this->dpeParcours = $event->getSubject();

        if (null === $this->dpeParcours) {
            return null;
        }

        $this->parcours = $this->dpeParcours->getParcours();

        if (null === $this->parcours) {
            return null;
        }

        $this->hasParcours = $this->parcours->isParcoursDefaut() === false;
        $this->formation = $this->parcours->getFormation();

        if (null === $this->formation) {
            return null;
        }

        $this->responsableDpe = $this->formation->getComposantePorteuse()?->getResponsableDpe();

        if (null === $this->responsableDpe) {
            return null;
        }

        return true;
    }

    protected function getDataFromChangeRfEvent($event): ?true
    {
        $this->demande = $event->getSubject();

        if (null === $this->demande) {
            return null;
        }

        $this->formation = $this->demande->getFormation();

        if (null === $this->formation) {
            return null;
        }

        $this->responsableDpe = $this->formation->getComposantePorteuse()?->getResponsableDpe();

        if (null === $this->responsableDpe) {
            return null;
        }

        return true;
    }

    protected function getData(): array
    {
        return [
            'dpeParcours' => $this->dpeParcours,
            'parcours'    => $this->parcours,
            'formation'   => $this->formation,
            'responsableDpe' => $this->responsableDpe,
            'hasParcours' => $this->hasParcours,
        ];
    }

    protected function getDataChangeRf(): array
    {
        return [
            'formation'   => $this->formation,
            'responsableDpe' => $this->responsableDpe,
            'demande' => $this->demande,
        ];
    }

    public function getDestinataires(bool $withDpe = false): array
    {
        $d = [];
        //si des parcours on prends les RP, sinon juste RF
        if ($this->hasParcours) {
            if ($this->parcours->getRespParcours() !== null) {
                $d[] = $this->parcours->getRespParcours()->getEmail();
            }
            if ($this->parcours->getCoResponsable() !== null) {
                $d[] = $this->parcours->getCoResponsable()->getEmail();
            }
        }

        if ($this->formation->getResponsableMention() !== null) {
            $d[] = $this->formation->getResponsableMention()->getEmail();
        }

        if ($this->formation->getCoResponsable() !== null) {
            $d[] = $this->formation->getCoResponsable()->getEmail();
        }

        if ($withDpe) {
            $d[] = $this->responsableDpe->getEmail();
        }

        return $d;
    }

    public function getEtablissement(): ?Etablissement
    {
        return $this->formation?->getComposantePorteuse()?->getEtablissement();
    }

    public function getEmailCentral(): string
    {
        return $this->getEtablissement()?->getEmailCentral();
    }

    public function getEmailOreof(): string
    {
        return $this->getEtablissement()?->getEmailOreof();
    }
}
