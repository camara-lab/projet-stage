<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\City;
use App\Form\CityType;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/cities', name: 'admin_city_')]
final class AdminCityController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, CityRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));

        $qb = $repo->createQueryBuilder('c')->orderBy('c.name', 'ASC');

        if ($search !== '') {
            $qb->andWhere('c.name LIKE :q')->setParameter('q', '%'.$search.'%');
        }

        $cities = $qb->getQuery()->getResult();

        return $this->render('admin/city/index.html.twig', [
            'cities' => $cities,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $city = new City();
        $form = $this->createForm(CityType::class, $city);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($city);
            $em->flush();
            $this->addFlash('success', "Ville \"{$city->getName()}\" créée avec succès.");
            return $this->redirectToRoute('admin_city_index');
        }

        return $this->render('admin/city/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle ville',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(City $city, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CityType::class, $city);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', "Ville \"{$city->getName()}\" modifiée.");
            return $this->redirectToRoute('admin_city_index');
        }

        return $this->render('admin/city/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la ville',
            'city' => $city,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(City $city, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-city-'.$city->getId(), $request->request->get('_token'))) {
            try {
                $em->remove($city);
                $em->flush();
                $this->addFlash('success', "Ville \"{$city->getName()}\" supprimée.");
            } catch (\Exception $e) {
                $this->addFlash('error', "Impossible de supprimer la ville \"{$city->getName()}\" : elle est utilisée par des lignes existantes.");
            }
        }
        return $this->redirectToRoute('admin_city_index');
    }
}
