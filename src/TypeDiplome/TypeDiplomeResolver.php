<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/TypeDiplomeResolver.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 09/01/2026 20:10
 */

namespace App\TypeDiplome;

use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use LogicException;
use Psr\Container\ContainerInterface;

final readonly class TypeDiplomeResolver
{
    public function __construct(
        private ContainerInterface $handlersByCode,
    )
    {
    }

    public function fromParcours(Parcours $parcours): TypeDiplomeHandlerInterface
    {
        return $this->fromFormation(
            $parcours->getFormation()
            ?? throw new LogicException('No formation on parcours')
        );
    }

    public function fromFormation(Formation $formation): TypeDiplomeHandlerInterface
    {
        return $this->fromTypeDiplome(
            $formation->getTypeDiplome()
            ?? throw new LogicException('No type diplome on formation')
        );
    }

    public function fromTypeDiplome(TypeDiplome $type): TypeDiplomeHandlerInterface
    {
        $code = strtoupper(trim($type->getLibelleCourt())); // idéalement $type->getCode()
        $key = $this->handlersByCode->has($code) ? $code : 'DEFAULT';

        if (!$this->handlersByCode->has($key)) {
            throw new LogicException(sprintf(
                'No handler for TypeDiplome "%s" (%s)',
                $type->getLibelle(),
                $code
            ));
        }

        $handler = $this->handlersByCode->get($key);

        if (!$handler instanceof TypeDiplomeHandlerInterface) {
            throw new LogicException(sprintf('Handler for "%s" is invalid.', $code));
        }

        return $handler;
    }

    public function get(TypeDiplome|Formation|Parcours $type): TypeDiplomeHandlerInterface
    {
        if ($type instanceof TypeDiplome) {
            return $this->fromTypeDiplome($type);
        }

        if ($type instanceof Formation) {
            return $this->fromFormation($type);
        }

        return $this->fromParcours($type);
    }
}
