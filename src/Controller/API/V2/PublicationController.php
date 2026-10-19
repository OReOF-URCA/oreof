<?php

namespace App\Controller\API\V2;

use App\Classes\GetDpeParcours;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Enums\TypeParcoursEnum;
use App\Repository\ParcoursVersioningRepository;
use App\Service\ParcoursExportV2;
use App\Service\SecureUploadService;
use App\Service\VersioningParcours;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api/site/web/v2/parcours', name: 'api_site_web_v2_parcours_')]
final class PublicationController extends AbstractController
{
    public function __construct(
        private readonly SecureUploadService $secureUploadService,
    ) {
    }

    #[Route('/{parcours}/maquette', name: 'maquette', methods: ['GET'])]
    public function maquette(
        Parcours $parcours,
        ParcoursExportV2 $parcoursExport,
    ): JsonResponse {
        return $this->json($parcoursExport->exportMaquetteJson($parcours));
    }

    #[Route('/{parcours}/maquette/validee-cfvu', name: 'maquette_validee_cfvu', methods: ['GET'])] // @phpstan-ignore return.unusedType
    public function maquetteValideeCfvu(
        Parcours $parcours,
        ParcoursExportV2 $parcoursExport,
        VersioningParcours $versioningParcours,
        ParcoursVersioningRepository $parcoursVersioningRepository, 
    ): RedirectResponse|JsonResponse {

        //todo: temporairement en attendant de régler le soucis des json de CFVU
        return $this->redirectToRoute('api_site_web_v2_parcours_maquette', ['parcours' => $parcours->getId()]);


        $versions = $parcoursVersioningRepository->findLastCfvuVersion($parcours); // @phpstan-ignore deadCode.unreachable

        if ($versions === []) {
            return $this->json(
                ['error' => 'no valid version available'],
                Response::HTTP_NOT_FOUND,
            );
        }

        $version = $versions[0];
        if (!$versioningParcours->versionFilesExist($version)) {
            return $this->json(
                ['error' => 'version file not available'],
                Response::HTTP_NOT_FOUND,
            );
        }
        $versionData = $versioningParcours->loadParcoursFromVersion($version);

        return $this->json($parcoursExport->exportLastValidVersionMaquetteJson(
            $versionData['dto'],
            $versionData['parcours'],
            $version->getParcours()->getId(),
            $version->getParcours()->getFormation()?->getId(),
            fermetureEmpty: true,
        ));
    }

