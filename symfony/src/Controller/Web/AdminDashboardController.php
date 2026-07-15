<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\BookingRepository;
use App\Repository\BusRepository;
use App\Repository\CityRepository;
use App\Repository\RouteRepository;
use App\Repository\TripRepository;
use App\Service\AdminStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'admin_')]
final class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function index(
        AdminStatsService $stats,
        CityRepository $cities,
        BusRepository $buses,
        RouteRepository $routes,
        TripRepository $trips,
        BookingRepository $bookings,
    ): Response {
        $caParMois   = $stats->caParMois();
        $statutsResa = $stats->reservationsParStatut();

        return $this->render('admin/dashboard.html.twig', [
            // KPIs
            'ca'              => $stats->chiffreAffairesTotal(),
            'occupation'      => $stats->tauxOccupationMoyen(),
            'passagersActifs' => $stats->passagersActifs(),
            'trajetsASemaine' => $stats->trajetsASemaine(),
            // Compteurs
            'nbVilles'   => count($cities->findAll()),
            'nbBus'      => count($buses->findAll()),
            'nbLignes'   => count($routes->findAll()),
            'nbTrajets'  => count($trips->findAll()),
            'nbResa'     => count($bookings->findAll()),
            // Graphiques
            'caLabels'   => json_encode(array_keys($caParMois)),
            'caData'     => json_encode(array_values($caParMois)),
            'statutLabels' => json_encode(array_keys($statutsResa)),
            'statutData'   => json_encode(array_values($statutsResa)),
            // Activité
            'topLignes'          => $stats->topTroisLignes(),
            'dernieresResas'     => $stats->dernieresReservations(),
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): never
    {
        throw new \LogicException('Géré par le firewall Symfony.');
    }
}
