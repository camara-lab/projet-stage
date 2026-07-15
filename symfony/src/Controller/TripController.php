<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Trip;
use App\Repository\TripRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur REST pour la consultation des trajets disponibles.
 *
 * Ces endpoints sont publics : aucun token JWT n'est requis pour consulter
 * les trajets. La réservation, elle, nécessite une authentification.
 *
 * Exemples de recherches typiques :
 *  - Tous les trajets Casablanca → Rabat
 *  - Tous les départs de Marrakech vers Agadir le 2026-05-15
 *  - Tous les trajets Fès → Tanger de la semaine
 */
#[AsController]
#[Route('/api/trips', name: 'api_trips_')]
final class TripController extends AbstractController
{

    /**
     * Liste les trajets disponibles (statut SCHEDULED) avec filtres optionnels.
     *
     * Paramètres de requête (query string) :
     *   - departureCity : ville de départ (ex: "Casablanca", "Fès")
     *   - arrivalCity   : ville d'arrivée (ex: "Marrakech", "Agadir")
     *   - date          : date de départ au format YYYY-MM-DD (ex: "2026-05-10")
     *   - page          : numéro de page (défaut: 1, 10 trajets par page)
     *
     * Exemple d'appel :
     *   GET /api/trips?departureCity=Casablanca&arrivalCity=Marrakech&date=2026-05-10
     *
     * @return JsonResponse 200 OK avec métadonnées de pagination + liste des trajets
     */
    #[OA\Get(
        path: '/api/trips',
        summary: 'Lister les trajets disponibles',
        description: 'Retourne les trajets SCHEDULED avec filtres optionnels et pagination (10 par page).',
        tags: ['Trips'],
        security: [],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 1, description: 'ID de la ville de départ'),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 2, description: 'ID de la ville d\'arrivée'),
            new OA\Parameter(name: 'departureCity', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'Casablanca', description: 'Nom de la ville de départ (alternatif à from)'),
            new OA\Parameter(name: 'arrivalCity', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'Marrakech', description: 'Nom de la ville d\'arrivée (alternatif à to)'),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-02-01'),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée des trajets'),
            new OA\Response(response: 400, description: 'Format de date invalide'),
        ]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, TripRepository $tripRepository): JsonResponse
    {
        // --- Lecture et validation des paramètres de filtrage ---

        // Paramètres par ID (cahier des charges) ou par nom (rétrocompatibilité)
        $fromId = $request->query->get('from') !== null ? (int) $request->query->get('from') : null;
        $toId = $request->query->get('to') !== null ? (int) $request->query->get('to') : null;
        $villeDepart = $request->query->get('departureCity');
        $villeArrivee = $request->query->get('arrivalCity');
        $dateStr = $request->query->get('date');
        $page = max(1, (int) $request->query->get('page', '1'));

        $date = null;
        if (null !== $dateStr && '' !== $dateStr) {
            $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
            if (false === $date) {
                return $this->json(
                    ['message' => 'Format de date invalide. Utilisez le format AAAA-MM-JJ (ex: 2026-02-01).'],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        $villeDepart = ('' === $villeDepart || null === $villeDepart) ? null : $villeDepart;
        $villeArrivee = ('' === $villeArrivee || null === $villeArrivee) ? null : $villeArrivee;

        // --- Exécution des requêtes optimisées ---

        $trajets = $tripRepository->findByFilters($villeDepart, $villeArrivee, $date, $page, $fromId, $toId);
        $total = $tripRepository->countByFilters($villeDepart, $villeArrivee, $date, $fromId, $toId);

        $totalPages = (int) ceil($total / TripRepository::PAR_PAGE);

        // Batch-count des sièges réservés pour tous les trajets de la page (1 seul SQL)
        $siegesReservesBatch = $tripRepository->countSiegesReservesBatch($trajets);

        return $this->json([
            'pagination' => [
                'page'       => $page,
                'parPage'    => TripRepository::PAR_PAGE,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
            'trajets' => array_map(
                fn (Trip $t) => $this->tripToArray($t, $siegesReservesBatch[$t->getId()] ?? 0),
                $trajets,
            ),
        ]);
    }


    /**
     * Retourne le détail complet d'un trajet, incluant le nombre de places
     * encore disponibles calculé dynamiquement depuis la table des réservations.
     *
     * Exemple : bus de 44 places sur Casa → Marrakech, 3 réservations actives
     * → placesDisponibles = 41
     *
     * @return JsonResponse 200 OK | 404 Trajet introuvable
     */
    #[OA\Get(
        path: '/api/trips/{id}',
        summary: 'Détail d\'un trajet',
        description: 'Retourne le détail complet d\'un trajet avec le nombre de places encore disponibles.',
        tags: ['Trips'],
        security: [],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail du trajet avec places disponibles'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, TripRepository $tripRepository): JsonResponse
    {
        $trajet = $tripRepository->find($id);

        if (null === $trajet) {
            return $this->json(
                ['message' => sprintf('Le trajet n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        $siegesReserves    = $tripRepository->countSiegesReserves($trajet);
        $numerosSieges     = $tripRepository->findBookedSeats($trajet);
        $placesDisponibles = $trajet->getBus()->getTotalSeats() - $siegesReserves;

        return $this->json(
            $this->tripToDetailArray($trajet, $placesDisponibles, $numerosSieges),
        );
    }


    /**
     * Sérialise un trajet pour la liste paginée (format allégé).
     * Les places disponibles ne sont pas calculées ici pour éviter le N+1.
     *
     * @return array<string, mixed>
     */
    private function tripToArray(Trip $trajet, int $siegesReserves = 0): array
    {
        $route = $trajet->getRoute();
        $totalSeats = $trajet->getBus()->getTotalSeats();

        return [
            'id'                => $trajet->getId(),
            'villeDepart'       => $route->getDepartureCity()->getName(),
            'villeArrivee'      => $route->getArrivalCity()->getName(),
            'heureDepart'       => $trajet->getDepartureTime()->format(\DateTimeInterface::ATOM),
            'heureArrivee'      => $trajet->getArrivalTime()->format(\DateTimeInterface::ATOM),
            'statut'            => $trajet->getStatus(),
            'prixBase'          => $route->getBasePrice(),
            'capaciteBus'       => $totalSeats,
            'placesDisponibles' => max(0, $totalSeats - $siegesReserves),
        ];
    }

    /**
     * Sérialise un trajet pour l'endpoint détail, avec les places disponibles
     * et la liste des numéros de sièges déjà occupés (pour la grille du frontend).
     *
     * @param int[] $numerosSiegesOccupes
     * @return array<string, mixed>
     */
    private function tripToDetailArray(Trip $trajet, int $placesDisponibles, array $numerosSiegesOccupes = []): array
    {
        $siegesReserves = $trajet->getBus()->getTotalSeats() - $placesDisponibles;

        return array_merge($this->tripToArray($trajet, $siegesReserves), [
            'immatriculationBus'  => $trajet->getBus()->getPlateNumber(),
            'siegesOccupes'       => $numerosSiegesOccupes,
        ]);
    }
}
