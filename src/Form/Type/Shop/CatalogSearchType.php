<?php

declare(strict_types=1);

namespace App\Form\Type\Shop;

use App\Shop\CatalogSearch\DestinationTaxonProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
final class CatalogSearchType extends AbstractType
{
    public function __construct(
        private readonly DestinationTaxonProvider $destinationTaxonProvider,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $travelerChoices = [
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4',
            '5' => '5',
            '6+' => '6+',
        ];

        $builder
            ->add('taxonSlug', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'noho.search.all_destinations',
                'choices' => $this->destinationTaxonProvider->getDestinationChoices(),
                'label' => false,
            ])
            ->add('travelers', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'noho.search.travelers_count',
                'choices' => $travelerChoices,
                'label' => false,
            ])
            ->add('bedrooms', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'noho.search.rooms_count',
                'choices' => $travelerChoices,
                'label' => false,
            ])
            ->add('dateStart', HiddenType::class, [
                'required' => false,
                'attr' => ['id' => 'homeArrivalDate'],
            ])
            ->add('dateEnd', HiddenType::class, [
                'required' => false,
                'attr' => ['id' => 'homeDepartureDate'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'noho_catalog_search',
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'catalog_search';
    }
}
