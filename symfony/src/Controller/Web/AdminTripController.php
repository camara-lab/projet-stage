<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Form\TripType;
use App\Repository\CityRepository;
use App\Repository\TripRepository;
use App\Service\TripCancellationNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/trips', name: 'admin_trip_')]
final class AdminTripController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, TripRepository $repo, CityRepository $cityRepository): Response
    {
        $statut  = $request->query->get('statut', '');
        $date    = trim((string) $request->query->get('date', ''));
        $ligne   = trim((string) $request->query->get('ligne', ''));

        $qb = $repo->createQueryBuilder('t')
            ->join('t.route', 'r')
            ->join('r.departureCity', 'dc')
            ->join('r.arrivalCity', 'ac')
            ->join('t.bus', 'b')
            ->addSelect('r', 'dc', 'ac', 'b')
            ->orderBy('t.departureTime', 'DESC');

        if (\in_array($statut, ['SCHEDULED', 'CANCELLED', 'COMPLETED'], true)) {
            $qb->andWhere('t.status = :statut')->setParameter('statut', $statut);
        }

        if ($date !== '' && false !== \DateTime::createFromFormat('Y-m-d', $date)) {
            $qb->andWhere('t.departureTime >= :debut')
               ->andWhere('t.departureTime <= :fin')
               ->setParameter('debut', $date.' 00:00:00')
               ->setParameter('fin',   $date.' 23:59:59');
        }

        if ($ligne !== '') {
            $qb->andWhere('dc.name LIKE :ligne OR ac.name LIKE :ligne')
               ->setParameter('ligne', '%'.$ligne.'%');
        }

        $trips = $qb->getQuery()->getResult();

        return $this->render('admin/trip/index.html.twig', [
            'trips'  => $trips,
            'statut' => $statut,
            'date'   => $date,
            'ligne'  => $ligne,
            'villes' => $cityRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em, TripRepository $tripRepo): Response
    {
        $trip = new Trip();
        $form = $this->createForm(TripType::class, $trip);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($trip->getDepartureTime() < new \DateTime()) {
                $this->addFlash('error', 'La date de départ doit être dans le futur.');
            } elseif ($trip->getArrivalTime() <= $trip->getDepartureTime()) {
                $this->addFlash('error', 'L\'heure d\'arrivée doit être postérieure au départ.');
            } elseif ($tripRepo->isBusOccupe($trip->getBus(), $trip->getDepartureTime(), $trip->getArrivalTime())) {
                $this->addFlash('error', 'Ce bus est déjà affecté sur cette plage horaire.');
            } else {
                $em->persist($trip);
                $em->flush();
                $this->addFlash('success', 'Trajet planifié avec succès.');
                return $this->redirectToRoute('admin_trip_index');
            }
        }

        return $this->render('admin/trip/form.html.twig', [
            'form' => $form,
            'title' => 'Planifier un trajet',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Trip $trip, Request $request, EntityManagerInterface $em, TripRepository $tripRepo): Response
    {
        if ($trip->getStatus() === 'CANCELLED') {
            $this->addFlash('error', 'Impossible de modifier un trajet annulé.');
            return $this->redirectToRoute('admin_trip_index');
        }

        $form = $this->createForm(TripType::class, $trip);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($trip->getArrivalTime() <= $trip->getDepartureTime()) {
                $this->addFlash('error', 'L\'heure d\'arrivée doit être postérieure au départ.');
            } elseif ($trip->getDepartureTime() < new \DateTime()) {
                $this->addFlash('error', 'La date de départ doit être dans le futur.');
            } elseif ($tripRepo->isBusOccupe($trip->getBus(), $trip->getDepartureTime(), $trip->getArrivalTime(), $trip->getId())) {
                $this->addFlash('error', 'Ce bus est déjà affecté sur cette plage horaire.');
            } else {
                $em->flush();
                $this->addFlash('success', 'Trajet modifié avec succès.');
                return $this->redirectToRoute('admin_trip_index');
            }
        }

        return $this->render('admin/trip/form.html.twig', [
            'form'  => $form,
            'title' => 'Modifier le trajet',
            'trip'  => $trip,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Trip $trip, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-trip-'.$trip->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_trip_index');
        }

        if ($trip->getBookings()->count() > 0) {
            $this->addFlash('error', 'Impossible de supprimer ce trajet : il possède des réservations liées.');
            return $this->redirectToRoute('admin_trip_index');
        }

        $em->remove($trip);
        $em->flush();
        $this->addFlash('success', 'Trajet supprimé.');

        return $this->redirectToRoute('admin_trip_index');
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(
        Trip $trip,
        Request $request,
        EntityManagerInterface $em,
        TripCancellationNotifier $notifier,
    ): Response {
        if (!$this->isCsrfTokenValid('cancel-trip-'.$trip->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_trip_index');
        }

        if ($trip->getStatus() === 'CANCELLED') {
            $this->addFlash('error', 'Ce trajet est déjà annulé.');
            return $this->redirectToRoute('admin_trip_index');
        }

        // Annuler les réservations actives et rembourser les paiements
        $notified = 0;
        foreach ($trip->getBookings() as $booking) {
            /** @var Booking $booking */
            if (\in_array($booking->getStatus(), ['PAID', 'PENDING'], true)) {
                $booking->setStatus('CANCELLED');
                if ($booking->getPayment() !== null && $booking->getPayment()->getPaymentStatus() === 'COMPLETED') {
                    $booking->getPayment()->setPaymentStatus('REFUNDED');
                }
                ++$notified;
            }
        }

        $trip->setStatus('CANCELLED');
        $em->flush();

        // Envoyer les emails de notification (ne bloque pas en cas d'erreur)
        $notifier->notifyPassengers($trip);

        $msg = 'Trajet annulé.';
        if ($notified > 0) {
            $msg .= sprintf(' %d passager(s) notifié(s) par email.', $notified);
        }

        $this->addFlash('success', $msg);
        return $this->redirectToRoute('admin_trip_index');
    }
}
