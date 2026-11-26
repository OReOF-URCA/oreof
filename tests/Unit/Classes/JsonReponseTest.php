<?php

declare(strict_types=1);

namespace App\Tests\Unit\Classes;

use App\Classes\JsonReponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class JsonReponseTest extends TestCase
{
    public function testSuccessWithoutExtra(): void
    {
        $response = JsonReponse::success('Sauvegarde OK');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $data = json_decode((string)$response->getContent(), true);
        self::assertIsArray($data);
        self::assertSame('Sauvegarde OK', $data['message']);
        self::assertTrue($data['success']);
    }

    public function testSuccessWithExtra(): void
    {
        $response = JsonReponse::success('DPE ouvert', ['redirect' => '/fiche-matiere/1/modifier']);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string)$response->getContent(), true);
        self::assertIsArray($data);
        self::assertSame('DPE ouvert', $data['message']);
        self::assertTrue($data['success']);
        self::assertSame('/fiche-matiere/1/modifier', $data['redirect']);
    }

    public function testError(): void
    {
        $response = JsonReponse::error('Erreur de validation');

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode((string)$response->getContent(), true);
        self::assertIsArray($data);
        self::assertSame('Erreur de validation', $data['message']);
    }
}
