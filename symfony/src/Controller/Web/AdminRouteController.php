<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Route as BusRoute;
use App\Form\RouteType;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/routes', name: 'admin_route_')]
final class AdminRouteController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, RouteRepository $repo): Response
    {
        $depart  = trim((string) $request->query->get('depart', ''));
        $arrivee = trim((string) $request->query->get('arrivee', ''));

        $qb = $repo->createQueryBuilder('r')
            ->join('r.departureCity', 'dc')
            ->join('r.arrivalCity', 'ac')
            ->addSelect('dc', 'ac');

        if ($depart !== '') {
            $qb->andWhere('dc.name LIKE :dep')->setParameter('dep', '%'.$depart.'%');
        }

        if ($arrivee !== '') {
            $qb->andWhere('ac.name LIKE :arr')->setParameter('arr', '%'.$arrivee.'%');
        }

        $routes = $qb->getQuery()->getResult();

        return $this->render('admin/route/index.html.twig', [
            'routes'  => $routes,
            'depart'  => $depart,
            'arrivee' => $arrivee,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $route = new BusRoute();
        $form = $this->createForm(RouteType::class, $route);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($route->getDepartureCity()->getId() === $route->getArrivalCity()->getId()) {
                $this->addFlash('error', 'La ville de départ et d\'arrivée doivent être différentes.');
            } else {
                $em->persist($route);
                $em->flush();
                $this->addFlash('success', 'Ligne créée avec succès.');
                return $this->redirectToRoute('admin_route_index');
            }
        }

        return $this->render('admin/route/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle ligne',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(BusRoute $route, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RouteType::class, $route);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($route->getDepartureCity()->getId() === $route->getArrivalCity()->getId()) {
                $this->addFlash('error', 'La ville de départ et d\'arrivée doivent être différentes.');
            } else {
                $em->flush();
                $this->addFlash('success', 'Ligne modifiée.');
                return $this->redirectToRoute('admin_route_index');
            }
        }

        return $this->render('admin/route/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la ligne',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(BusRoute $route, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-route-'.$route->getId(), $request->request->get('_token'))) {
            try {
                $em->remove($route);
                $em->flush();
                $this->addFlash('success', 'Ligne supprimée.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Impossible de supprimer cette ligne : elle est utilisée par des trajets existants.');
            }
        }
        return $this->redirectToRoute('admin_route_index');
    }
}
