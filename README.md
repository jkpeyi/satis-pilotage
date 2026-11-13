# Satis Pilotage

Plateforme interne de pilotage du refactoring des solutions Satis (Satis Macro, Satis Pro, Satis Assur) : référentiel des versions client, catalogue fonctionnel, états, écarts, règles métier, problèmes et rapports.

## Prérequis

PHP 8.3+, Composer, Node 22, MySQL 8.

## Installation locale

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# Renseigner DB_CONNECTION=mysql, DB_DATABASE, DB_USERNAME, DB_PASSWORD dans .env
php artisan migrate
composer dev
```

Les conventions de code et les règles de gestion sont dans `CLAUDE.md`.
