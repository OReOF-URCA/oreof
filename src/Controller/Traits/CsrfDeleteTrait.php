<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Controller/Traits/CsrfDeleteTrait.php
 * @author davidannebicque
 * @project oreofv2
 */

declare(strict_types=1);

namespace App\Controller\Traits;

use App\Utils\JsonRequest;
use Symfony\Component\HttpFoundation\Request;

trait CsrfDeleteTrait
{
    protected function getCsrfTokenFromRequest(Request $request): ?string
    {
        $token = $request->request->get('_token') ?? $request->request->get('csrf_token');
        if ($token === null) {
            try {
                $content = $request->getContent();
                if ($content !== '' && (str_starts_with(trim($content), '{') || str_starts_with(trim($content), '['))) {
                    $token = JsonRequest::getValueFromRequest($request, 'csrf');
                }
            } catch (\JsonException) {
                $token = null;
            }
        }

        return $token;
    }

    protected function isDeleteTokenValid(object $entity, ?string $token): bool
    {
        $className = (new \ReflectionClass($entity))->getShortName();
        $intention = sprintf('delete-%s-%s', strtolower($className), $entity->getId());

        return $this->isCsrfTokenValid($intention, $token);
    }

    abstract protected function isCsrfTokenValid(string $id, ?string $token): bool;
}
