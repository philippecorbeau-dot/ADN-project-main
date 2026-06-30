<?php

/**
 * Bootstrap commun aux scripts cron OVH.
 *
 * - Charge l'autoloader Symfony et les variables d'environnement
 * - Force APP_ENV=prod et APP_DEBUG=0
 * - Initialise le logging dans var/log/o2s-cron.log
 * - Expose run_console_command() pour exécuter une commande CLI Symfony
 *
 * Path attendu sur OVH : www/cron/ovh/<script>.php
 * Donc projet root = dirname(__DIR__, 2)
 */

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;

ini_set('memory_limit', '512M');
set_time_limit(3500); // < 60 min OVH cron limit
date_default_timezone_set('Europe/Paris');

$projectRoot = dirname(__DIR__, 2);

if (!is_file($projectRoot . '/vendor/autoload.php')) {
    fwrite(STDERR, "[FATAL] vendor/autoload.php introuvable. Project root attendu : $projectRoot\n");
    exit(2);
}

require_once $projectRoot . '/vendor/autoload.php';

if (class_exists(Dotenv::class) && is_file($projectRoot . '/.env')) {
    (new Dotenv())->bootEnv($projectRoot . '/.env');
}

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'prod';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';

$LOG_FILE = $projectRoot . '/var/log/o2s-cron.log';

if (!is_dir(dirname($LOG_FILE))) {
    @mkdir(dirname($LOG_FILE), 0775, true);
}

if (!function_exists('cron_log')) {
    /**
     * Log structuré (console + fichier).
     */
    function cron_log(string $action, string $level, string $message, array $context = []): void
    {
        global $LOG_FILE;

        $line = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            $action,
            $level,
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        @file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
        echo $line;
    }
}

if (!function_exists('run_console_command')) {
    /**
     * Exécute une commande Symfony Console et retourne [exitCode, output].
     */
    function run_console_command(string $command, array $args = []): array
    {
        $kernel = new Kernel('prod', false);
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array_merge(['command' => $command], $args));
        $output = new BufferedOutput();

        $exitCode = $application->run($input, $output);

        return [$exitCode, $output->fetch()];
    }
}

if (!function_exists('get_service')) {
    /**
     * Récupère un service depuis le container Symfony (services publics uniquement).
     */
    function get_service(string $serviceId): object
    {
        static $kernel = null;
        if ($kernel === null) {
            $kernel = new Kernel('prod', false);
            $kernel->boot();
        }

        $container = $kernel->getContainer();

        if ($container->has($serviceId)) {
            return $container->get($serviceId);
        }

        throw new RuntimeException("Service introuvable ou non public : $serviceId");
    }
}
