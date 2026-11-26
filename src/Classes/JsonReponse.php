<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/JsonReponse.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/06/2023 09:17
 */

namespace App\Classes;

use Symfony\Component\HttpFoundation\Response;

class JsonReponse
{
    public function __construct(
        private readonly int    $state,
        private readonly string $message,
        private readonly array  $array = []
    )
    {
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function success(string $message, array $extra = []): Response
    {
        return new Response(json_encode(array_merge([
            'message' => $message,
            'success' => true,
        ], $extra), JSON_THROW_ON_ERROR), Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    public static function error(string $message): Response
    {
        return new Response(json_encode([
            'message' => $message,
        ], JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR, ['Content-Type' => 'application/json']);
    }

    public function getReponse(): Response
    {
        return new Response(json_encode([
            'message' => $this->message,
            'data' => $this->array
        ], JSON_THROW_ON_ERROR), $this->state, ['Content-Type' => 'application/json']);
    }
}