    #[Route('/{parcours}/cfvu-valid', name: 'cfvu_valid', methods: ['GET'])]
    public function cfvuValid(
        Parcours $parcours,
        EntityManagerInterface $entityManager,
        VersioningParcours $versioningParcours,
    ): JsonResponse {
        $parcoursVersion = $entityManager
            ->getRepository(ParcoursVersioning::class)
            ->findLastCfvuVersion($parcours);
        if (count($parcoursVersion) === 0) {
            throw $this->createNotFoundException('Version not found.');
        }

        $parcoursVersion = $parcoursVersion[0];

        if (!$versioningParcours->versionFilesExist($parcoursVersion)) {
            throw $this->createNotFoundException('Fichiers de version introuvables.');
        }

        $versionData = $versioningParcours->loadParcoursFromVersion($parcoursVersion);
        $parcoursVersionData = $versionData['parcours'];
        $dtoVersionData = $versionData['dto'];

        $typeDiplome = $parcoursVersionData->getFormation()?->getTypeDiplome();
        $ects = $dtoVersionData->heuresEctsFormation->sommeFormationEcts;

        // Gestion de la localisation
        // Vide par défaut : -
        $localisationMetadata = ['-'];
        // Si l'on a une ville sur le parcours
        $loc = $parcoursVersionData->getLocalisation();
        if ($loc !== null && $loc->getLibelle() !== null) {
            $localisationMetadata = [$loc->getLibelle()];
        }
        // Sinon on prend au niveau de la composante
        else {
            $villeArray = $parcoursVersionData->getFormation()?->getLocalisationMention()?->toArray();
            if ($villeArray !== null && count($villeArray) > 0) {
                $localisationMetadata = array_map(
                    fn ($ville) => $ville->getLibelle(),
                    $villeArray
                );
            }
        }

        // Gestion de 'faculte-ecole-institut'
        // ---> Il peut y avoir plusieurs composantes d'inscription au niveau de la formation
        // "-" par défaut
        $faculteEcoleInstitut = ['-'];
        // Si on a au niveau du parcours
        $ci = $parcoursVersionData->getComposanteInscription();
        if ($ci !== null && $ci->getLibelle() !== null) {
            $composanteInscriptionParent = $entityManager->getRepository(Parcours::class)
                ->findOneById($parcoursVersion->getParcours()->getId())
                ?->getComposanteInscription()
                ?->getComposanteParent();

            if ($composanteInscriptionParent === null) {
                $faculteEcoleInstitut = [$ci->getLibelle()];
            } else {
                $faculteEcoleInstitut = [$composanteInscriptionParent->getLibelle()];
            }
        }
        // Sinon, on prend les composantes d'inscription de la formation
        else {
            if (count($parcoursVersionData->getFormation()?->getComposantesInscription()->toArray()) > 0) {
                $faculteEcoleInstitut = array_map(
                    fn ($composanteInscription) => $composanteInscription->getLibelle(),
                    $parcoursVersionData->getFormation()?->getComposantesInscription()->toArray()
                );
            }
        }

        $typeF = [];
        $typeF[] = $typeDiplome?->getLibelle() ?? '-';

        $typeParcours = $entityManager->getRepository(Parcours::class)
            ->findOneById($parcoursVersion->getParcours()->getId())
            ?->getTypeParcours();

        $lasTypeP = [
            TypeParcoursEnum::TYPE_PARCOURS_LAS1,
            TypeParcoursEnum::TYPE_PARCOURS_LAS23,
            TypeParcoursEnum::TYPE_PARCOURS_LAS123,
        ];

        $isDiplomeInge = in_array($typeDiplome?->getLibelleCourt() ?? '-', ['DI', 'CMI', 'CPI'], true);

        if ($typeParcours === TypeParcoursEnum::TYPE_PARCOURS_CPI || $isDiplomeInge) {
            $typeF[] = 'Diplôme d’ingénieur / CMI / CPI';
        } elseif (in_array($typeParcours, $lasTypeP, true)) {
            $typeF[] = 'Licence Accès Santé';
        }

        $logoTypeDiplomeArray = $this->getLogoTypeDiplomeParcoursApi($parcoursVersion->getParcours());
        $logosParcours = $this->getLogoArrayParcoursApi($parcoursVersion->getParcours());

        $data = [
            'description' => '',
            'ects' => $ects ?? 0,
            'metadata' => [
                'domaine' => $parcoursVersionData->getFormation()?->getDomaine()?->getLibelle() ?? '-',
                'type-formation' => $typeF,
                'logos-type-formation' => $logoTypeDiplomeArray,
                'localisation' => $localisationMetadata,
                'faculte-ecole-institut' => $faculteEcoleInstitut,
                'public-concerne' => $parcoursVersionData->getRegimeInscription() ?? [], // Certains sont des tableaux, d'autres en JSON
                'niveau-francais' => $parcoursVersionData->getNiveauFrancais()?->libelle() ?? '-',
            ],
            'xml-lheo' => $this->generateUrl('app_parcours_export_xml_lheo_v2', ['parcours' => $parcoursVersion->getParcours()->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'fiche-pdf' => $this->generateUrl('app_parcours_export_pdf_versioning', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'maquette-pdf' => $this->generateUrl('app_parcours_mccc_export_cfvu_valid', ['parcours' => $parcoursVersion->getParcours()->getId(), 'format' => 'simplifie'], UrlGeneratorInterface::ABSOLUTE_URL),
            'maquette-json' => $this->generateUrl('api_site_web_v2_parcours_maquette_validee_cfvu', ['parcours' => $parcoursVersion->getParcours()->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'logos-parcours' => $logosParcours,
        ];

        return new JsonResponse($data);
    }

    #[Route('/{parcours}/annee-suivante-light', name: 'annee_suivante_light', methods: ['GET'])]
    public function anneeSuivanteLight(
        Parcours $parcours,
        EntityManagerInterface $entityManager,
        TypeDiplomeResolver $typeDiplomeResolver,
    ): JsonResponse {
        // Pas de PDF pour N+1
        $urlMaquettePdf = '#';

        // Afficher la maquette de N pour N+1
        // S'il n'y a pas de parcours précédent
        // envoyer un json minimaliste
        $parcoursOrigineVersion = [];
        if ($parcours->getParcoursOrigineCopie()) {
            $parcoursOrigineVersion = $entityManager->getRepository(ParcoursVersioning::class)
                ->findLastCfvuVersion($parcours->getParcoursOrigineCopie());
        }
        if ($parcours->getParcoursOrigineCopie()?->getId() && count($parcoursOrigineVersion) > 0) {
            $urlMaquetteJson = $this->generateUrl(
                'api_site_web_v2_parcours_maquette_validee_cfvu',
                ['parcours' => $parcours->getParcoursOrigineCopie()->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } else {
            $urlMaquetteJson = $this->generateUrl(
                'app_parcours_export_maquette_json_minimum',
                ['parcours' => $parcours->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        $formation = $parcours->getFormation();
        $typeDiplome = $formation?->getTypeDiplome();

        if ($formation === null || $typeDiplome === null) {
            throw $this->createNotFoundException('Type de diplôme non trouvé pour le parcours.');
        }

        $typeD = $typeDiplomeResolver->fromTypeDiplome($typeDiplome);

        $ects = 0;
        $structure = $typeD->calcul($parcours);
        if (isset($structure->heuresEctsFormation->sommeFormationEcts)) {
            $ects = $structure->heuresEctsFormation->sommeFormationEcts;
        }

        // Gestion de la localisation
        // Vide par défaut : -
        $localisationMetadata = ['-'];
        // Si l'on a une ville sur le parcours
        $loc = $parcours->getLocalisation();
        if ($loc !== null && $loc->getLibelle() !== null) {
            $localisationMetadata = [$loc->getLibelle()];
        }
        // Sinon on prend au niveau de la composante
        else {
            $villes = $formation->getLocalisationMention();
            if (!$villes->isEmpty()) {
                $localisationMetadata = array_map(
                    fn ($ville) => $ville->getLibelle(),
                    $villes->toArray()
                );
            }
        }

        // Gestion de 'faculte-ecole-institut'
        // ---> Il peut y avoir plusieurs composantes d'inscription au niveau de la formation
        // "-" par défaut
        $faculteEcoleInstitut = ['-'];
        // Si on a au niveau du parcours
        $ci = $parcours->getComposanteInscription();
        if ($ci !== null && $ci->getLibelle() !== null) {
            if ($ci->getComposanteParent() === null) {
                $faculteEcoleInstitut = [$ci->getLibelle()];
            } else {
                $faculteEcoleInstitut = [$ci->getComposanteParent()->getLibelle()];
            }
        }
        // Sinon, on prend les composantes d'inscription de la formation
        else {
            if (count($formation->getComposantesInscription()->toArray()) > 0) {
                $faculteEcoleInstitut = array_map(
                    fn ($composanteInscription) => $composanteInscription->getLibelle(),
                    $formation->getComposantesInscription()->toArray()
                );
            }
        }

        $typeF = [];
        $typeF[] = $typeDiplome->getLibelle() ?? '-';

        $lasTypeP = [
            TypeParcoursEnum::TYPE_PARCOURS_LAS1,
            TypeParcoursEnum::TYPE_PARCOURS_LAS23,
            TypeParcoursEnum::TYPE_PARCOURS_LAS123,
        ];

        $isDiplomeInge = in_array($typeDiplome->getLibelleCourt() ?? '-', ['DI', 'CMI', 'CPI'], true);

        if ($parcours->getTypeParcours() === TypeParcoursEnum::TYPE_PARCOURS_CPI || $isDiplomeInge) {
            $typeF[] = 'Diplôme d’ingénieur / CMI / CPI';
        } elseif (in_array($parcours->getTypeParcours(), $lasTypeP, true)) {
            $typeF[] = 'Licence Accès Santé';
        }

        $logoTypeDiplomeArray = $this->getLogoTypeDiplomeParcoursApi($parcours);
        $logosParcours = $this->getLogoArrayParcoursApi($parcours);

        $data = [
            'description' => '',
            'ects' => $ects,
            'metadata' => [
                'domaine' => $parcours->getFormation()?->getDomaine()?->getLibelle() ?? '-',
                'type-formation' => $typeF,
                'logo-type-formation' => $logoTypeDiplomeArray,
                'localisation' => $localisationMetadata,
                'faculte-ecole-institut' => $faculteEcoleInstitut,
                'public-concerne' => $parcours->getRegimeInscription(), // Certains sont des tableaux, d'autres en JSON
                'niveau-francais' => $parcours->getNiveauFrancais()?->libelle() ?? '-',
            ],
            'xml-lheo' => $this->generateUrl('app_parcours_export_xml_lheo_v2', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'fiche-pdf' => $this->generateUrl('app_parcours_export', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'maquette-pdf' => $urlMaquettePdf,
            'maquette-json' => $urlMaquetteJson,
            'logos-parcours' => $logosParcours,
        ];

        return new JsonResponse($data);
    }

    private function getLogoArrayParcoursApi(Parcours $parcours): array
    {
        $result = [];
        foreach ($parcours->getLogo() ?? [] as $filename) {
            $filePath = $this->secureUploadService->resolveStoredFilePath('logos', $filename);
            $result[] = [
                'image_data' => $this->generateUrl(
                    'app_parcours_logo',
                    ['id' => $parcours->getId(), 'filename' => $filename],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'image_type' => file_exists($filePath) ? mime_content_type($filePath) : null,
                'id' => pathinfo($filename, PATHINFO_FILENAME),
            ];
        }

        return $result;
    }

    private function getLogoTypeDiplomeParcoursApi(Parcours $parcours): array
    {
        $result = [];
        foreach ($parcours->getFormation()?->getTypeDiplome()?->getLogo() ?? [] as $filename) {
            $filePath = $this->secureUploadService->resolveStoredFilePath('logos', $filename);
            $result[] = [
                'image_data' => $this->generateUrl(
                    'app_parcours_type_diplome_logos',
                    [
                        'td' => $parcours->getFormation()?->getTypeDiplome()?->getId(),
                        'filename' => $filename,
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'image_type' => file_exists($filePath) ? mime_content_type($filePath) : null,
                'id' => pathinfo($filename, PATHINFO_FILENAME),
            ];
        }

        return $result;
    }
}
