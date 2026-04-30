<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Entity\Order\Order as AppOrder;
use App\Reservation\ReservationAvailabilityChecker;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Bundle\ShopBundle\Form\Type\AddToCartType;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AddToCartTypeExtension extends AbstractTypeExtension
{
    private const MIN_NIGHTS = 3;

    public function __construct(
        private readonly ReservationAvailabilityChecker $availabilityChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!isset($options['product']) || !$options['product'] instanceof ProductInterface) {
            return;
        }

        $notBlank = [new NotBlank(['groups' => ['sylius', 'Default']])];

        $builder
            ->add('reservationStartAt', DateType::class, [
                'mapped' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'label' => 'noho.reservation.check_in',
                'constraints' => $notBlank,
            ])
            ->add('reservationEndAt', DateType::class, [
                'mapped' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => true,
                'label' => 'noho.reservation.check_out',
                'constraints' => $notBlank,
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, $this->hydrateReservationFields(...));
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->syncReservationToOrder(...));
    }

    private function hydrateReservationFields(FormEvent $event): void
    {
        $command = $event->getData();
        if (!$command instanceof AddToCartCommandInterface) {
            return;
        }

        $order = $command->getCart();
        if (!$order instanceof AppOrder) {
            return;
        }

        $form = $event->getForm();
        if (!$form->has('reservationStartAt') || !$form->has('reservationEndAt')) {
            return;
        }

        $form->get('reservationStartAt')->setData($order->getReservationStartAt());
        $form->get('reservationEndAt')->setData($order->getReservationEndAt());
    }

    private function syncReservationToOrder(FormEvent $event): void
    {
        $form = $event->getForm();
        if (!$form->has('reservationStartAt') || !$form->has('reservationEndAt')) {
            return;
        }

        $command = $form->getData();
        if (!$command instanceof AddToCartCommandInterface) {
            return;
        }

        $order = $command->getCart();
        if (!$order instanceof AppOrder) {
            return;
        }

        if (!$form->isValid()) {
            return;
        }

        $start = $form->get('reservationStartAt')->getData();
        $end = $form->get('reservationEndAt')->getData();

        $order->setReservationStartAt($start);
        $order->setReservationEndAt($end);

        if (!$start instanceof \DateTimeInterface || !$end instanceof \DateTimeInterface) {
            return;
        }

        if ($end <= $start) {
            $form->get('reservationEndAt')->addError(new FormError('noho.reservation.period_invalid'));

            return;
        }

        $minSeconds = self::MIN_NIGHTS * 86400;
        if (($end->getTimestamp() - $start->getTimestamp()) < $minSeconds) {
            $form->get('reservationEndAt')->addError(
                new FormError('noho.reservation.min_nights', null, ['%min%' => (string) self::MIN_NIGHTS]),
            );

            return;
        }

        $variant = $command->getCartItem()->getVariant();
        if (null !== $variant && $this->availabilityChecker->hasConflict($variant, $start, $end, $order)) {
            $form->get('reservationEndAt')->addError(new FormError('noho.reservation.unavailable'));
        }
    }

    public static function getExtendedTypes(): iterable
    {
        return [AddToCartType::class];
    }
}
