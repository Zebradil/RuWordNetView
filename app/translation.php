<?php

use Silex\Application;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Translator;
use Symfony\Component\Translation\Loader\YamlFileLoader;

// @var $app Application

$app->register(new SessionServiceProvider());

$app->register(new TranslationServiceProvider(), [
    'locale_fallbacks' => ['en'],
]);

$app['translator'] = $app->share($app->extend('translator', function (Translator $translator, Application $app) {
    $translator->addLoader('yaml', new YamlFileLoader());

    $translator->addResource('yaml', __DIR__.'/locales/en.yml', 'en');
    $translator->addResource('yaml', __DIR__.'/locales/ru.yml', 'ru');

    return $translator;
}));
