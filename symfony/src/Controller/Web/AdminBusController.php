<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Bus;
use App\Form\BusType;
use App\Repository\BusRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/buses', name: 'admin_bus_')]
final class AdminBusController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, BusRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $statut = $request->query->get('statut', '');

        $qb = $repo->createQueryBuilder('b')->orderBy('b.plateNumber', 'ASC');

        if ($search !== '') {
            $qb->andWhere('b.plateNumber LIKE :q')->setParameter('q', '%'.$search.'%');
        }

        if (\in_array($statut, ['AVAILABLE', 'MAINTENANCE'], true)) {
            $qb->andWhere('b.status = :statut')->setParameter('statut', $statut);
        }

        $buses = $qb->getQuery()->getResult();

        return $this->render('admin/bus/index.html.twig', [
            'buses'  => $buses,
            'search' => $search,
            'statut' => $statut,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $bus = new Bus();
        $form = $this->createForm(BusType::class, $bus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($bus);
            $em->flush();
            $this->addFlash('success', "Bus \"{$bus->getPlateNumber()}\" ajouté.");
            return $this->redirectToRoute('admin_bus_index');
        }

        return $this->render('admin/bus/form.html.twig', [
            'form' => $form,
            'title' => 'Ajouter un bus',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Bus $bus, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(BusType::class, $bus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', "Bus \"{$bus->getPlateNumber()}\" modifié.");
            return $this->redirectToRoute('admin_bus_index');
        }

        return $this->render('admin/bus/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le bus',
            'bus' => $bus,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Bus $bus, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-bus-'.$bus->getId(), $request->request->get('_token'))) {
            try {
                $em->remove($bus);
                $em->flush();
                $this->addFlash('success', "Bus \"{$bus->getPlateNumber()}\" supprimé.");
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('error', "Impossible de supprimer le bus \"{$bus->getPlateNumber()}\" : il est affecté à des trajets existants.");
            }
        }
        return $this->redirectToRoute('admin_bus_index');
    }
}
