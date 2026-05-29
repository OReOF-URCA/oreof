<?php

namespace App\Twig\Components;

use App\Classes\GetDpeParcours;
use App\Classes\ValidationProcess;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\HistoriqueFormationRepository;
use App\Repository\HistoriqueParcoursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent('parcours_manage')]
final class ParcoursManageComponent extends AbstractController
{
    public const TAB = [
        'initialisation_dpe' => 'formation',
        'autorisation_saisie' => null,
        'en_cours_redaction' => 'parcours',
        'en_cours_redaction_ss_cfvu' => 'parcours',
        'soumis_parcours' => 'parcours_rf',
        'valide_parcours_rf' => 'formation',
        'soumis_rf' => 'formation',
        'soumis_dpe_composante' => 'dpe',
        'soumis_central_sans_cfvu' => 'ses',
        'refuse_rf' => 'formation',
        'refuse_dpe_composante' => 'dpe',
        'dpe' => 'dpe',
        'vp' => 'vp',
        'soumis_conseil' => 'conseil',
        'conseil' => 'conseil',
        'refuse_conseil' => 'ses',
        'refuse_central' => 'ses',
        'soumis_central' => 'ses',
        'ses' => 'ses',
        'soumis_vp' => 'vp',
        'valide_cfvu' => 'valide_cfvu',
        'cfvu' => 'cfvu',
        'cloture' => 'cloture',
        'publication' => 'publication',
        'soumis_cfvu' => 'cfvu',
        'refuse_definitif_cfvu' => 'cfvu',
        'valide_a_publier' => 'publication',//todo: bizarre d'ajouter cet état, a revoir les deux dernières phases
        'publie' => 'publication',
        'valide_pour_publication' => 'publication',
        'soumis_conseil_reserve' => 'cfvu',
        'non_ouverture_ses' => 'ses',
        'non_ouverture_cfvu' => 'cfvu',
        'non_ouverture' => 'publication',
        'tacite_reconduction' => 'conseil',
    ];

    public const TAB_PROCESS = [
        'ouverture_ses_ss_cfvu' => 0,
        'cloture_ses_ss_cfvu' => 0,
        'soumis_parcours' => 0,
        'en_cours_redaction' => 0,
        'en_cours_redaction_ss_cfvu' => 0,
        'soumis_dpe_composante' => 3,
        'soumis_conseil' => 4,
        'parcours' => 0,
        'parcours_rf' => 1,
        'formation' => 2,
        'dpe' => 3,
        'conseil' => 4,
        'ses' => 5,
        'vp' => 5,
        'soumis_central' => 5,
        'soumis_central_sans_cfvu' => 5,
        'soumis_conseil_reserve' => 4,
        'valide_cfvu' => 7,
        'soumis_cfvu' => 6,
        'cfvu' => 6,
        'publication' => 8,
        'publie' => 8,
        'valide_a_publier' => 8,
        'non_ouverture_ses' => 5,
        'non_ouverture_cfvu' => 7,
        'non_ouverture' => 8,
        'cloture' => 8,
    ];

    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?Formation $formation = null;

    #[LiveProp]
    public ?Parcours $parcours = null;

    public array $process;
    public array $historiques = [];

    #[LiveProp]
    public ?TypeDiplome $typeDiplome;

    public string $place = '';

    public bool $hasDemande = true;

    public DpeParcours $dpeParcours;

    public function __construct(
        private readonly HistoriqueFormationRepository $historiqueFormationRepository,
        private readonly HistoriqueParcoursRepository  $historiqueParcoursRepository,
        private readonly ValidationProcess             $validationProcess,
        #[Target('dpeParcours')]
        private readonly WorkflowInterface             $dpeParcoursWorkflow,
    ) {
        $this->process = $this->validationProcess->getProcess();
    }

    public function redirige(): Response
    {
        if ($this->parcours->isParcoursDefaut() === false) {
            return $this->redirectToRoute('app_parcours_show', [
                'id' => $this->parcours->getId()
            ]);
        }

        return $this->redirectToRoute('app_formation_show', [
            'slug' => $this->formation->getSlug()
        ]);
    }

    #[LiveListener('mention_manage:edit')]
    public function edit(): void
    {
        $this->place = $this->getPlace();
    }

    #[LiveListener('mention_manage:valider')]
    public function valide(): void
    {
        $this->init();
        $this->place = $this->getPlace();
        $this->getHistorique();
    }

    #[LiveListener('mention_manage:refuser')]
    public function refuse(): void
    {
        $this->init();
        $this->place = $this->getPlace();
        $this->getHistorique();
    }

    #[LiveListener('mention_manage:reserver')]
    public function reserve(): void
    {
        $this->init();
        $this->place = $this->getPlace();
        $this->getHistorique();
    }

    private function getHistorique(): void
    {
        $historiques = $this->historiqueFormationRepository->findBy(
            ['formation' => $this->parcours->getFormation()],
            ['created' => 'ASC']
        );

        foreach ($historiques as $historique) {
            $placeKey = self::TAB[$this->place] ?? null;

            if (
                $historique->getChangeRf() === null
                && $placeKey !== null
                && isset(self::TAB_PROCESS[$historique->getEtape()], self::TAB_PROCESS[$placeKey])
                && self::TAB_PROCESS[$historique->getEtape()] < self::TAB_PROCESS[$placeKey]
            ) {
                $this->historiques[$historique->getEtape()] = $historique;
            }
        }

        $historiques = $this->historiqueParcoursRepository->findBy(
            ['parcours' => $this->parcours],
            ['created' => 'ASC']
        );

//todo: gérer le cas d'une réouverture sans CFVU
        foreach ($historiques as $historique) {
            $placeKey = self::TAB[$this->place] ?? null;

            if (
                $placeKey !== null
                && isset(self::TAB_PROCESS[$historique->getEtape()], self::TAB_PROCESS[$placeKey])
                && self::TAB_PROCESS[$historique->getEtape()] < self::TAB_PROCESS[$placeKey]
            ) {
                $this->historiques[$historique->getEtape()] = $historique;
            }
        }
    }

    private function init(): void
    {
        $this->dpeParcours = GetDpeParcours::getFromParcours($this->parcours);
        $this->typeDiplome = $this->parcours?->getFormation()?->getTypeDiplome();
        $this->formation = $this->parcours?->getFormation();
        $this->place = $this->getPlace();
        $this->hasDemande = $this->dpeParcours->isReouvert();
    }

    #[PostMount]
    public function postMount(): void
    {
       $this->init();
        $this->getHistorique();
    }

    public function dateHistorique(string $transition): string
    {
        if (array_key_exists($transition, $this->historiques)) {
            if ($this->historiques[$transition]->getEtape() === 'soumis_conseil' && ($this->dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC || $this->dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE)) {
                if (!array_key_exists('fichier', $this->historiques[$transition]->getComplements())) {
                    return '- à venir -';
                }
            }

            return $this->historiques[$transition]->getDate() !== null ? $this->historiques[$transition]->getDate()->format('d/m/Y') : '- à venir -';
        }
        return '- à venir -';
    }

    private function getWorkflow(): WorkflowInterface
    {
        return $this->dpeParcoursWorkflow;
    }

    private function getPlace(): string
    {

        if (null === $this->dpeParcours) {
            return 'initialisation_dpe';
        }

        return array_keys($this->getWorkflow()->getMarking($this->dpeParcours)->getPlaces())[0];
    }
}
