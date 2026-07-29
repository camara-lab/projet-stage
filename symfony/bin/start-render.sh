#!/bin/sh
# Démarrage du service sur Render : prépare la base puis lance le serveur HTTP.
set -e

php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

exec php -S 0.0.0.0:"${PORT:-10000}" -t public
