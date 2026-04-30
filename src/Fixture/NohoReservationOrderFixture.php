<?php

declare(strict_types=1);

namespace App\Fixture;

use App\Entity\Order\Order as AppOrder;
use App\Entity\Order\OrderItem;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

final class NohoReservationOrderFixture extends AbstractFixture
{
    private Generator $faker;

    public function __construct(
        private readonly ObjectManager $orderManager,
        #[Autowire(service: 'sylius.factory.order')]
        private readonly FactoryInterface $orderFactory,
        #[Autowire(service: 'sylius.factory.order_item')]
        private readonly FactoryInterface $orderItemFactory,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly RepositoryInterface $channelRepository,
        private readonly RepositoryInterface $customerRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly RepositoryInterface $countryRepository,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly ShippingMethodRepositoryInterface $shippingMethodRepository,
        #[Autowire(service: 'sylius.factory.address')]
        private readonly FactoryInterface $addressFactory,
        private readonly StateMachineInterface $stateMachine,
    ) {
        $this->faker = Factory::create('fr_FR');
    }

    public function getName(): string
    {
        return 'noho_reservation_order';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function load(array $options): void
    {
        if (isset($options['enabled']) && false === $options['enabled']) {
            return;
        }

        $channel = $this->channelRepository->findOneBy(['code' => 'NOHO_WEB']);
        $customer = $this->customerRepository->findOneBy(['email' => 'customer@noho-conciergerie.com']);
        $country = $this->countryRepository->findOneBy(['code' => 'FR']);

        if (!$channel instanceof ChannelInterface
            || !$customer instanceof CustomerInterface
            || !$country instanceof CountryInterface) {
            throw new \RuntimeException('NohoReservationOrderFixture requires channel NOHO_WEB, demo customer and FR country.');
        }

        $createdAt = new \DateTimeImmutable('2026-01-15 12:00:00');

        $bookings = [
            ['product' => 'villa-montmartre', 'start' => '2026-07-01', 'end' => '2026-07-08'],
            ['product' => 'appartement-marais', 'start' => '2026-08-10', 'end' => '2026-08-20'],
            ['product' => 'villa-promenade', 'start' => '2026-09-05', 'end' => '2026-09-12'],
        ];

        foreach ($bookings as $booking) {
            $order = $this->createCompletedOrder(
                $channel,
                $customer,
                $country,
                $booking['product'],
                new \DateTimeImmutable($booking['start']),
                new \DateTimeImmutable($booking['end']),
                $createdAt,
            );
            $this->orderManager->persist($order);
            // SequentialOrderNumberGenerator updates sylius_order_sequence in memory;
            // without a flush, findOneBy() cannot see a newly persisted row and will
            // create another row at index 0, producing duplicate number 000000001, etc.
            $this->orderManager->flush();
        }
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode->children()->booleanNode('enabled')->defaultTrue()->end();
    }

    private function createCompletedOrder(
        ChannelInterface $channel,
        CustomerInterface $customer,
        CountryInterface $country,
        string $productCode,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        \DateTimeInterface $createdAt,
    ): OrderInterface {
        $baseCurrency = $channel->getBaseCurrency();
        if (null === $baseCurrency) {
            throw new \RuntimeException('Channel has no base currency.');
        }
        $currencyCode = $baseCurrency->getCode();
        $locales = $channel->getLocales()->toArray();
        if ([] === $locales) {
            throw new \RuntimeException('Channel NOHO_WEB has no locales.');
        }
        $pickedLocale = $this->faker->randomElement($locales);
        if (!$pickedLocale instanceof LocaleInterface) {
            throw new \RuntimeException('Channel locale must implement LocaleInterface.');
        }
        $localeCode = $pickedLocale->getCode();

        /** @var AppOrder $order */
        $order = $this->orderFactory->createNew();
        $order->setChannel($channel);
        $order->setCustomer($customer);
        $order->setCurrencyCode($currencyCode);
        $order->setLocaleCode($localeCode);

        $product = $this->productRepository->findOneByChannelAndCode($channel, $productCode);
        Assert::isInstanceOf($product, ProductInterface::class, sprintf('Product "%s" not found for channel.', $productCode));

        $variant = $product->getVariants()->first();

        Assert::isInstanceOf($variant, ProductVariantInterface::class, sprintf('Product "%s" has no enabled variant.', $productCode));

        $item = $this->orderItemFactory->createNew();
        $item->setVariant($variant);
        $this->orderItemQuantityModifier->modify($item, 1);
        $order->addItem($item);
        $order->setReservationStartAt($start);
        $order->setReservationEndAt($end);

        $this->orderProcessor->process($order);

        $countryCode = $country->getCode();
        if (null === $countryCode || '' === $countryCode) {
            throw new \RuntimeException('Country must have a non-empty code.');
        }

        $this->address($order, $countryCode);
        $this->selectShipping($order, $channel, $createdAt);
        $this->selectPayment($order, $channel, $createdAt);
        $this->completeCheckout($order);

        if ($order->getCheckoutState() === OrderCheckoutStates::STATE_COMPLETED) {
            $order->setCheckoutCompletedAt($createdAt);
        }

        return $order;
    }

    private function address(OrderInterface $order, string $countryCode): void
    {
        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setFirstName($this->faker->firstName);
        $address->setLastName($this->faker->lastName);
        $address->setStreet($this->faker->streetAddress);
        $address->setCountryCode($countryCode);
        $address->setCity($this->faker->city);
        $address->setPostcode($this->faker->postcode);

        $order->setShippingAddress($address);
        $order->setBillingAddress(clone $address);

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);
    }

    private function selectShipping(
        OrderInterface $order,
        ChannelInterface $channel,
        \DateTimeInterface $createdAt,
    ): void {
        if ($order->getCheckoutState() === OrderCheckoutStates::STATE_SHIPPING_SKIPPED) {
            return;
        }

        $shippingMethods = $this->shippingMethodRepository->findEnabledForChannel($channel);
        if (0 === \count($shippingMethods)) {
            return;
        }

        /** @var ShippingMethodInterface $shippingMethod */
        $shippingMethod = $this->faker->randomElement($shippingMethods);

        foreach ($order->getShipments() as $shipment) {
            $shipment->setMethod($shippingMethod);
            $shipment->setCreatedAt($createdAt);
        }

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
    }

    private function selectPayment(
        OrderInterface $order,
        ChannelInterface $channel,
        \DateTimeInterface $createdAt,
    ): void {
        if ($order->getCheckoutState() === OrderCheckoutStates::STATE_PAYMENT_SKIPPED) {
            return;
        }

        $methods = $this->paymentMethodRepository->findEnabledForChannel($channel);
        if (0 === \count($methods)) {
            throw new \RuntimeException('No payment method for channel; add payment_method to fixtures.');
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->faker->randomElement($methods);

        foreach ($order->getPayments() as $payment) {
            $payment->setMethod($paymentMethod);
            $payment->setCreatedAt($createdAt);
        }

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
    }

    private function completeCheckout(OrderInterface $order): void
    {
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
    }
}
