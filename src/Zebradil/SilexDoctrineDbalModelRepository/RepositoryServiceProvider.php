<?php

namespace Zebradil\SilexDoctrineDbalModelRepository;

use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Class RepositoryServiceProvider.
 */
class RepositoryServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Application $app
     */
    public function register(Application $app)
    {
    }

    /**
     * @param Application $app
     */
    public function boot(Application $app)
    {
        $app['repository'] = $app->share(function ($app) {
            $app['db']->setFetchMode(\PDO::FETCH_ASSOC);

            return new RepositoryFactoryService($app['db'], $app['repository.repositories']);
        });
    }
}
