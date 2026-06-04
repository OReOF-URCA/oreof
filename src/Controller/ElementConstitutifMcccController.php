<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FicheMatiereController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\GetElementConstitutif;
use App\Classes\JsonReponse;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Mccc;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Entity\TypeDiplome;
use App\Entity\TypeEpreuve;
use App\Events\McccUpdateEvent;
use App\Repository\TypeEpreuveRepository;
use App\Service\McccCompletionChecker;
use App\Service\TypeDiplomeResolver;
use App\Service\VersioningParcours;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\Utils\Access;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[Route('/element/constitutif')]
class ElementConstitutifMcccController extends AbstractController
{
    //todo: à revoir, des cas ne sont pas bien gérés avec le passage des datas sur les fiches. Exemple un EC à choix restreint devoir toujours avec les MCCC sur l'EC, mais formulaire innaccessible parfois;
    public function __construct(private readonly TypeDiplomeResolver $typeDiplomeResolver)
    {
    }

    #[Route('/{id}/mccc-ec/{parcours}', name: 'app_element_constitutif_mccc', methods: ['GET', 'POST'])]
    public function mcccEc(
        EventDispatcherInterface $eventDispatcher,
        EntityManagerInterface       $entityManager,
        Request                      $request,
        ElementConstitutif           $elementConstitutif,
        Parcours                     $parcours
    ): Response {
        //todo: sans doute à simplifier. Les cas sont : EC parent, EC enfant, EC propriétaire de la fiche, EC raccroché ? récupérer les infos du badge ?l
        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        $isParcoursProprietaire = (($elementConstitutif->getFicheMatiere()?->getParcours()?->getId() === $parcours->getId()) || $elementConstitutif->getNatureUeEc()?->isLibre() || ($elementConstitutif->getNatureUeEc()?->isChoix() && $elementConstitutif->getParcours()?->getId() === $parcours->getId()));

        if ($dpeParcours === null) {
            throw new RuntimeException('DPE Parcours non trouvé');
        }

        $formation = $parcours?->getFormation();
        if ($formation === null) {
            throw new RuntimeException('Formation non trouvée');
        }

        $typeD = $this->typeDiplomeResolver->getFromParcours($parcours);

        $raccroche = $elementConstitutif->getFicheMatiere()?->getParcours()?->getId() !== $parcours->getId();
        $getElement = new GetElementConstitutif($elementConstitutif, $parcours);
        $typeMccc = $getElement->getTypeMcccFromFicheMatiere();
        $typeEpreuve = $getElement->getTypeMcccFromFicheMatiere();

        /**
         * Contrôles du formulaire
         */
        if ($request->request->has('ec_step4') && array_key_exists('quitus', $request->request->all()['ec_step4'])) {
            $argumentQuitus = $request->request->all()['ec_step4']['quitus_argument'] ?? "";
            if (mb_strlen($argumentQuitus) < 15) {
                return $this->json(
                    ['message' => "L'argumentaire du quitus doit faire au moins 15 caractères."],
                    500,
                    ['Content-Type' => 'application/json']
                );
            }
        }
        // Contrôle de la justification de MCCC
        $typeEpreuvesArray = $typeD->getTypeEpreuves();
        $minLengthJustification = 15;

        foreach ($request->request->all() as $fieldName => $fieldValue) {
            if (preg_match('/typeEpreuve_s([0-9])_ct([0-9])/', $fieldName, $matches) === 1) {
//                $hasJustification = array_values(
//                    array_filter(
//                        $typeEpreuvesArray,
//                        fn($type) => $type->getId() === (int)$fieldValue
//                    )
//                )[0]->hasJustification();

                $filtered = array_values(
                    array_filter(
                        $typeEpreuvesArray,
                        fn($type) => $type->getId() === (int)$fieldValue
                    )
                );
                $hasJustification = false;
                if (isset($filtered[0])) {
                    $hasJustification = $filtered[0]->hasJustification();
                    if ($hasJustification && mb_strlen($request->request->all()["justification_s{$matches[1]}_ct{$matches[2]}"]) < $minLengthJustification) {
                        return $this->json(
                            ['message' => "La justification d'un MCCC doit être supérieure à {$minLengthJustification} caractères."],
                            500,
                            ['Content-Type' => 'application/json']
                        );
                    }
                }
            }
        }

        if ($this->isGranted(
                'EDIT',
                [
                    'route' => 'app_parcours',
                    'subject' => $dpeParcours,
                ]
            ) && Access::isAccessible($dpeParcours)) {
            if ($request->isMethod('POST')) {
                $newMcccToText = '';
                $newEcts = '';
                $originalMcccToText = $this->mcccToTexte($getElement->getMcccsFromFicheMatiereCollection());
                $originalEcts = $getElement->getFicheMatiereEcts() ?? '';
                $event = new McccUpdateEvent($elementConstitutif, $parcours);

                if ($request->request->has('ec_step4') && array_key_exists('ects', $request->request->all()['ec_step4'])) {
                    if ($elementConstitutif->isEctsSpecifiques() === true &&
                        $elementConstitutif->getEcParent() === null &&
                        $elementConstitutif->getFicheMatiere()?->isEctsImpose() === false) {
                        $elementConstitutif->setEcts((float)$request->request->all()['ec_step4']['ects']);
                        $newEcts = $elementConstitutif->getEcts();
                    } elseif ($elementConstitutif->getNatureUeEc()?->isLibre() && $elementConstitutif->getEcParent() === null) {
                        $elementConstitutif->setEcts((float)$request->request->all()['ec_step4']['ects']);
                        $newEcts = $elementConstitutif->getEcts();
                    } elseif ($elementConstitutif->getNatureUeEc()?->isChoix() && $elementConstitutif->getEcParent() === null) {
                        //cas de l'EC parent d'un choix. ECTS géré par le choix
                        $elementConstitutif->setEcts((float)$request->request->all()['ec_step4']['ects']);
                    } elseif ($elementConstitutif->getEcParent() !== null) {
                        $elementConstitutif->setEcts($elementConstitutif->getEcParent()?->getEcts());
                        $elementConstitutif->setEctsSpecifiques(true); //du coup ca devient spécifique ?
                        $newEcts = $elementConstitutif->getEcts() ?? '';
                    } else {
                        $elementConstitutif->getFicheMatiere()?->setEcts((float)$request->request->all()['ec_step4']['ects']);
                        $newEcts = $elementConstitutif->getFicheMatiere()?->getEcts() ?? '';
                    }

                    //evenement pour ECTS sur EC mis à jour
                    $event->setNewEcts($originalEcts, $newEcts);

                    $entityManager->flush();
                }

                if ($elementConstitutif->isMcccSpecifiques() === false
                    && $isParcoursProprietaire) {
                    //MCCC sur fiche matière
                    if ($elementConstitutif->getFicheMatiere() !== null) {
                        $fm = $elementConstitutif->getFicheMatiere();

                        if ($request->request->has('ec_step4') && array_key_exists('quitus', $request->request->all()['ec_step4'])) {
                            $fm->setQuitus((bool)$request->request->all()['ec_step4']['quitus']);
                            $fm->setQuitusText($request->request->all()['ec_step4']['quitus_argument']);
                        }
                        // Si la checkbox est décochée, 'quitus' ne fait pas partie de la requête POST
                        elseif ($request->request->has('ec_step4')
                            && array_key_exists('quitus', $request->request->all()['ec_step4']) === false
                            && $fm->isQuitus() !== false
                        ) {
                            $fm->setQuitus(false);
                            $fm->setQuitusText(null);
                        }

                        if ($request->request->get('choix_type_mccc') !== $fm->getTypeMccc()) {
                            $fm->setTypeMccc($request->request->get('choix_type_mccc'));
                            $entityManager->flush();
                            $typeD->clearMcccs($fm);
                        }

                        $typeD->saveMcccs($fm, $request->request);
                        $newMcccToText = $this->mcccToTexte($fm->getMcccs());
                    } elseif ($elementConstitutif->getNatureUeEc()?->isLibre() || ($elementConstitutif->getNatureUeEc()?->isChoix())) {
                        //todo: a refactor
                        if ($request->request->has('ec_step4') && array_key_exists('quitus', $request->request->all()['ec_step4'])) {
                            $elementConstitutif->setQuitus((bool)$request->request->all()['ec_step4']['quitus']);
                            $elementConstitutif->setQuitusText($request->request->all()['ec_step4']['quitus_argument']);
                        } // Si la checkbox est décochée, 'quitus' ne fait pas partie de la requête POST
                        elseif ($request->request->has('ec_step4')
                            && array_key_exists('quitus', $request->request->all()['ec_step4']) === false
                            && $elementConstitutif->isQuitus() !== false
                        ) {
                            $elementConstitutif->setQuitus(false);
                            $elementConstitutif->setQuitusText(null);
                        }

                        if ($request->request->has('choix_type_mccc') && $request->request->get('choix_type_mccc') !== $elementConstitutif->getTypeMccc()) {
                            $elementConstitutif->setTypeMccc($request->request->get('choix_type_mccc'));
                            $entityManager->flush();
                            $typeD->clearMcccs($elementConstitutif);
                        }
                        $typeD->saveMcccs($elementConstitutif, $request->request);
                        $newMcccToText = $this->mcccToTexte($elementConstitutif->getMcccs());
                    }
                } else {
                    //MCCC sur EC
                    if ($elementConstitutif->isMcccSpecifiques() === true || $elementConstitutif->getNatureUeEc()?->isLibre() || ($elementConstitutif->getNatureUeEc()?->isChoix())) {
                        if ($request->request->has('ec_step4') && array_key_exists('quitus', $request->request->all()['ec_step4'])) {
                            $elementConstitutif->setQuitus((bool)$request->request->all()['ec_step4']['quitus']);
                            $elementConstitutif->setQuitusText($request->request->all()['ec_step4']['quitus_argument']);
                        }

                        // Si la checkbox est décochée, 'quitus' ne fait pas partie de la requête POST
                        elseif ($request->request->has('ec_step4')
                            && array_key_exists('quitus', $request->request->all()['ec_step4']) === false
                            && $elementConstitutif->isQuitus() !== false
                        ) {
                            $elementConstitutif->setQuitus(false);
                            $elementConstitutif->setQuitusText(null);
                        }

                        if ($request->request->has('choix_type_mccc') && $request->request->get('choix_type_mccc') !== $elementConstitutif->getTypeMccc()) {
                            $elementConstitutif->setTypeMccc($request->request->get('choix_type_mccc'));
                            $entityManager->flush();
                            $typeD->clearMcccs($elementConstitutif);
                        }
                        $typeD->saveMcccs($elementConstitutif, $request->request);
                        $newMcccToText = $this->mcccToTexte($elementConstitutif->getMcccs());
                    }
                }

                if ($request->request->has('ec_step4') && array_key_exists('mcccEnfantsIdentique', $request->request->all()['ec_step4'])) {
                    if ($elementConstitutif->getEcParent() !== null) {
                        $elementConstitutif->getEcParent()->setMcccEnfantsIdentique((bool)$request->request->all()['ec_step4']['mcccEnfantsIdentique']);
                    } else {
                        $elementConstitutif->setMcccEnfantsIdentique((bool)$request->request->all()['ec_step4']['mcccEnfantsIdentique']);
                    }
                } else {
                    $elementConstitutif->setMcccEnfantsIdentique(false);
                }
                $entityManager->flush();

                //evenement pour MCCC sur EC mis à jour

                $event->setNewMccc($originalMcccToText, $newMcccToText);

                // on emet l'évent vers les abonnés que si c'est un parcours propriétaire
                if ($isParcoursProprietaire) {
                    $eventDispatcher->dispatch($event, McccUpdateEvent::UPDATE_MCCC);
                }

                return $this->json(true);
            }

            return $this->render('element_constitutif/_mcccEcModal.html.twig', [
                'isMcccImpose' => $elementConstitutif->getFicheMatiere()?->isMcccImpose(),
                'isEctsImpose' => $elementConstitutif->getFicheMatiere()?->isEctsImpose(),
                'typeEpreuve' => $typeEpreuve,
                'typeMccc' => $typeMccc,
                'typeEpreuves' => $typeD->getTypeEpreuves(),
                'ec' => $elementConstitutif,
                'typeDiplome' => $typeD,
                'ects' => $getElement->getFicheMatiereEcts(),
                'templateForm' => $typeD::TEMPLATE_FORM_MCCC,
                'mcccs' => $getElement->getMcccsFromFicheMatiereCollection(),
                'wizard' => false,
                'parcours' => $parcours,
                'isParcoursProprietaire' => $isParcoursProprietaire,
                'raccroche' => $raccroche
            ]);
        }

        $ec = new GetElementConstitutif($elementConstitutif, $parcours);
        $ects = $ec->getFicheMatiereEcts();

        return $this->render('element_constitutif/_mcccEcNonEditable.html.twig', ['isMcccImpose' => $elementConstitutif->getFicheMatiere()?->isMcccImpose(),
        'isEctsImpose' => $elementConstitutif->getFicheMatiere()?->isEctsImpose(),
            'typeMccc' => $typeMccc,
            'typeEpreuves' => $typeD->getTypeEpreuves(),
        'ec' => $elementConstitutif,
        'ects' => $ects,
            'mcccs' => $typeD->getDisplayMccc($getElement->getMcccsFromFicheMatiere($typeD), $typeMccc),
            'typeDiplome' => $typeD,
            'templateForm' => $typeD::TEMPLATE_FORM_MCCC
        ]);
    }

