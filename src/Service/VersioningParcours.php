<?php

namespace App\Service;

use App\DTO\StructureParcours;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Serializer\IdEntityDenormalizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Jfcherng\Diff\DiffHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class VersioningParcours
{

    private EntityManagerInterface $entityManager;
    private Serializer $serializer;

    private array $textDifferences = [];
    private Filesystem $fileSystem;
    private TypeDiplomeResolver $typeD;

    public function __construct(
        EntityManagerInterface $entityManager,
        Filesystem $fileSystem,
        TypeDiplomeResolver $typeD
    ) {
        $this->entityManager = $entityManager;
        $this->typeD = $typeD;
        $this->fileSystem = $fileSystem;
        // Définition du serializer
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $extractors = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $this->serializer = new Serializer(
        [
            new IdEntityDenormalizer(),
            new DateTimeNormalizer(),
            new BackedEnumNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer($classMetadataFactory, propertyTypeExtractor: $extractors)
        ],
            [new JsonEncoder()]
        );
    }

    public function saveVersionOfParcours(Parcours $parcours, DateTimeImmutable $now, bool $withFlush = false, bool $isCfvu = false): void
    {
        $dateHeure = $now->format('d-m-Y_H-i-s');
        // Objet BD Parcours Versioning
        $parcoursVersioning = new ParcoursVersioning();
        $parcoursVersioning->setParcours($parcours);
        $parcoursVersioning->setVersionTimestamp($now);
        $parcoursVersioning->setCvfuFlag($isCfvu);

        // Nom du fichier
        $parcoursFileName = "parcours-{$parcours->getId()}-{$dateHeure}";
        $dtoFileName = "dto-{$parcours->getId()}-{$dateHeure}";
        $parcoursVersioning->setParcoursFileName($parcoursFileName);
        $parcoursVersioning->setDtoFileName($dtoFileName);
        // Création du fichier JSON
        // Parcours
        $parcoursJson = $this->serializer->serialize($parcours, 'json', [
            AbstractNormalizer::GROUPS => ['parcours_json_versioning'],
            'circular_reference_limit' => 2,
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s',
        ]);
        // DTO
        $typeD = $this->typeD->get($parcours->getFormation()?->getTypeDiplome());
        $dto = $typeD->calculStructureParcours($parcours);
        $dtoJson = $this->serializer->serialize($dto, 'json', [
            AbstractNormalizer::GROUPS => ['DTO_json_versioning'],
            'circular_reference_limit' => 2,
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s',
        ]);
        // Enregistrement dans un fichier
        $this->fileSystem->appendToFile(__DIR__ . "/../../versioning_json/parcours/{$parcours->getId()}/{$parcoursFileName}.json", $parcoursJson);
        $this->fileSystem->appendToFile(__DIR__ . "/../../versioning_json/parcours/{$parcours->getId()}/{$dtoFileName}.json", $dtoJson);
        // Enregistrement de la référence en BD
        $this->entityManager->persist($parcoursVersioning);
        if($withFlush) {
            $this->entityManager->flush();
        }
    }

    public function loadParcoursFromVersion(ParcoursVersioning $parcours_versioning): array
    {
        $fileParcours = file_get_contents(
            __DIR__ . "/../../versioning_json/parcours/"
                                    . "{$parcours_versioning->getParcours()->getId()}/"
                                    . "{$parcours_versioning->getParcoursFileName()}.json"
        );
        $fileDTO = file_get_contents(
            __DIR__ . "/../../versioning_json/parcours/"
                                    . "{$parcours_versioning->getParcours()->getId()}/"
                                    . "{$parcours_versioning->getDtoFileName()}.json"
        );
        $parcours = $this->serializer->deserialize($fileParcours, Parcours::class, 'json');
        $dto = $this->serializer->deserialize($fileDTO, StructureParcours::class, 'json');
        $dateVersion = $parcours_versioning->getVersionTimestamp()->format('d-m-Y à H:i');

        return [
            'parcours' => $parcours,
            'dto' => $dto,
            'dateVersion' => $dateVersion
        ];
    }

    private function getLastVersion(Parcours $parcours): ParcoursVersioning|null
    {
        $lastVersion = $this->entityManager->getRepository(ParcoursVersioning::class)->findLastVersion($parcours);
        return count($lastVersion) > 0 ? $lastVersion[0] : null;
    }

    public function getLastCfvuVersion(Parcours $parcours) : ParcoursVersioning|null {
        $lastCfvu = $this->entityManager->getRepository(ParcoursVersioning::class)->findLastCfvuVersion($parcours);
        return $lastCfvu[0] ?? null;
    }

    public function getLastVersionOrLastYearCfvu(Parcours $parcours)
    {
        // Dernière version CFVU
        $lastVersion = $this->entityManager->getRepository(ParcoursVersioning::class)->findLastCfvuVersion($parcours);
        if(count($lastVersion) === 0 && $parcours->getParcoursOrigineCopie() !== null){
            // Sinon, on prend la plus récente de l'année dernière
            $lastVersion = $this->entityManager->getRepository(ParcoursVersioning::class)
                ->findLastCfvuVersion($parcours->getParcoursOrigineCopie());
        }

        return $lastVersion[0] ?? null;
    }

    public function getDifferencesBetweenParcoursAndLastVersion(Parcours $parcours, bool $fromLastYear = false): array
    {
        $this->textDifferences = [];

        if (($this->hasLastVersion($parcours) && $fromLastYear === false)
            || ($fromLastYear && $parcours->getParcoursOrigineCopie() !== null)
        ) {
            if($fromLastYear){
                $lastVersion = $parcours->getParcoursOrigineCopie();
            }
            else {
                // Données du parcours en JSON
                $lastVersion = $this->getLastVersion($parcours);
                $fileParcours = file_get_contents(
                    __DIR__ . "/../../versioning_json/parcours/"
                    . "{$lastVersion->getParcours()?->getId()}/"
                    . "{$lastVersion->getParcoursFileName()}.json"
                );
                $lastVersion = $this->serializer->deserialize($fileParcours, Parcours::class, 'json');
            }
            // Configuration du calcul des différences
            $rendererName = 'Combined';
            $differOptions = [
                'context' => 1,
                'ignoreWhitespace' => true,
                'ignoreLineEnding' => true,
                'lengthLimit' => 4500
            ];
            // Affichage
            $rendererOptions = [
                'detailLevel' => 'word',
                'lineNumbers' => false,
                'showHeader' => false,
                'separateBlock' => false,
                'wordGlues' => [' ', '.']
            ];

            $this->textDifferences = [
                'presentationParcoursContenuFormation' =>
                self::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                        self::cleanUpHtmlTextForComparison($lastVersion->getContenuFormation() ?? ""),
                        self::cleanUpHtmlTextForComparison($parcours->getContenuFormation() ?? ""),
                        $rendererName,
                        $differOptions,
                        $rendererOptions
                    ))
                ),
                'presentationParcoursObjectifsParcours' =>
                self::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                        self::cleanUpHtmlTextForComparison($lastVersion->getObjectifsParcours() ?? ""),
                        self::cleanUpHtmlTextForComparison($parcours->getObjectifsParcours() ?? ""),
                        $rendererName,
                        $differOptions,
                        $rendererOptions
                    ))
                ),
                'presentationParcoursResultatsAttendus' =>
                self::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                        self::cleanUpHtmlTextForComparison($lastVersion->getResultatsAttendus() ?? ""),
                        self::cleanUpHtmlTextForComparison($parcours->getResultatsAttendus() ?? ""),
                        $rendererName,
                        $differOptions,
                        $rendererOptions
                    ))
                ),
                'contactsParcoursResponsableParcours' => html_entity_decode(DiffHelper::calculate(
                    // Version
                    $lastVersion->getRespParcours() ?
                    (
                        $lastVersion->getRespParcours()->getNom()
                     . " " .
                     $lastVersion->getRespParcours()->getPrenom()
                    )  : "",
                    // Actuel
                    $parcours->getRespParcours() ?
                    (
                        $parcours->getRespParcours()->getNom()
                     . " " .
                     $parcours->getRespParcours()->getPrenom()
                    ) : "",
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                "emailParcoursResponsableParcours" => html_entity_decode(DiffHelper::calculate(
                    // Version
                    $lastVersion->getRespParcours()?->getEmail() ?? "",
                    // Actuel
                    $parcours->getRespParcours()?->getEmail() ?? "",
                    $rendererName,
                    [...$differOptions, 'ignoreLineEnding' => false],
                    [...$rendererOptions, 'detailLevel' => 'none']
                )),
                'contactsParcoursCoResponsableDuParcours' => html_entity_decode(DiffHelper::calculate(
                    // Version
                    $lastVersion->getCoResponsable() ?
                    (
                        $lastVersion->getCoResponsable()->getNom()
                     . " " .
                     $lastVersion->getCoResponsable()->getPrenom()
                    )  : "",
                    // Actuel
                    $parcours->getCoResponsable() ?
                    (
                        $parcours->getCoResponsable()->getNom()
                     . " " .
                     $parcours->getCoResponsable()->getPrenom()
                    ) : "",
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                "emailParcoursCoResponsableDuParcours" => html_entity_decode(DiffHelper::calculate(
                    // Version
                    $lastVersion->getCoResponsable()?->getEmail() ?? "",
                    // Actuel
                    $parcours->getCoResponsable()?->getEmail() ?? "",
                    $rendererName,
                    [...$differOptions, 'ignoreLineEnding' => false],
                    [...$rendererOptions, 'detailLevel' => 'none']
                )),
                "regimeInscriptionParcours" => html_entity_decode(DiffHelper::calculate(
                    "<p class=\"list-item\">"
                    . implode(
                        "</p><p class=\"list-item\">",
                        array_map(
                            fn ($regime) => $regime->value,
                            $lastVersion->getRegimeInscription()
                        )
                    ) . "</p>",
                    "<p class=\"list-item\">"
                    . implode(
                        "</p><p class=\"list-item\">",
                        array_map(
                            fn ($regime) => $regime->value,
                            $parcours->getRegimeInscription()
                        )
                    ) . "</p>",
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                "rythmeFormationParcours" => html_entity_decode(DiffHelper::calculate(
                    $lastVersion->getRythmeFormation()?->getLibelle() ?? "",
                    $parcours->getRythmeFormation()?->getLibelle() ?? "",
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                "memoireTextParcours" => html_entity_decode(DiffHelper::calculate(
                    $lastVersion->getMemoireText() ?? "",
                    $parcours->getMemoireText() ?? "",
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                "stageTextParcours" =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            self::cleanUpHtmlTextForComparison($lastVersion->getStageText() ?? ""),
                            self::cleanUpHtmlTextForComparison($parcours->getStageText() ?? ""),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                "prerequisRecommandesParcours" =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            self::cleanUpHtmlTextForComparison($lastVersion->getPrerequis() ?? ""),
                            self::cleanUpHtmlTextForComparison($parcours->getPrerequis() ?? ""),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                "debouchesTextParcours" =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            self::cleanUpHtmlTextForComparison($lastVersion->getDebouches() ?? ""),
                            self::cleanUpHtmlTextForComparison($parcours->getDebouches() ?? ""),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                "poursuiteEtudesParcours" =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            self::cleanUpHtmlTextForComparison($lastVersion->getPoursuitesEtudes() ?? ""),
                            self::cleanUpHtmlTextForComparison($parcours->getPoursuitesEtudes() ?? ""),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                "modaliteAlternanceParcours" =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            self::cleanUpHtmlTextForComparison($lastVersion->getModalitesAlternance() ?? ""),
                            self::cleanUpHtmlTextForComparison($parcours->getModalitesAlternance() ?? ""),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                "rythmeFormationTexteParcours" =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            self::cleanUpHtmlTextForComparison($lastVersion->getRythmeFormationTexte() ?? ""),
                            self::cleanUpHtmlTextForComparison($parcours->getRythmeFormationTexte() ?? ""),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                "niveauFrancaisParcours" =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $lastVersion->getNiveauFrancais()?->libelle() ?? "",
                            $parcours->getNiveauFrancais()?->libelle() ?? "",
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'composanteInscriptionParcours' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $lastVersion->getComposanteInscription()?->getLibelle() ?? "",
                            $parcours->getComposanteInscription()?->getLibelle() ?? "",
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'localisationParcours' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $lastVersion->getLocalisation()?->getLibelle() ?? "",
                            $parcours->getLocalisation()?->getLibelle() ?? "",
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'codesROMEParcours' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $this->getArrayAsHtmlDiffList($lastVersion->getCodesRome() ?? [], 'code'),
                            $this->getArrayAsHtmlDiffList($parcours->getCodesRome() ?? [], 'code'),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'responsableMentionParcoursParDefaut' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $lastVersion->getFormation()?->getResponsableMention()?->getDisplay() ?? '',
                            $parcours->getFormation()?->getResponsableMention()?->getDisplay() ?? '',
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'coResponsableMentionParcoursParDefaut' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $lastVersion->getFormation()?->getCoResponsable()?->getDisplay() ?? '',
                            $parcours->getFormation()?->getCoResponsable()?->getDisplay() ?? '',
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'parcoursHasStage' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $this->decodeBooleanWithHours(
                                $lastVersion->hasStage() ?? false,
                                $lastVersion->getNbHeuresStages() ?? 0.0
                            ),
                            $this->decodeBooleanWithHours(
                                $parcours->hasStage() ?? false,
                                $parcours->getNbHeuresStages() ?? 0.0
                            ),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'modaliteEnseignementParcours' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $lastVersion->getModalitesEnseignement()?->libelle() ?? "",
                            $parcours->getModalitesEnseignement()?->libelle() ?? "",
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'parcoursHasProjet' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $this->decodeBooleanWithHours(
                                $lastVersion->hasProjet() ?? false,
                                $lastVersion->getNbHeuresProjet() ?? 0.0,
                            ),
                            $this->decodeBooleanWithHours(
                                $parcours->hasProjet() ?? false,
                                $parcours->getNbHeuresProjet() ?? 0.0
                            ),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'projetTexteParcours' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $lastVersion->getProjetText() ?? "",
                            $parcours->getProjetText() ?? "",
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'hasMemoireParcours' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            ($lastVersion->isHasMemoire() ?? false) ? 'Oui' : 'Non',
                            ($parcours->isHasMemoire() ?? false) ? 'Oui' : 'Non',
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'hasSituationProHeures' =>
                self::cleanUpComparison(
                    html_entity_decode(
                        DiffHelper::calculate(
                            $this->decodeBooleanWithHours(
                                $lastVersion->isHasSituationPro() ?? false,
                                $lastVersion->getNbHeuresSituationPro() ?? 0.0
                            ),
                            $this->decodeBooleanWithHours(
                                $parcours->isHasSituationPro() ?? false,
                                $parcours->getNbHeuresSituationPro() ?? 0.0
                            ),
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                )
            ];
        }

        return $this->textDifferences;
    }

    public static function cleanUpHtmlTextForComparison(string $html) : string
    {
        $cleaned = $html;
        $cleaned = preg_replace('/\<div\>/m', '', $cleaned);
        $cleaned = preg_replace('/\<\/div\>/m', '', $cleaned);
        $cleaned = preg_replace('/\<ul\>|\<\/ul\>/m', '', $cleaned);
        $cleaned = preg_replace('/\<li\>/m', '<p class="list-item"><span class="list-item-text">', $cleaned);
        $cleaned = preg_replace('/\<\/li\>/m', '</span></p>', $cleaned);
        $cleaned = preg_replace('/\<\!--block--\>/m', '', $cleaned);
        $cleaned = preg_replace('/\<br\>/m', '', $cleaned);
        // $cleaned = preg_replace('/\>(.+)\<br\>/m', '<p>$1</p>', $cleaned);


        return $cleaned;
    }

    public static function removeBlockHtml(string $html) : string
    {
        return preg_replace('/\<\!--block--\>/m', '', $html);
    }

    public static function cleanUpComparison(string $htmlComparison) : string
    {
        return preg_replace(
            '/(\<del\>)(\<\/span\>\<\/p\>\<p class\="list-item"\>\<span class\="list-item-text"\>)/m',
            "$2$1",
            $htmlComparison
        );
    }

    public function getStructureDifferencesBetweenParcoursAndLastVersion(Parcours $parcours): ?StructureParcours
    {
        $lastVersion = $this->entityManager->getRepository(ParcoursVersioning::class)->findLastVersion($parcours);
        $lastVersion = count($lastVersion) > 0 ? $lastVersion[0] : null;

        if($lastVersion) {
            $fileDTO = file_get_contents(
                __DIR__ . "/../../versioning_json/parcours/"
                . "{$lastVersion->getParcours()->getId()}/"
                . "{$lastVersion->getDtoFileName()}.json"
            );

            $dto = $this->serializer->deserialize($fileDTO, StructureParcours::class, 'json');
            //            if ($parcours ->getId() === 405) {
            //                dd($dto);
            //            }

            return $dto;
        }

        return null;
    }

    public function getStructureDifferencesBetweenParcoursAndLastCfvu(Parcours $parcours): ?StructureParcours
    {
        // regarder si une CFVU existe sur le parcours actuel. Si non, regarder si une CFVU existe sur la version du parcours d'origine du copie
        $lastVersion = $this->entityManager->getRepository(ParcoursVersioning::class)->findLastCfvuVersion($parcours);
        $lastVersion = count($lastVersion) > 0 ? $lastVersion[0] : null;

        if ($lastVersion === null && $parcours->getParcoursOrigineCopie() !== null) {

            $lastVersion = $this->entityManager->getRepository(ParcoursVersioning::class)->findLastVersion($parcours->getParcoursOrigineCopie());
            $lastVersion = count($lastVersion) > 0 ? $lastVersion[0] : null;
        }

        if ($lastVersion) {
            $fileDTO = file_get_contents(
                __DIR__ . "/../../versioning_json/parcours/"
                . "{$lastVersion->getParcours()->getId()}/"
                . "{$lastVersion->getDtoFileName()}.json"
            );

            return $this->serializer->deserialize($fileDTO, StructureParcours::class, 'json');
        }

        return null;
    }

    public function hasLastVersion(Parcours $parcours): bool
    {
        return $this->getLastVersion($parcours) !== null;
    }

    public function loadJsonCfvu(Parcours $parcours, ParcoursVersioning $lastVersion) : ?string
    {
        if($lastVersion) {
            $fileDTO = file_get_contents(
                __DIR__ . "/../../versioning_json/parcours/"
                . "{$lastVersion->getParcours()->getId()}/"
                . "{$lastVersion->getDtoFileName()}.json"
            );

            return $fileDTO;
        }

        return null;
    }

    public function saveVersionOfParcoursCourant(Parcours $parcours): string
    {

        // DTO
        $typeD = $this->typeD->get($parcours->getFormation()?->getTypeDiplome());
        $dto = $typeD->calculStructureParcours($parcours);
        $dtoJson = $this->serializer->serialize($dto, 'json', [
            AbstractNormalizer::GROUPS => ['DTO_json_versioning'],
            'circular_reference_limit' => 2,
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s',
        ]);

        return $dtoJson;

    }

    public function rollbackToLastVersion(?Parcours $parcours): void
    {
        // si parcours et différences alors remettre dans parcours les données venant de this->textDifferences

        if($parcours && count($this->textDifferences) > 0) {
            $parcours->setContenuFormation($this->textDifferences['presentationParcoursContenuFormation']);
            $parcours->setObjectifsParcours($this->textDifferences['presentationParcoursObjectifsParcours']);
            $parcours->setResultatsAttendus($this->textDifferences['presentationParcoursResultatsAttendus']);
            $parcours->setRespParcours($this->textDifferences['contactsParcoursResponsableParcours']);
            $parcours->setCoResponsable($this->textDifferences['contactsParcoursCoResponsableDuParcours']);
            $parcours->setRegimeInscription($this->textDifferences['regimeInscriptionParcours']);
            $parcours->setRythmeFormation($this->textDifferences['rythmeFormationParcours']);
            $parcours->setMemoireText($this->textDifferences['memoireTextParcours']);
            $parcours->setStageText($this->textDifferences['stageTextParcours']);
            $parcours->setPrerequis($this->textDifferences['prerequisRecommandesParcours']);
            $parcours->setDebouches($this->textDifferences['debouchesTextParcours']);
            $parcours->setPoursuitesEtudes($this->textDifferences['poursuiteEtudesParcours']);
        }

    }

    private function getArrayAsHtmlDiffList(array $array, ?string $key) : string {
        if(count($array) > 0){
            $html = "";
            foreach($array as $element){
                $value = $element;
                if($key !== null){
                    $value = $element[$key] ?? "";
                }
                $html .= '<p class="list-item">' . $value . '</p>';
           }

           return $html;
        }

        return "";
    }

    private function decodeBooleanWithHours(bool $isTrue, int $nbHeures) {
        if($isTrue){
            return "Oui ({$nbHeures} heures).";
        }

        return 'Non';
    }
}
