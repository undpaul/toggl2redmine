<?php

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use undpaul\Kernel;

if (version_compare('8.3.0', PHP_VERSION, '>')) {
  fwrite(
    STDERR,
    sprintf(
      'This version of SchemaValidator requires PHP >= 8.2.' . PHP_EOL .
      'You are using PHP %s (%s).' . PHP_EOL,
      PHP_VERSION,
      PHP_BINARY
    )
  );

  die(1);
}

if (isset($GLOBALS['_composer_autoload_path'])) {
  define('TOGGL2REDMINE_COMPOSER_AUTOLOAD', $GLOBALS['_composer_autoload_path']);

  unset($GLOBALS['_composer_autoload_path']);
}
else {
  $autoload_candidates = [
    __DIR__ . '/../../autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
  ];
  foreach ($autoload_candidates as $file) {
    if (file_exists($file)) {
      define('TOGGL2REDMINE_COMPOSER_AUTOLOAD', $file);

      break;
    }
  }

  unset($file);
}

if (!defined('TOGGL2REDMINE_COMPOSER_AUTOLOAD')) {
  fwrite(
    STDERR,
    'You need to set up the project dependencies using Composer:' . PHP_EOL . PHP_EOL .
    '    composer install' . PHP_EOL . PHP_EOL .
    'You can learn all about Composer on https://getcomposer.org/.' . PHP_EOL
  );

  die(1);
}

require TOGGL2REDMINE_COMPOSER_AUTOLOAD;

// Initialize the kernel
$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? false));
$kernel->boot();

$application = new Application();

// Create a new ContainerBuilder instance
$container_builder = new ContainerBuilder();

// Manually set parameters that are usually set by the Symfony kernel
$container_builder->setParameter('kernel.project_dir', $kernel->getProjectDir());
$container_builder->setParameter('kernel.environment', $kernel->getEnvironment());
$container_builder->setParameter('kernel.debug', $kernel->isDebug());
$container_builder->setParameter('kernel.cache_dir', $kernel->getCacheDir());
$container_builder->setParameter('kernel.logs_dir', $kernel->getLogDir());
$container_builder->setParameter('kernel.container_class', get_class($container_builder));

// Load additional service configurations
$loader = new YamlFileLoader($container_builder, new FileLocator($kernel->getProjectDir() . '/config'));
$loader->load('services.yaml');

// Setup the RequestStack manually
$request_stack = new RequestStack();
$request = Request::createFromGlobals();
$request_stack->push($request);

// Register the RequestStack in the container
$container_builder->set('request_stack', $request_stack);

// Alias the Psr\Container\ContainerInterface to the Symfony service container
$container_builder->setAlias(ContainerInterface::class, 'service_container');

// Compile container
$container_builder->compile();

// Register commands from the container
foreach ($container_builder->findTaggedServiceIds('console.command') as $id => $tags) {
  $application->add($container_builder->get($id));
}

// Set input and output
$input = new ArgvInput();
$output = new ConsoleOutput();

// Run the application
exit($application->run($input, $output));
