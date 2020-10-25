<?php

use Silex\Application;
use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zebradil\RuWordNet\Controllers\SiteController;
use Zebradil\RuWordNet\Models\Sense;
use Zebradil\RuWordNet\Models\SenseRelation;
use Zebradil\RuWordNet\Models\Synset;
use Zebradil\RuWordNet\Models\SynsetRelation;
use Zebradil\RuWordNet\Repositories\SenseRelationRepository;
use Zebradil\RuWordNet\Repositories\SenseRepository;
use Zebradil\RuWordNet\Repositories\SynsetRelationRepository;
use Zebradil\RuWordNet\Repositories\SynsetRepository;
use Zebradil\SilexDoctrineDbalModelRepository\RepositoryServiceProvider;

require_once __DIR__.'/../vendor/autoload.php';

$app = new Application();

$app['debug'] = false;

// logging

$app->register(new MonologServiceProvider(), [
    'monolog.logfile' => __DIR__.'/../var/log/development.log',
]);

// database
$cfg = json_decode(file_get_contents(__DIR__.'/config/database.json'), true);
if (null === $cfg
    || !isset(
        $cfg['driver'],
        $cfg['dbname'],
        $cfg['host'],
        $cfg['port'],
        $cfg['user'],
        $cfg['password']
    )
) {
    // TODO Log this
    echo 'Oops! Something wrong.';
    exit;
}
$app->register(new DoctrineServiceProvider(), [
    'db.options' => [
        'driver' => $cfg['driver'],
        'dbname' => $cfg['dbname'],
        'host' => $cfg['host'],
        'port' => $cfg['port'],
        'user' => $cfg['user'],
        'password' => $cfg['password'],
    ],
]);

if ($app['debug']) {
    $logger = new Doctrine\DBAL\Logging\DebugStack();
    $app['db.config']->setSQLLogger($logger);
    $app->error(function (Exception $e, $code) use ($app, $logger) {
        if ($e instanceof PDOException && count($logger->queries)) {
            // We want to log the query as an ERROR for PDO exceptions!
            $query = array_pop($logger->queries);
            $app['monolog']->err($query['sql'], [
                'params' => $query['params'],
                'types' => $query['types'],
            ]);
        }
    });
    $app->after(function (Request $request, Response $response) use ($app, $logger) {
        // Log all queries as DEBUG.
        foreach ($logger->queries as $query) {
            $app['monolog']->debug($query['sql'], [
                'params' => $query['params'],
                'types' => $query['types'],
            ]);
        }
    });
}

// repositories

$app->register(new RepositoryServiceProvider(), [
    'repository.repositories' => [
        Sense::class => SenseRepository::class,
        Synset::class => SynsetRepository::class,
        SenseRelation::class => SenseRelationRepository::class,
        SynsetRelation::class => SynsetRelationRepository::class,
    ],
]);

// controllers

$app->register(new ServiceControllerServiceProvider());

$app['site.controller'] = $app->share(function () use ($app) {
    return new SiteController($app['twig'], $app['monolog']);
});

// templates

$app->register(new TwigServiceProvider(), [
    'twig.path' => __DIR__.'/../views',
    'twig.strict_variables' => false,
    'twig.options' => [
        'cache' => __DIR__.'/../var/cache/twig',
    ],
]);

$app->extend('twig', function ($twig, $app) {
    $tr = Transliterator::create('Cyrillic-Latin');
    $twig->addFilter(
        'translit',
        new \Twig_Filter_Function(
            function ($string) use ($tr) {
                return $tr->transliterate($string);
            }
        )
    );

    return $twig;
});

include 'translation.php';

// URL generator

$app->register(new UrlGeneratorServiceProvider());

include 'routing.php';

return $app;
