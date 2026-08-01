<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO de validation pour l'endpoint POST /api/register.
 * Toutes les contraintes sont vérifiées par le Validator Symfony avant persistance.
 */
final class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'adresse e-mail est obligatoire.')]
        #[Assert\Email(message: 'L\'adresse e-mail "{{ value }}" est invalide.')]
        #[Assert\Length(max: 255)]
        public readonly string $email,

        #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
        #[Assert\Length(min: 8, max: 255, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
        #[Assert\Regex(
            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.',
        )]
        public readonly string $password,

        #[Assert\NotBlank(message: 'Le nom complet est obligatoire.')]
        #[Assert\Length(min: 2, max: 60, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.')]
        // Le billet est nominatif : le nom doit correspondre à une pièce d'identité.
        #[Assert\Regex(
            pattern: '/^[\p{L}]+(?:[ \'\-][\p{L}]+)*$/u',
            message: 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.',
        )]
        public readonly string $fullName,

        #[Assert\Regex(
            pattern: '/^(?:\+212|0)[5-7][0-9]{8}$/',
            message: 'Le numéro doit être un mobile ou fixe marocain valide (ex: 0612345678 ou +212612345678).',
        )]
        public readonly ?string $phone = null,

        #[Assert\Length(min: 8, max: 10, minMessage: 'Le CIN doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le CIN ne peut pas dépasser {{ limit }} caractères.')]
        #[Assert\Regex(pattern: '/^[A-Z]{1,2}[0-9]{5,8}$/i', message: 'Le format du CIN est invalide (ex: AB123456).')]
        public readonly ?string $cin = null,
    ) {
    }
}
