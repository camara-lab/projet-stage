<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Trip;
use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * Envoie un email de notification à chaque passager affecté
 * par l'annulation d'un trajet.
 *
 * Utilise la fonction native PHP mail() pour éviter toute dépendance
 * sur symfony/mailer (conflits de versions dans ce projet).
 * En production, configurer MAILER_DSN via un vrai SMTP.
 */
final class TripCancellationNotifier
{
    private const FROM_EMAIL = 'kolou25camara@gmail.com';
    private const FROM_NAME  = 'Bus Booking';

    public function __construct(
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Notifie tous les passagers actifs du trajet annulé.
     */
    public function notifyPassengers(Trip $trip): int
    {
        $bookings = $trip->getBookings();
        $sent = 0;

        if ($bookings->isEmpty()) {
            return 0;
        }

        foreach ($bookings as $booking) {
            /** @var Booking $booking */
            if (!\in_array($booking->getStatus(), ['PAID', 'PENDING', 'CANCELLED'], true)) {
                continue;
            }

            if ($this->sendCancellationEmail($booking)) {
                ++$sent;
            }
        }

        return $sent;
    }

    private function sendCancellationEmail(Booking $booking): bool
    {
        $user = $booking->getUser();

        try {
            $subject = sprintf(
                '⚠️ Annulation de votre trajet %s → %s du %s',
                $booking->getTrip()->getRoute()->getDepartureCity()->getName(),
                $booking->getTrip()->getRoute()->getArrivalCity()->getName(),
                $booking->getTrip()->getDepartureTime()->format('d/m/Y'),
            );

            $htmlBody = $this->twig->render('emails/trip_cancelled.html.twig', [
                'booking' => $booking,
            ]);

            $headers = implode("\r\n", [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                sprintf('From: %s <%s>', self::FROM_NAME, self::FROM_EMAIL),
                sprintf('Reply-To: %s', self::FROM_EMAIL),
                'X-Mailer: BusBooking/PHP',
            ]);

            $result = mail(
                $user->getEmail(),
                '=?UTF-8?B?'.base64_encode($subject).'?=',
                $htmlBody,
                $headers,
            );

            $this->logger->info('Email annulation trajet envoyé', [
                'booking_id' => $booking->getId(),
                'user_email' => $user->getEmail(),
                'trip_id'    => $booking->getTrip()->getId(),
                'sent'       => $result,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email annulation trajet', [
                'booking_id' => $booking->getId(),
                'user_email' => $user->getEmail(),
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }
}
