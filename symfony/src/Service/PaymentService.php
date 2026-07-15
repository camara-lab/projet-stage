<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Enum\MethodePaiement;
use App\Exception\InvalidPaymentMethodException;
use App\Exception\PaymentAlreadyPaidException;
use App\Exception\PaymentCancelledException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service métier responsable du traitement des paiements de réservations.
 *
 * Flux de paiement :
 *  1. Valider la méthode de paiement (CARD, UPI, NET_BANKING, WALLET).
 *  2. Vérifier que la réservation est en statut PENDING.
 *  3. Créer l'entité Payment avec le montant du trajet.
 *  4. Mettre à jour le statut du Booking en PAID.
 *  5. Persister le tout dans une transaction SQL (rollback automatique en cas d'erreur).
 *
 * Exemple : Ahmed paye son billet siège 12 sur Casa → Marrakech via WAFA_CASH.
 */
final class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Traite le paiement d'une réservation existante.
     *
     * @param string $methodeBrute Méthode de paiement brute (ex: "CARD", "UPI", "NET_BANKING", "WALLET")
     *
     * @throws InvalidPaymentMethodException si la méthode n'est pas reconnue
     * @throws PaymentAlreadyPaidException   si la réservation est déjà payée
     * @throws PaymentCancelledException     si la réservation est annulée
     * @throws \RuntimeException             en cas d'erreur SQL (transaction rollback)
     */
    public function processPayment(Booking $booking, string $methodeBrute): Payment
    {
        // --- Étape 1 : Validation de la méthode de paiement ---
        $methode = MethodePaiement::tryFrom(strtoupper(trim($methodeBrute)));

        if (null === $methode) {
            throw new InvalidPaymentMethodException($methodeBrute);
        }

        // --- Étape 2 : Vérification du statut de la réservation ---
        $this->assertReservationPayable($booking);

        // --- Étapes 3-4 : Persistance dans une transaction SQL ---
        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();

        try {
            $montant = $booking->getUnitPrice();

            // Création du paiement
            $paiement = new Payment();
            $paiement->setBooking($booking);
            $paiement->setAmount($montant);
            $paiement->setPaymentMethod($methode->value);
            $paiement->setPaymentProvider($methode->prestataire());
            $paiement->setPaymentStatus('SUCCESS');
            // Identifiant de transaction unique (simulé — intégration réelle via API STRIPE/PAYPAL/RAZORPAY)
            $paiement->setTransactionId($this->genererIdTransaction($methode));

            // Mise à jour du statut de la réservation
            $booking->setStatus('PAID');

            $this->em->persist($paiement);
            $this->em->flush();

            $connexion->commit();
        } catch (\Throwable $erreur) {
            // Annulation de toutes les modifications en cas d'erreur SQL
            $connexion->rollBack();
            throw new \RuntimeException('Une erreur est survenue lors du traitement du paiement. Veuillez réessayer.', previous: $erreur);
        }

        return $paiement;
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    /**
     * Vérifie que la réservation est en statut PENDING avant de la payer.
     *
     * @throws PaymentAlreadyPaidException si statut = PAID
     * @throws PaymentCancelledException   si statut = CANCELLED
     */
    private function assertReservationPayable(Booking $booking): void
    {
        $bookingId = $booking->getId() ?? throw new \LogicException('La réservation doit être persistée avant le paiement.');

        if ('PAID' === $booking->getStatus()) {
            throw new PaymentAlreadyPaidException($bookingId);
        }

        if ('CANCELLED' === $booking->getStatus()) {
            throw new PaymentCancelledException($bookingId);
        }
    }

    /**
     * Génère un identifiant de transaction unique par prestataire.
     *
     * Format : {PRESTATAIRE}-{timestamp}-{hex aléatoire}
     * Exemple : CMI-17769836-a3f2c1b0
     *
     * En production, cet ID serait fourni par l'API du prestataire de paiement.
     */
    private function genererIdTransaction(MethodePaiement $methode): string
    {
        return sprintf(
            '%s-%d-%s',
            $methode->prestataire(),
            time(),
            bin2hex(random_bytes(4)),
        );
    }
}