    #[Route('/{id}/mccc-ec/{parcours}/recompute-validity', name: 'app_element_constitutif_mccc_recompute', methods: ['POST'])]
    public function recomputeMcccValidity(
        ElementConstitutif     $elementConstitutif,
        Parcours               $parcours,
        McccCompletionChecker  $mcccCompletionChecker,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($elementConstitutif->getParcours()?->getId() !== $parcours->getId()) {
            return JsonReponse::error('EC / parcours incohérents.');
        }

        $owner = $this->resolveMcccOwner($elementConstitutif);
        $owner->setEtatMccc($mcccCompletionChecker->isCompletedForOwner($owner) ? 'Complet' : 'A saisir');

        $entityManager->persist($owner);
        $entityManager->flush();

        return JsonReponse::success('Contrôle MCCC recalculé.');
    }

    #[Route('/{id}/mccc-ec/{parcours}/non-editable', name: 'app_element_constitutif_mccc_non_editable', methods: ['GET', 'POST'])]
    public function mcccEcNonEditable(
        ElementConstitutif           $elementConstitutif,
        Parcours                     $parcours,
        VersioningParcours           $versioningParcours
    ): Response {
//todo: gérer par type de diplôme
        $dpeParcours = GetDpeParcours::getFromParcours($parcours);

        if ($dpeParcours === null) {
            throw new RuntimeException('DPE Parcours non trouvé');
        }

        $formation = $parcours?->getFormation();
        if ($formation === null) {
            throw new RuntimeException('Formation non trouvée');
        }
        $typeDiplome = $formation->getTypeDiplome();
        if ($typeDiplome === null) {
            throw new RuntimeException('Type de diplome non trouvé');
        }
        $typeD = $this->typeDiplomeResolver->get($typeDiplome);

        $getElement = new GetElementConstitutif($elementConstitutif, $parcours);
        $typeMccc = $getElement->getTypeMcccFromFicheMatiere();
        $ects = $getElement->getFicheMatiereEcts();

        $lastVersion = $versioningParcours->getLastVersionOrLastYearCfvu($parcours);

        $typeMcccLibelle = [
            'ct' => 'Contrôle Terminal',
            'cc' => 'Contrôle Continu',
            'cci' => 'Contrôle Continu Intégral',
            'cc_ct' => 'Contrôle Continu + Contrôle Terminal'
        ];

        return $this->render('element_constitutif/_mcccEcNonEditable.html.twig', [
            'isMcccImpose' => $elementConstitutif->getFicheMatiere()?->isMcccImpose(),
            'isEctsImpose' => $elementConstitutif->getFicheMatiere()?->isEctsImpose(),
            'typeMccc' => $typeMccc,
            'typeEpreuves' => $typeD->getTypeEpreuves(), //todo: @deprecated => si nouvel affichage validé
            'typeMcccLibelle' => $typeMcccLibelle,
            'ec' => $elementConstitutif,
            'ects' => $ects,
            'typeDiplome' => $typeD,
            'templateForm' => $typeD::TEMPLATE_FORM_MCCC,
            'mcccs' => $typeD->getDisplayMccc($getElement->getMcccsFromFicheMatiere($typeD), $typeMccc ?? ''),
            'isFromVersioning' => 'false',
            'lastVersion' => $lastVersion,
            'libelleQuelleVersion' => 'Version actuellement saisie en attente de validation',
            'parcoursId' => $parcours->getId()
        ]);
    }

