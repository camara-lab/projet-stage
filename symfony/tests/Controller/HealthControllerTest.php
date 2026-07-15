<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $client->getResponse();
        $content  = $response->getContent();
        self::assertNotFalse($content);

        $payload = json_decode($content, true);
        self::assertIsArray($payload);
        self::assertSame('ok', $payload['status']);
        self::assertSame('bus-booking-api', $payload['service']);
    }
}
