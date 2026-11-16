# Migrations Doctrine

Ce dossier contient les migrations de schéma générées par Doctrine Migrations, une classe PHP par migration (namespace `App\Migrations`).

## Commandes

Toutes les commandes passent par le script composer `doctrine`, qui expose `vendor/bin/doctrine-migrations` avec la configuration du projet (`cli-config.php` à la racine, qui réutilise l'EntityManager de `app/database.php`) :

```bash
composer doctrine -- migrations:diff       # Génère une migration à partir des Entities (src/Application/*/*.php)
composer doctrine -- migrations:migrate    # Applique les migrations en attente
composer doctrine -- migrations:status     # Affiche l'état des migrations
```

## Flux de travail habituel

1. Créer ou modifier une Entity (le Model d'un domaine, `src/Application/DOMAIN_NAME/DOMAIN_NAMEModel.php`), par exemple après `composer make:domain`.
2. Générer la migration correspondante : `composer doctrine -- migrations:diff`.
3. Relire la migration générée avant de l'appliquer (Doctrine ne devine pas toujours l'intention exacte d'un changement de schéma).
4. Appliquer la migration : `composer doctrine -- migrations:migrate`.

La configuration de connexion (moteur MySQL/MariaDB par défaut) se fait via les variables `DB_*` du fichier `.env` (voir `.env.example`).
