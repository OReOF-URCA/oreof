<?php
// src/Controller/TranslationController.php
namespace App\Controller;

use App\Service\TranslationFileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/translations')]
class TranslationController extends AbstractController
{
    public function __construct(private readonly TranslationFileManager $tm)
    {
    }

    #[Route('/', name: 'translations_index')]
    public function index(): Response
    {
        return $this->render('translations/index.html.twig', [
            'files' => $this->tm->listFiles(),
        ]);
    }

    #[Route('/search', name: 'translations_search')]
    public function search(
        Request $request
    ): Response
    {
        $search = $request->query->get('search');

        // parcours l'ensemble des fichiers, et regarder si la clé ou la valeur correspondent et ajouter dans le tableau translations

        $translations = [];
        /** @var  SplFileInfo $file */
        foreach ($this->tm->listFiles() as $file) {
            $data = $this->tm->read($file->getFileName());
            foreach ($data as $key => $value) {
                if (str_contains($key, $search) || str_contains($value, $search)) {
                    $translations[$file->getFileName()][$key] = $value;
                }
            }
        }

        return $this->render('translations/_search.html.twig', [
            'translations' => $translations,
        ]);
    }

    #[Route('/edit/{filename}', name: 'translations_edit')]
    public function edit(string $filename, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            /** @var array<string,string> $data */
            $data = $request->request->all('translations');
            $this->tm->write($filename, $data);
            $this->addFlash('success', 'Fichier enregistré.');
            return $this->redirectToRoute('translations_edit', ['filename' => $filename]);
        }

        return $this->render('translations/edit.html.twig', [
            'filename' => $filename,
            'translations' => $this->tm->read($filename),
        ]);
    }
}
