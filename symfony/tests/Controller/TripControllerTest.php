<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TripControllerTest extends WebTestCase
{
    public function testListTripsReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/trips');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('pagination', $data);
        self::assertArrayHasKey('trajets', $data);
        self::assertIsArray($data['trajets']);
    }

    public function testListTripsFilterByCity(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/trips', ['departureCity' => 'Casablanca', 'arrivalCity' => 'Marrakech']);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('trajets', $data);
    }

    public function testListTripsInvalidDateReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/trips', ['date' => 'not-a-date']);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('message', $data);
    }

    public function testShowTripNotFoundReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/trips/999999');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('message', $data);
    }

    public function testShowTripReturnsSeatsInfo(): void
    {
        $client = static::createClient();
        // Cherche un trajet existant
        $client->request('GET', '/api/trips');
        $list = json_decode($client->getResponse()->getContent(), true);

        if (empty($list['trajets'])) {
            $this->markTestSkipped('Aucun trajet en base pour ce test.');
        }

        $id = $list['trajets'][0]['id'];
        $client->request('GET', "/api/trips/{$id}");

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('placesDisponibles', $data);
        self::assertArrayHasKey('siegesOccupes', $data);
        self::assertArrayHasKey('capaciteBus', $data);
    }

    public function testListTripsPaginationMetadata(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/trips', ['page' => '1']);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('page', $data['pagination']);
        self::assertArrayHasKey('total', $data['pagination']);
        self::assertArrayHasKey('totalPages', $data['pagination']);
    }
}
