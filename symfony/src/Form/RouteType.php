<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\City;
use App\Entity\Route;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class RouteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('departureCity', EntityType::class, [
                'label' => 'Ville de départ',
                'class' => City::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => '-- Sélectionner --',
                'constraints' => [new NotBlank(message: 'La ville de départ est obligatoire.')],
            ])
            ->add('arrivalCity', EntityType::class, [
                'label' => 'Ville d\'arrivée',
                'class' => City::class,
                'choice_label' => 'name',
                'attr' => ['class' => 'form-select'],
                'placeholder' => '-- Sélectionner --',
                'constraints' => [new NotBlank(message: 'La ville d\'arrivée est obligatoire.')],
            ])
            ->add('basePrice', MoneyType::class, [
                'label' => 'Prix de base (DH)',
                'currency' => false,
                'attr' => ['placeholder' => 'ex: 90.00', 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(),
                    new Positive(message: 'Le prix doit être positif.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Route::class]);
    }
}
