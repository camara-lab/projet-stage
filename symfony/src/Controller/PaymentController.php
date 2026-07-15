<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Payment;
use App\Exception\InvalidPaymentMethodException;
use App\Exception\PaymentAlreadyPaidException;
use App\Exception\PaymentCancelledException;
use App\Repository\BookingRepository;
use App\Security\BookingVoter;
use App\Service\PaymentService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur REST pour le traitement des paiements de réservations.
 *
 * Règle de sécurité : seul le propriétaire d'une réservation (ou un ADMIN)
 * peut initier son paiement — contrôlé par BookingVoter::PAY.
 *
 * Méthodes de paiement acceptées (Maroc) :
 *  - CARD     : paiement par carte bancaire en ligne (CMI)
 *  - CASH     : paiement en espèces via agent (CashPlus / Wafacash)
 *  - TRANSFER : virement bancaire (CIH / Attijariwafa / BCP)
 */
#[AsController]
#[Route('/api/payments', name: 'api_payments_')]
final class PaymentController extends AbstractController
{
    /**
     * Initie et enregistre le paiement d'une réservation existante.
     *
     * Corps JSON attendu : { "method": "WAFA_CASH" }
     *
     * Codes de retour :
     *   201 Created          — paiement enregistré avec succès
     *   400 Bad Request      — réservation déjà payée ou annulée
     *   403 Forbidden        — l'utilisateur ne possède pas cette réservation
     *   404 Not Found        — réservation introuvable
     *   422 Unprocessable    — méthode de paiement invalide ou champ manquant
     *   500 Internal Error   — erreur SQL (transaction rollback)
     */
    #[OA\Post(
        path: '/api/payments/booking/{id}',
        summary: 'Payer une réservation',
        description: "Initie le paiement d'une réservation existante.\n\nMéthodes acceptées : `CARD`, `CASH`, `TRANSFER`.\n\nSeul le propriétaire de la réservation (ou un ADMIN) peut effectuer le paiement.",
        tags: ['Payments'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID de la réservation', schema: new OA\Schema(type: 'integer', example: 2)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['method'],
                properties: [
                    new OA\Property(
                        property: 'method',
                        type: 'string',
                        enum: ['CARD', 'CASH', 'TRANSFER'],
                        example: 'CARD',
                        description: 'Méthode de paiement'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Paiement enregistré avec succès'),
            new OA\Response(response: 400, description: 'Réservation déjà payée ou annulée'),
            new OA\Response(response: 401, description: 'Token JWT manquant ou invalide'),
            new OA\Response(response: 403, description: 'L\'utilisateur ne possède pas cette réservation'),
            new OA\Response(response: 404, description: 'Réservation introuvable'),
            new OA\Response(response: 422, description: 'Méthode de paiement invalide ou champ manquant'),
            new OA\Response(response: 500, description: 'Erreur interne (transaction rollback)'),
        ]
    )]
    #[Route('/booking/{id}', name: 'create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function create(
        int $id,
        Request $request,
        BookingRepository $bookingRepository,
        PaymentService $paymentService,
    ): JsonResponse {
        // --- Récupération de la réservation ---
        $booking = $bookingRepository->find($id);

        if (null === $booking) {
            return $this->json(
                ['message' => sprintf('La réservation n°%d est introuvable.', $id)],
                Response::HTTP_NOT_FOUND,
            );
        }

        // --- Contrôle d'accès : seul le propriétaire ou un ADMIN peut payer ---
        $this->denyAccessUnlessGranted(BookingVoter::PAY, $booking);

        // --- Lecture du corps JSON ---
        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        $rawMethod = (string) ($data['paymentMethod'] ?? $data['method'] ?? '');

        if ('' === $rawMethod) {
            return $this->json(
                ['message' => 'Le champ "paymentMethod" est obligatoire. Valeurs acceptées : CARD, CASH, TRANSFER.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // --- Traitement du paiement via le service ---
        try {
            $paiement = $paymentService->processPayment($booking, $rawMethod);
        } catch (PaymentAlreadyPaidException|PaymentCancelledException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (InvalidPaymentMethodException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            // Erreur SQL après rollback — ne pas exposer le détail technique au client
            return $this->json(
                ['message' => 'Une erreur interne est survenue. Veuillez réessayer dans quelques instants.'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $this->json($this->paymentToArray($paiement), Response::HTTP_CREATED);
    }

    /**
     * Sérialise une entité Payment en tableau associatif pour la réponse JSON.
     *
     * @return array<string, mixed>
     */
    private function paymentToArray(Payment $paiement): array
    {
        return [
            'id' => $paiement->getId(),
            'reservationId' => $paiement->getBooking()->getId(),
            'montant' => $paiement->getAmount(),
            'methodePaiement' => $paiement->getPaymentMethod(),
            'prestataire' => $paiement->getPaymentProvider(),
            'statut' => $paiement->getPaymentStatus(),
            'idTransaction' => $paiement->getTransactionId(),
            'datePaiement' => $paiement->getPaymentDate()->format(\DateTimeInterface::ATOM),
        ];
    }
}
