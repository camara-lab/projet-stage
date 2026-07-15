#  Système de réservation de bus inter-villes

Application web de réservation de billets de bus : recherche de trajets, choix des sièges, tarification par type de passager (adulte / enfant / bébé), réservation aller-retour et paiement, avec un espace d'administration pour la gestion de la flotte et des statistiques.

Projet réalisé dans le cadre d'un stage de fin d'études (PFE).

## Stack technique

- **Backend** : PHP 8.3, Symfony 7, Doctrine ORM, authentification JWT (LexikJWT) avec refresh token en cookie HttpOnly
- **Frontend** : Next.js 14 (App Router), TailwindCSS
- **Base de données** : MySQL 8
- **Infrastructure** : Docker Compose (PHP-FPM, Nginx, MySQL), Makefile

## Prérequis

- Docker et Docker Compose
- Node.js 18+ (pour le frontend en dev)
- Make (optionnel, facilite les commandes)

## Installation

### Backend (API)

```bash
# Lancer la stack Docker (PHP + Nginx + MySQL)
docker compose up -d

# Installer les dépendances
docker compose exec app composer install

# Générer les clés JWT
docker compose exec app php bin/console lexik:jwt:generate-keypair

# Créer le schéma et charger les données de test
docker compose exec app php bin/console doctrine:migrations:migrate -n
docker compose exec app php bin/console doctrine:fixtures:load -n
```

L'API est disponible sur `http://localhost:8080` (port configurable via `NGINX_PORT` dans le fichier `.env` à la racine).

> Un `Makefile` est fourni pour simplifier ces commandes (`make up`, `make composer c=install`, etc.) si `make` est installé.

### Frontend

```bash
cd frontend
npm install
npm run dev
```

L'interface est disponible sur `http://localhost:3000`.

## Tests

```bash
docker compose exec app php bin/phpunit
```

## Outils

- **phpMyAdmin** (optionnel) : `docker compose --profile tools up -d` → `http://localhost:8081`
- **Collection Postman** : `BusBooking_Postman_Collection.json` à la racine
- **Qualité de code** : PHPStan et PHP-CS-Fixer configurés dans `symfony/`

## Structure du projet

```
├── symfony/       API Symfony (contrôleurs, entités, services, tests)
├── frontend/      Application Next.js
├── docker/        Configuration des conteneurs (PHP, Nginx)
├── nginx/         Configuration production
└── scripts/       Scripts utilitaires
```

## Fonctionnalités principales

- Inscription et connexion (JWT + refresh token sécurisé)
- Recherche de trajets par ville et date, aller simple ou aller-retour
- Sélection des sièges sur plan de bus, affectation par passager
- Tarification par type de passager (adulte, enfant, bébé)
- Paiement et émission de billet électronique
- Espace client : historique des réservations, profil
- Administration : gestion des bus, des trajets, statistiques (chiffre d'affaires, taux d'occupation)
