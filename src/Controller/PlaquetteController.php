<?php

namespace App\Controller;

use App\Entity\Composante;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlaquetteController extends BaseController
{
    #[Route('/communication/plaquette', name: 'app_plaquette')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $composante = $user->getUserCentres()->first()->getComposante();//todo: faire un filtre sur les droits ?
        return $this->render('plaquette/index.html.twig', [
            'composante' => $composante,
        ]);
    }

    #[Route('/communication/plaquette/rubriques/{composante}', name: 'app_plaquette_rubriques', methods: ['GET'])]
    public function rubriques(Composante $composante): Response
    {
        return $this->render('plaquette/_listeRubriques.html.twig', [
            'composante' => $composante,
            'rubriques' => $composante->getPlaquetteRubriques(),
        ]);
    }

    #[Route('/communication/plaquette/rubriques/{composante}', name: 'app_plaquette_sauvegarde', methods: ['POST'])]
    public function sauvegarde(
        EntityManagerInterface $em,
        Request $request,
        Composante $composante
    ): Response {
        $rubrique = $request->query->get('rubrique');
        $action = $request->query->get('action');

        if ($action === null || $rubrique === null) {
            throw $this->createNotFoundException('Action ou rubrique non définie');
        }

        if (!in_array($action, ['up', 'down', 'show', 'hide', 'reset'])) {
            throw $this->createNotFoundException('Action inconnue');
        }

        if ($action !== 'reset' && !array_key_exists($rubrique, Composante::RUBRIQUES)) {
            throw $this->createNotFoundException('Rubrique inconnue');
        }

        $rubriques = $composante->getPlaquetteRubriques();

        if ($action === 'up') {
            // vérifier que la rubrique n'est pas déjà en première position
            // récupérer la rubrique sur la future position
            // inverser les positions
            // sauvegarder

            if ($rubriques[$rubrique] > 1) {
                $newPosition = array_search($rubriques[$rubrique] - 1, $rubriques);
                $rubriques[$newPosition] = $rubriques[$rubrique];
                $rubriques[$rubrique] = $rubriques[$rubrique] - 1;
            }

        } elseif ($action === 'reset') {
            $rubriques = Composante::RUBRIQUES;
        } elseif ($action === 'down') {

            if ($rubriques[$rubrique] <= count($rubriques)) {
                $newPosition = array_search($rubriques[$rubrique] + 1, $rubriques);
                $rubriques[$newPosition] = $rubriques[$rubrique];
                $rubriques[$rubrique] = $rubriques[$rubrique] + 1;
            }

        } elseif ($action === 'show') {
            $rubriques[$rubrique] = $request->query->get('place', 0);
        } elseif ($action === 'hide') {
            // on met la rubrique à null
            // on décale les rubriques suivantes
            $position = $rubriques[$rubrique];
            $rubriques[$rubrique] = null;

            // on décale les rubriques suivantes

            foreach ($rubriques as $key => $value) {
                if ($value > $position) {
                    $rubriques[$key] = $value - 1;
                }
            }

        }

        $composante->setPlaquetteRubriques($rubriques);

        $em->flush();

        return $this->redirectToRoute('app_plaquette_rubriques', ['composante' => $composante->getId()]);
    }
}
