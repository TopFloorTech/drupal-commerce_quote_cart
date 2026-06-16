<?php

namespace Drupal\commerce_quote_cart;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use Drupal\Core\Entity\EntityStorageException;

class QuoteCartHelper {

  /**
   * Quote field constant.
   *
   * @var string
   */
  const QUOTE_FIELD = 'field_quote';

  /**
   * If there is a quote cart.
   *
   * @return bool
   */
  public static function hasQuoteCart(): bool {
    /** @var \Drupal\commerce_cart\CartProvider $cartProvider */
    $cartProvider = \Drupal::getContainer()->get('commerce_cart.cart_provider');
    $carts = $cartProvider->getCarts();
    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      // There is a chance the cart may have converted from a draft order, but
      // is still in session. Such as just completing check out. So we verify
      // that the cart is still a cart.
      return $cart->hasItems() && $cart->cart->value;
    });

    $hasQuoteCart = FALSE;

    /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
    foreach ($carts as $cart) {
      if (self::isQuoteCart($cart)) {
        $hasQuoteCart = TRUE;
        break;
      }
    }

    return $hasQuoteCart;
  }

  public static function getCurrentCart() {
    /** @var \Drupal\commerce_cart\CartProviderInterface $cartProvider */
    $cartProvider = \Drupal::service('commerce_cart.cart_provider');

    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $carts */
    $carts = $cartProvider->getCarts();
    $carts = array_filter($carts, function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      // There is a chance the cart may have converted from a draft order, but
      // is still in session. Such as just completing check out. So we verify
      // that the cart is still a cart.
      return $cart->hasItems() && $cart->cart->value;
    });

    return $carts;
  }

  /**
   * If the order is a mixed cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface|NULL $cartOrder
   *
   * @return bool
   */
  public static function isMixedCart(?OrderInterface $cartOrder = NULL): bool {
    return self::isQuoteCart($cartOrder) && self::isPurchaseCart($cartOrder);
  }

  public static function isPurchaseCart(OrderInterface $cartOrder = NULL) {
    if (is_null($cartOrder)) {
      $purchaseCart = FALSE;

      foreach (self::getCurrentCart() as $cart) {
        if (self::isPurchaseCart($cart)) {
          $purchaseCart = TRUE;
        }
      }

      return $purchaseCart;
    }

    $isPurchaseCart = FALSE;

    /** @var OrderItemInterface $item */
    foreach ($cartOrder->getItems() as $item) {
      if (!$item->hasField(self::QUOTE_FIELD)
        || !$item->get(self::QUOTE_FIELD)->value) {
        $isPurchaseCart = TRUE;
        break;
      }
    }

    return $isPurchaseCart;
  }

  public static function isQuoteCart(?OrderInterface $cartOrder = NULL) {
    if (is_null($cartOrder)) {
      $quoteCart = FALSE;

      foreach (self::getCurrentCart() as $cart) {
        if (self::isQuoteCart($cart)) {
          $quoteCart = TRUE;
        }
      }

      return $quoteCart;
    }

    $isQuoteCart = FALSE;

    foreach ($cartOrder->getItems() as $item) {
      if ($item->hasField(self::QUOTE_FIELD) && $item->get(self::QUOTE_FIELD)->value) {
        $isQuoteCart = TRUE;
        break;
      }
    }

    return $isQuoteCart;
  }

  /**
   * Convert to a quote.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $cartOrder
   *   The order / cart.
   *
   * @throws EntityStorageException
   */
  public static function convertToQuote(OrderInterface $cartOrder) {
    $save = FALSE;

    foreach ($cartOrder->getItems() as $item) {
      if (!$item->hasField(self::QUOTE_FIELD)) {
        continue;
      }

      if (!$item->get(self::QUOTE_FIELD)->value) {
        self::convertItemToQuote($item);

        $save = TRUE;
      }
    }

    if ($save) {
      $cartOrder->save();
    }
  }

  /**
   * Tests for a quote item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $orderItem
   *
   * @return bool
   */
  public static function isQuoteItem(OrderItemInterface $orderItem): bool {
    return ($orderItem->hasField(self::QUOTE_FIELD) && $orderItem->get(self::QUOTE_FIELD)->value);
  }

  /**
   * Convert item to quote.
   *
   * @param OrderItemInterface $item
   * @throws EntityStorageException
   */
  public static function convertItemToQuote(OrderItemInterface $item) {
    if (!self::isQuoteItem($item)) {
      $field = $item->get(self::QUOTE_FIELD);
      $field->value = TRUE;
      $item->save();
    }
  }

  /**
   * Filters shipping methods.
   *
   * @param array $shippingMethods
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *
   * @return array
   */
  public static function filterShippingMethods(array $shippingMethods, ShipmentInterface $shipment): array {
    $quoteMethodName = 'Quote';
    $quote = !self::isPurchaseCart($shipment->getOrder());

    return array_filter($shippingMethods, function (ShippingMethodInterface $shippingMethod) use ($quote, $quoteMethodName) {
      return $quote
        ? ($shippingMethod->getName() === $quoteMethodName)
        : ($shippingMethod->getName() !== $quoteMethodName);
    });
  }

}
