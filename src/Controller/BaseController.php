<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/BaseController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 05/02/2023 11:05
 */

namespace App\Controller;

use App\Classes\DataUserSession;
use App\Entity\CampagneCollecte;
use App\Entity\Constantes;
use App\Entity\Etablissement;
use App\TypeDiplome\TypeDiplomeResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Component\HttpFoundation\RequestStack;

class BaseController extends AbstractController
{
    protected DataUserSession $dataUserSession;
    protected ?TypeDiplomeResolver $typeDiplomeResolver = null;

    private ?RequestStack $requestStack = null;

    #[Required]
    public function setDataUserSession(DataUserSession $dataUserSession): void
    {
        $this->dataUserSession = $dataUserSession;
    }

    #[Required]
    public function getTypeDiplomeResolver(TypeDiplomeResolver $typeDiplomeResolver): void
    {
        $this->typeDiplomeResolver = $typeDiplomeResolver;
    }

    #[Required]
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    protected function isTurboFrameRequest(): bool
    {
        if ($this->requestStack === null) {
            return false;
        }

        $req = $this->requestStack->getCurrentRequest();

        if (!$req) {
            return false;
        }

        if ($req->headers->has('Turbo-Frame')) {
            return true;
        }

        $accept = $req->headers->get('Accept', '');
        if (str_contains($accept, 'text/vnd.turbo-stream.html') || str_contains($accept, 'text/vnd.turbo-html')) {
            return true;
        }

        return false;
    }

    public function addFlashBag(string $niveau, string $message): void
    {
        switch ($niveau) {
            case Constantes::FLASHBAG_INFO:
                $this->toast(Constantes::FLASHBAG_INFO, $message, 'Information');
                break;
            case Constantes::FLASHBAG_SUCCESS:
                $this->toast(Constantes::FLASHBAG_SUCCESS, $message, 'Succès');
                break;
            case Constantes::FLASHBAG_NOTICE:
                $this->toast(Constantes::FLASHBAG_NOTICE, $message, 'Attention');
                break;
            case Constantes::FLASHBAG_ERROR:
                $this->toast(Constantes::FLASHBAG_ERROR, $message, 'Erreur');
                break;
            default:
                $this->toast(Constantes::FLASHBAG_ERROR, 'Clé inexistante', 'Erreur');
        }
    }

    protected function toast(string $type, string $text, ?string $title = null): void
    {
        $this->addFlash('toast', [
            'type' => $type,
            'text' => $text,
            'title' => $title,
        ]);
    }

    public function getCampagneCollecte(): CampagneCollecte
    {
        return $this->dataUserSession->getCampagneCollecte();
    }

    public function getEtablissement(): ?Etablissement
    {
        return $this->dataUserSession->getEtablissement();
    }
}
