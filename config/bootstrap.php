<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (!class_exists(Dotenv::class)) {
    throw new RuntimeException('Install symfony/dotenv to load environment variables from .env');
}

if (!isset($_SERVER['APP_ENV'])) {
    (new Dotenv())
        ->usePutenv()
        ->bootEnv(dirname(__DIR__).'/.env');
}

// Définir un fuseau horaire cohérent (Europe/Paris par défaut)
// Permet d'afficher les dates/heures correctement côté UI sans décalage
if (!ini_get('date.timezone')) {
    date_default_timezone_set($_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? 'Europe/Paris');
}
