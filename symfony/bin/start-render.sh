#!/bin/sh
# Démarrage du service sur Render : prépare la base puis lance le serveur HTTP.
set -e

php bin/console cache:clear --no-interaction
php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

# Garde toujours des trajets réservables (sans effet s'ils sont déjà à venir).
# Un échec ici ne doit pas empêcher le démarrage du serveur.
php bin/console app:demo:refresh --no-interaction || echo "Rafraichissement des trajets ignore."

exec php -S 0.0.0.0:"${PORT:-10000}" -t public
