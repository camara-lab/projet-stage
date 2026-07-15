<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthControllerTest extends WebTestCase
{
    private function truncate(EntityManagerInterface $em): void
    {
        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('TRUNCATE TABLE bookings');
        $conn->executeStatement('TRUNCATE TABLE users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    // ── Register ─────────────────────────────────────────────────────────────

    public function testRegisterCreatesUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->truncate($em);

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'test.register@busgo.ma',
            'password' => 'BusGo2024!',
            'fullName' => 'Test User',
        ]));

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('test.register@busgo.ma', $data['email']);
        self::assertSame('Test User', $data['fullName']);
        self::assertArrayHasKey('id', $data);
    }

    public function testRegisterWithDuplicateEmailReturnsConflict(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->truncate($em);

        $payload = json_encode([
            'email'    => 'duplicate@busgo.ma',
            'password' => 'BusGo2024!',
            'fullName' => 'Duplicate',
        ]);

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        self::assertResponseStatusCodeSame(409);
    }

    public function testRegisterWithInvalidPasswordReturnsValidationError(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'weak@busgo.ma',
            'password' => '123',
            'fullName' => 'Weak Password',
        ]));

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('password', $data['errors']);
    }

    // ── Login ────────────────────────────────────────────────────────────────

    public function testLoginReturnsJwtToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->truncate($em);

        // Créer un utilisateur
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'login.test@busgo.ma',
            'password' => 'BusGo2024!',
            'fullName' => 'Login Test',
        ]));
        self::assertResponseStatusCodeSame(201);

        // Login
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'login.test@busgo.ma',
            'password' => 'BusGo2024!',
        ]));

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $data);
        self::assertNotEmpty($data['token']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email'    => 'nobody@busgo.ma',
            'password' => 'WrongPassword1',
        ]));

        self::assertResponseStatusCodeSame(401);
    }
}
