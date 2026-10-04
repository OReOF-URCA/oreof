<?php

namespace App\Service;

use App\Entity\Formation;
use App\Entity\FormationVersioning;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Jfcherng\Diff\DiffHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
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

class VersioningFormation
{

    private Serializer $serializer;
    private EntityManagerInterface $entityManager;
    private Filesystem $filesystem;
    private array $formationTextDifferences;

    public function __construct(
        EntityManagerInterface $entityManager,
        Filesystem $filesystem
    ) {
        $this->entityManager = $entityManager;
        $this->filesystem = $filesystem;
        // Serializer
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $this->serializer = new Serializer(
            [
                new BackedEnumNormalizer(),
                new DateTimeNormalizer(),
                new ArrayDenormalizer(),
                new ObjectNormalizer($classMetadataFactory, propertyTypeExtractor: new ReflectionExtractor())
            ],
            [
                new JsonEncoder()
            ]
        );
    }

    public function saveVersionOfFormation(Formation $formation, DateTimeImmutable $now, bool $withFlush = false, bool $isCfvu = false): void
    {
        $dateHeure = $now->format('d-m-Y_H-i-s');
        // Nom du fichier
        $formationFilename = "formation-{$formation->getId()}-{$dateHeure}";
        // Objet BD Formation Versioning
        $formationVersioning = new FormationVersioning();
        $formationVersioning->setFormation($formation);
        $formationVersioning->setVersionTimestamp($now);
        $formationVersioning->setFilename($formationFilename);
        $formationVersioning->setSlug($formation->getSlug());
        if($isCfvu) {
            $formationVersioning->setCfvuFlag(true);
        }
        // Serialization
        $formationJSON = $this->serializer->serialize($formation, 'json', [
            AbstractNormalizer::GROUPS => ['formation_json_versioning'],
            'circular_reference_limit' => 2,
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s',
        ]);
        // Enregistrement du fichier
        $this->filesystem->appendToFile(
            __DIR__ . "/../../versioning_json/formation/{$formation->getSlug()}/{$formationFilename}.json",
            $formationJSON
        );
        $this->entityManager->persist($formationVersioning);
        if($withFlush) {
            $this->entityManager->flush();
        }
    }

    public function loadFormationFromVersion(FormationVersioning $formationVersioning): Formation
    {
        $version = (string) file_get_contents(
            __DIR__ . "/../../versioning_json/formation/{$formationVersioning->getSlug()}/"
            . "{$formationVersioning->getFilename()}.json"
        );

        return $this->serializer->deserialize($version, Formation::class, 'json');
    }

