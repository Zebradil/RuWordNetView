<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Symfony\Component\Translation\Translator;
use Zebradil\RuWordNet\Controllers\SiteController;

/** @var \Slim\App $app */

// Root: redirect to locale-prefixed homepage
$app->get('/', function (Request $request, Response $response): Response {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $locale = $_SESSION['locale'] ?? 'ru';
    return $response->withHeader('Location', "/{$locale}")->withStatus(302);
});

// All locale-prefixed routes in a group so the locale middleware runs after routing
$app->group('/{_locale}', function (\Slim\Routing\RouteCollectorProxy $group) use ($app): void {
    $group->get('',  [SiteController::class, 'homepageAction'])->setName('homepage');
    $group->get('/', [SiteController::class, 'homepageAction']);

    $group->get('/search',              [SiteController::class, 'searchAction'])->setName('search');
    $group->get('/search/',             [SiteController::class, 'searchAction']);
    $group->get('/search/{searchString:.+}', [SiteController::class, 'searchAction']);

    // /{_locale}/sense/{name}+{meaning} — meaning is optional (defaults to 0 in controller)
    $group->get('/sense/{name:[^+]+}[+{meaning:\d+}]', [SiteController::class, 'senseAction'])->setName('sense');

    // Unknown sub-paths → 404
    $group->get('/{whatever:.*}', function (Request $request, Response $response) use ($app): Response {
        $twig = $app->getContainer()->get(Twig::class);
        return $twig->render($response->withStatus(404), 'Site/404.html.twig');
    });

})->add(function (Request $request, $handler) use ($app): Response {
    // Locale middleware: runs after route matching so RouteContext is available
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $route  = RouteContext::fromRequest($request)->getRoute();
    $locale = $route?->getArgument('_locale') ?? $_SESSION['locale'] ?? 'ru';

    $_SESSION['locale'] = $locale;

    $container = $app->getContainer();
    $container->get(Translator::class)->setLocale($locale);

    $ctx = $container->get('requestContext');
    $ctx->locale       = $locale;
    $ctx->route_name   = $route?->getName() ?? 'homepage';
    $ctx->route_params = $route?->getArguments() ?? [];

    return $handler->handle($request);
});

// Non-prefixed paths: redirect to /{locale}/{path}
$app->get('/{whatever:.*}', function (Request $request, Response $response, array $args): Response {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $locale  = $_SESSION['locale'] ?? 'ru';
    $whatever = $args['whatever'];
    return $response->withHeader('Location', "/{$locale}/{$whatever}")->withStatus(302);
});