    #[Route('/{elementConstitutif}/mccc-ec-versioning/{parcoursVersioning}/non-editable/{isFromVersioning}', name: 'app_element_constitutif_mccc_versioning')]
    public function mcccVersioning(
        string $isFromVersioning,
        ?ElementConstitutif $elementConstitutif,
        ParcoursVersioning $parcoursVersioning,
        VersioningParcours $versioningParcours,
        EntityManagerInterface $entityManager
    ): Response
    {
        if ($elementConstitutif === null) {
            return $this->render("element_constitutif/_versioning_ecNotFound.html.twig", [
                'ecNotFound' => true
            ]);
        }

        $versionData = $versioningParcours->loadParcoursFromVersion($parcoursVersioning);
        $libelleTypeDiplome = $versionData['parcours']->getFormation()->getTypeDiplome()->getLibelle();
        $typeDiplome = $entityManager->getRepository(TypeDiplome::class)->findOneBy(['libelle' => $libelleTypeDiplome]);

        if ($typeDiplome === null) {
            throw new RuntimeException('Type de diplome non trouvé');
        }

        $typeD = $this->typeDiplomeResolver->get($typeDiplome);
        $templateForm = $this->typeDiplomeResolver->get($typeDiplome)::TEMPLATE_FORM_MCCC;
        $typeEpreuveDiplome = $entityManager->getRepository(TypeEpreuve::class)->findByTypeDiplome($typeDiplome);

        $getElement = new GetElementConstitutif($elementConstitutif, $parcoursVersioning->getParcours());
        $typeMccc = $getElement->getTypeMcccFromFicheMatiere();
        $ectsActuel = $getElement->getFicheMatiereEcts();
        $typeMcccLibelle = [
            'ct' => 'Contrôle Terminal',
            'cc' => 'Contrôle Continu',
            'cci' => 'Contrôle Continu Intégral',
            'cc_ct' => 'Contrôle Continu + Contrôle Terminal'
        ];

        // On rassemble toutes les UE
        $ueArray = array_map(function ($semestre) {
            $ueFromSemestre = [...$semestre->ues];
            $uesEnfants = array_filter(
                array_map(
                    fn ($ue) => $ue->uesEnfants(),
                    $ueFromSemestre
                ),
                fn ($array) => count($array) > 0
            );
            $uesEnfants = array_merge(...$uesEnfants);
            $uesEnfantsDeuxiemeNiveau = array_filter(
                array_map(
                    fn ($ueEnfant) => $ueEnfant->uesEnfants(),
                    $uesEnfants
                ),
                fn ($array) => count($array) > 0
            );
            $uesEnfantsDeuxiemeNiveau = array_merge(...$uesEnfantsDeuxiemeNiveau);
            return array_merge($ueFromSemestre, $uesEnfants, $uesEnfantsDeuxiemeNiveau);
        }, $versionData['dto']->semestres);

        $ueArray = array_merge(...$ueArray);

        // Et on cherche le StructureEc de la version qui a le même Id
        // ou l'ID de l'année dernière
        $structureEc = null;
        foreach ($ueArray as $structUe) {
            foreach ($structUe->elementConstitutifs as $structEc) {
                if (($structEc->elementConstitutif->getDeserializedId() === $elementConstitutif->getId())
                    || ($structEc->elementConstitutif->getDeserializedId() === $elementConstitutif->getEcOrigineCopie()->getId())
                ) {
                    $structureEc = $structEc;
                }
                foreach ($structEc->elementsConstitutifsEnfants as $structEcEnfant) {
                    if (($structEcEnfant->elementConstitutif->getDeserializedId() === $elementConstitutif->getId())
                        || ($structEcEnfant->elementConstitutif->getDeserializedId() === $elementConstitutif->getEcOrigineCopie()->getId())
                    ) {
                        $structureEc = $structEcEnfant;
                    }
                }
            }
        }

        if ($structureEc === null) {
            return $this->render("element_constitutif/_versioning_ecNotFound.html.twig", [
                'structureEcNotFound' => true
            ]);
        }

        // Denormalizer - récupération du bon type de MCCC
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $extractors = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $serializer = new Serializer(
            [new DateTimeNormalizer(), new ObjectNormalizer($classMetadataFactory, propertyTypeExtractor: $extractors)]
        );
        $restoreMcccType = function (array $mccc) use ($serializer) {
            return $serializer->denormalize($mccc, Mccc::class);
        };
        // Mise en forme du tableau des MCCC
        $tabMcccVersioning = [];
        if ($structureEc->typeMccc === 'cci') {
            foreach ($structureEc->mcccs as $mccc) {
                $tabMcccVersioning[$mccc['numeroSession']] = $restoreMcccType($mccc);
            }
        } else {
            foreach ($structureEc->mcccs as $mccc) {
                if ($mccc['secondeChance']) {
                    $tabMcccVersioning[3]['chance'] = $restoreMcccType($mccc);
                } elseif ($mccc['controleContinu'] === true && $mccc['examenTerminal'] === false) {
                    $tabMcccVersioning[$mccc['numeroSession']]['cc'][$mccc['numeroEpreuve'] ?? 1] = $restoreMcccType($mccc);
                } elseif ($mccc['controleContinu'] === false && $mccc['examenTerminal'] === true) {
                    $tabMcccVersioning[$mccc['numeroSession']]['et'][$mccc['numeroEpreuve'] ?? 1] = $restoreMcccType($mccc);
                }
            }
        }
        // Nouvelle mise en forme pour le template
        $tabMcccVersioning = $typeD->getDisplayMccc($tabMcccVersioning, $structureEc->typeMccc);
        // MCCC Actuels
        $getElement = new GetElementConstitutif($elementConstitutif, $parcoursVersioning->getParcours());
        $tabMcccActuels = $typeD->getDisplayMccc($getElement->getMcccsFromFicheMatiere($typeD), $typeMccc);

        $mcccsToDisplay = $tabMcccActuels;
        if ($isFromVersioning === 'true' || ($structureEc->typeMccc !== $typeMccc)) {
            $mcccsToDisplay = $tabMcccVersioning;
        }

        return $this->render('element_constitutif/_mcccEcNonEditable.html.twig', [
            'isMcccImpose' => $structureEc->elementConstitutif->getFicheMatiere()?->isMcccImpose(),
            'isEctsImpose' => $structureEc->elementConstitutif->getFicheMatiere()?->isEctsImpose(),
            'typeMccc' => $structureEc->typeMccc, //Versioning
            'typeMcccActuel' => $typeMccc, // Actuel
            'typeEpreuves' => $typeEpreuveDiplome,
            'typeMcccLibelle' => $typeMcccLibelle,
            'ec' => $structureEc->elementConstitutif,
            'ects' => $ectsActuel, // Actuel
            'typeDiplome' => $typeD,
            'ectsVersioning' => $structureEc->heuresEctsEc->ects, // Versioning
            'templateForm' => $templateForm,
            'mcccVersioning' => $tabMcccVersioning, // Versioning
            'mcccs' => $mcccsToDisplay,
            'isMcccFromVersion' => true,
            'parcoursId' => $parcoursVersioning->getParcours()?->getId(),
            'isFromVersioning' => $isFromVersioning,
            'libelleQuelleVersion' => 'Comparaison des versions'
        ]);
    }