    public function getDifferencesBetweenFormationAndLastVersion(Formation $formation, bool $fromLastYear = false): array
    {
        $this->formationTextDifferences = [];
        $lastVersion = $this->entityManager->getRepository(FormationVersioning::class)->findBy(
            ['formation' => $formation],
            ['version_timestamp' => 'DESC']
        );
        $lastVersion = count($lastVersion) > 0 ? $lastVersion[0] : null;
        if( ($lastVersion && $fromLastYear === false) 
            || ($fromLastYear && $formation->getFormationOrigineCopie() !== null)
        ) {
            if($fromLastYear){
                $lastVersion = $formation->getFormationOrigineCopie();
            }
            else {
                $lastVersion = $this->loadFormationFromVersion($lastVersion);
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

            $localisationVersion = '';
            if ($lastVersion !== null) {
                $localisationVersion = implode(", ", array_map(
                    fn($ville) => $ville->getLibelle(),
                    $lastVersion->getLocalisationMention()->toArray()
                ));
            }
            $localisationActuelle = implode(", ", array_map(
                fn ($ville) => $ville->getLibelle(),
                $formation->getLocalisationMention()->toArray()
            ));

            $this->formationTextDifferences = [
                "composanteInscriptionFormation" => html_entity_decode(DiffHelper::calculate(
                    "<p class=\"list-item\">"
                    . implode(
                        "</p><p class=\"list-item\">",
                        array_map(
                            fn ($composante) => $composante->getLibelle(),
                            $lastVersion?->getComposantesInscription()->toArray() ?? []
                        )
                    ) . "</p>",
                    "<p class=\"list-item\">"
                    . implode(
                        "</p><p class=\"list-item\">",
                        array_map(
                            fn ($composante) => $composante->getLibelle(),
                            $formation->getComposantesInscription()->toArray()
                        )
                    ) . "</p>",
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                "regimeInscriptionFormation" => html_entity_decode(DiffHelper::calculate(
                    "<p class=\"list-item\">"
                    . implode(
                        "</p><p class=\"list-item\">",
                        array_map(
                            fn ($regime) => $regime->value,
                            $lastVersion?->getRegimeInscription() ?? []
                        )
                    ) . "</p>",
                    "<p class=\"list-item\">"
                    . implode(
                        "</p><p class=\"list-item\">",
                        array_map(
                            fn ($regime) => $regime->value,
                            $formation->getRegimeInscription()
                        )
                    ) . "</p>",
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                "responsableDeFormation" => html_entity_decode(DiffHelper::calculate(
                    // Version
                    (
                        $lastVersion?->getResponsableMention()->getNom()
                     . " " .
                        $lastVersion?->getResponsableMention()->getPrenom()
                    ),
                    // Actuel
                    (
                        $formation->getResponsableMention()->getNom()
                     . " " .
                     $formation->getResponsableMention()->getPrenom()
                    ),
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                "emailResponsableFormation" => html_entity_decode(DiffHelper::calculate(
                    // Version
                    $lastVersion?->getResponsableMention()->getEmail() ?? "",
                    // Actuel
                    $formation->getResponsableMention()->getEmail() ?? "",
                    $rendererName,
                    [...$differOptions, 'ignoreLineEnding' => false],
                    [...$rendererOptions, 'detailLevel' => 'none']
                )),
                "coResponsableDeFormation" => html_entity_decode(DiffHelper::calculate(
                    // Version
                    $lastVersion?->getCoResponsable() ?
                    (
                        $lastVersion->getCoResponsable()->getNom()
                     . " " .
                        $lastVersion->getCoResponsable()->getPrenom()
                    )  : "",
                    // Actuel
                    $formation->getCoResponsable() ?
                    (
                        $formation->getCoResponsable()->getNom()
                     . " " .
                     $formation->getCoResponsable()->getPrenom()
                    ) : "",
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                "emailCoResponsable" => html_entity_decode(DiffHelper::calculate(
                    // Version
                    $lastVersion?->getCoResponsable()?->getEmail() ?? "",
                    // Actuel
                    $formation->getCoResponsable()?->getEmail() ?? "",
                    $rendererName,
                    [...$differOptions, 'ignoreLineEnding' => false],
                    [...$rendererOptions, 'detailLevel' => 'none']
                )),
                "localisationMention" => html_entity_decode(DiffHelper::calculate(
                    // Version
                    $localisationVersion,
                    // Actuel
                    $localisationActuelle,
                    $rendererName,
                    $differOptions,
                    $rendererOptions
                )),
                'presentationFormationObjectifsFormation' =>
                VersioningParcours::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                        VersioningParcours::cleanUpHtmlTextForComparison($lastVersion?->getObjectifsFormation() ?? ""),
                        VersioningParcours::cleanUpHtmlTextForComparison($formation->getObjectifsFormation() ?? ""),
                        $rendererName,
                        $differOptions,
                        $rendererOptions
                    ))
                ),
                'presentationFormationContenuFormation' =>
                VersioningParcours::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                        VersioningParcours::cleanUpHtmlTextForComparison($lastVersion?->getContenuFormation() ?? ""),
                        VersioningParcours::cleanUpHtmlTextForComparison($formation->getContenuFormation() ?? ""),
                        $rendererName,
                        $differOptions,
                        $rendererOptions
                    ))
                ),
                'presentationFormationResultatsAttendus' =>
                VersioningParcours::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                        VersioningParcours::cleanUpHtmlTextForComparison($lastVersion?->getResultatsAttendus() ?? ""),
                        VersioningParcours::cleanUpHtmlTextForComparison($formation->getResultatsAttendus() ?? ""),
                        $rendererName,
                        $differOptions,
                        $rendererOptions
                    ))
                ),
                'rythmeDeFormation' =>
                VersioningParcours::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                            $lastVersion?->getRythmeFormation()?->getLibelle() ?? "",
                            $formation->getRythmeFormation()?->getLibelle() ?? "",
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'rythmeDeFormationTexte' =>
                VersioningParcours::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                            $lastVersion?->getRythmeFormationTexte() ?? "",
                            $formation->getRythmeFormationTexte() ?? "",
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'regimeInscriptionTexte' =>
                VersioningParcours::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                            $lastVersion?->getRegimeInscriptionTexte() ?? "",
                            $formation->getRegimeInscriptionTexte() ?? "",
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                ),
                'modaliteAlternanceFormation' =>
                VersioningParcours::cleanUpComparison(
                    html_entity_decode(DiffHelper::calculate(
                            $lastVersion?->getModalitesAlternance() ?? "",
                            $formation->getModalitesAlternance() ?? "",
                            $rendererName,
                            $differOptions,
                            $rendererOptions
                        )
                    )
                )
            ];
        }

        return $this->formationTextDifferences;
    }

    public function hasLastVersion(Formation $formation): bool
    {
        return $this->getLastVersion($formation) !== null;
    }

    private function getLastVersion(Formation $formation): FormationVersioning|null
    {
        $lastVersion = $this->entityManager->getRepository(FormationVersioning::class)->findLastVersion($formation);
        return count($lastVersion) > 0 ? $lastVersion[0] : null;
    }

    public function rollbackToLastVersion(Formation $formation): void
    {
        if (count($this->formationTextDifferences) > 0) {
            //todo: a finir
            //                $formation->setC($this->formationTextDifferences['composanteInscriptionFormation']);
            //                $formation->setRegimeInscription($lastVersion->getRegimeInscription());
            //                $formation->setResponsableMention($lastVersion->getResponsableMention());
            //                $formation->setCoResponsable($lastVersion->getCoResponsable());
            //                $formation->setLocalisationMention($lastVersion->getLocalisationMention());
            //                $formation->setObjectifsFormation($lastVersion->getObjectifsFormation());
            //                $formation->setContenuFormation($lastVersion->getContenuFormation());
            //                $formation->setResultatsAttendus($lastVersion->getResultatsAttendus());
        }
    }
}
