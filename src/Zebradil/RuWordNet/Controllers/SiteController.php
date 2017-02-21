<?php

namespace Zebradil\RuWordNet\Controllers;

use Psr\Log\LoggerInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Twig_Environment;
use Zebradil\RuWordNet\Models\Sense;

/**
 * Class SiteController.
 */
class SiteController
{
    /** @var Twig_Environment */
    private $twig;
    /** @var LoggerInterface */
    private $logger;

    /**
     * SiteController constructor.
     *
     * @param Twig_Environment $twig
     * @param LoggerInterface $logger
     */
    public function __construct(Twig_Environment $twig, LoggerInterface $logger)
    {
        $this->twig = $twig;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function homepageAction()
    {
        return $this->twig->render('Site/homepage.html.twig');
    }

    /**
     * @param Application $app
     * @param string $name
     * @param int $meaning
     *
     * @return string
     */
    public function senseAction(Application $app, string $name, int $meaning)
    {
        $sense = $app['repository']
            ->getFor(Sense::class)
            ->find([
                'name' => mb_strtoupper($name),
                'meaning' => $meaning
            ]);

        $data = [
            'sense' => $sense,
        ];

        return $this->twig->render('Site/sense.html.twig', $data);
    }

    public function searchAction(Request $request, Application $app)
    {
        $searchString = $request->get('searchString');
        $senses = $app['repository']
            ->getFor(Sense::class)
            ->getByName($searchString);

        usort($senses, function ($a, $b) {
            return $a->meaning <=> $b->meaning;
        });

        $data = [
            'searchString' => $searchString,
            'senses' => $senses,
        ];

        return $this->twig->render('Site/lexeme_summary.html.twig', $data);
    }
}
