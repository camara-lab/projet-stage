<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CityRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint public pour lister les villes desservies.
 * Utilisé par le formulaire de recherche du frontend.
 */
#[AsController]
final class CityController extends AbstractController
{
    #[OA\Get(
        path: '/api/cities',
        summary: 'Lister les villes disponibles',
        description: 'Retourne toutes les villes desservies, triées alphabétiquement. Endpoint public — aucun token requis.',
        tags: ['Trips'],
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Liste des villes'),
        ]
    )]
    #[Route('/api/cities', name: 'api_cities_list', methods: ['GET'])]
    public function list(CityRepository $cityRepository): JsonResponse
    {
        $villes = $cityRepository->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(
            fn ($v) => ['id' => $v->getId(), 'name' => $v->getName()],
            $villes,
        ));
    }
}
