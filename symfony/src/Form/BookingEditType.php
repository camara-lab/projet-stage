<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;

class BookingEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('seatNumber', IntegerType::class, [
                'label' => 'Numéro de siège',
                'attr'  => ['min' => 1, 'max' => $options['max_seats'], 'class' => 'form-control'],
                'constraints' => [
                    new NotBlank(),
                    new Positive(message: 'Le siège doit être un entier positif.'),
                    new Range(
                        min: 1,
                        max: $options['max_seats'],
                        notInRangeMessage: 'Le numéro de siège doit être entre 1 et {{ max }}.',
                    ),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'attr'  => ['class' => 'form-select'],
                'choices' => [
                    'En attente'  => 'PENDING',
                    'Payée'       => 'PAID',
                    'Annulée'     => 'CANCELLED',
                    'Remboursée'  => 'REFUNDED',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
            'max_seats'  => 50,
        ]);
        $resolver->setAllowedTypes('max_seats', 'int');
    }
}
