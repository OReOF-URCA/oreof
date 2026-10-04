<?php

namespace App\Twig\Components;

use App\Classes\GetDpeParcours;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent]
/** @deprecated */
final class ParcoursOuvert
{
    use DefaultActionTrait;

    #[LiveProp]
    public Parcours $parcours;

    #[LiveProp]
    public string $type = 'parcours';

    #[LiveProp]
    public ?DpeParcours $dpeParcours = null;
    #[LiveProp]
    public ?CampagneCollecte $campagne = null;

    #[LiveProp(writable: true)]
    public bool $isOuvert = false;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[PostMount]
    public function onMounted(): void
    {
        $this->dpeParcours = GetDpeParcours::getFromParcours($this->parcours);
        if ($this->dpeParcours !== null) {
            $this->campagne = $this->dpeParcours->getCampagneCollecte();
            if (
                $this->dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::NON_OUVERTURE ||
                $this->dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::NON_OUVERTURE_SES ||
                $this->dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::NON_OUVERTURE_CFVU
            ) {
                $this->isOuvert = false;
            } else {
                $this->isOuvert = true;
            }
        }
    }

    #[LiveAction]
    public function save(): void
    {
        $this->dpeParcours = GetDpeParcours::getFromParcours($this->parcours);
        if ($this->dpeParcours !== null) {
            $this->campagne = $this->dpeParcours->getCampagneCollecte();

            if ($this->isOuvert) {
                $this->dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERTURE_SES);
            } else {
                $this->dpeParcours->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE_SES);
            }

            $this->entityManager->flush();
        }
    }

    public function getColor(): string
    {
        if ($this->dpeParcours?->getEtatReconduction() === TypeModificationDpeEnum::NON_OUVERTURE) {
            return 'danger';
        }

        if ($this->dpeParcours?->getEtatReconduction() === TypeModificationDpeEnum::NON_OUVERTURE_SES || $this->dpeParcours?->getEtatReconduction() === TypeModificationDpeEnum::NON_OUVERTURE_CFVU) {
            return 'warning';
        }
        return 'success';
    }
}
