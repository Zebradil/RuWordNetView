<?php

use Silex\Application;

/** @var $app Application */

$app->get('/', function () use ($app) {
    $locale = $app['session']->get('locale') ?? 'ru';
    return $app->redirect("/$locale");
});
$app->get('/{_locale}/', "site.controller:homepageAction")->bind('homepage');
$app->get('/{_locale}/search', "site.controller:searchAction")->bind('search');
$app->get('/{_locale}/search/{searchString}', "site.controller:searchAction");
$app->get('/{_locale}/sense/{name}/{meaning}', "site.controller:senseAction")
    ->value('meaning', 0)
    ->bind('sense');
