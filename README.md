# Satis Pilotage

Plateforme interne de pilotage du refactoring des solutions Satis (Satis Macro, Satis Pro, Satis Assur) : référentiel des versions client, catalogue fonctionnel, états, écarts, règles métier, problèmes et rapports.

Les conventions de code et les règles de gestion sont dans `CLAUDE.md`.

## Développement local

Prérequis : PHP 8.3+, Composer, Node 22, MySQL 8 avec deux bases (`satis` et `satis_test`).

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# Renseigner DB_DATABASE, DB_USERNAME et DB_PASSWORD dans .env
php artisan migrate
php artisan user:create
composer dev
```

Avant chaque livraison :

```bash
composer check   # Pint, Larastan, tests (base satis_test)
```

## Comptes

Il n'y a pas d'inscription : les comptes sont créés en ligne de commande.

```bash
php artisan user:create
php artisan user:password adresse@exemple.com
```

## Déploiement (serveur Satisworld)

Prérequis : Docker avec Docker Compose, accès au dépôt Git.

Première installation :

```bash
cp .env.example .env
# APP_ENV=production, APP_DEBUG=false, APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD, APP_PORT
docker compose run --rm app php artisan key:generate --show   # copier la clé dans APP_KEY
./deploy.sh
docker compose exec app php artisan user:create
```

Mises à jour : `./deploy.sh` (branche `main` par défaut, ou `./deploy.sh autre-branche`). Le script construit les images, lance `composer check` dans un conteneur de test et s'arrête en cas d'échec, puis applique les migrations et redémarre les services.

Le HTTPS est attendu en frontal (reverse proxy Satisworld) ; le conteneur `web` écoute en HTTP sur `APP_PORT`.

Sauvegardes : `docker/backup.sh`, à planifier dans la crontab de l'hôte (dump chiffré quotidien, conservé 30 jours). La procédure de restauration est en commentaire dans le script.
