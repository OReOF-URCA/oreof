<?php

namespace App\Twig\Components;

use App\Controller\BaseController;
use App\Enums\CentreGestionEnum;
use App\Repository\ComposanteRepository;
use App\Repository\EtablissementRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ProfilRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('select_centre')]
class SelectCentre extends BaseController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $selectedCentre = '';

    #[LiveProp(writable: true)]
    public $selectedCibleId = null;

    #[LiveProp(writable: true)]
    public $selectedProfilId = null;

    #[LiveProp(writable: true)]
    public array $cibles = [];
    #[LiveProp(writable: true)]
    public array $centres = [];
    #[LiveProp(writable: true)]
    public array $profils = [];

    #[LiveProp(writable: true)]
    public ?string $labelCible = '';

    public function __construct(
        protected EtablissementRepository $etablissementRepository,
        protected FormationRepository     $formationRepository,
        protected ParcoursRepository      $parcoursRepository,
        protected ComposanteRepository    $composanteRepository,
        protected ProfilRepository        $profilRepository,
    )
    {
    }

    public function mount(): void
    {
        // Initialize the selectedCentre with the first centre if not set
        $centres = CentreGestionEnum::cases();
        foreach ($centres as $centre) {
            $this->centres[$centre->value] = $centre->getLibelle();
        }
    }


    #[LiveAction]
    public function updateCibles(): void
    {

        $this->cibles = [];
        $this->profils = [];
        $this->selectedCibleId = null;
        $this->selectedProfilId = null;
        switch ($this->selectedCentre) {
            case CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT->value:
                $etab = $this->etablissementRepository->findAll();
                $this->labelCible = 'un établissement';
                foreach ($etab as $item) {
                    $this->cibles[$item->getId()] = $item->getLibelle();
                }
                break;
            case CentreGestionEnum::CENTRE_GESTION_FORMATION->value:
                $formation = $this->formationRepository->findAll();
                $this->labelCible = 'une formation';
                foreach ($formation as $item) {
                    $this->cibles[$item->getId()] = $item->getDisplayLong();
                }
                break;
            case CentreGestionEnum::CENTRE_GESTION_COMPOSANTE->value:
                $compo = $this->composanteRepository->findAll();
                $this->labelCible = 'une composante';
                foreach ($compo as $item) {
                    $this->cibles[$item->getId()] = $item->getLibelle();
                }
                break;
            case CentreGestionEnum::CENTRE_GESTION_PARCOURS->value:
                $parcours = $this->parcoursRepository->findAll();
                $this->labelCible = 'un parcours';
                foreach ($parcours as $item) {
                    $this->cibles[$item->getId()] = $item->getFormation()?->getDisplayLong() . '. Parcours : ' . $item->getDisplay();
                }

                break;
        }

        // profils selon le centre sélectionné
        $profils = $this->profilRepository->findByCentre(['centre' => $this->selectedCentre], ['libelle' => 'ASC']);
        foreach ($profils as $profil) {
            $this->profils[$profil->getId()] = $profil->getLibelle();
        }
    }
}
