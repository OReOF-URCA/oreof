<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DocumentController extends AbstractController
{
    private const TYPES = [
        'doc' => 'word', 'docx' => 'word', 'odt' => 'word',
        'xls' => 'excel', 'xlsx' => 'excel', 'ods' => 'excel', 'csv' => 'excel',
        'ppt' => 'powerpoint', 'pptx' => 'powerpoint', 'odp' => 'powerpoint',
        'pdf' => 'pdf',
        'zip' => 'archive',
        'mp4' => 'video', 'webm' => 'video', 'mov' => 'video',
        'mp3' => 'audio',
        'png' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'gif' => 'image', 'svg' => 'image',
    ];

    private const PREFIXE_AIDE = 'Aide à la rédaction';

    /** Liste réellement présente dans public/docs, classée par rubrique. */
    #[Route('/documents', name: 'app_document')]
    public function index(#[Autowire('%kernel.project_dir%/public/docs')] string $dossier): Response
    {
        $rubriques = [
            'aide' => [
                'title' => 'Guides de rédaction',
                'description' => 'Modèles et aides à la rédaction des fiches, à utiliser pour préparer la saisie hors de l\'outil.',
                'items' => [],
            ],
            'autres' => ['title' => 'Documentation et guides', 'description' => null, 'items' => []],
            'video' => ['title' => 'Vidéos', 'description' => null, 'items' => []],
        ];

        if (is_dir($dossier)) {
            $finder = (new Finder())->files()->in($dossier)->depth(0)->ignoreDotFiles(true)->sortByName(true);
            foreach ($finder as $fichier) {
                $nom = $fichier->getFilename();
                $ext = strtolower($fichier->getExtension());
                $type = self::TYPES[$ext] ?? 'file';
                $base = pathinfo($nom, PATHINFO_FILENAME);
                $cle = str_starts_with($base, self::PREFIXE_AIDE) ? 'aide' : ($type === 'video' ? 'video' : 'autres');

                $titre = $cle === 'aide'
                    ? trim(preg_replace('/^' . self::PREFIXE_AIDE . '( v\d+)?\s*-\s*/u', '', $base))
                    : trim(str_replace('_', ' ', $base));

                $rubriques[$cle]['items'][] = [
                    'label' => mb_strtoupper(mb_substr($titre, 0, 1)) . mb_substr($titre, 1),
                    'file' => $nom,
                    'type' => $type,
                    'meta' => $this->taille($fichier->getSize()) . ' · ' . date('d/m/Y', $fichier->getMTime()),
                ];
            }
        }

        return $this->render('document/index.html.twig', [
            'sections' => array_filter($rubriques, static fn (array $r): bool => $r['items'] !== []),
        ]);
    }

    private function taille(int $octets): string
    {
        return match (true) {
            $octets >= 1048576 => number_format($octets / 1048576, 1, ',', ' ') . ' Mo',
            $octets >= 1024 => (int) round($octets / 1024) . ' Ko',
            default => $octets . ' o',
        };
    }
}
