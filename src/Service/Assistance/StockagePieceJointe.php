<?php

namespace App\Service\Assistance;

use App\DTO\Assistance\PieceJointeEntrante;
use App\Entity\PieceJointeAssistance;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Stocke les pièces jointes des mails d'assistance hors du dossier public, sous un nom généré.
 */
class StockagePieceJointe
{
    public function __construct(
        #[Autowire('%assistance_pieces_jointes_dir%')]
        private readonly string $dossier,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * @return string chemin relatif au dossier de stockage (AAAA/MM/identifiant aléatoire)
     */
    public function enregistrer(PieceJointeEntrante $piece): string
    {
        $relatif = date('Y/m') . '/' . bin2hex(random_bytes(16));
        $this->filesystem->dumpFile($this->dossier . '/' . $relatif, $piece->contenu);

        return $relatif;
    }

    public function cheminAbsolu(PieceJointeAssistance $piece): string
    {
        $relatif = $piece->getChemin();
        if ($relatif === '' || str_contains($relatif, '..')) {
            throw new \InvalidArgumentException('Chemin de pièce jointe invalide.');
        }

        return $this->dossier . '/' . $relatif;
    }
}
