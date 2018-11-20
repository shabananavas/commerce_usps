<?php

namespace Drupal\commerce_usps;

use Drupal\commerce_shipping\Entity\ShipmentInterface;

/**
 * Interface to create and return a USPS API shipment object.
 *
 * @package Drupal\commerce_usps
 */
interface USPSShipmentInterface {

  /**
   * Returns an initialized rate package object.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   *   A Drupal Commerce shipment entity.
   *
   * @return \USPS\RatePackage
   *   The rate package entity.
   */
  public function getPackage(ShipmentInterface $commerce_shipment);

}
