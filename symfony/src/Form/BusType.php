<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Bus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class BusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plateNumber', TextType::class, [
                'label' => 'Immatriculation',
                'attr' => ['placeholder' => 'ex: CTM-W-99999', 'class' => 'form-control'],
                'constraints' => [new NotBlank(message: 'L\'immatriculation est obligatoire.')],
            ])
            ->add('totalSeats', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr' => ['min' => 1, 'max' => 100, 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(),
                    new Range(min: 1, max: 100, notInRangeMessage: 'La capacité doit être entre 1 et 100.'),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'attr' => ['class' => 'form-select'],
                'choices' => ['Disponible' => 'AVAILABLE', 'En maintenance' => 'MAINTENANCE'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Bus::class]);
    }
}
