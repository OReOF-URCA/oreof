<?php

namespace App\Twig\Components;

use App\Classes\GetDpeParcours;
use App\Classes\GetHistorique;
use App\Entity\ChangeRf;
use App\Entity\Parcours;
use DateTimeInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('historique_date')]
final class HistoriqueDateComponent
{
    public ?Parcours $parcours = null;
    public ?ChangeRf $changeRf = null;
    public string $type = 'parcours';
    public string $step = 'parcours';
    public ?DateTimeInterface $date = null;

    public function __construct(private GetHistorique $getHistorique)
    {
    }

    #[PostMount]
    public function postMount(): void
    {
        if ($this->date !== null) {
            return;
        }

        if ($this->type === 'parcours') {
            if ($this->parcours === null) {
                return;
            }
            $dpe = GetDpeParcours::getFromParcours($this->parcours);
            if ($dpe === null) {
                return;
            }

            $historique = $this->getHistorique->getHistoriqueParcoursLastStep($dpe, $this->step);


        } else if ($this->type === 'change_rf') {
            if ($this->changeRf === null) {
                return;
            }
            $historique = $this->getHistorique->getHistoriqueChangeRfLastStep($this->changeRf, 'changeRf.'.$this->step);
        } else {
            return;
        }


        if ($historique !== null) {//état conseil avant, les deux pour compatibilité
           $this->date = $historique->getDate();
        }
    }
}
