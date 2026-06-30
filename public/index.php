<?php
// Front controller classique sans Symfony Runtime
// Désactive d'éventuels autoloaders Composer restants (workers LSCache)
if (function_exists('spl_autoload_functions')) {
    foreach (spl_autoload_functions() ?: [] as $fn) {
        if (is_array($fn) && is_object($fn[0]) && get_class($fn[0]) === 'Composer\\Autoload\\ClassLoader') {
            spl_autoload_unregister($fn);
        }
    }
}

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/config/bootstrap.php';

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'prod', (bool) ($_SERVER['APP_DEBUG'] ?? false));
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
