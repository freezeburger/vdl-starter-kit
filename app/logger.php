<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Construit le logger PSR-3 partagé de l'application.
 *
 * Monolog est le choix par défaut : implémentation PSR-3 de référence de
 * l'écosystème PHP, déjà une dépendance transitive de plusieurs paquets
 * de ce socle (Slim, Doctrine Migrations). Un seul flux de sortie (fichier)
 * suffit pour ce starter ; ajouter d'autres canaux (Sentry, syslog...) se
 * fait en ajoutant un Handler supplémentaire, sans toucher au reste du code
 * (tout composant applicatif dépend uniquement de LoggerInterface).
 *
 * @param string $logFile chemin absolu du fichier de log (créé si absent)
 * @param string $level niveau minimal journalisé (debug, info, warning...),
 *   voir les constantes de Psr\Log\LogLevel
 */
function buildLogger(string $logFile, string $level): LoggerInterface
{
    $logsDirectory = dirname($logFile);
    if (!is_dir($logsDirectory)) {
        mkdir($logsDirectory, 0777, true);
    }

    $logger = new Logger('app');
    $logger->pushHandler(new StreamHandler($logFile, Level::fromName(ucfirst(strtolower($level)))));

    return $logger;
}
