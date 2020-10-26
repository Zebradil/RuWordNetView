<?php

namespace Zebradil\RuWordNet\Controllers;

use Psr\Log\LoggerInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig_Environment;
use Zebradil\RuWordNet\Models\Sense;
use Zebradil\RuWordNet\Repositories\SenseRepository;

/**
 * Class SiteController.
 */
class SiteController
{
    /** @var Twig_Environment */
    private Twig_Environment $twig;
    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /**
     * SiteController constructor.
     *
     * @param Twig_Environment $twig
     * @param LoggerInterface  $logger
     */
    public function __construct(Twig_Environment $twig, LoggerInterface $logger)
    {
        $this->twig = $twig;
        $this->logger = $logger;
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     *
     * @return string
     */
    public function homepageAction(): string
    {
        return $this->twig->render('Site/homepage.html.twig');
    }

    /**
     * @param Application $app
     * @param string      $name
     * @param int         $meaning
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     *
     * @return string
     */
    public function senseAction(Application $app, string $name, int $meaning): string
    {
        $sense = $app['repository']
            ->getFor(Sense::class)
            ->find([
                'name' => mb_strtoupper($name),
                'meaning' => $meaning,
            ])
        ;

        $data = [
            'searchString' => $name,
            'senses' => $sense ? [$sense] : [],
        ];

        return $this->twig->render('Site/lexeme_summary.html.twig', $data);
    }

    /**
     * @param Request     $request
     * @param Application $app
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     *
     * @return string
     */
    public function searchAction(Request $request, Application $app): string
    {
        $searchString = $request->get('searchString');

        if (empty($searchString)) {
            $senses = [];
        } else {
            /** @var SenseRepository $senseRepository */
            $senseRepository = $app['repository']->getFor(Sense::class);
            /** @var Sense[] $senses */
            $senses = $senseRepository->getByName($searchString);

            usort($senses, function ($a, $b) {
                return $a->meaning <=> $b->meaning;
            });
        }

        $data = [
            'searchString' => $searchString,
            'senses' => $senses,
        ];

        return $this->twig->render('Site/lexeme_summary.html.twig', $data);
    }
}