    #[Route('/{id}/mccc-ec-but', name: 'app_element_constitutif_mccc_but', methods: ['GET', 'POST'])]
    public function mcccEcBut(
        TypeEpreuveRepository $typeEpreuveRepository,
        Request               $request,
        FicheMatiere          $ficheMatiere
    ): Response {
        $formation = $ficheMatiere->getParcours()?->getFormation();
        if ($formation === null) {
            throw new RuntimeException('Formation non trouvée');
        }
        $typeDiplome = $formation->getTypeDiplome();
        if ($typeDiplome === null) {
            throw new RuntimeException('Type de diplome non trouvé');
        }
        $typeD = $this->typeDiplomeResolver->get($typeDiplome);

        if ($this->isGranted(
                'EDIT',
                [
                    'route' => 'app_formation',
                    'subject' => $formation,
                ]
            ) ||
            $this->isGranted(
                'EDIT',
                [
                    'route' => 'app_parcours',
                    'subject' => $ficheMatiere->getParcours(),
                ]
            )) {
            if ($request->isMethod('POST')) {
                $typeD->saveMcccs($ficheMatiere, $request->request);
                return JsonReponse::success('MCCCs enregistrés');
            }

            return $this->render('element_constitutif/_mcccEcModalBut.html.twig', [
                'typeEpreuves' => $typeD->getTypeEpreuves(),
                'ficheMatiere' => $ficheMatiere,
                'templateForm' => $typeD::TEMPLATE_FORM_MCCC,
                'mcccs' => $typeD->getMcccs($ficheMatiere),
                'wizard' => false,
                'typeDiplome' => $typeDiplome,//todo: utile ?
            ]);
        }
        //todo: else ?
        return $this->json(['message' => 'Acces refuse'], Response::HTTP_FORBIDDEN);


    }

    private function mcccToTexte(Collection $getMcccs): string
    {
        //sérialisation de tous les champs de l'entité MCCC
        $mcccs = [];
        /** @var Mccc $mccc */
        foreach ($getMcccs as $mccc) {
            $mcccs[] = $mccc->getMcccTexte();
        }

        return implode(';', $mcccs);


    }

    private function resolveMcccOwner(ElementConstitutif $elementConstitutif): FicheMatiere|ElementConstitutif
    {
        $isMcccImpose = $elementConstitutif->getFicheMatiere()?->isMcccImpose() ?? false;

        if ($elementConstitutif->isMcccSpecifiques() && !$isMcccImpose) {
            return $elementConstitutif;
        }

        if ($elementConstitutif->getEcParent()?->isMcccEnfantsIdentique() && !$isMcccImpose) {
            return $elementConstitutif->getEcParent();
        }

        if ($elementConstitutif->getFicheMatiere() !== null) {
            return $elementConstitutif->getFicheMatiere();
        }

        return $elementConstitutif;
    }
}
