<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Repository\FicheMatiereRepository;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FormationSynchronisationController extends AbstractController
{
    #[Route('/formation/synchronisation-acs/{formation}', name: 'app_formation_synchronisation_acs')]
    public function synchroAcs(
        EntityManagerInterface $entityManager,
        ParameterBagInterface        $container,
         HttpClientInterface          $client,
        FicheMatiereRepository $ficheMatiereRepository,
        Formation $formation
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $page = 1;
        $response2 = $client->request(
            'GET',
            $container->get('api_url') . '/specialite/' . strtolower($formation->getSigle()) . '/ressources?page=' . $page,
            [
                'headers' => [
                    'Accept' => 'application/ld+json',
                    'Content-Type' => 'application/ld+json'
                ]
            ]
        );
        $data2 = json_decode($response2->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $apprentissageCritique = [];

        foreach ($formation->getButCompetences() as $butCompetence) {
            foreach ($butCompetence->getButNiveaux() as $niveau) {
                foreach ($niveau->getButApprentissageCritiques() as $ac) {
                    $apprentissageCritique[$ac->getCode()] = $ac;
                }
            }
        }

        foreach ($data2['hydra:member'] as $res) {
            $ressources = $ficheMatiereRepository->findByCodeAndFormation($res['codeMatiere'], $formation);

            foreach ($res['apcRessourceApprentissageCritiques'] as $apprentissage) {
                if (array_key_exists($apprentissage['apprentissageCritique']['code'], $apprentissageCritique)) {
                    foreach ($ressources as $resso) {
                        $resso->addApprentissagesCritique($apprentissageCritique[$apprentissage['apprentissageCritique']['code']]);

                    }
                }
            }
        }

        $page = 1;
        $response2 = $client->request(
            'GET',
            $container->get('api_url') . '/specialite/' . strtolower($formation->getSigle()) . '/saes?page=' . $page,
            [
                'headers' => [
                    'Accept' => 'application/ld+json',
                    'Content-Type' => 'application/ld+json'
                ]
            ]
        );
        $data2 = json_decode($response2->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $apprentissageCritique = [];

        foreach ($formation->getButCompetences() as $butCompetence) {
            foreach ($butCompetence->getButNiveaux() as $niveau) {
                foreach ($niveau->getButApprentissageCritiques() as $ac) {
                    $apprentissageCritique[$ac->getCode()] = $ac;
                }
            }
        }

        foreach ($data2['hydra:member'] as $res) {
            $saes = $ficheMatiereRepository->findByCodeAndFormation($res['codeMatiere'], $formation);

            foreach ($res['apcSaeApprentissageCritiques'] as $apprentissage) {
                if (array_key_exists($apprentissage['apprentissageCritique']['code'], $apprentissageCritique)) {
                    foreach ($saes as $resso) {
                        $resso->addApprentissagesCritique($apprentissageCritique[$apprentissage['apprentissageCritique']['code']]);

                    }
                }
            }
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_formation_edit', [
            'slug' => $formation->getSlug(),
        ]);
    }

    #[Route('/formation/synchronisation/{formation}', name: 'app_formation_synchronisation')]
    public function index(
        TypeDiplomeResolver $typeDiplomeResolver,
        Formation $formation
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $typeDiplome = $typeDiplomeResolver->fromTypeDiplome($formation->getTypeDiplome());
        $state = $typeDiplome->synchroniser($formation);

        if ($state) {
            $this->addFlash('success', 'La synchronisation a été effectuée avec succès.');
        } else {
            $this->addFlash('danger', 'La synchronisation a échoué.');
        }

        return $this->redirectToRoute('app_formation_edit', [
            'slug' => $formation->getSlug(),
        ]);
    }

    #[Route('/formation/synchronisation-mccc/{formation}', name: 'app_formation_synchronisation_mccc')]
    public function synchronisationMccc(
        TypeDiplomeResolver $typeDiplomeResolver,
        Formation $formation
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $typeDiplome = $typeDiplomeResolver->fromTypeDiplome($formation->getTypeDiplome());
        $state = $typeDiplome->synchroniserMccc($formation);

        if ($state) {
            $this->addFlash('success', 'La synchronisation a été effectuée avec succès.');
        } else {
            $this->addFlash('danger', 'La synchronisation a échoué.');
        }

        return $this->redirectToRoute('app_formation_edit', [
            'slug' => $formation->getSlug(),
        ]);
    }
}
