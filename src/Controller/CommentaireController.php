<?php

namespace App\Controller;

use App\Classes\GetCommentaires;
use App\Classes\JsonReponse;
use App\Classes\MyGotenbergPdf;
use App\Entity\Commentaire;
use App\Utils\CleanTexte;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CommentaireController extends AbstractController
{
    #[Route('/commentaire', name: 'app_commentaire')]
    public function index(
        GetCommentaires $getCommentaires
    ): Response
    {
        return $this->render('commentaire/index.html.twig', [
            'commentaires' => $getCommentaires->getAllCommentairesByUser($this->getUser())
        ]);
    }

    #[Route('/commentaire/export', name: 'app_commentaire_export')]
    public function export(
        MyGotenbergPdf $myGotenbergPdf,
        GetCommentaires $getCommentaires
    ): Response
    {
        return $myGotenbergPdf->render('pdf/commentaires.html.twig', [
            'titre' => 'Liste des commentaires',
            'commentaires' => $getCommentaires->getAllCommentairesByUser($this->getUser())
        ], 'commentaires_'.(new DateTime())->format('d-m-Y_H-i-s'));
    }

    #[Route('/commentaire/ajout', name: 'app_commentaire_ajout')]
    public function ajout(
        GetCommentaires $getCommentaires,
        Request         $request
    ): Response
    {
        $id = $request->query->get('id');
        $type = $request->query->get('type');
        $zone = $request->query->get('zone');

        if ($request->isMethod('POST')) {
            $message = $request->request->get('message');
            $message = is_string($message) ? $message : '';
            $message = trim(CleanTexte::cleanTextArea($message, true) ?? '');

            if ($message === '') {
                return JsonReponse::error('Le message est vide.');
            }

            $getCommentaires->ajoutCommentaire($id, $type, $zone, $message, $this->getUser());

            return JsonReponse::success('Commentaire ajouté');
        }


        return $this->render('commentaire/_ajout.html.twig', [
            'id' => $id,
            'type' => $type,
            'zone' => $zone,
        ]);
    }

    #[Route('/commentaire/liste', name: 'app_commentaire_liste')]
    public function liste(
        GetCommentaires $getCommentaires,
        Request         $request
    ): Response
    {
        $id = $request->query->get('id');
        $type = $request->query->get('type');
        $zone = $request->query->get('zone');

        $commentaires = $getCommentaires->getCommentairesByUser($id, $type, $zone, $this->getUser());

        return $this->render('commentaire/_liste.html.twig', [
            'commentaires' => $commentaires
        ]);
    }

    #[Route('/commentaire/delete/{id}', name: 'app_commentaire_delete', methods: ['DELETE'])]
    public function delete(
        EntityManagerInterface $entityManager,
        Commentaire            $commentaire
    ): Response
    {
        $entityManager->remove($commentaire);
        $entityManager->flush();

        return JsonReponse::success('Commentaire supprimé');
    }
}
