<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Form\Type\Shop\CatalogSearchType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class HomepageController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('@SyliusShop/homepage/index.html.twig', [
            'catalog_search_form' => $this->createForm(CatalogSearchType::class)->createView(),
        ]);
    }
}
