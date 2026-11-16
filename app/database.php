<?php

declare(strict_types=1);

/**
 * Fonctions partagées de configuration de l'environnement et de Doctrine.
 *
 * Isolées de app/app.php pour être réutilisables sans démarrer toute
 * l'application Slim : app/app.php (bootstrap HTTP) et cli-config.php
 * (commandes composer doctrine:*, migrations) chargent ce fichier.
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

/**
 * Charge les variables simples du fichier .env sans ajouter de dépendance.
 */
function loadEnvironment(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key !== '' && getenv($key) === false) {
            $_ENV[$key] = trim($value, "\"'");
            putenv($key . '=' . $_ENV[$key]);
        }
    }
}

/**
 * Construit l'EntityManager Doctrine. Le mapping des Entities est fait par
 * attributs PHP 8 (pas d'annotations docblock ni de XML) et scanné sous
 * src/Repositories/NOM/, où vit le Model (devenu Entity) à côté de son
 * Repository. La connexion cible MySQL/MariaDB par défaut, configurée via
 * les variables DB_* du fichier .env — voir repositoriesExist() ci-dessous :
 * ces variables ne sont nécessaires que si l'application compte au moins
 * un Repository.
 *
 * Cet EntityManager est ensuite injecté dans chaque Repository
 * (src/Repositories/) : voir src/Repositories/README.md pour le schéma
 * complet Entity → Repository → EntityManager → base de données.
 */
function buildEntityManager(string $repositoriesDirectory, bool $isDevMode): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([$repositoriesDirectory], $isDevMode);

    // Les proxies Doctrine ont besoin d'objets "lazy" : nativement supportés
    // par PHP 8.4+, sinon via symfony/var-exporter (version 6.4 ou 7 requise
    // par Doctrine ORM ; à ajouter explicitement au projet si l'équipe cible
    // PHP 8.3 en production).
    $config->enableNativeLazyObjects(PHP_VERSION_ID >= 80400);

    $connection = DriverManager::getConnection([
        'driver' => 'pdo_mysql',
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'dbname' => $_ENV['DB_NAME'] ?? 'vdl_starter',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
    ], $config);

    return new EntityManager($connection, $config);
}

/**
 * Indique si l'application compte au moins un Repository généré
 * (`composer make:repository`, src/Repositories/NOM/NOMRepository.php).
 *
 * Les variables DB_* (.env) ne sont consommées par Doctrine qu'au travers
 * de buildEntityManager() : tant qu'aucun Repository n'existe, rien dans
 * l'application n'a besoin d'une base de données, et ces variables
 * peuvent donc rester absentes du .env (voir app/app.php, qui n'appelle
 * buildEntityManager() que si cette fonction retourne true).
 */
function repositoriesExist(string $repositoriesDirectory): bool
{
    return glob($repositoriesDirectory . '/*/*Repository.php') !== [];
}
