<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Bus;
use App\Entity\City;
use App\Entity\Payment;
use App\Entity\Route;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fixtures de test pour le système de réservation de bus au Maroc.
 *
 * Chargement : docker exec bus_booking_app php bin/console doctrine:fixtures:load
 *
 * Données créées :
 *  - 5 villes : Casablanca (id≈1), Rabat (id≈2), Marrakech (id≈3), Fès (id≈4), Tanger (id≈5)
 *  - 3 bus CTM
 *  - 5 lignes inter-villes
 *  - 8 trajets (6 SCHEDULED futurs, 1 CANCELLED, 1 COMPLETED)
 *  - 3 utilisateurs (2 passagers + 1 admin)
 *  - 2 réservations (1 PENDING, 1 PAID) avec paiement associé
 *
 * Comptes de test :
 *  - passager@test.ma  / Test1234   (ROLE_USER)
 *  - passager2@test.ma / Test1234   (ROLE_USER)
 *  - admin@test.ma     / Admin1234  (ROLE_ADMIN)
 *
 * Recherche rapide pour tester l'API :
 *  GET /api/trips?from=1&to=3        → Casa → Marrakech (par ID)
 *  GET /api/trips?departureCity=Casablanca&arrivalCity=Marrakech  (par nom)
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // -----------------------------------------------------------------
        // 1. Villes marocaines
        // L'ordre de persist détermine les IDs auto-incrémentés :
        //   Casablanca=1, Rabat=2, Marrakech=3, Fès=4, Tanger=5
        // -----------------------------------------------------------------

        $casablanca = $this->makeCity('Casablanca');
        $rabat      = $this->makeCity('Rabat');
        $marrakech  = $this->makeCity('Marrakech');
        $fes        = $this->makeCity('Fès');
        $tanger     = $this->makeCity('Tanger');

        foreach ([$casablanca, $rabat, $marrakech, $fes, $tanger] as $ville) {
            $manager->persist($ville);
        }

        // -----------------------------------------------------------------
        // 2. Bus CTM
        // -----------------------------------------------------------------

        $busCtm1 = $this->makeBus('CTM-W-12345', 44); // grand coach
        $busCtm2 = $this->makeBus('CTM-W-67890', 36); // coach standard
        $busCtm3 = $this->makeBus('CTM-W-11223', 28); // minibus

        foreach ([$busCtm1, $busCtm2, $busCtm3] as $bus) {
            $manager->persist($bus);
        }

        // -----------------------------------------------------------------
        // 3. Lignes inter-villes (prix en dirhams)
        // -----------------------------------------------------------------

        $routeCasaMarrakech  = $this->makeRoute($casablanca, $marrakech, '90.00');
        $routeRabatTanger    = $this->makeRoute($rabat, $tanger, '75.00');
        $routeFesMarrakech   = $this->makeRoute($fes, $marrakech, '130.00');
        $routeCasaRabat      = $this->makeRoute($casablanca, $rabat, '45.00');
        $routeMarrakechCasa  = $this->makeRoute($marrakech, $casablanca, '90.00');

        foreach ([$routeCasaMarrakech, $routeRabatTanger, $routeFesMarrakech, $routeCasaRabat, $routeMarrakechCasa] as $route) {
            $manager->persist($route);
        }

        // -----------------------------------------------------------------
        // 4. Trajets planifiés — juillet 2026 (dates futures)
        // -----------------------------------------------------------------

        // Trajet 1 : Casa → Marrakech, matin — utilisé pour la réservation PENDING
        $trajet1 = $this->makeTrip($routeCasaMarrakech, $busCtm1, '2026-07-10 08:00', '2026-07-10 11:00', 'SCHEDULED');
        $manager->persist($trajet1);

        // Trajet 2 : Casa → Marrakech, après-midi
        $manager->persist($this->makeTrip($routeCasaMarrakech, $busCtm1, '2026-07-10 14:00', '2026-07-10 17:00', 'SCHEDULED'));

        // Trajet 3 : Rabat → Tanger — utilisé pour la réservation PAID
        $trajet3 = $this->makeTrip($routeRabatTanger, $busCtm3, '2026-07-11 09:30', '2026-07-11 12:00', 'SCHEDULED');
        $manager->persist($trajet3);

        // Trajet 4 : Fès → Marrakech
        $manager->persist($this->makeTrip($routeFesMarrakech, $busCtm2, '2026-07-12 07:00', '2026-07-12 12:00', 'SCHEDULED'));

        // Trajet 5 : Casa → Rabat (court)
        $manager->persist($this->makeTrip($routeCasaRabat, $busCtm3, '2026-07-13 06:45', '2026-07-13 07:45', 'SCHEDULED'));

        // Trajet 6 : Marrakech → Casa (retour)
        $manager->persist($this->makeTrip($routeMarrakechCasa, $busCtm1, '2026-07-14 16:00', '2026-07-14 19:00', 'SCHEDULED'));

        // Trajet annulé — pour tester la réponse 400 TripNotAvailableException
        $manager->persist($this->makeTrip($routeCasaMarrakech, $busCtm1, '2026-07-01 08:00', '2026-07-01 11:00', 'CANCELLED'));

        // Trajet terminé — idem
        $manager->persist($this->makeTrip($routeRabatTanger, $busCtm3, '2026-06-01 09:00', '2026-06-01 11:30', 'COMPLETED'));

        // -----------------------------------------------------------------
        // 5. Utilisateurs de test
        // -----------------------------------------------------------------

        $passager = new User();
        $passager->setEmail('josephine20camara@gmail.com');
        $passager->setFullName('Josephine Camara');
        $passager->setPhone('0661234567');
        $passager->setPassword($this->passwordHasher->hashPassword($passager, 'Test1234'));
        $manager->persist($passager);

        $passager2 = new User();
        $passager2->setEmail('passager2@test.ma');
        $passager2->setFullName('Zineb Alaoui');
        $passager2->setPhone('0662345678');
        $passager2->setPassword($this->passwordHasher->hashPassword($passager2, 'Test1234'));
        $manager->persist($passager2);

        $admin = new User();
        $admin->setEmail('admin.josephine20camara@gmail.com');
        $admin->setFullName('Josephine Camara Admin');
        $admin->setRole('ADMIN');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin1234'));
        $manager->persist($admin);

        // Flush avant les réservations pour que les IDs soient disponibles
        $manager->flush();

        // -----------------------------------------------------------------
        // 6. Réservations de test
        // -----------------------------------------------------------------

        // Réservation PENDING — Ahmed, siège 5, trajet Casa → Marrakech du 10/07
        // Permet de tester POST /api/payments/booking/{id}
        $booking1 = new Booking();
        $booking1->setUser($passager);
        $booking1->setTrip($trajet1);
        $booking1->setSeatNumber(5);
        $booking1->setStatus('PENDING');
        $manager->persist($booking1);

        // Réservation PAID — Zineb, siège 3, trajet Rabat → Tanger du 11/07
        // Permet de tester GET /api/bookings/user/{userId} et l'annulation REFUNDED
        $booking2 = new Booking();
        $booking2->setUser($passager2);
        $booking2->setTrip($trajet3);
        $booking2->setSeatNumber(3);
        $booking2->setStatus('PAID');
        $manager->persist($booking2);

        $manager->flush();

        // -----------------------------------------------------------------
        // 7. Paiement associé à la réservation PAID
        // -----------------------------------------------------------------

        $paiement = new Payment();
        $paiement->setBooking($booking2);
        $paiement->setAmount($trajet3->getRoute()->getBasePrice());
        $paiement->setPaymentMethod('CARD');
        $paiement->setPaymentProvider('CMI');
        $paiement->setPaymentStatus('SUCCESS');
        $paiement->setTransactionId('CMI-' . time() . '-fixture');
        $manager->persist($paiement);

        $manager->flush();
    }

    // -------------------------------------------------------------------------
    // Méthodes privées de construction
    // -------------------------------------------------------------------------

    private function makeCity(string $nom): City
    {
        $ville = new City();
        $ville->setName($nom);

        return $ville;
    }

    private function makeBus(string $immatriculation, int $nombrePlaces): Bus
    {
        $bus = new Bus();
        $bus->setPlateNumber($immatriculation);
        $bus->setTotalSeats($nombrePlaces);

        return $bus;
    }

    private function makeRoute(City $depart, City $arrivee, string $prixBase): Route
    {
        $route = new Route();
        $route->setDepartureCity($depart);
        $route->setArrivalCity($arrivee);
        $route->setBasePrice($prixBase);

        return $route;
    }

    private function makeTrip(
        Route $route,
        Bus $bus,
        string $depart,
        string $arrivee,
        string $statut,
    ): Trip {
        $trajet = new Trip();
        $trajet->setRoute($route);
        $trajet->setBus($bus);
        $trajet->setDepartureTime(new \DateTime($depart));
        $trajet->setArrivalTime(new \DateTime($arrivee));
        $trajet->setStatus($statut);

        return $trajet;
    }
}
