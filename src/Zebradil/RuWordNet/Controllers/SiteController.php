<?php

namespace Zebradil\RuWordNet\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use Zebradil\RuWordNet\Models\Sense;
use Zebradil\RuWordNet\Repositories\SenseRepository;

class SiteController
{
    public function __construct(
        private Twig $twig,
        private LoggerInterface $logger,
        private SenseRepository $senseRepository,
    ) {}

    public function homepageAction(Request $request, Response $response, array $args): Response
    {
        return $this->twig->render($response, 'Site/homepage.html.twig');
    }

    public function senseAction(Request $request, Response $response, array $args): Response
    {
        $name    = trim(mb_strtoupper($args['name']));
        $meaning = (int) ($args['meaning'] ?? 0);

        $sense = $this->senseRepository->find(['name' => $name, 'meaning' => $meaning]);

        return $this->twig->render($response, 'Site/lexeme_summary.html.twig', [
            'searchString' => $args['name'],
            'senses'       => $sense ? [$sense] : [],
        ]);
    }

    public function searchAction(Request $request, Response $response, array $args): Response
    {
        $searchString = trim(
            $args['searchString'] ?? ($request->getQueryParams()['searchString'] ?? '')
        );

        $senses = [];
        if ($searchString !== '') {
            $senses = $this->senseRepository->getByName($searchString);
            usort($senses, fn(Sense $a, Sense $b) => $a->meaning <=> $b->meaning);
        }

        return $this->twig->render($response, 'Site/lexeme_summary.html.twig', [
            'searchString' => $searchString,
            'senses'       => $senses,
        ]);
    }
}
