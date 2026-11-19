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

Données de démonstration (fictives, jamais en production) :

```bash
php artisan migrate:fresh && php artisan db:seed --class=DemoSeeder && php artisan user:create
```

Volumétrie (environ 2 100 éléments × 20 versions, pour vérifier les performances) :

```bash
php artisan db:seed --class=VolumeSeeder
```

Avant chaque livraison :

```bash
composer check   # Pint, Larastan, tests (base satis_test)
```

## Rapports (livrables DTI)

L'écran « Rapports » génère les livrables du plan DTI (SPEC-001) à partir des données de l'application : un document Word rempli depuis le modèle de `resources/report-templates`, et une annexe XLSX avec les tableaux complets. Chaque export est figé (instantané des données, empreintes SHA-256) et conservé dans `storage/app/private/reports`. Les données absentes de l'application apparaissent « À confirmer ».

Livrables exportables à ce jour : L1, L2 et L3. Les autres modèles sont en place et seront branchés au fil des lots. L'annexe XLSX est écrite en flux : un rapport de plusieurs dizaines de milliers de lignes reste sous 150 Mo de mémoire.

## Technologies

Les technologies saisies sur un dépôt (stack), un composant ou un environnement (moteur de base, version PHP) s'ajoutent automatiquement au référentiel « Technologies », sans date de fin de support : il ne reste qu'à la renseigner (filtre « Seulement les dates à renseigner »). Pour reprendre ce qui a été saisi avant cette fonction :

```bash
php artisan technologies:sync
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

Sauvegardes : `docker/backup.sh`, à planifier dans la crontab de l'hôte (dump chiffré quotidien et archive chiffrée des rapports générés, conservés 30 jours). La procédure de restauration est en commentaire dans le script.
