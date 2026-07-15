<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Bus;
use App\Entity\Route;
use App\Entity\Trip;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TripType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('route', EntityType::class, [
                'label' => 'Ligne',
                'class' => Route::class,
                'choice_label' => fn(Route $r) => $r->getDepartureCity()->getName().' → '.$r->getArrivalCity()->getName().' ('.$r->getBasePrice().' DH)',
                'attr' => ['class' => 'form-select'],
                'placeholder' => '-- Sélectionner une ligne --',
                'constraints' => [new NotBlank()],
            ])
            ->add('bus', EntityType::class, [
                'label' => 'Bus',
                'class' => Bus::class,
                'choice_label' => fn(Bus $b) => $b->getPlateNumber().' ('.$b->getTotalSeats().' places)',
                'attr' => ['class' => 'form-select'],
                'placeholder' => '-- Sélectionner un bus --',
                'constraints' => [new NotBlank()],
            ])
            ->add('departureTime', DateTimeType::class, [
                'label' => 'Départ',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()],
            ])
            ->add('arrivalTime', DateTimeType::class, [
                'label' => 'Arrivée',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'constraints' => [new NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Trip::class]);
    }
}
