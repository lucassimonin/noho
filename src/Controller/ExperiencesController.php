<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/{_locale}/experiences', name: 'noho_experiences', methods: ['GET'], requirements: ['_locale' => '^[A-Za-z]{2,4}(_[A-Za-z0-9]+)*$'])]
final class ExperiencesController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('experiences/index.html.twig');
    }
}
