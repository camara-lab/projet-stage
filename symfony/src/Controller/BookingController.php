<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Exception\BookingConflictException;
use App\Exception\SeatOutOfRangeException;
use App\Exception\TripNotAvailableException;
use App\Repository\BookingRepository;
use App\Repository\TripRepository;
use App\Repository\UserRepository;
use App\Security\BookingVoter;
use App\Service\BookingService;
use App\Service\PdfTicketGenerator;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur REST pour la gestion des réservations de billets de bus.
 *
 * Tous les endpoints exigent un token JWT valide (Authorization: Bearer <token>).
 * L'accès aux réservations d'autrui est contrôlé par le BookingVoter.
 *
 * Exemple de flux : un passager réserve le siège 12 sur le trajet
 * Casa Voyageurs → Marrakech du 2026-05-10 à 08h00.
 */
#[AsController]
#[Route('/api/bookings', name: 'api_bookings_')]
final class BookingController extends AbstractController
{

    /**
     * Réserve un siège pour l'utilisateur connecté.
     *
     * Corps JSON attendu : { "tripId": int, "seatNumber": int }
     *
     * Codes de retour :
     *   201 Created          — réservation créée avec succès
     *   400 Bad Request      — trajet indisponible ou numéro de siège invalide
     *   404 Not Found        — trajet introuvable
     *   409 Conflict         — siège déjà réservé sur ce trajet
     *   422 Unprocessable    — champs obligatoires manquants
     */
    #[OA\Post(
        path: '/api/bookings',
        summary: 'Créer une réservation',
        description: 'Réserve un siège pour l\'utilisateur connecté sur un trajet donné.',
        tags: ['Bookings'],
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tripId', 'seatNumber'],
                properties: [
                    new OA\Property(property: 'tripId', type: 'integer', example: 1, description: 'ID du trajet'),
                    new OA\Property(property: 'seatNumber', type: 'integer', example: 12, description: 'Numéro de siège'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Réservation créée avec succès'),
            new OA\Response(response: 400, description: 'Trajet indisponible ou numéro de siège invalide'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 404, description: 'Trajet introuvable'),
            new OA\Response(response: 409, description: 'Siège déjà réservé sur ce trajet'),
            new OA\Response(response: 422, description: 'Champs obligatoires manquants'),
        ]
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        BookingService $bookingService,
        TripRepository $tripRepository,
    ): JsonResponse {
        $user = $this->requireUser();

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(
                ['message' => 'Corps de requête JSON invalide.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (!isset($data['tripId'], $data['seatNumber'])) {
            return $this->json(
                ['message' => 'Les champs tripId et seatNumber sont obligatoires.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $tripId        = (int) $data['tripId'];
        $seatNumber    = (int) $data['seatNumber'];
        $passengerType = strtoupper(trim((string) ($data['passengerType'] ?? 'ADULT')));

        $trip = $tripRepository->find($tripId);

        if (null === $trip) {
            return $this->json(
                ['message' => sprintf('Le trajet n°%d est introuvable.', $tripId)],
                Response::HTTP_NOT_FOUND,
            );
        }

        try {
            $booking = $bookingService->createBooking($user, $trip, $seatNumber, $passengerType);
        } catch (BookingConflictException) {
            return $this->json(
                ['message' => 'Ce siège est déjà réservé sur ce trajet.'],
                Response::HTTP_CONFLICT,
            );
        } catch (TripNotAvailableException) {
            return $this->json(
                ['message' => 'Ce voyage n\'est plus disponible à la réservation.'],
                Response::HTTP_BAD_REQUEST,
            );
        } catch (SeatOutOfRangeException) {
            return $this->json(
                ['message' => 'Numéro de siège invalide pour ce bus.'],
                Response::HTTP_BAD_REQUEST,
            );
        } catch (\RuntimeException) {
            return $this->json(
                ['message' => 'Une erreur interne est survenue. Veuillez réessayer.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json($this->bookingToArray($booking), Response::HTTP_CREATED);
    }


    /**
     * Retourne toutes les réservations de l'utilisateur connecté,
     * triées de la plus récente à la plus ancienne.
     *
     * @return JsonResponse 200 OK avec un tableau de réservations (vide si aucune)
     */
    #[OA\Get(
        path: '/api/bookings',
        summary: 'Lister mes réservations',
        description: 'Retourne toutes les réservations de l\'utilisateur connecté.',
        tags: ['Bookings'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des réservations'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
        ]
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(BookingRepository $bookingRepository): JsonResponse
    {
        $user = $this->requireUser();

        $reservations = $bookingRepository->findByUser($user);

        return $this->json(array_map($this->bookingToArray(...), $reservations));
    }


    #[OA\Get(
        path: '/api/bookings/user/{userId}',
        summary: 'Réservations d\'un utilisateur',
        description: 'Retourne toutes les réservations d\'un utilisateur. Accessible par le propriétaire ou un admin.',
        tags: ['Bookings'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des réservations'),
            new OA\Response(response: 403, description: 'Accès interdit'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
        ]
    )]
    #[Route('/user/{userId}', name: 'list_by_user', methods: ['GET'], requirements: ['userId' => '\d+'])]
    public function listByUser(
        int $userId,
        BookingRepository $bookingRepository,
        UserRepository $userRepository,
    ): JsonResponse {
        $currentUser = $this->requireUser();

        // Seul l'admin peut consulter les réservations d'un autre utilisateur
        if ($currentUser->getId() !== $userId && !\in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            throw $this->createAccessDeniedException('Accès interdit.');
        }

        $targetUser = $userRepository->find($userId);
        if (null === $targetUser) {
            return $this->json(['message' => sprintf('Utilisateur n°%d introuvable.', $userId)], Response::HTTP_NOT_FOUND);
        }

        $reservations = $bookingRepository->findByUser($targetUser);

        return $this->json(array_map($this->bookingToArray(...), $reservations));
    }


    /**
     * Retourne le détail d'une réservation donnée.
     *
     * Accès restreint au propriétaire de la réservation ou à un administrateur
     * (règle appliquée par BookingVoter::VIEW).
     *
     * @return JsonResponse 200 OK | 403 Interdit | 404 Introuvable
     */
    #[OA\Get(
        path: '/api/bookings/{id}',
        summary: 'Détail d\'une réservation',
        description: 'Accès réservé au propriétaire ou à un administrateur.',
        tags: ['Bookings'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail de la réservation'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès interdit'),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, BookingRepository $bookingRepository): JsonResponse
    {
        $booking = $bookingRepository->find($id);

        if (null === $booking) {
            return $this->json(
                ['message' => sprintf('La réservation n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        // Refuse l'accès si l'utilisateur n'est ni le propriétaire ni un ADMIN
        $this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);

        return $this->json($this->bookingToArray($booking));
    }


    /**
     * Permet au passager connecté d'annuler l'une de ses réservations.
     *
     * Règles métier :
     *  - La réservation doit appartenir à l'utilisateur connecté (ou ADMIN).
     *  - Elle ne doit pas être déjà CANCELLED ou REFUNDED.
     *  - Si la réservation est PAID, le paiement associé est automatiquement
     *    marqué REFUNDED.
     *
     * Codes de retour :
     *   200 OK               — annulation effectuée
     *   400 Bad Request      — réservation déjà annulée ou remboursée
     *   403 Forbidden        — la réservation n'appartient pas à l'utilisateur
     *   404 Not Found        — réservation introuvable
     */
    #[OA\Post(
        path: '/api/bookings/{id}/cancel',
        summary: 'Annuler une réservation',
        description: 'Annule la réservation. Si elle était PAID, le paiement est automatiquement marqué REFUNDED.',
        tags: ['Bookings'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Réservation annulée avec succès'),
            new OA\Response(response: 400, description: 'Réservation déjà annulée ou remboursée'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'Accès interdit — réservation appartenant à un autre utilisateur'),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(
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

        // Vérifie que l'utilisateur connecté est le propriétaire ou un ADMIN
        $this->denyAccessUnlessGranted(BookingVoter::CANCEL, $booking);

        $currentStatus = $booking->getStatus();

        if (\in_array($currentStatus, ['CANCELLED', 'REFUNDED'], true)) {
            return $this->json(
                ['message' => sprintf('La réservation n°%d est déjà %s.', $id, strtolower($currentStatus))],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $wasPaid = $currentStatus === 'PAID';

        // Annulation de la réservation
        $booking->setStatus('CANCELLED');

        // Remboursement automatique si la réservation était payée
        if ($wasPaid && null !== $booking->getPayment()) {
            $booking->getPayment()->setPaymentStatus('REFUNDED');
        }

        $em->flush();

        return $this->json([
            'message'    => sprintf(
                'Réservation n°%d annulée avec succès.%s',
                $id,
                $wasPaid ? ' Le paiement a été marqué comme remboursé.' : ''
            ),
            'reservation' => $this->bookingToArray($booking),
        ]);
    }


    /**
     * Retourne l'utilisateur authentifié ou lève une AccessDeniedException.
     *
     * Nécessaire pour satisfaire PHPStan niveau 8 : getUser() retourne
     * UserInterface|null, alors que le reste du code attend un User typé.
     */
    private function requireUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        return $user;
    }

    /**
     * Sérialise une entité Booking en tableau associatif pour la réponse JSON.
     *
     * @return array<string, mixed>
     */

    #[OA\Get(
        path: '/api/bookings/{id}/ticket',
        summary: 'Télécharger le billet PDF',
        description: 'Génère et retourne le billet de réservation au format PDF. Le passager doit être propriétaire de la réservation.',
        tags: ['Réservations'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'PDF du billet (application/pdf)'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
        ]
    )]
    #[Route('/{id}/ticket', name: 'api_booking_ticket', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function ticket(int $id, BookingRepository $bookingRepo, PdfTicketGenerator $generator): Response
    {
        $booking = $bookingRepo->find($id);

        if (null === $booking) {
            return new JsonResponse(['error' => 'Réservation introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(BookingVoter::VIEW, $booking);

        $pdf      = $generator->generate($booking);
        $filename = $generator->getFilename($booking);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
            'Content-Length'      => \strlen($pdf),
        ]);
    }

    private function bookingToArray(Booking $booking): array
    {
        $trip    = $booking->getTrip();
        $route   = $trip->getRoute();
        $payment = $booking->getPayment();

        return [
            'id'            => $booking->getId(),
            'seatNumber'    => $booking->getSeatNumber(),
            'passengerType' => $booking->getPassengerType(),
            'unitPrice'     => $booking->getUnitPrice(),
            'status'        => $booking->getStatus(),
            'createdAt'     => $booking->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'trip' => [
                'id'            => $trip->getId(),
                'departureTime' => $trip->getDepartureTime()->format(\DateTimeInterface::ATOM),
                'arrivalTime'   => $trip->getArrivalTime()->format(\DateTimeInterface::ATOM),
                'status'        => $trip->getStatus(),
                'bus' => [
                    'plateNumber' => $trip->getBus()->getPlateNumber(),
                    'totalSeats'  => $trip->getBus()->getTotalSeats(),
                ],
                'route' => [
                    'basePrice'     => $route->getBasePrice(),
                    'departureCity' => ['name' => $route->getDepartureCity()->getName()],
                    'arrivalCity'   => ['name' => $route->getArrivalCity()->getName()],
                ],
            ],
            'payment' => $payment ? [
                'id'              => $payment->getId(),
                'amount'          => $payment->getAmount(),
                'paymentMethod'   => $payment->getPaymentMethod(),
                'paymentProvider' => $payment->getPaymentProvider(),
                'paymentStatus'   => $payment->getPaymentStatus(),
                'transactionId'   => $payment->getTransactionId(),
            ] : null,
        ];
    }
}
