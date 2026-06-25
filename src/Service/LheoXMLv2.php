<?php

namespace App\Service;

use App\Classes\GetDpeParcours;
use App\Entity\Etablissement;
use App\Entity\Parcours;
use App\Entity\Ville;
use App\Enums\CampagnePublicationTagEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Enums\TypeParcoursEnum;
use App\Repository\ElementConstitutifRepository;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class LheoXMLv2 extends LheoXML {

    public function __construct(
        protected EntityManagerInterface $em,
        protected TypeDiplomeResolver $tdResolver,
        protected UrlGeneratorInterface $router,
        protected ElementConstitutifRepository $ecRepo,
    ) {
        parent::__construct($em, $tdResolver, $router, $ecRepo);
    }

    #[Override]
    public function generateLheoXMLFromParcours(Parcours $parcours, bool $with_extras = false): string
    {
        // Paramètres de l'encodeur
        $contextOptions = [
            'xml_root_node_name' => 'lheo',
            'xml_format_output' => true,
            'xml_encoding' => 'utf-8'
        ];

        //Récupération des valeurs
        // Codes ROME
        $codesRome = [];
        foreach ($parcours->getCodesRome() as $code) {
            $codeRomeData = $code['code'];
            // Si la saisie comporte des tabulations
            if (preg_match_all('/\\t/m', $codeRomeData)) {
                $codeRomeData = preg_replace('/\\t/m', '', $codeRomeData);
            }
            preg_match_all('/([A-Za-z][0-9]{4})/m', $codeRomeData, $matches);
            if (isset($matches[1][0])) {
                $codesRome[] = $matches[1][0];
            }
        }

        // 5 codes ROME max
        $codesRomeLheo = array_slice($codesRome, 0, 5);

        // LHEO exige au moins un code ROME : valeur par défaut au bon format (lettre + 4 chiffres).
        if (count($codesRomeLheo) === 0) {
            $codesRomeLheo = ['R0000'];
        }

        // Intitulé de la formation
        $intituleFormation = 'Non renseigné.';
        if ($typeDiplomeLibelle = $parcours->getFormation()?->getTypeDiplome()?->getLibelle()) {
            $mention = $parcours->getFormation()?->getMention()?->getLibelle() ?? "";
            $intituleFormation = $typeDiplomeLibelle . " " . $mention;
            if ($parcours->isParcoursDefaut() === false) {
                $intituleFormation .= "<br>parcours " . $parcours->getDisplay();
            }

            if ($parcours->getTypeParcours() === TypeParcoursEnum::TYPE_PARCOURS_LAS1) {
                $intituleFormation .= " - LAS 1";
            }

            if ($parcours->getTypeParcours() === TypeParcoursEnum::TYPE_PARCOURS_LAS23) {
                $intituleFormation .= " - LAS 2 et 3";
            }

            if ($parcours->getTypeParcours() === TypeParcoursEnum::TYPE_PARCOURS_LAS123) {
                $intituleFormation .= " - L.AS 1, 2 et 3";
            }
        }

        // Niveau d'entree
        $niveauEntree = -1;
        if ($niveau = $parcours->getFormation()?->getNiveauEntree()) {
            $niveauEntree = $niveau->value;
        }

        // Adresses de la composante d'inscription
        $composantesInscription = [];

        if ($composante = $parcours->getComposanteInscription() ?? $parcours->getFormation()?->getComposantePorteuse()) {
            $adresse = [
                'denomination' => '',
                'ligne' => '',
                'codepostal' => '',
                'ville' => '',
            ];
            // Adresse de l'accueil
            $adresseComp = $composante->getAdresse();
            if ($adresseComp !== null) {
                $adresse['denomination'] = $composante->getLibelle();
                $adresse['ligne'] = $adresseComp->getAdresse1() . " " . ($adresseComp->getAdresse2() ?? '');
                $adresse['codepostal'] = $adresseComp->getCodePostal();
                $adresse['ville'] = $adresseComp->getVille();
            }
            // Téléphone
            $telephone = ['numtel' => $composante->getTelStandard() ?? $composante->getTelComplementaire() ?? 'Non renseigné'];
            // Résultat
            $result = [
                'type-contact' => 4,
                'coordonnees' => [
                    'adresse' => $adresse,
                    'telfixe' => $telephone,
                    'courriel' => $composante->getMailContact(),
                    'web' => ['urlweb' => $composante->getUrlSite()]
                ]
            ];

            $composantesInscription[] = $result;
        }

        $contactsOrganismes = [];

        foreach ($parcours->getContacts() as $contacts) {

                $adresse = [
                    'denomination' => '',
                    'ligne' => '',
                    'codepostal' => '',
                    'ville' => '',
                ];
                // Adresse de l'accueil
                $adresseComp = $contacts->getAdresse();

            if ($adresseComp !== null) {
                $adresse['denomination'] = $contacts->getDenomination();
                $adresse['ligne'] = $adresseComp->getAdresse1() . " " . ($adresseComp->getAdresse2() ?? '');
                $adresse['codepostal'] = $adresseComp->getCodePostal();
                $adresse['ville'] = $adresseComp->getVille();
            }

                // Téléphone
                $telephone = ['numtel' => $contacts->getTelephone() ?? $contacts->getTelephone() ?? 'Non renseigné'];
                // Résultat
                $result = [
                    'type-contact' => 4,
                    'coordonnees' => [
                        'adresse' => $adresse,
                        'telfixe' => $telephone,
                        'courriel' =>  $contacts->getEmail() ?? $contacts->getEmail() ?? 'Non renseigné',
                    ]
                ];

                $contactsOrganismes[] = $result;

        }

        // Durée de la formation (durée cycle)
        $dureeCycle = 0;

        foreach ($parcours->getSemestreParcours() as $semestre) {
            if ($semestre->getSemestre()?->isNonDispense() === false && $semestre->isOuvert() === true) {
                ++$dureeCycle;
            }
        }

        $dureeCycle /= 2;
        //arrondi supérieur
        $dureeCycle = ceil($dureeCycle);

        // code RNCP
        // On prend le RNCP du parcours, et sinon celui de la formation
        $rncp = 'RNCP';
        $rncp .= $parcours->getCodeRNCP() ?? $parcours->getFormation()?->getCodeRNCP();

        // Coordonnées Organisme (composante)
        $coordonneesComposante = [];
        if ($composante = $parcours->getComposanteInscription() ?? $parcours->getFormation()?->getComposantePorteuse()) {
            if ($adresse = $composante->getAdresse()) {
                $coordonneesComposante['adresse'] = [
                    'denomination' => $composante->getLibelle(),
                    'ligne' => $adresse->getAdresse1() . " " . $adresse->getAdresse2() ?? '',
                    'codepostal' => $adresse->getCodePostal(),
                    'ville' => $adresse->getVille(),
                ];
            }
            $coordonneesComposante['telfixe'] = ['numtel' => $composante->getTelStandard() ?? $composante->getTelComplementaire() ?? 'Non renseigné'];
            $coordonneesComposante['courriel'] = $composante->getMailContact();
            $coordonneesComposante['web'] = ['urlweb' => $composante->getUrlSite()];
        }

        //Adresse du siège de l'URCA
        $adresseSiegeURCA = [
            'denomination' => 'Université de Reims Champagne-Ardenne',
            'ligne' => '2 Avenue Robert Schuman',
            'codepostal' => '51724',
            'ville' => 'REIMS CEDEX'
        ];

        // Référentiel de compétences
        $competencesAcquisesExtra = "Non renseigné.";
        $competencesAcquisesExtraTitre = "<h3>Compétences acquises</h3><br>";

        // Si Parcours NON BUT
        if ($parcours->getTypeDiplome()?->getLibelleCourt() !== "BUT") {
            if ($blocCompetences = $parcours->getBlocCompetences()) {
                $competencesAcquisesExtra = $competencesAcquisesExtraTitre . "<ul>";
                foreach ($blocCompetences as $bloc) {
                    $competencesHTML = "";
                    foreach ($bloc->getCompetences() as $competence) {
                        $competencesHTML .= "<li>{$competence->display()}</li>";
                    }
                    $competencesAcquisesExtra .= <<<HTML
                    <li>
                        {$bloc->display()}
                        <ul>
                            {$competencesHTML}
                        </ul>
                    </li>
HTML;
                }
                $competencesAcquisesExtra .= "</ul>";
            }
        }
        // Si le Parcours EST un BUT
        if ($parcours->getTypeDiplome()?->getLibelleCourt() === "BUT") {
            $typeD = $this->typeDiplomeResolver->get($parcours->getFormation()->getTypeDiplome());
            $competences = $typeD->getStructureCompetences($parcours);
            if ($competences) {
                $competencesAcquisesExtra = $competencesAcquisesExtraTitre . "<ul>";
                foreach ($competences as $comp) {
                    $competencesHTML = "";
                    foreach ($comp->getButNiveaux() as $niveau) {
                        $competencesHTML .= "<li>Niveau {$niveau->getOrdre()} - {$niveau->getLibelle()}</li>";
                    }
                    $competencesAcquisesExtra .= <<<HTML
                    <li>
                        {$comp->getLibelle()}
                        <ul>
                            {$competencesHTML}
                        </ul>
                    </li>
HTML;
                }
                $competencesAcquisesExtra .= "</ul>";
            }
        }

        $etablissementInformation = $this->entityManager->getRepository(Etablissement::class)
            ->findOneById(1)->getEtablissementInformation();

        // Élément Maquette Iframe
//         $UrlMaquetteIframe = $this->router->generate('app_parcours_maquette_iframe', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
//         $maquetteIframe = <<<HTML
//         <iframe
//             id="maquettePedagogiqueFormation"
//             width="800"
//             height="750"
//             src="{$UrlMaquetteIframe}"
//             style="max-height: auto;"
//             loading="eager"
//             >
//         </iframe>
// HTML;
        // Stage et projet tuteuré (Organisation pédagogique)
        $stage = "Non concerné";
        $projetTuteure = "Non concerné";
        $situationsPros = "Non concerné";
        $terMemoire = "Non concerné";
        $calendrierUniversitaire = $etablissementInformation->getCalendrierUniversitaire() ?? "";
        if ($parcours->isHasStage() && $parcours->isAlternance() === false) {
            $stage = $parcours->getStageText();
        }
        if ($parcours->isHasProjet()) {
            $projetTuteure = $parcours->getProjetText();
        }

        if ($parcours->isHasMemoire()) {
            $terMemoire = $parcours->getMemoireText();
        }

        if ($parcours->isHasSituationPro()) {
            $situationsPros = $parcours->getSituationProText();
        }

        $maquettePdf = $this->router->generate(
            'app_parcours_mccc_export',
            [
                'parcours' => $parcours->getId(),
                '_format' => 'pdf'
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $organisationPedagogique = '';
        if ($parcours->isParcoursDefaut() === false && !empty($parcours->getRythmeFormationTexte())) {
            $organisationPedagogique .= $parcours->getRythmeFormationTexte();
        }
        elseif ($parcours->isParcoursDefaut() === true && !empty($parcours->getFormation()?->getRythmeFormationTexte())) {
            $organisationPedagogique .= $parcours->getFormation()?->getRythmeFormationTexte();
        }

        $organisationPedagogique 
            /* ---- Différence V2 --------
                Déplacé dans une balise extra
               ---------------------------
            .= "<h3>Stages</h3>"
            . $stage
            . "<br>"
            . "<h3>Projets tuteurés</h3>"
            . $projetTuteure
            . "<br>"
            . "<h3>Mise(s) en situations professionnelles</h3>"
            . $situationsPros
            . "<br>"
            . "<h3>TER/Mémoire de recherche</h3>"
            . $terMemoire
            . "<br><br>" 
            */
            .= "<h3>Calendrier universitaire</h3>"
            . $calendrierUniversitaire;

        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        if ($dpeParcours !== null 
            && ( $dpeParcours?->getEtatReconduction() !== TypeModificationDpeEnum::NON_OUVERTURE 
                && $dpeParcours?->getEtatReconduction() !== TypeModificationDpeEnum::NON_OUVERTURE_SES 
                && $dpeParcours?->getEtatReconduction() !== TypeModificationDpeEnum::NON_OUVERTURE_CFVU
                )
            // N'afficher le lien que sur l'année valide en cours (N)
            && $dpeParcours->getCampagneCollecte()?->getPublicationTag() === CampagnePublicationTagEnum::ANNEE_COURANTE->value
            ) {
            $organisationPedagogique .= "<h3>Maquette de la formation</h3>"
            . "<a href=\"$maquettePdf\" target=\"_blank\">Maquette et modalités de contrôle de la formation au format PDF</a>";
        }

        /**
         * 
         * --- Différence V2 ---
         *  Modifications des informations pratiques
         * ---------------------
         */

        // Informations pratiques
        $tdPresentationF = null;    
        if($parcours->getFormation()?->getTypeDiplome()?->getPresentationFormation()){
            $tdPresentationF = "<h3>Pour en savoir plus sur ce type de formation :</h3>"
            . $parcours->getFormation()?->getTypeDiplome()?->getPresentationFormation();
        }

        $devenirEtudiants = null;
        if ($parcours->getTypeDiplome()?->getInsertionProfessionnelle()) {
            $devenirEtudiants .= "<br><h2>Devenir des étudiants</h2>"
            . $parcours->getTypeDiplome()->getInsertionProfessionnelle() ?? '-';
        }

        $commonData = $this->getBasicInformationDataV2([
            'devenir_etudiants' => $devenirEtudiants,
            'type_diplome_presentation_formation' => $tdPresentationF
        ]);

        $organisationPedagogique .= $commonData['handicap'];

        /*
        if($etablissementInformation->getInformationsPratiques()){
            $informationsPratiques .= "<br>" . $etablissementInformation->getInformationsPratiques();
        }
        */

        $informationsPratiques = implode("", [
            $commonData['devenir_des_etudiants'],
            $commonData['savoir_plus_sur_ce_type_formation'],
            $commonData['savoir_plus_sur_orientation_insertion'],
            $commonData['savoir_plus_relations_internationales'],
            $commonData['associations_etudiantes']
        ]);
        $informationsPratiques = preg_replace("/<h1>/m", "<h3>", $informationsPratiques);
        $informationsPratiques = preg_replace("/<\/h1>/m", "</h3>", $informationsPratiques);

        // Modalités d'admission
        $admissionParcours = "<h3>Modalités d'admission</h3><br>";
        $admissionParcours .= $parcours->getTypeDiplome()?->getModalitesAdmission() ?? "";
        $admissionParcours .= "<h3>Calendrier d'inscription</h3>";
        $admissionParcours .= $etablissementInformation->getCalendrierInscription() ?? "";
        $admissionParcours .= $commonData['tarif_inscription'];

        // Poursuite d'études
        $poursuiteEtudes = $parcours->getPoursuitesEtudes() ?? '';
        $poursuiteEtudes .= "<br><h2>Débouchés</h2>";
        $poursuiteEtudes .= $parcours->getDebouches() ?? '-';
        $poursuiteEtudes .= "<br><h2>Codes ROME</h2>";
        $poursuiteEtudes .= "<ul>";
        foreach ($codesRome as $code) {
            $poursuiteEtudes .= "<li>{$code}</li>";
        }
        $poursuiteEtudes .= "</ul>";
        $poursuiteEtudes .= "<br><p>Le ROME est le répertoire des métiers et d'emplois de Pôle Emploi.</p>";

        // Poursuite d'études L.As (Licence Accès Santé)
        // Si LAS 1
        if ($parcours->getTypeParcours()->name === "TYPE_PARCOURS_LAS1") {
            $poursuiteEtudes .= "<br><h2>Accès Santé</h2>";
            $poursuiteEtudes .=
                "<h3>Après la 1ère année de licence \"Sciences pour la Santé\" - Accès santé </h3>"
                . $etablissementInformation->getTextLas1()
                . "<h3>Licence - Accès santé 2ème année (L.As 2)</h3>"
                . "<br>" . $etablissementInformation->getTextLas2()
                . "<h3>Licence - Accès santé 3ème année (L.As 3)</h3>"
                . $etablissementInformation->getTextLas3()
                . "<h3>Droit à la 2nde chance</h3>"
                . $etablissementInformation->getSecondeChance();
        }
        // Si LAS 2 ou 3
        if ($parcours->getTypeParcours()->name === "TYPE_PARCOURS_LAS23") {
            $poursuiteEtudes .= "<br><h2>Accès Santé</h2>";
            $poursuiteEtudes .=
                "<h3>Licence - Accès santé 2ème année (L.As 2)</h3>"
                . $etablissementInformation->getTextLas2()
                . "<h3>Licence - Accès santé 3ème année (L.As 3)</h3>"
                . $etablissementInformation->getTextLas3()
                . "<h3>Droit à la 2nde chance</h3>"
                . $etablissementInformation->getSecondeChance();
        }


        // Prérequis
        $prerequis = "";
        if ($parcours->getTypeDiplome()?->getPrerequisObligatoires()) {
            $prerequis .= '<strong>Prérequis obligatoires :</strong><br>';
            $prerequis .= $this->cleanString($parcours->getTypeDiplome()?->getPrerequisObligatoires());
        }
        $prerequis .= '<br><strong>Niveau de français requis :</strong><br>';
        $prerequis .= $parcours->getNiveauFrancais()?->libelle() ?? 'Aucune condition spécifique.';

        $prerequis .= '<br><br><strong>Prérequis recommandés :</strong><br>';
        $prerequis .= $this->cleanString($parcours->getPrerequis()) ?? 'Aucune condition spécifique.';

        // Rythme de la formation
        $rythmeFormation = 'Non renseigné.';

        // On dispose déjà des objectifs de formation si c'est un parcours par défaut
        // ---> XML 'objectif-formation'
        $modalitesAlternance = "La formation n'est pas dispensée en alternance";

        $referentsPedagogiques = [];
        if ($parcours->isParcoursDefaut()) { // si par défaut ou parcours uniquement alors RF et CO-RF
            // Contact de la formation
            if ($parcours->getFormation()?->getResponsableMention()) {
                $resp = $parcours->getFormation()?->getResponsableMention();
                $referentPedagogique = [
                    // Référent pédagogique
                    'type-contact' => 3,
                    'coordonnees' => [
                        'nom' => $resp ? $resp->getNom() : 'Non renseigné.',
                        'prenom' => $resp ? $resp->getPrenom() : 'Non renseigné.',
                        'courriel' => $resp ? $resp->getEmail() : 'Non renseigné.',
                    ]
                ];
                $referentsPedagogiques[] = $referentPedagogique;
            }
            if ($parcours->getFormation()?->getCoResponsable()) {
                $coResp = $parcours->getFormation()?->getCoResponsable();
                $coReferentPedagogique = [
                    // Référent pédagogique
                    'type-contact' => 3,
                    'coordonnees' => [
                        'nom' => $coResp ? $coResp->getNom() : 'Non renseigné.',
                        'prenom' => $coResp ? $coResp->getPrenom() : 'Non renseigné.',
                        'courriel' => $coResp ? $coResp->getEmail() : 'Non renseigné.',
                    ]
                ];
                $referentsPedagogiques[] = $coReferentPedagogique;
            }
            //gestion du parcours par défaut, il faut reprendre les infs de la formation dans ce cas
            $resultatsAttendus = $this->cleanString($parcours->getFormation()?->getResultatsAttendus()) ?? 'Non renseigné.';
            $contenuFormation = $this->cleanString($parcours->getFormation()?->getContenuFormation()) ?? 'Non renseigné.';
            $objectifFormation = $this->cleanString($parcours->getFormation()?->getObjectifsFormation()) ?? 'Non renseigné.';
            if ($parcours->getFormation()?->getRythmeFormation() !== null && $parcours->getFormation()?->getRythmeFormation()->getLibelle() !== null) {
                $rythmeFormation = $parcours->getFormation()?->getRythmeFormation()->getLibelle();
            }
            $localisation = $parcours->getFormation()?->getLocalisationMention()->first();
            if (!empty($parcours->getFormation()?->getModalitesAlternance())) {
                $modalitesAlternance = $this->cleanString($parcours->getFormation()?->getModalitesAlternance());
            }
        } else {
            // Contact de la formation
//            if ($parcours->getFormation()?->getParcours()->count() === 1) {
//                // Un seul parcours, on reprend les infos de la formation
//                if ($parcours->getFormation()?->getResponsableMention()) {
//                    $resp = $parcours->getFormation()?->getResponsableMention();
//                    $referentPedagogique = [
//                        // Référent pédagogique
//                        'type-contact' => 3,
//                        'coordonnees' => [
//                            'nom' => $resp ? $resp->getNom() : 'Non renseigné.',
//                            'prenom' => $resp ? $resp->getPrenom() : 'Non renseigné.',
//                            'courriel' => $resp ? $resp->getEmail() : 'Non renseigné.',
//                        ]
//                    ];
//                    $referentsPedagogiques[] = $referentPedagogique;
//                }
//                if ($parcours->getFormation()?->getCoResponsable()) {
//                    $coResp = $parcours->getFormation()?->getCoResponsable();
//                    $coReferentPedagogique = [
//                        // Référent pédagogique
//                        'type-contact' => 3,
//                        'coordonnees' => [
//                            'nom' => $coResp ? $coResp->getNom() : 'Non renseigné.',
//                            'prenom' => $coResp ? $coResp->getPrenom() : 'Non renseigné.',
//                            'courriel' => $coResp ? $coResp->getEmail() : 'Non renseigné.',
//                        ]
//                    ];
//                    $referentsPedagogiques[] = $coReferentPedagogique;
//                }
//            } else {
                if ($parcours->getFormation()?->getResponsableMention()) {
                    $resp = $parcours->getFormation()?->getResponsableMention();
                    $referentPedagogique = [
                        // Référent pédagogique
                        'type-contact' => 3,
                        'coordonnees' => [
                            'nom' => $resp ? $resp->getNom() : 'Non renseigné.',
                            'prenom' => $resp ? $resp->getPrenom() : 'Non renseigné.',
                            'courriel' => $resp ? $resp->getEmail() : 'Non renseigné.',
                        ]
                    ];
                    $referentsPedagogiques[$resp?->getId()] = $referentPedagogique;
                }
                if ($parcours->getFormation()?->getCoResponsable()) {
                    $coResp = $parcours->getFormation()?->getCoResponsable();
                    $coReferentPedagogique = [
                        // Référent pédagogique
                        'type-contact' => 3,
                        'coordonnees' => [
                            'nom' => $coResp ? $coResp->getNom() : 'Non renseigné.',
                            'prenom' => $coResp ? $coResp->getPrenom() : 'Non renseigné.',
                            'courriel' => $coResp ? $coResp->getEmail() : 'Non renseigné.',
                        ]
                    ];
                    $referentsPedagogiques[$coResp?->getId()] = $coReferentPedagogique;
                }
                if ($parcours->getRespParcours()) {
                    $respParcours = $parcours->getRespParcours();
                    $referentPedagogiqueParcours = [
                        // Référent pédagogique
                        'type-contact' => 3,
                        'coordonnees' => [
                            'nom' => $respParcours ? $respParcours->getNom() : 'Non renseigné.',
                            'prenom' => $respParcours ? $respParcours->getPrenom() : 'Non renseigné.',
                            'courriel' => $respParcours ? $respParcours->getEmail() : 'Non renseigné.',
                        ]
                    ];
                    $referentsPedagogiques[$respParcours?->getId()] = $referentPedagogiqueParcours;
                }
                if ($parcours->getCoResponsable()) {
                    $coRespParcours = $parcours->getCoResponsable();
                    $coReferentPedagogiqueParcours = [
                        // Référent pédagogique
                        'type-contact' => 3,
                        'coordonnees' => [
                            'nom' => $coRespParcours ? $coRespParcours->getNom() : 'Non renseigné.',
                            'prenom' => $coRespParcours ? $coRespParcours->getPrenom() : 'Non renseigné.',
                            'courriel' => $coRespParcours ? $coRespParcours->getEmail() : 'Non renseigné.',
                        ]
                    ];
                    $referentsPedagogiques[$coRespParcours?->getId()] = $coReferentPedagogiqueParcours;
                }
            // }


            $resultatsAttendus = $this->cleanString($parcours->getResultatsAttendus()) ?? 'Non renseigné.';
            $contenuFormation = $this->cleanString($parcours->getContenuFormation()) ?? 'Non renseigné.';
            $objectifFormation = $this->cleanString($parcours->getObjectifsParcours()) ?? 'Non renseigné.';
            $localisation = $parcours->getLocalisation();
            if ($parcours->getRythmeFormation() !== null && $parcours->getRythmeFormation()->getLibelle() !== null) {
                $rythmeFormation = $parcours->getRythmeFormation()->getLibelle();
            }
            // Modalités de l'alternance

            if (!empty($parcours->getModalitesAlternance())) {
                $modalitesAlternance = $this->cleanString($parcours->getModalitesAlternance());
            }
        }

        $libelleMillesimeCatalogue = GetDpeParcours::getFromParcours($parcours)->getCampagneCollecte()->getLibelle();
        $libelleMillesimeCatalogue = "Rentrée " . $libelleMillesimeCatalogue;

        // EXTRAS
        $extraArray = [
            'description-haut' => $this->cleanString($libelleMillesimeCatalogue . " " . $parcours->getDescriptifHautPageAffichage()),
            'description-bas' => $this->cleanString($parcours->getDescriptifBasPageAffichage() ?? $etablissementInformation->getDescriptifBasPage()),
            'competences-acquises' => $this->cleanString($competencesAcquisesExtra),
            'organisation-pedagogique' => $this->cleanString($organisationPedagogique),
            'poursuite-etudes' => $this->cleanString($poursuiteEtudes),
            'informations-pratiques' => $this->cleanString($informationsPratiques),
            'admission' => $this->cleanString($admissionParcours),
            'formation-continue-et-apprentissage' => [],
        ];

        // Nouvelle balise extra - organisation pédagogique
        $extraContenuFormation =
            "<h3>Stages</h3>"
            . $stage
            . "<br>"
            . "<h3>Projets tuteurés</h3>"
            . $projetTuteure
            . "<br>"
            . "<h3>Mise(s) en situations professionnelles</h3>"
            . $situationsPros
            . "<br>"
            . "<h3>TER/Mémoire de recherche</h3>"
            . $terMemoire
            . "<br><br>";

        $extraArray['extra-contenu-formation'] = $extraContenuFormation;

        // Description de la mention
        if($parcours->isParcoursDefaut() === false){
            $extraArray['description-mention'] = $this->cleanString($parcours->getFormation()?->getObjectifsFormation());
        }

        if(!($localisation instanceof Ville)){
            // Si null, on affiche 'Non renseigné' plus bas.
            // Utile pour les parcours en construction
            $localisation = null;
        }

        // Génération du XML
        $encoder = new XmlEncoder([
        ]);
        $xml = $encoder->encode([
            // Attribut de l'élément racine
            '@xmlns' => 'http://lheo.gouv.fr/2.3',
            '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            '@xsi:schemaLocation' => 'http://lheo.gouv.fr/2.3/lheo.xsd',
            'offres' => [
                'formation' => [
                    'domaine-formation' => [
                        // Formacode et code nsf optionnels
                        // 'code-FORMACODE' => '',
                        // 'code-NSF' => '',
                        'code-ROME' => $codesRomeLheo,
                    ],
                    'intitule-formation' => $this->cleanString($intituleFormation),
                    'objectif-formation' => $objectifFormation,
                    'resultats-attendus' => $resultatsAttendus,
                    'contenu-formation' => $contenuFormation,
                    'certifiante' => 1,
                    'contact-formation' => $referentsPedagogiques,
                    'parcours-de-formation' => 1,
                    'code-niveau-entree' => $niveauEntree,
                    'objectif-general-formation' => 6, // (Certification) A CHANGER OU FAIRE EVOLUER
                    'certification' => [
                        'code-RNCP' => $rncp
                    ],
                    'code-niveau-sortie' => $parcours->getFormation()?->getNiveauSortie()->value ?? 0, // A CHANGER
                    'action' => [

                        'rythme-formation' => $rythmeFormation,
                        'code-public-vise' => '00000',
                        'niveau-entree-obligatoire' => 1,
                        'modalites-alternance' => $modalitesAlternance,
                        'modalites-enseignement' => $parcours->getModalitesEnseignement() ? $parcours->getModalitesEnseignement()->value : 1,
                        'conditions-specifiques' => $prerequis,
                        'prise-en-charge-frais-possible' => 1, // A CHANGER - 1 oui | 0 non
                        'modalites-entrees-sorties' => 0,
                        'duree-cycle' => $dureeCycle,
                        'session' => [
                            'periode' => [
                                'debut' => '00000000',
                                'fin' => '00000000'
                            ],
                            'adresse-inscription' => [
                                'adresse' => [
                                    $adresseSiegeURCA
                                ]
                            ],
                            'modalites-inscription' => $localisation?->getEtablissement()?->getEtablissementInformation()?->getTarifInscription() ?? "Non renseigné."
                        ],
                        'adresse-information' => ['adresse' => $adresseSiegeURCA],
                        'restauration' => $localisation?->getEtablissement()?->getEtablissementInformation()?->getRestauration() ?? "Non renseigné.",
                        'hebergement' => $localisation?->getEtablissement()?->getEtablissementInformation()?->getHebergement() ?? "Non renseigné.",
                        'transport' => $localisation?->getEtablissement()?->getEtablissementInformation()?->getTransport() ?? "Non renseigné"

                    ],
                    'organisme-formation-responsable' => [
                        'numero-activite' => $localisation?->getEtablissement()?->getNumeroActivite() ?? '00000000000',
                        'SIRET-organisme-formation' => ['SIRET' => $localisation?->getEtablissement()?->getNumeroSIRET() ?? '00000000000000'],
                        'nom-organisme' => 'Université de Reims Champagne-Ardenne',
                        'raison-sociale' => 'Université de Reims Champagne-Ardenne',
                        'coordonnees-organisme' => [
                            // Coordonnées de l'URCA
                            'coordonnees' => $coordonneesComposante
                        ],
                        'contact-organisme' => $contactsOrganismes
                    ],
                    'sous-modules' => [
                        'sous-module' => [
                            'reference-module' => "<p>/</p>",
                            'type-module' => 0
                        ]
                    ],
                    'extras' => [
                        'extra' => $extraArray,
                    ]
                ]
            ]

        ], 'xml', $contextOptions);

        return $xml;
    }

    #[Override]
    public function isValidLHEO(Parcours $parcours) : bool {
        return parent::validateLheoSchema($this->generateLheoXMLFromParcours($parcours));
    }

    public function getBasicInformationDataV2(array $linkedData) : array {
        return [
            // à mettre dans : <extras><extra><organisation-pedagogique>
            'handicap' =>   "<h3><strong>Pour tout renseignement sur les aménagements proposés par la mission handicap</strong></h3>
                            <ul><li><a href=\"#\">La mission handicap</a></li></ul>
                            <div>Vous avez des besoins d'aménagements d'études et d'examens, la Mission Handicap vous accompagne tout 
                            au long de votre cursus universitaire.<br>Elle vous renseigne sur tous les aspects de la vie universitaire : 
                            déroulement des études, accessibilité des lieux universitaires, participation à la vie des campus, accès aux ressources 
                            de la Bibliothèque Universitaire.<br>Pour toute demande ou information : 
                            <a href=\"mailto:handicap@univ-reims.fr\">handicap@univ-reims.fr</a><br><br></div>",
            // à mettre dans : <extras><extra><admission>
            'tarif_inscription' => "<br><div><a href=\"#\">&nbsp;Lien vers la page présentant 
                                    les tarifs d'inscription&nbsp;</a></div>",
            // à mettre dans <extras><extra><informations-pratiques>
            'devenir_des_etudiants' => $linkedData['devenir_etudiants'] ?? "",
            // à mettre dans <extras><extra><informations-pratiques>
            'savoir_plus_sur_ce_type_formation' => $linkedData['type_diplome_presentation_formation'] ?? "",
            // à mettre dans <extras><extra><informations-pratiques>
            'savoir_plus_sur_orientation_insertion' => "<h3><strong>Pour en savoir plus sur l'orientation et l'insertion professionnelle :</strong></h3>
                <ul><li><a href=\"#\">La Mission Orientation du Service d'Accompagnement des Etudiants (SAE)</a></li>
                <li><a href=\"#\">L'insertion professionnelle</a></li></ul>",
            'savoir_plus_relations_internationales' => "<h3><strong>Pour en savoir plus sur les relations internationales à l'Université :</strong></h3>
                <ul><li><a href=\"#\">Direction des Relations Extérieures et du Développement International (DREDI)</a></li>
                <li><a href=\"#\">Partir à l'étranger</a></li></ul>",
            'associations_etudiantes' => "<h3><strong>Lien vers les associations étudiantes :</strong></h3>
                <ul><li><a href=\"#\">Associations étudiantes</a></li></ul>",
        ];
    }
}