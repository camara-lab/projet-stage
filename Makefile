# ============================================================
# Bus Booking System - Makefile
# Cibles principales pour piloter le projet depuis la racine
# ============================================================

DC              = docker compose
APP_EXEC        = $(DC) exec app
APP_EXEC_ROOT   = $(DC) exec -u root app
COMPOSER        = $(APP_EXEC) composer
SYMFONY_CONSOLE = $(APP_EXEC) php bin/console

.DEFAULT_GOAL := help

# ------------------------------------------------------------
# Aide
# ------------------------------------------------------------
.PHONY: help
help: ## Affiche cette aide
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-25s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------
# Docker
# ------------------------------------------------------------
.PHONY: build up down restart logs ps
build: ## Build des images Docker
	$(DC) build --pull

up: ## Lance la stack en arrière-plan
	$(DC) up -d

down: ## Arrête la stack
	$(DC) down

restart: down up ## Redémarre la stack

logs: ## Affiche les logs (ctrl+c pour quitter)
	$(DC) logs -f

ps: ## Affiche l'état des conteneurs
	$(DC) ps

# ------------------------------------------------------------
# Shells
# ------------------------------------------------------------
.PHONY: bash shell-root db
bash: ## Ouvre un shell dans le conteneur PHP (user symfony)
	$(APP_EXEC) bash

shell-root: ## Shell root dans le conteneur PHP
	$(APP_EXEC_ROOT) bash

db: ## Client MySQL interactif
	$(DC) exec db mysql -uroot -proot bus_booking

# ------------------------------------------------------------
# Symfony & Composer
# ------------------------------------------------------------
.PHONY: composer install console cc
composer: ## Alias composer (usage: make composer c="require symfony/uid")
	$(COMPOSER) $(c)

install: ## composer install
	$(COMPOSER) install

console: ## Alias bin/console (usage: make console c="make:entity")
	$(SYMFONY_CONSOLE) $(c)

cc: ## Vide le cache Symfony
	$(SYMFONY_CONSOLE) cache:clear

# ------------------------------------------------------------
# Base de données
# ------------------------------------------------------------
.PHONY: db-create db-migrate db-reset fixtures
db-create: ## Crée la base
	$(SYMFONY_CONSOLE) doctrine:database:create --if-not-exists

db-migrate: ## Joue les migrations
	$(SYMFONY_CONSOLE) doctrine:migrations:migrate --no-interaction

db-reset: ## Drop + recreate + migrate
	$(SYMFONY_CONSOLE) doctrine:database:drop --force --if-exists
	$(SYMFONY_CONSOLE) doctrine:database:create
	$(SYMFONY_CONSOLE) doctrine:migrations:migrate --no-interaction

fixtures: ## Charge les fixtures
	$(SYMFONY_CONSOLE) doctrine:fixtures:load --no-interaction

# ------------------------------------------------------------
# Qualité : PHPStan, CS-Fixer, Tests
# ------------------------------------------------------------
.PHONY: stan cs cs-fix test test-coverage qa
stan: ## Analyse statique PHPStan niveau 8
	$(APP_EXEC) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Vérifie les règles PHP-CS-Fixer (dry-run)
	$(APP_EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Corrige les règles PHP-CS-Fixer
	$(APP_EXEC) vendor/bin/php-cs-fixer fix

test: ## Lance la suite de tests PHPUnit
	$(APP_EXEC) vendor/bin/phpunit

test-coverage: ## Tests avec rapport de couverture HTML (var/coverage)
	$(APP_EXEC) sh -c "XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html var/coverage"

qa: cs stan test ## Pipeline qualité complet (CS + Stan + Tests)
