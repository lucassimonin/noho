<?php

declare(strict_types=1);

namespace App\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use App\Entity\Taxonomy\TaxonImage;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TaxonImageFixture extends AbstractFixture
{
    public function __construct(
        private TaxonRepositoryInterface $taxonRepository,
        private EntityManagerInterface $entityManager,
        private ImageUploaderInterface $imageUploader,
        private string $projectDir,
    ) {
    }

    public function getName(): string
    {
        return 'taxon_image';
    }

    public function load(array $options): void
    {
        foreach ($options['images'] as $imageData) {
            $taxon = $this->taxonRepository->findOneBy(['code' => $imageData['taxon_code']]);
            
            if (null === $taxon) {
                continue;
            }

            $imagePath = str_replace('%kernel.project_dir%', $this->projectDir, $imageData['path']);
            
            if (!file_exists($imagePath)) {
                continue;
            }

            $image = new TaxonImage();
            $image->setType($imageData['type'] ?? 'banner');
            
            $uploadedFile = new UploadedFile(
                $imagePath,
                basename($imagePath),
                mime_content_type($imagePath),
                null,
                true
            );
            
            $image->setFile($uploadedFile);
            $this->imageUploader->upload($image);
            
            $taxon->addImage($image);
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('images')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('taxon_code')->isRequired()->end()
                            ->scalarNode('path')->isRequired()->end()
                            ->scalarNode('type')->defaultValue('banner')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
