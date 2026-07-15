<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Bus;
use App\Entity\City;
use App\Entity\Route as BusRoute;
use App\Entity\Trip;
use App\Repository\BookingRepository;
use App\Repository\BusRepository;
use App\Repository\CityRepository;
use App\Repository\RouteRepository;
use App\Repository\TripRepository;
use App\Service\TripCancellationService;
use App\Service\AdminStatsService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur d'administration — accès réservé à ROLE_ADMIN.
 *
 * La règle d'accès est définie dans security.yaml :
 *   { path: ^/api/admin, roles: ROLE_ADMIN }
 *
 * Fonctionnalités :
 *  - Gestion de la flotte de bus (CTM, Supratours…)
 *  - Planification des trajets inter-villes
 *  - Tableau de bord statistiques (CA, occupation, top lignes)
 *  - Annulation forcée de réservations
 */
#[AsController]
#[Route('/api/admin', name: 'api_admin_')]
final class AdminController extends AbstractController
{

    /**
     * Enregistre un nouveau bus dans la flotte (CTM, Supratours, etc.).
     *
     * Corps JSON attendu :
     *   { "plateNumber": "CTM-W-99999", "totalSeats": 44 }
     *
     * @return JsonResponse 201 Created | 422 Champs invalides
     */
    #[OA\Post(
        path: '/api/admin/buses',
        summary: 'Ajouter un bus à la flotte',
        description: 'Enregistre un nouveau bus (CTM, Supratours…). Capacité entre 1 et 100 places.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['plateNumber', 'totalSeats'],
                properties: [
                    new OA\Property(property: 'plateNumber', type: 'string', example: 'CTM-W-99999', description: 'Immatriculation du bus'),
                    new OA\Property(property: 'totalSeats', type: 'integer', example: 44, description: 'Nombre de places (1-100)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Bus créé avec succès'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 422, description: 'Champs obligatoires manquants ou capacité invalide'),
        ]
    )]
    #[Route('/buses', name: 'bus_create', methods: ['POST'])]
    public function creerBus(
        Request $request,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(
                ['message' => 'Corps de requête JSON invalide.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!isset($data['plateNumber'], $data['totalSeats'])) {
            return $this->json(
                ['message' => 'Les champs plateNumber et totalSeats sont obligatoires.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $totalSeats = (int) $data['totalSeats'];

        if ($totalSeats < 1 || $totalSeats > 100) {
            return $this->json(
                ['message' => 'La capacité du bus doit être comprise entre 1 et 100 places.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $bus = new Bus();
        $bus->setPlateNumber((string) $data['plateNumber']);
        $bus->setTotalSeats($totalSeats);

        $em->persist($bus);
        $em->flush();

        return $this->json($this->busToArray($bus), Response::HTTP_CREATED);
    }


    #[OA\Get(
        path: '/api/admin/buses',
        summary: 'Lister tous les bus',
        description: 'Retourne la liste complète de la flotte, triée par immatriculation.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des bus'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    #[Route('/buses', name: 'bus_list', methods: ['GET'])]
    public function listerBus(BusRepository $busRepository): JsonResponse
    {
        $buses = $busRepository->findBy([], ['plateNumber' => 'ASC']);

        return $this->json(array_map($this->busToArray(...), $buses));
    }


    #[OA\Get(
        path: '/api/admin/buses/{id}',
        summary: 'Détail d\'un bus',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail du bus'),
            new OA\Response(response: 404, description: 'Bus introuvable'),
        ]
    )]
    #[Route('/buses/{id}', name: 'bus_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function voirBus(int $id, BusRepository $busRepository): JsonResponse
    {
        $bus = $busRepository->find($id);

        if (null === $bus) {
            return $this->json(
                ['message' => sprintf('Le bus n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json($this->busToArray($bus));
    }


    #[OA\Put(
        path: '/api/admin/buses/{id}',
        summary: 'Modifier un bus',
        description: 'Met à jour l\'immatriculation, la capacité ou le statut d\'un bus.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'plateNumber', type: 'string', example: 'CTM-W-99999'),
                    new OA\Property(property: 'totalSeats', type: 'integer', example: 44),
                    new OA\Property(property: 'status', type: 'string', example: 'MAINTENANCE', enum: ['AVAILABLE', 'MAINTENANCE']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Bus modifié'),
            new OA\Response(response: 404, description: 'Bus introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    #[Route('/buses/{id}', name: 'bus_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function modifierBus(
        int $id,
        Request $request,
        BusRepository $busRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $bus = $busRepository->find($id);

        if (null === $bus) {
            return $this->json(
                ['message' => sprintf('Le bus n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(['message' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['plateNumber']) && '' !== trim((string) $data['plateNumber'])) {
            $bus->setPlateNumber(trim((string) $data['plateNumber']));
        }

        if (isset($data['totalSeats'])) {
            $seats = (int) $data['totalSeats'];
            if ($seats < 1 || $seats > 100) {
                return $this->json(
                    ['message' => 'La capacité doit être comprise entre 1 et 100 places.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
            $bus->setTotalSeats($seats);
        }

        if (isset($data['status'])) {
            if (!\in_array($data['status'], ['AVAILABLE', 'MAINTENANCE'], true)) {
                return $this->json(
                    ['message' => 'Le statut doit être AVAILABLE ou MAINTENANCE.'],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
            $bus->setStatus((string) $data['status']);
        }

        $em->flush();

        return $this->json($this->busToArray($bus));
    }


    #[OA\Delete(
        path: '/api/admin/buses/{id}',
        summary: 'Supprimer un bus',
        description: 'Supprime un bus. Impossible s\'il est affecté à des trajets.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Bus supprimé'),
            new OA\Response(response: 404, description: 'Bus introuvable'),
            new OA\Response(response: 409, description: 'Bus affecté à des trajets existants'),
        ]
    )]
    #[Route('/buses/{id}', name: 'bus_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function supprimerBus(
        int $id,
        BusRepository $busRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $bus = $busRepository->find($id);

        if (null === $bus) {
            return $this->json(
                ['message' => sprintf('Le bus n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($bus->getTrips()->count() > 0) {
            return $this->json(
                ['message' => sprintf('Impossible de supprimer le bus "%s" : il est affecté à %d trajet(s).', $bus->getPlateNumber(), $bus->getTrips()->count())],
                Response::HTTP_CONFLICT,
            );
        }

        $em->remove($bus);
        $em->flush();

        return $this->json(['message' => sprintf('Bus "%s" supprimé avec succès.', $bus->getPlateNumber())]);
    }


    /**
     * Planifie un nouveau trajet en vérifiant l'absence de chevauchement horaire.
     *
     * Corps JSON attendu :
     * {
     *   "routeId": 1,
     *   "busId": 2,
     *   "departureTime": "2026-06-01T08:00:00",
     *   "arrivalTime": "2026-06-01T11:00:00"
     * }
     *
     * Règle métier : un bus CTM ne peut pas être sur deux trajets en même temps.
     * Ex : si CTM-W-12345 fait Casa → Marrakech de 08h à 11h, impossible de le
     * planifier sur Rabat → Casablanca de 10h à 12h (chevauchement de 1h).
     *
     * @return JsonResponse 201 Created | 409 Bus déjà occupé | 422 Données invalides
     */
    #[OA\Post(
        path: '/api/admin/trips',
        summary: 'Planifier un trajet',
        description: "Crée un nouveau trajet inter-villes.\n\n**Règle métier** : un bus ne peut pas être sur deux trajets simultanément. Exemple : si CTM-W-12345 fait Casa→Marrakech de 08h à 11h, impossible de le planifier sur Rabat→Casa de 10h à 12h (chevauchement de 1h).",
        tags: ['Admin'],
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['routeId', 'busId', 'departureTime', 'arrivalTime'],
                properties: [
                    new OA\Property(property: 'routeId', type: 'integer', example: 1, description: 'ID de la ligne'),
                    new OA\Property(property: 'busId', type: 'integer', example: 2, description: 'ID du bus'),
                    new OA\Property(property: 'departureTime', type: 'string', format: 'date-time', example: '2026-06-01T08:00:00', description: 'Heure de départ (ISO 8601)'),
                    new OA\Property(property: 'arrivalTime', type: 'string', format: 'date-time', example: '2026-06-01T11:00:00', description: 'Heure d\'arrivée (ISO 8601)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Trajet planifié avec succès'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Ligne ou bus introuvable'),
            new OA\Response(response: 409, description: 'Bus déjà occupé sur cette plage horaire'),
            new OA\Response(response: 422, description: 'Champs manquants ou format de date invalide'),
        ]
    )]
    #[Route('/trips', name: 'trip_create', methods: ['POST'])]
    public function planifierTrajet(
        Request $request,
        RouteRepository $routeRepository,
        BusRepository $busRepository,
        TripRepository $tripRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(
                ['message' => 'Corps de requête JSON invalide.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!isset($data['routeId'], $data['busId'], $data['departureTime'], $data['arrivalTime'])) {
            return $this->json(
                ['message' => 'Les champs routeId, busId, departureTime et arrivalTime sont obligatoires.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // --- Validation de la ligne ---
        $route = $routeRepository->find((int) $data['routeId']);
        if (null === $route) {
            return $this->json(
                ['message' => sprintf('La ligne n°%d est introuvable.', (int) $data['routeId'])],
                Response::HTTP_NOT_FOUND,
            );
        }

        // --- Validation du bus ---
        $bus = $busRepository->find((int) $data['busId']);
        if (null === $bus) {
            return $this->json(
                ['message' => sprintf('Le bus n°%d est introuvable.', (int) $data['busId'])],
                Response::HTTP_NOT_FOUND,
            );
        }

        // --- Validation et parsing des horaires ---
        $heureDepart = \DateTime::createFromFormat(\DateTimeInterface::ATOM, (string) $data['departureTime'])
            ?: \DateTime::createFromFormat('Y-m-d\TH:i:s', (string) $data['departureTime']);
        $heureArrivee = \DateTime::createFromFormat(\DateTimeInterface::ATOM, (string) $data['arrivalTime'])
            ?: \DateTime::createFromFormat('Y-m-d\TH:i:s', (string) $data['arrivalTime']);

        if (false === $heureDepart || false === $heureArrivee) {
            return $this->json(
                ['message' => 'Format de date invalide. Utilisez le format ISO 8601 (ex: 2026-06-01T08:00:00).'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($heureArrivee <= $heureDepart) {
            return $this->json(
                ['message' => 'L\'heure d\'arrivée doit être postérieure à l\'heure de départ.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // --- Vérification du chevauchement horaire ---
        if ($tripRepository->isBusOccupe($bus, $heureDepart, $heureArrivee)) {
            return $this->json(
                ['message' => sprintf(
                    'Le bus "%s" est déjà affecté à un autre trajet sur cette plage horaire.',
                    $bus->getPlateNumber(),
                )],
                Response::HTTP_CONFLICT,
            );
        }

        // --- Création du trajet ---
        $trajet = new Trip();
        $trajet->setRoute($route);
        $trajet->setBus($bus);
        $trajet->setDepartureTime($heureDepart);
        $trajet->setArrivalTime($heureArrivee);

        $em->persist($trajet);
        $em->flush();

        return $this->json($this->trajetToArray($trajet), Response::HTTP_CREATED);
    }


    #[OA\Get(
        path: '/api/admin/trips',
        summary: 'Lister tous les trajets',
        description: 'Retourne tous les trajets triés par date de départ décroissante. Filtre optionnel par statut.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['SCHEDULED', 'CANCELLED', 'COMPLETED'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des trajets'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    #[Route('/trips', name: 'trip_list', methods: ['GET'])]
    public function listerTrajets(Request $request, TripRepository $tripRepository): JsonResponse
    {
        $statut = $request->query->get('statut');

        $criteria = [];
        if (null !== $statut && \in_array($statut, ['SCHEDULED', 'CANCELLED', 'COMPLETED'], true)) {
            $criteria['status'] = $statut;
        }

        $trajets = $tripRepository->findBy($criteria, ['departureTime' => 'DESC']);

        return $this->json(array_map($this->trajetToArray(...), $trajets));
    }


    #[OA\Get(
        path: '/api/admin/trips/{id}',
        summary: 'Détail d\'un trajet',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail du trajet'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    #[Route('/trips/{id}', name: 'trip_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function voirTrajet(int $id, TripRepository $tripRepository): JsonResponse
    {
        $trajet = $tripRepository->find($id);

        if (null === $trajet) {
            return $this->json(
                ['message' => sprintf('Le trajet n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json($this->trajetToArray($trajet));
    }


    #[OA\Put(
        path: '/api/admin/trips/{id}',
        summary: 'Modifier un trajet',
        description: 'Modifie les horaires, la ligne ou le bus d\'un trajet SCHEDULED. Impossible sur un trajet annulé.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'routeId', type: 'integer', example: 1),
                    new OA\Property(property: 'busId', type: 'integer', example: 2),
                    new OA\Property(property: 'departureTime', type: 'string', format: 'date-time', example: '2026-07-01T08:00:00'),
                    new OA\Property(property: 'arrivalTime', type: 'string', format: 'date-time', example: '2026-07-01T11:00:00'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Trajet modifié'),
            new OA\Response(response: 400, description: 'Trajet annulé ou horaires invalides'),
            new OA\Response(response: 404, description: 'Trajet, ligne ou bus introuvable'),
            new OA\Response(response: 409, description: 'Bus déjà occupé sur cette plage horaire'),
            new OA\Response(response: 422, description: 'Format de date invalide'),
        ]
    )]
    #[Route('/trips/{id}', name: 'trip_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function modifierTrajet(
        int $id,
        Request $request,
        TripRepository $tripRepository,
        RouteRepository $routeRepository,
        BusRepository $busRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $trajet = $tripRepository->find($id);

        if (null === $trajet) {
            return $this->json(
                ['message' => sprintf('Le trajet n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($trajet->getStatus() === 'CANCELLED') {
            return $this->json(
                ['message' => 'Impossible de modifier un trajet annulé.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(['message' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        // Mise à jour optionnelle de la ligne
        if (isset($data['routeId'])) {
            $route = $routeRepository->find((int) $data['routeId']);
            if (null === $route) {
                return $this->json(['message' => sprintf('La ligne n°%d est introuvable.', (int) $data['routeId'])], Response::HTTP_NOT_FOUND);
            }
            $trajet->setRoute($route);
        }

        // Mise à jour optionnelle du bus
        if (isset($data['busId'])) {
            $bus = $busRepository->find((int) $data['busId']);
            if (null === $bus) {
                return $this->json(['message' => sprintf('Le bus n°%d est introuvable.', (int) $data['busId'])], Response::HTTP_NOT_FOUND);
            }
            $trajet->setBus($bus);
        }

        // Mise à jour optionnelle des horaires
        $parseDate = static fn (string $val): \DateTime|false =>
            \DateTime::createFromFormat(\DateTimeInterface::ATOM, $val)
            ?: \DateTime::createFromFormat('Y-m-d\TH:i:s', $val);

        if (isset($data['departureTime'])) {
            $hd = $parseDate((string) $data['departureTime']);
            if (false === $hd) {
                return $this->json(['message' => 'Format departureTime invalide. Utilisez ISO 8601.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $trajet->setDepartureTime($hd);
        }

        if (isset($data['arrivalTime'])) {
            $ha = $parseDate((string) $data['arrivalTime']);
            if (false === $ha) {
                return $this->json(['message' => 'Format arrivalTime invalide. Utilisez ISO 8601.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $trajet->setArrivalTime($ha);
        }

        // Validation des horaires
        if ($trajet->getArrivalTime() <= $trajet->getDepartureTime()) {
            return $this->json(
                ['message' => 'L\'heure d\'arrivée doit être postérieure à l\'heure de départ.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if ($trajet->getDepartureTime() < new \DateTime()) {
            return $this->json(
                ['message' => 'La date de départ doit être dans le futur.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Vérification du chevauchement (en excluant le trajet lui-même)
        if ($tripRepository->isBusOccupe($trajet->getBus(), $trajet->getDepartureTime(), $trajet->getArrivalTime(), $trajet->getId())) {
            return $this->json(
                ['message' => sprintf('Le bus "%s" est déjà affecté à un autre trajet sur cette plage horaire.', $trajet->getBus()->getPlateNumber())],
                Response::HTTP_CONFLICT,
            );
        }

        $em->flush();

        return $this->json($this->trajetToArray($trajet));
    }


    #[OA\Put(
        path: '/api/admin/trips/{id}/cancel',
        summary: 'Annuler un trajet',
        description: 'Passe le statut du trajet à CANCELLED. Les réservations liées restent inchangées (à gérer séparément).',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet annulé'),
            new OA\Response(response: 400, description: 'Trajet déjà annulé'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
        ]
    )]
    #[Route('/trips/{id}/cancel', name: 'trip_cancel', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function annulerTrajet(
        int $id,
        TripRepository $tripRepository,
        TripCancellationService $cancellationService,
    ): JsonResponse {
        $trajet = $tripRepository->find($id);

        if (null === $trajet) {
            return $this->json(
                ['message' => sprintf('Le trajet n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($trajet->getStatus() === 'CANCELLED') {
            return $this->json(
                ['message' => sprintf('Le trajet n°%d est déjà annulé.', $id)],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $result = $cancellationService->cancelTrip($trajet);

        return $this->json([
            'message'            => sprintf('Trajet n°%d annulé avec succès.', $id),
            'trajetId'           => $trajet->getId(),
            'nbReservations'     => $result['nbReservations'],
            'nbRembourses'       => $result['nbRembourses'],
        ]);
    }


    #[OA\Delete(
        path: '/api/admin/trips/{id}',
        summary: 'Supprimer un trajet',
        description: 'Supprime un trajet. Impossible s\'il possède des réservations.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trajet supprimé'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
            new OA\Response(response: 409, description: 'Trajet lié à des réservations'),
        ]
    )]
    #[Route('/trips/{id}', name: 'trip_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function supprimerTrajet(
        int $id,
        TripRepository $tripRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $trajet = $tripRepository->find($id);

        if (null === $trajet) {
            return $this->json(
                ['message' => sprintf('Le trajet n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($trajet->getBookings()->count() > 0) {
            return $this->json(
                ['message' => sprintf('Impossible de supprimer le trajet n°%d : il possède %d réservation(s).', $id, $trajet->getBookings()->count())],
                Response::HTTP_CONFLICT,
            );
        }

        $em->remove($trajet);
        $em->flush();

        return $this->json(['message' => sprintf('Trajet n°%d supprimé avec succès.', $id)]);
    }


    /**
     * Retourne les indicateurs clés de performance du réseau de bus.
     *
     * Indicateurs :
     *  - chiffreAffaires    : somme des paiements confirmés en DH
     *  - tauxOccupation     : pourcentage moyen de remplissage des bus
     *  - topLignes          : top 3 des lignes par nombre de réservations
     *
     * @return JsonResponse 200 OK avec les 3 indicateurs
     */
    #[OA\Get(
        path: '/api/admin/stats',
        summary: 'Tableau de bord statistiques',
        description: 'Retourne le chiffre d\'affaires, le taux d\'occupation moyen et le top 3 des lignes.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques du réseau'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function dashboard(AdminStatsService $statsService): JsonResponse
    {
        return $this->json([
            'chiffreAffaires' => [
                'total' => $statsService->chiffreAffairesTotal(),
                'devise' => 'DH',
            ],
            'tauxOccupation' => [
                'moyen' => $statsService->tauxOccupationMoyen(),
                'unite' => '%',
            ],
            'topLignes' => $statsService->topTroisLignes(),
        ]);
    }


    #[OA\Post(
        path: '/api/admin/cities',
        summary: 'Créer une ville',
        description: 'Ajoute une nouvelle ville desservie par le réseau.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Agadir'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ville créée avec succès'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 409, description: 'Ville déjà existante'),
            new OA\Response(response: 422, description: 'Champ name manquant'),
        ]
    )]
    #[Route('/cities', name: 'city_create', methods: ['POST'])]
    public function creerVille(
        Request $request,
        CityRepository $cityRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data) || !isset($data['name']) || '' === trim((string) $data['name'])) {
            return $this->json(
                ['message' => 'Le champ name est obligatoire.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $nom = trim((string) $data['name']);

        if (null !== $cityRepository->findOneBy(['name' => $nom])) {
            return $this->json(
                ['message' => sprintf('La ville "%s" existe déjà.', $nom)],
                Response::HTTP_CONFLICT,
            );
        }

        $ville = new City();
        $ville->setName($nom);
        $em->persist($ville);
        $em->flush();

        return $this->json(['id' => $ville->getId(), 'name' => $ville->getName()], Response::HTTP_CREATED);
    }


    #[OA\Get(
        path: '/api/admin/cities',
        summary: 'Lister toutes les villes',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des villes'),
        ]
    )]
    #[Route('/cities', name: 'city_list', methods: ['GET'])]
    public function listerVilles(CityRepository $cityRepository): JsonResponse
    {
        $villes = $cityRepository->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(
            fn ($v) => ['id' => $v->getId(), 'name' => $v->getName(), 'creeLe' => $v->getCreatedAt()->format(\DateTimeInterface::ATOM)],
            $villes,
        ));
    }


    #[OA\Get(
        path: '/api/admin/cities/{id}',
        summary: 'Détail d\'une ville',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail de la ville'),
            new OA\Response(response: 404, description: 'Ville introuvable'),
        ]
    )]
    #[Route('/cities/{id}', name: 'city_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function voirVille(int $id, CityRepository $cityRepository): JsonResponse
    {
        $ville = $cityRepository->find($id);

        if (null === $ville) {
            return $this->json(['message' => sprintf('La ville n°%d est introuvable.', $id)], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['id' => $ville->getId(), 'name' => $ville->getName(), 'creeLe' => $ville->getCreatedAt()->format(\DateTimeInterface::ATOM)]);
    }


    #[OA\Put(
        path: '/api/admin/cities/{id}',
        summary: 'Modifier une ville',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [new OA\Property(property: 'name', type: 'string', example: 'Tanger-Med')]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ville modifiée'),
            new OA\Response(response: 404, description: 'Ville introuvable'),
            new OA\Response(response: 409, description: 'Nom déjà utilisé'),
            new OA\Response(response: 422, description: 'Champ name manquant'),
        ]
    )]
    #[Route('/cities/{id}', name: 'city_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function modifierVille(
        int $id,
        Request $request,
        CityRepository $cityRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $ville = $cityRepository->find($id);

        if (null === $ville) {
            return $this->json(['message' => sprintf('La ville n°%d est introuvable.', $id)], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data) || !isset($data['name']) || '' === trim((string) $data['name'])) {
            return $this->json(['message' => 'Le champ name est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $nom = trim((string) $data['name']);

        $existante = $cityRepository->findOneBy(['name' => $nom]);
        if (null !== $existante && $existante->getId() !== $ville->getId()) {
            return $this->json(['message' => sprintf('Le nom "%s" est déjà utilisé par une autre ville.', $nom)], Response::HTTP_CONFLICT);
        }

        $ville->setName($nom);
        $em->flush();

        return $this->json(['id' => $ville->getId(), 'name' => $ville->getName()]);
    }


    #[OA\Delete(
        path: '/api/admin/cities/{id}',
        summary: 'Supprimer une ville',
        description: 'Supprime une ville. Impossible si elle est reliée à des lignes.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ville supprimée'),
            new OA\Response(response: 404, description: 'Ville introuvable'),
            new OA\Response(response: 409, description: 'Ville liée à des lignes existantes'),
        ]
    )]
    #[Route('/cities/{id}', name: 'city_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function supprimerVille(
        int $id,
        CityRepository $cityRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $ville = $cityRepository->find($id);

        if (null === $ville) {
            return $this->json(['message' => sprintf('La ville n°%d est introuvable.', $id)], Response::HTTP_NOT_FOUND);
        }

        if ($ville->getDepartureRoutes()->count() > 0 || $ville->getArrivalRoutes()->count() > 0) {
            $nb = $ville->getDepartureRoutes()->count() + $ville->getArrivalRoutes()->count();
            return $this->json(
                ['message' => sprintf('Impossible de supprimer "%s" : elle est utilisée par %d ligne(s).', $ville->getName(), $nb)],
                Response::HTTP_CONFLICT,
            );
        }

        $nom = $ville->getName();
        $em->remove($ville);
        $em->flush();

        return $this->json(['message' => sprintf('Ville "%s" supprimée avec succès.', $nom)]);
    }


    #[OA\Post(
        path: '/api/admin/routes',
        summary: 'Créer une ligne (route)',
        description: 'Connecte deux villes avec un tarif de base. Les villes de départ et d\'arrivée doivent être différentes.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['departureCityId', 'arrivalCityId', 'basePrice'],
                properties: [
                    new OA\Property(property: 'departureCityId', type: 'integer', example: 1),
                    new OA\Property(property: 'arrivalCityId', type: 'integer', example: 2),
                    new OA\Property(property: 'basePrice', type: 'number', format: 'float', example: 120.00),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ligne créée avec succès'),
            new OA\Response(response: 400, description: 'Départ et arrivée identiques'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Ville de départ ou d\'arrivée introuvable'),
            new OA\Response(response: 422, description: 'Champs obligatoires manquants'),
        ]
    )]
    #[Route('/routes', name: 'route_create', methods: ['POST'])]
    public function creerLigne(
        Request $request,
        CityRepository $cityRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data) || !isset($data['departureCityId'], $data['arrivalCityId'], $data['basePrice'])) {
            return $this->json(
                ['message' => 'Les champs departureCityId, arrivalCityId et basePrice sont obligatoires.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $depId = (int) $data['departureCityId'];
        $arrId = (int) $data['arrivalCityId'];

        if ($depId === $arrId) {
            return $this->json(
                ['message' => 'La ville de départ et d\'arrivée doivent être différentes.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $villeDepart = $cityRepository->find($depId);
        if (null === $villeDepart) {
            return $this->json(['message' => sprintf('Ville de départ n°%d introuvable.', $depId)], Response::HTTP_NOT_FOUND);
        }

        $villeArrivee = $cityRepository->find($arrId);
        if (null === $villeArrivee) {
            return $this->json(['message' => sprintf('Ville d\'arrivée n°%d introuvable.', $arrId)], Response::HTTP_NOT_FOUND);
        }

        $route = new BusRoute();
        $route->setDepartureCity($villeDepart);
        $route->setArrivalCity($villeArrivee);
        $route->setBasePrice((string) $data['basePrice']);
        $em->persist($route);
        $em->flush();

        return $this->json([
            'id' => $route->getId(),
            'villeDepart' => $villeDepart->getName(),
            'villeArrivee' => $villeArrivee->getName(),
            'prixBase' => $route->getBasePrice(),
        ], Response::HTTP_CREATED);
    }


    #[OA\Get(
        path: '/api/admin/routes',
        summary: 'Lister toutes les lignes',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des lignes'),
        ]
    )]
    #[Route('/routes', name: 'route_list', methods: ['GET'])]
    public function listerLignes(RouteRepository $routeRepository): JsonResponse
    {
        $lignes = $routeRepository->findAll();

        return $this->json(array_map($this->ligneToArray(...), $lignes));
    }


    #[OA\Get(
        path: '/api/admin/routes/{id}',
        summary: 'Détail d\'une ligne',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail de la ligne'),
            new OA\Response(response: 404, description: 'Ligne introuvable'),
        ]
    )]
    #[Route('/routes/{id}', name: 'route_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function voirLigne(int $id, RouteRepository $routeRepository): JsonResponse
    {
        $ligne = $routeRepository->find($id);

        if (null === $ligne) {
            return $this->json(['message' => sprintf('La ligne n°%d est introuvable.', $id)], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->ligneToArray($ligne));
    }


    #[OA\Put(
        path: '/api/admin/routes/{id}',
        summary: 'Modifier une ligne',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'departureCityId', type: 'integer', example: 1),
                    new OA\Property(property: 'arrivalCityId', type: 'integer', example: 3),
                    new OA\Property(property: 'basePrice', type: 'number', example: 150.00),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ligne modifiée'),
            new OA\Response(response: 400, description: 'Départ = arrivée'),
            new OA\Response(response: 404, description: 'Ligne ou ville introuvable'),
        ]
    )]
    #[Route('/routes/{id}', name: 'route_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function modifierLigne(
        int $id,
        Request $request,
        RouteRepository $routeRepository,
        CityRepository $cityRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $ligne = $routeRepository->find($id);

        if (null === $ligne) {
            return $this->json(['message' => sprintf('La ligne n°%d est introuvable.', $id)], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(['message' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['departureCityId'])) {
            $dep = $cityRepository->find((int) $data['departureCityId']);
            if (null === $dep) {
                return $this->json(['message' => sprintf('Ville de départ n°%d introuvable.', (int) $data['departureCityId'])], Response::HTTP_NOT_FOUND);
            }
            $ligne->setDepartureCity($dep);
        }

        if (isset($data['arrivalCityId'])) {
            $arr = $cityRepository->find((int) $data['arrivalCityId']);
            if (null === $arr) {
                return $this->json(['message' => sprintf('Ville d\'arrivée n°%d introuvable.', (int) $data['arrivalCityId'])], Response::HTTP_NOT_FOUND);
            }
            $ligne->setArrivalCity($arr);
        }

        if ($ligne->getDepartureCity()->getId() === $ligne->getArrivalCity()->getId()) {
            return $this->json(['message' => 'La ville de départ et d\'arrivée doivent être différentes.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['basePrice'])) {
            $prix = (float) $data['basePrice'];
            if ($prix <= 0) {
                return $this->json(['message' => 'Le prix de base doit être positif.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $ligne->setBasePrice((string) $prix);
        }

        $em->flush();

        return $this->json($this->ligneToArray($ligne));
    }


    #[OA\Delete(
        path: '/api/admin/routes/{id}',
        summary: 'Supprimer une ligne',
        description: 'Supprime une ligne. Impossible si elle est liée à des trajets.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ligne supprimée'),
            new OA\Response(response: 404, description: 'Ligne introuvable'),
            new OA\Response(response: 409, description: 'Ligne liée à des trajets'),
        ]
    )]
    #[Route('/routes/{id}', name: 'route_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function supprimerLigne(
        int $id,
        RouteRepository $routeRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $ligne = $routeRepository->find($id);

        if (null === $ligne) {
            return $this->json(['message' => sprintf('La ligne n°%d est introuvable.', $id)], Response::HTTP_NOT_FOUND);
        }

        if ($ligne->getTrips()->count() > 0) {
            return $this->json(
                ['message' => sprintf('Impossible de supprimer la ligne n°%d : elle est liée à %d trajet(s).', $id, $ligne->getTrips()->count())],
                Response::HTTP_CONFLICT,
            );
        }

        $em->remove($ligne);
        $em->flush();

        return $this->json(['message' => sprintf('Ligne %s → %s supprimée avec succès.', $ligne->getDepartureCity()->getName(), $ligne->getArrivalCity()->getName())]);
    }


    #[OA\Get(
        path: '/api/admin/bookings',
        summary: 'Lister toutes les réservations',
        description: 'Retourne toutes les réservations du système (toutes statuts confondus).',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste de toutes les réservations'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
        ]
    )]
    #[Route('/bookings', name: 'bookings_list', methods: ['GET'])]
    public function listerReservations(BookingRepository $bookingRepository): JsonResponse
    {
        $reservations = $bookingRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->json(array_map(function ($booking) {
            return [
                'id' => $booking->getId(),
                'utilisateurId' => $booking->getUser()->getId(),
                'trajetId' => $booking->getTrip()->getId(),
                'numeroSiege' => $booking->getSeatNumber(),
                'statut' => $booking->getStatus(),
                'reserveLe' => $booking->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }, $reservations));
    }


    /**
     * Annule une réservation. Si elle était PAID, le paiement passe à REFUNDED.
     *
     * @return JsonResponse 200 OK | 400 Déjà annulée | 404 Introuvable
     */
    #[OA\Put(
        path: '/api/admin/bookings/{id}/cancel',
        summary: 'Annuler une réservation (admin)',
        description: 'Force l\'annulation. Si la réservation était PAID, le paiement associé passe à REFUNDED.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réservation annulée avec succès'),
            new OA\Response(response: 400, description: 'Réservation déjà annulée'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès réservé aux administrateurs'),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    #[Route('/bookings/{id}/cancel', name: 'booking_cancel', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function annulerReservation(
        int $id,
        BookingRepository $bookingRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $booking = $bookingRepository->find($id);

        if (null === $booking) {
            return $this->json(
                ['message' => sprintf('La réservation n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        if ('CANCELLED' === $booking->getStatus()) {
            return $this->json(
                ['message' => sprintf('La réservation n°%d est déjà annulée.', $id)],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $statutPrecedent = $booking->getStatus();
        $booking->setStatus('CANCELLED');

        // Si la réservation était payée, marquer le paiement comme remboursé
        $paiement = $booking->getPayment();
        if (null !== $paiement && 'PAID' === $statutPrecedent) {
            $paiement->setPaymentStatus('REFUNDED');
        }

        $em->flush();

        return $this->json([
            'message' => sprintf('La réservation n°%d a été annulée avec succès.', $id),
            'reservationId' => $booking->getId(),
            'statutPrecedent' => $statutPrecedent,
            'nouveauStatut' => 'CANCELLED',
            'paiementRembourse' => null !== $paiement && 'PAID' === $statutPrecedent,
        ]);
    }


    /** @return array<string, mixed> */
    private function busToArray(Bus $bus): array
    {
        return [
            'id' => $bus->getId(),
            'immatriculation' => $bus->getPlateNumber(),
            'capacite' => $bus->getTotalSeats(),
            'statut' => $bus->getStatus(),
        ];
    }

    /** @return array<string, mixed> */
    private function ligneToArray(BusRoute $ligne): array
    {
        return [
            'id'           => $ligne->getId(),
            'villeDepart'  => $ligne->getDepartureCity()->getName(),
            'villeArrivee' => $ligne->getArrivalCity()->getName(),
            'prixBase'     => $ligne->getBasePrice(),
            'nbTrajets'    => $ligne->getTrips()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function trajetToArray(Trip $trajet): array
    {
        $route = $trajet->getRoute();

        return [
            'id' => $trajet->getId(),
            'villeDepart' => $route->getDepartureCity()->getName(),
            'villeArrivee' => $route->getArrivalCity()->getName(),
            'heureDepart' => $trajet->getDepartureTime()->format(\DateTimeInterface::ATOM),
            'heureArrivee' => $trajet->getArrivalTime()->format(\DateTimeInterface::ATOM),
            'statut' => $trajet->getStatus(),
            'busId' => $trajet->getBus()->getId(),
        ];
    }
}
