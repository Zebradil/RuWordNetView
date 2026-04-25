<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Zebradil\RuWordNet\Controllers\SiteController;
use Zebradil\RuWordNet\Models\Sense;
use Zebradil\RuWordNet\Models\SenseRelation;
use Zebradil\RuWordNet\Models\Synset;
use Zebradil\RuWordNet\Models\SynsetRelation;
use Zebradil\RuWordNet\Repositories\SenseRelationRepository;
use Zebradil\RuWordNet\Repositories\SenseRepository;
use Zebradil\RuWordNet\Repositories\SynsetRelationRepository;
use Zebradil\RuWordNet\Repositories\SynsetRepository;
use Zebradil\SilexDoctrineDbalModelRepository\RepositoryFactoryService;

require_once __DIR__ . '/../vendor/autoload.php';

// Load DB config (keep the same JSON format as before for zero-touch deploy)
$cfgPath = __DIR__ . '/config/database.json';
$cfg = json_decode(@file_get_contents($cfgPath) ?: '', true);
if (!is_array($cfg)
    || !array_key_exists('driver', $cfg)
    || !array_key_exists('dbname', $cfg)
    || !array_key_exists('host', $cfg)
    || !array_key_exists('port', $cfg)
    || !array_key_exists('user', $cfg)
    || !array_key_exists('password', $cfg)
) {
    throw new RuntimeException("Invalid or missing database configuration at {$cfgPath}");
}

// Mutable per-request context exposed to all Twig templates as {{ app.locale }} etc.
$requestCtx = new stdClass();
$requestCtx->locale      = 'ru';
$requestCtx->route_name  = 'homepage';
$requestCtx->route_params = [];

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([

    LoggerInterface::class => function (): LoggerInterface {
        $logger = new Logger('app');
        $logger->pushHandler(new RotatingFileHandler(
            __DIR__ . '/../var/log/app.log',
            7,
            Logger::INFO,
        ));
        return $logger;
    },

    Connection::class => function () use ($cfg): Connection {
        return DriverManager::getConnection([
            'driver'   => $cfg['driver'],
            'dbname'   => $cfg['dbname'],
            'host'     => $cfg['host'],
            'port'     => (int) $cfg['port'],
            'user'     => $cfg['user'],
            'password' => $cfg['password'],
        ]);
    },

    RepositoryFactoryService::class => function (ContainerInterface $c): RepositoryFactoryService {
        return new RepositoryFactoryService($c->get(Connection::class), [
            Sense::class         => SenseRepository::class,
            Synset::class        => SynsetRepository::class,
            SenseRelation::class => SenseRelationRepository::class,
            SynsetRelation::class => SynsetRelationRepository::class,
        ]);
    },

    SenseRepository::class => function (ContainerInterface $c): SenseRepository {
        /** @var SenseRepository */
        return $c->get(RepositoryFactoryService::class)->getFor(Sense::class);
    },

    Translator::class => function (): Translator {
        $translator = new Translator('ru');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__ . '/locales/en.yml', 'en');
        $translator->addResource('yaml', __DIR__ . '/locales/ru.yml', 'ru');
        return $translator;
    },

    Twig::class => function (ContainerInterface $c) use ($requestCtx): Twig {
        $twig = Twig::create(__DIR__ . '/../views', [
            'cache'            => __DIR__ . '/../var/cache/twig',
            'strict_variables' => false,
        ]);
        $env = $twig->getEnvironment();

        // Per-request context: accessible in all templates as {{ app.locale }},
        // {{ app.route_name }}, {{ app.route_params }}. Mutated by locale middleware.
        $env->addGlobal('app', $requestCtx);

        // Cyrillic → Latin transliteration filter
        $tr = Transliterator::create('Cyrillic-Latin');
        $env->addFilter(new TwigFilter('translit', fn(string $s): string => $tr->transliterate($s)));

        // Translation filter: {{ 'key'|trans }} or {{ 'key'|trans({'%p%': val}) }}
        $translator = $c->get(Translator::class);
        $env->addFilter(new TwigFilter('trans',
            fn(string $id, array $params = [], ?string $domain = null): string =>
                $translator->trans($id, $params, $domain)
        ));

        // path(routeName, data) — like Symfony's path(), auto-injects _locale, delegates
        // to slim/twig-view's TwigRuntime (available after TwigMiddleware runs).
        $env->addFunction(new TwigFunction('path',
            function (string $routeName, array $data = [], array $queryParams = []) use ($env, $requestCtx): string {
                if (!isset($data['_locale'])) {
                    $data['_locale'] = $requestCtx->locale;
                }
                $data = array_map('strval', $data);
                return $env->getRuntime(\Slim\Views\TwigRuntimeExtension::class)->urlFor($routeName, $data, $queryParams);
            }
        ));

        return $twig;
    },

    SiteController::class => function (ContainerInterface $c): SiteController {
        return new SiteController(
            $c->get(Twig::class),
            $c->get(LoggerInterface::class),
            $c->get(SenseRepository::class),
        );
    },

    // Expose the shared mutable context object for use in middleware
    'requestContext' => fn() => $requestCtx,
]);

$container = $containerBuilder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();

// TwigMiddleware registers the url_for() runtime per request (must come before routes)
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));

// Routing addBodyParsing is not needed (all GETs), but routing middleware is required
$app->addRoutingMiddleware();

$debug = (bool) getenv('APP_DEBUG');
$errorMiddleware = $app->addErrorMiddleware($debug, true, true);

// Custom 404 → render the 404 template
$errorMiddleware->setErrorHandler(
    HttpNotFoundException::class,
    function ($request, $exception) use ($container): Response {
        $twig     = $container->get(Twig::class);
        $response = new Response(404);
        return $twig->render($response, 'Site/404.html.twig');
    },
    true,
);

require __DIR__ . '/routing.php';

return $app;
