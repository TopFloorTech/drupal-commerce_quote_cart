<?php

namespace Drupal\commerce_quote_cart\Plugin\StockEvents;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_stock\Plugin\StockEvents\CoreStockEvents;
use Drupal\commerce_stock\StockLocationInterface;

/**
 * Quote Cart Stock Events.
 *
 * @StockEvents(
 *   id = "quote_cart_stock_events",
 *   description = @Translation("Quote cart stock Events."),
 * )
 */
class QuoteCartStockEvents extends CoreStockEvents {
  public function stockEvent(Context $context, PurchasableEntityInterface $entity, $stockEvent, $quantity, StockLocationInterface $location, $transaction_type, array $metadata) {
    $orderId = $metadata['related_oid'];
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($orderId);

    foreach ($order->getItems() as $orderItem) {
      if (
        (float) $orderItem->getQuantity() === (float) $quantity
        && $orderItem->hasPurchasedEntity()
        && $orderItem->getPurchasedEntity()->id() === $entity->id()
      ) {
        if ($orderItem->hasField('field_quote')
          && !$orderItem->get('field_quote')->isEmpty()
          && $orderItem->get('field_quote')->value) {
          return NULL;
        }

        break;
      }
    }

    return parent::stockEvent($context, $entity, $stockEvent, $quantity, $location, $transaction_type, $metadata);
  }

}
