<?php

namespace App\Twig\Components;

use App\Classes\ValidationProcessFicheMatiere;
use App\Entity\FicheMatiere;
use App\Repository\HistoriqueFicheMatiereRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent('fiche_matiere_manage')]
/** @deprecated */
final class FicheMatiereManageComponent extends AbstractController
{

    const TAB = [
        'en_cours_redaction' => 'fiche_matiere',
        'soumis_central' => 'soumis_central',
        'valide_pour_publication' => 'valide_pour_publication',
        'publie' => 'publie',
    ];
    use DefaultActionTrait;

    public array $process;
    public array $historiques = [];

    #[LiveProp(writable: true)]
    public ?FicheMatiere $ficheMatiere = null;

    public ?string $etape = '';
    public string $place = '';

    public bool $hasDemande = false;

    public function __construct(
        private HistoriqueFicheMatiereRepository $historiqueFicheMatiereRepository,
        private ValidationProcessFicheMatiere    $validationProcess,
        #[Target('fiche')]
        private WorkflowInterface             $ficheMatiereWorkflow,
    ) {
        $this->process = $this->validationProcess->getProcess();
    }

    #[LiveListener('fiche_matiere_manage:valider')]
    public function valide(): void
    {
        $this->place = $this->getPlace();
        $this->etape = self::TAB[$this->place];
        $this->getHistorique();
    }

    private function getHistorique(): void
    {
        $historiques = $this->historiqueFicheMatiereRepository->findBy(['ficheMatiere' => $this->ficheMatiere], ['created' => 'ASC']);
        foreach ($historiques as $historique) {
            $this->historiques[$historique->getEtape()] = $historique;
        }
    }

    #[LiveListener('fiche_matiere_manage:edit')]
    public function edit(): void
    {
        $this->place = $this->getPlace();
        $this->etape = self::TAB[$this->place];
    }

    #[LiveListener('fiche_matiere_manage:reserver')]
    public function reserve(): void
    {
        $this->place = $this->getPlace();
        $this->etape = self::TAB[$this->place];
    }

    #[PostMount]
    public function postMount(): void
    {
        $this->getHistorique();
        //        $lastHistorique = $this->historiqueFicheMatiereRepository->findOneBy(['ficheMatiere' => $this->ficheMatiere], ['created' => 'DESC']);
        //        if ($lastHistorique !== null && $lastHistorique->getEtape() === 'rouvrir_fiche_matiere') {
        //          //  $this->hasDemande = true;
        //        }

        $this->place = $this->getPlace();
        $this->etape = self::TAB[$this->place] ;
    }


    private function getPlace(): int|string
    {
        if (null === $this->ficheMatiere) {
            return 'initialisation_dpe';
        }

        return array_keys($this->ficheMatiereWorkflow->getMarking($this->ficheMatiere)->getPlaces())[0];
    }
}
