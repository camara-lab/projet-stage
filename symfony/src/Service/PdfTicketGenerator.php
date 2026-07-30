<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Twig\Environment;

/**
 * Génère un billet PDF pour une réservation donnée.
 */
final class PdfTicketGenerator
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    /**
     * Génère le PDF du billet et retourne son contenu binaire.
     */
    public function generate(Booking $booking): string
    {
        $html = $this->twig->render('pdf/ticket.html.twig', [
            'booking' => $booking,
            'qrCode'  => $this->buildQrCode($booking),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Construit le QR code de contrôle (image encodée en base64).
     *
     * Il contient les données vérifiables à l'embarquement : référence de la
     * réservation, trajet et siège. Généré localement, sans appel réseau.
     */
    private function buildQrCode(Booking $booking): string
    {
        $contenu = sprintf(
            'BUSGO|RES:%d|TRAJET:%d|SIEGE:%d|%s',
            $booking->getId(),
            $booking->getTrip()->getId(),
            $booking->getSeatNumber(),
            $booking->getStatus(),
        );

        $qrCode = new QrCode(data: $contenu, size: 220, margin: 8);

        return (new PngWriter())->write($qrCode)->getDataUri();
    }

    /**
     * Retourne le nom de fichier du billet.
     */
    public function getFilename(Booking $booking): string
    {
        return sprintf(
            'billet-%05d-%s-%s.pdf',
            $booking->getId(),
            strtolower(preg_replace('/\s+/', '-', $booking->getTrip()->getRoute()->getDepartureCity()->getName()) ?? ''),
            $booking->getTrip()->getDepartureTime()->format('Ymd'),
        );
    }
}
