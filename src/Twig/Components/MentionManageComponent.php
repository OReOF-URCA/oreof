<?php

namespace App\Twig\Components;

use App\Classes\GetDpeParcours;
use App\Classes\MentionProcess;
use App\Entity\Formation;
use App\Entity\TypeDiplome;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\HistoriqueFormationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent('mention_manage')]
/** @deprecated */
final class MentionManageComponent extends AbstractController
{
    public const TAB = [
        'initialisation_dpe' => 'formation',
        'autorisation_saisie' => null,
        'en_cours_redaction' => null,
        'soumis_parcours' => 'parcours_rf',
        'valide_parcours_rf' => 'formation',
        'soumis_rf' => 'formation',
        'soumis_dpe_composante' => 'dpe',
        'refuse_rf' => 'formation',
        'refuse_dpe_composante' => 'dpe',
        'dpe' => 'dpe',
        'soumis_conseil' => 'conseil',
        'conseil' => 'conseil',
        'refuse_conseil' => 'ses',
        'refuse_central' => 'ses',
        'soumis_central' => 'ses',
        'ses' => 'ses',
        'soumis_vp' => 'vp',
        'cfvu' => 'cfvu',
        'soumis_cfvu' => 'cfvu',
        'refuse_definitif_cfvu' => 'cfvu',
        'valide_a_publier' => 'cfvu',
        'publie' => 'publication',
        'valide_pour_publication' => 'publication',
        'soumis_conseil_reserve' => 'cfvu',
    ];

    public const TAB_PROCESS = [
        'ouverture_ses_ss_cfvu' => 0,
        'cloture_ses_ss_cfvu' => 0,
        'parcours' => 0,
        'parcours_rf' => 1,
        'formation' => 2,
        'dpe' => 3,
        'conseil' => 4,
        'ses' => 5,
        'soumis_central' => 5,
        'cfvu' => 6,
        'publication' => 7
    ];

    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?Formation $formation = null;

    public array $process;
    public array $historiques = [];

    #[LiveProp]
    public ?TypeDiplome $typeDiplome;

    public string $etape = '';
    public string $place = '';
    public bool $hasDemande = true;

    #[LiveProp]
    public string $type = 'formation';

    #[LiveProp(writable: true)]
    public string $event = 'none';

    public function __construct(
        private readonly HistoriqueFormationRepository $historiqueFormationRepository,
        private readonly MentionProcess                $validationProcess,
        #[Target('dpeParcours')]
        private readonly WorkflowInterface             $dpeParcoursWorkflow,
    ) {
        $this->process = $this->validationProcess->getProcess();
    }

    private function getHistorique(): void
    {
        $historiques = $this->historiqueFormationRepository->findBy(['formation' => $this->formation], ['created' => 'ASC']);
        foreach ($historiques as $historique) {
            $this->historiques[$historique->getEtape()] = $historique;
        }
    }

    #[PostMount]
    public function postMount(): void
    {
        $this->typeDiplome = $this->formation->getTypeDiplome();
        $this->place = $this->getPlace($this->type);

        if ($this->formation !== null) {
            $this->hasDemande = $this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_TEXTE ||
                $this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_INTITULE ||
                $this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_PARCOURS ||$this->formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION;
        }

        $this->etape = self::TAB[$this->place] ?? $this->type;
        $this->getHistorique();
    }

    public function dateHistorique(string $transition): string
    {
        if (array_key_exists($transition, $this->historiques)) {
            return $this->historiques[$transition]->getDate() !== null ? $this->historiques[$transition]->getDate()->format('d/m/Y') : $this->historiques[$transition]->getCreated()->format('d/m/Y');
        }

        return '- à venir -';
    }

    private function getWorkflow(): WorkflowInterface
    {
        //un seul workflow celui du DpeParcours
        return $this->dpeParcoursWorkflow;
    }

    private function getPlace(string $type): string
    {
        $objet = GetDpeParcours::getFromFormation($this->formation);
        if (null === $objet) {
            return 'initialisation_dpe';
        }

        return array_keys($this->getWorkflow()->getMarking($objet)->getPlaces())[0];
    }
}
