<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Booking;
use App\Form\BookingEditType;
use App\Repository\BookingRepository;
use App\Service\PdfTicketGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/bookings', name: 'admin_booking_')]
final class AdminBookingController extends AbstractController
{

    #[Route('', name: 'index')]
    public function index(Request $request, BookingRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $statut = $request->query->get('statut', '');

        $qb = $repo->createQueryBuilder('b')
            ->join('b.user', 'u')
            ->join('b.trip', 't')
            ->join('t.route', 'r')
            ->join('r.departureCity', 'dc')
            ->join('r.arrivalCity', 'ac')
            ->addSelect('u', 't', 'r', 'dc', 'ac')
            ->orderBy('b.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('u.fullName LIKE :q OR u.email LIKE :q')
               ->setParameter('q', '%'.$search.'%');
        }

        if (\in_array($statut, ['PENDING', 'PAID', 'CANCELLED', 'REFUNDED'], true)) {
            $qb->andWhere('b.status = :statut')->setParameter('statut', $statut);
        }

        $bookings = $qb->getQuery()->getResult();

        return $this->render('admin/booking/index.html.twig', [
            'bookings' => $bookings,
            'search'   => $search,
            'statut'   => $statut,
        ]);
    }


    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        $ancienStatut = $booking->getStatus();

        $maxSeats = $booking->getTrip()->getBus()->getTotalSeats();
        $form = $this->createForm(BookingEditType::class, $booking, ['max_seats' => $maxSeats]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nouveauStatut = $booking->getStatus();

            // Si on passe à CANCELLED et que c'était PAID → rembourser le paiement
            if ($nouveauStatut === 'CANCELLED' && $ancienStatut === 'PAID') {
                $paiement = $booking->getPayment();
                if (null !== $paiement) {
                    $paiement->setPaymentStatus('REFUNDED');
                    $this->addFlash('success', 'Réservation annulée — paiement marqué REFUNDED.');
                } else {
                    $this->addFlash('success', 'Réservation annulée.');
                }
            } else {
                $this->addFlash('success', 'Réservation #'.$booking->getId().' modifiée avec succès.');
            }

            $em->flush();
            return $this->redirectToRoute('admin_booking_index');
        }

        return $this->render('admin/booking/edit.html.twig', [
            'form'    => $form,
            'booking' => $booking,
        ]);
    }


    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(Booking $booking, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('cancel-booking-'.$booking->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_booking_index');
        }

        if ('CANCELLED' === $booking->getStatus()) {
            $this->addFlash('error', 'Cette réservation est déjà annulée.');
            return $this->redirectToRoute('admin_booking_index');
        }

        $wasPaid = 'PAID' === $booking->getStatus();
        $booking->setStatus('CANCELLED');

        if ($wasPaid && null !== $booking->getPayment()) {
            $booking->getPayment()->setPaymentStatus('REFUNDED');
        }

        $em->flush();
        $this->addFlash('success',
            'Réservation #'.$booking->getId().' annulée'
            .($wasPaid ? ' — paiement marqué REFUNDED.' : '.')
        );

        return $this->redirectToRoute('admin_booking_index');
    }


    #[Route('/{id}/ticket', name: 'ticket', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function ticket(Booking $booking, PdfTicketGenerator $generator): Response
    {
        $pdf      = $generator->generate($booking);
        $filename = $generator->getFilename($booking);

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
            'Content-Length'      => \strlen($pdf),
        ]);
    }
}
