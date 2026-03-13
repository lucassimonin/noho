<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/{_locale}/espace-proprietaire', name: 'noho_espace_proprietaire', methods: ['GET'], requirements: ['_locale' => '^[A-Za-z]{2,4}(_[A-Za-z0-9]+)*$'])]
final class EspaceProprietaireController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('espace_proprietaire/index.html.twig');
    }
}
