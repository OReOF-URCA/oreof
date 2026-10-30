<?php

namespace App\Controller\Offre;

use App\Entity\Formation;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Droits d'accès à la configuration de l'offre d'une formation : même règle que la liste
 * (`app_offre_composante_index`), étendue à la scolarité centrale qui conserve l'édition.
 */
trait OffreAccessTrait
{
    private function canConfigurerOffre(?Formation $formation): bool
    {
        try {
            $this->denyAccessUnlessCanConfigurerOffre($formation);
        } catch (AccessDeniedException) {
            return false;
        }

        return true;
    }

    private function denyAccessUnlessCanConfigurerOffre(?Formation $formation): void
    {
        if ($formation === null) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        $composante = $formation->getComposantePorteuse();
        if ($composante === null) {
            throw $this->createAccessDeniedException();
        }

        $this->denyAccessUnlessGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $composante,
        ]);
    }
}
