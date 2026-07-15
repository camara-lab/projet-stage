<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Payment;
use App\Form\PaymentEditType;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/payments', name: 'admin_payment_')]
final class AdminPaymentController extends AbstractController
{
    // ─────────────────────────────────────────────────────────────────────────
    // Liste de tous les paiements
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaymentRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $statut = $request->query->get('statut', '');

        $qb = $repo->createQueryBuilder('p')
            ->join('p.booking', 'b')
            ->join('b.user', 'u')
            ->join('b.trip', 't')
            ->join('t.route', 'r')
            ->join('r.departureCity', 'cd')
            ->join('r.arrivalCity', 'ca')
            ->addSelect('b', 'u', 't', 'r', 'cd', 'ca')
            ->orderBy('p.paymentDate', 'DESC');

        if ($search !== '') {
            $qb->andWhere('u.fullName LIKE :q OR u.email LIKE :q OR p.transactionId LIKE :q')
               ->setParameter('q', '%'.$search.'%');
        }

        if (\in_array($statut, ['PENDING', 'COMPLETED', 'REFUNDED'], true)) {
            $qb->andWhere('p.paymentStatus = :statut')->setParameter('statut', $statut);
        }

        $payments = $qb->getQuery()->getResult();

        // Les KPIs sont calculés sur TOUS les paiements (pas seulement les filtrés)
        $all       = $repo->createQueryBuilder('p2')->getQuery()->getResult();
        $total     = array_sum(array_map(fn ($p) => (float) $p->getAmount(), array_filter($all, fn ($p) => $p->getPaymentStatus() === 'COMPLETED')));
        $completed = count(array_filter($all, fn ($p) => $p->getPaymentStatus() === 'COMPLETED'));
        $pending   = count(array_filter($all, fn ($p) => $p->getPaymentStatus() === 'PENDING'));
        $refunded  = count(array_filter($all, fn ($p) => $p->getPaymentStatus() === 'REFUNDED'));

        return $this->render('admin/payment/index.html.twig', [
            'payments'  => $payments,
            'total'     => $total,
            'completed' => $completed,
            'pending'   => $pending,
            'refunded'  => $refunded,
            'search'    => $search,
            'statut'    => $statut,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Détail d'un paiement
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Payment $payment): Response
    {
        return $this->render('admin/payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Modifier le statut et l'ID de transaction d'un paiement
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Payment $payment, Request $request, EntityManagerInterface $em): Response
    {
        $ancienStatut = $payment->getPaymentStatus();

        $form = $this->createForm(PaymentEditType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nouveauStatut = $payment->getPaymentStatus();

            // Synchronisation automatique de la réservation liée
            $booking = $payment->getBooking();

            if ($nouveauStatut === 'REFUNDED' && $ancienStatut !== 'REFUNDED') {
                // Paiement remboursé → réservation annulée
                $booking->setStatus('CANCELLED');
                $this->addFlash('success', sprintf(
                    'Paiement #%d marqué REMBOURSÉ — réservation #%d annulée.',
                    $payment->getId(),
                    $booking->getId(),
                ));
            } elseif ($nouveauStatut === 'COMPLETED' && $ancienStatut !== 'COMPLETED') {
                // Paiement validé manuellement → réservation payée
                $booking->setStatus('PAID');
                $this->addFlash('success', sprintf(
                    'Paiement #%d marqué COMPLÉTÉ — réservation #%d mise à jour.',
                    $payment->getId(),
                    $booking->getId(),
                ));
            } else {
                $this->addFlash('success', sprintf('Paiement #%d mis à jour.', $payment->getId()));
            }

            $em->flush();

            return $this->redirectToRoute('admin_payment_index');
        }

        return $this->render('admin/payment/edit.html.twig', [
            'form'    => $form,
            'payment' => $payment,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Remboursement rapide (action depuis la liste, CSRF protégée)
    // ─────────────────────────────────────────────────────────────────────────

    #[Route('/{id}/refund', name: 'refund', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function refund(Payment $payment, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('refund-payment-'.$payment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_payment_index');
        }

        if ($payment->getPaymentStatus() === 'REFUNDED') {
            $this->addFlash('error', sprintf('Le paiement #%d est déjà remboursé.', $payment->getId()));
            return $this->redirectToRoute('admin_payment_index');
        }

        if ($payment->getPaymentStatus() === 'PENDING') {
            $this->addFlash('error', sprintf('Impossible de rembourser le paiement #%d : il n\'est pas encore complété.', $payment->getId()));
            return $this->redirectToRoute('admin_payment_index');
        }

        // Marquer le paiement comme remboursé
        $payment->setPaymentStatus('REFUNDED');

        // Annuler la réservation liée
        $booking = $payment->getBooking();
        $booking->setStatus('CANCELLED');

        $em->flush();

        $this->addFlash('success', sprintf(
            'Paiement #%d remboursé — réservation #%d annulée.',
            $payment->getId(),
            $booking->getId(),
        ));

        return $this->redirectToRoute('admin_payment_index');
    }
}
