<?php

declare(strict_types=1);

/**
 * Configuration Doctrine Migrations, utilisée par `vendor/bin/doctrine-migrations`
 * (exposé via le script composer `composer doctrine -- <commande>`).
 *
 * Réutilise le même EntityManager que le bootstrap HTTP (app/database.php) :
 * la connexion et le mapping des Entities restent une source unique de
 * vérité, qu'on lance l'application ou une commande de migration.
 *
 * Exemples :
 *   composer doctrine -- migrations:diff      Génère une migration à partir des Entities
 *   composer doctrine -- migrations:migrate   Applique les migrations en attente
 *   composer doctrine -- migrations:status    Affiche l'état des migrations
 */

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/database.php';

loadEnvironment(__DIR__ . '/.env');

$entityManager = buildEntityManager(
    __DIR__ . '/src/Repositories',
    filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL)
);

$configuration = new ConfigurationArray([
    'migrations_paths' => [
        'App\\Migrations' => __DIR__ . '/migrations',
    ],
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
    ],
]);

return DependencyFactory::fromEntityManager(
    $configuration,
    new ExistingEntityManager($entityManager)
);
