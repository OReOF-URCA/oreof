<?php

declare(strict_types=1);

namespace App\Service\Coherence;

use App\Entity\FicheMatiere;
use App\Entity\Parcours;

final readonly class ParcoursCoherencePayloadBuilder
{
    public function __construct(
        private TextQualityDetector $textQualityDetector,
    )
    {
    }

    /**
     * Construit la représentation structurée et normalisée d'un parcours
     * (textes, paramètres stage/projet/alternance, compétences et maquette UE/EC).
     *
     * @return array{
     *     identity: array<string, mixed>,
     *     texts: array<string, array{label: string, value: string}>,
     *     modalites: array<string, mixed>,
     *     competences: list<array{bloc: string, items: list<string>}>,
     *     maquette: list<array{semestre: string, ues: list<array{libelle: string, ects: ?float, ecs: list<string>}>}>
     * }
     */
    public function buildParcoursPayload(Parcours $parcours): array
    {
        $formation = $parcours->getFormation();
        $domaines = [];
        if ($formation?->getDomaines() !== null) {
            foreach ($formation->getDomaines() as $domaine) {
                $libelle = $domaine->getLibelle();
                if ($libelle !== null && $libelle !== '') {
                    $domaines[] = $libelle;
                }
            }
        }

        $identity = [
            'parcoursId' => $parcours->getId(),
            'parcoursLibelle' => (string)($parcours->getLibelle() ?? ''),
            'isParcoursDefaut' => $parcours->isParcoursDefaut(),
            'typeParcours' => $parcours->getTypeParcours()?->libelle(),
            'formationDisplay' => $formation?->getDisplayLong() ?? '',
            'typeDiplome' => $formation?->getTypeDiplome()?->getLibelle() ?? '',
            'typeDiplomeCourt' => $formation?->getTypeDiplome()?->getLibelleCourt() ?? '',
            'isClassique' => $formation?->isClassique() ?? true,
            'domaines' => $domaines,
            'modaliteEnseignement' => $parcours->getModalitesEnseignement()?->libelle(),
            'regimesInscription' => $parcours->getRegimeInscriptionValues(),
            'localisation' => $parcours->getLocalisation()?->getLibelle(),
        ];

        $texts = [
            'objectifsParcours' => [
                'label' => 'Objectifs du parcours',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getObjectifsParcours()),
            ],
            'resultatsAttendus' => [
                'label' => 'Résultats attendus',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getResultatsAttendus()),
            ],
            'contenuFormation' => [
                'label' => 'Contenu de la formation',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getContenuFormation()),
            ],
            'motsCles' => [
                'label' => 'Mots-clés',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getMotsCles()),
            ],
            'prerequis' => [
                'label' => 'Prérequis',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getPrerequis()),
            ],
            'poursuitesEtudes' => [
                'label' => 'Poursuites d\'études',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getPoursuitesEtudes()),
            ],
            'debouches' => [
                'label' => 'Débouchés professionnels',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getDebouches()),
            ],
            'rythmeFormationTexte' => [
                'label' => 'Rythme de formation',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getRythmeFormationTexte()),
            ],
            'modalitesAlternance' => [
                'label' => 'Modalités d\'alternance',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getDisplayModalitesAlternance() ?? $parcours->getModalitesAlternance()),
            ],
            'stageText' => [
                'label' => 'Modalités de stage',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getStageText()),
            ],
            'projetText' => [
                'label' => 'Modalités de projet tutoré',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getProjetText()),
            ],
            'memoireText' => [
                'label' => 'Modalités de mémoire',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getMemoireText()),
            ],
            'situationProText' => [
                'label' => 'Mises en situation professionnelle',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getSituationProText()),
            ],
            'descriptifHautPage' => [
                'label' => 'Descriptif haut de page',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getDescriptifHautPage()),
            ],
            'descriptifBasPage' => [
                'label' => 'Descriptif bas de page',
                'value' => $this->textQualityDetector->htmlToPlainText($parcours->getDescriptifBasPage()),
            ],
        ];

        $modalites = [
            'hasStage' => $parcours->isHasStage(),
            'nbHeuresStages' => $parcours->getNbHeuresStages(),
            'hasProjet' => $parcours->isHasProjet(),
            'nbHeuresProjet' => $parcours->getNbHeuresProjet(),
            'hasMemoire' => $parcours->isHasMemoire(),
            'hasSituationPro' => $parcours->isHasSituationPro(),
            'nbHeuresSituationPro' => $parcours->getNbHeuresSituationPro(),
            'codesRome' => $parcours->getCodesRomeNormalized(),
        ];

        $competences = [];
        foreach ($parcours->getBlocCompetences() as $bloc) {
            $items = [];
            foreach ($bloc->getCompetences() as $competence) {
                $label = trim($competence->display());
                if ($label !== '') {
                    $items[] = $label;
                }
            }
            $competences[] = [
                'bloc' => trim($bloc->display()),
                'items' => $items,
            ];
        }

        $maquette = [];
        foreach ($parcours->getSemestreParcours() as $semestreParcours) {
            $semestre = $semestreParcours->getSemestre();
            if ($semestre?->getSemestreRaccroche() !== null) {
                $semestre = $semestre->getSemestreRaccroche()->getSemestre();
            }
            if ($semestre === null) {
                continue;
            }

            $uesData = [];
            foreach ($semestre->getUes() as $ue) {
                if ($ue->getUeRaccrochee() !== null) {
                    $ue = $ue->getUeRaccrochee()->getUe();
                }
                if ($ue === null) {
                    continue;
                }

                $ecsLabels = [];
                foreach ($ue->getElementConstitutifs() as $ec) {
                    $ecLabel = trim((string)($ec->getFicheMatiere()?->getLibelle() ?? $ec->getLibelle() ?? $ec->getTexteEcLibre() ?? ''));
                    if ($ecLabel !== '') {
                        $ecsLabels[] = $ecLabel;
                    }
                    foreach ($ec->getEcEnfants() as $ecEnfant) {
                        $childLabel = trim((string)($ecEnfant->getFicheMatiere()?->getLibelle() ?? $ecEnfant->getLibelle() ?? $ecEnfant->getTexteEcLibre() ?? ''));
                        if ($childLabel !== '') {
                            $ecsLabels[] = $childLabel;
                        }
                    }
                }

                $uesData[] = [
                    'libelle' => trim((string)($ue->getLibelle() ?? $ue->display($parcours))),
                    'ects' => $ue->getEcts(),
                    'ecs' => array_values(array_unique($ecsLabels)),
                ];
            }

            $maquette[] = [
                'semestre' => $semestre->display(),
                'ues' => $uesData,
            ];
        }

        return [
            'identity' => $identity,
            'texts' => $texts,
            'modalites' => $modalites,
            'competences' => $competences,
            'maquette' => $maquette,
        ];
    }

    /**
     * @return array{
     *     identity: array<string, mixed>,
     *     texts: array<string, array{label: string, value: string}>,
     *     volumes: array<string, float>,
     *     competences: list<string>,
     *     nbMccc: int
     * }
     */
    public function buildFicheMatierePayload(FicheMatiere $fiche): array
    {
        $parcours = $fiche->getParcours();
        $formation = $parcours?->getFormation();

        $competences = [];
        foreach ($fiche->getCompetences() as $competence) {
            $label = trim($competence->display());
            if ($label !== '') {
                $competences[] = $label;
            }
        }

        $volumes = [
            'cmPresentiel' => (float)($fiche->getVolumeCmPresentiel() ?? 0.0),
            'tdPresentiel' => (float)($fiche->getVolumeTdPresentiel() ?? 0.0),
            'tpPresentiel' => (float)($fiche->getVolumeTpPresentiel() ?? 0.0),
            'cmDistanciel' => (float)($fiche->getVolumeCmDistanciel() ?? 0.0),
            'tdDistanciel' => (float)($fiche->getVolumeTdDistanciel() ?? 0.0),
            'tpDistanciel' => (float)($fiche->getVolumeTpDistanciel() ?? 0.0),
            'te' => (float)($fiche->getVolumeTe() ?? 0.0),
            'ects' => (float)($fiche->getEcts() ?? 0.0),
        ];

        return [
            'identity' => [
                'ficheId' => $fiche->getId(),
                'libelle' => (string)($fiche->getLibelle() ?? ''),
                'libelleAnglais' => (string)($fiche->getLibelleAnglais() ?? ''),
                'horsDiplome' => (bool)$fiche->isHorsDiplome(),
                'parcoursLibelle' => $parcours?->getLibelle() ?? '',
                'formationDisplay' => $formation?->getDisplayLong() ?? '',
                'typeMccc' => $fiche->getTypeMccc(),
                'languesDispense' => $fiche->getLangueDispense(),
            ],
            'texts' => [
                'libelle' => [
                    'label' => 'Intitulé de la matière',
                    'value' => trim((string)($fiche->getLibelle() ?? '')),
                ],
                'libelleAnglais' => [
                    'label' => 'Intitulé en anglais',
                    'value' => trim((string)($fiche->getLibelleAnglais() ?? '')),
                ],
                'description' => [
                    'label' => 'Description / Programme',
                    'value' => $this->textQualityDetector->htmlToPlainText($fiche->getDescription()),
                ],
                'objectifs' => [
                    'label' => 'Objectifs pédagogiques',
                    'value' => $this->textQualityDetector->htmlToPlainText($fiche->getObjectifs()),
                ],
            ],
            'volumes' => $volumes,
            'competences' => $competences,
            'nbMccc' => $fiche->getMcccs()->count(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function computeHash(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $encoded === false ? '' : $encoded);
    }
}
