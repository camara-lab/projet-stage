<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint de santé — permet de vérifier que l'API répond.
 * Utilisé par les sondes Docker/Kubernetes et comme premier smoke-test.
 */
final class HealthController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'service' => 'bus-booking-api',
            'version' => '0.1.0',
        ]);
    }
}
