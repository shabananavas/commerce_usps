<?php

namespace Drupal\commerce_usps;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use USPS\Address;
use USPS\RatePackage;

/**
 * Class that sets the shipment details needed for the USPS request.
 *
 * @package Drupal\commerce_usps
 */
class USPSShipment implements USPSShipmentInterface {

  /**
   * The commerce shipment entity.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $commerceShipment;

  /**
   * The USPS rate package entity.
   *
   * @var \USPS\RatePackage
   */
  protected $uspsPackage;

  /**
   * Returns an initialized rate package object.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   *   A Drupal Commerce shipment entity.
   *
   * @return \USPS\RatePackage
   *   The rate package entity.
   */
  public function getPackage(ShipmentInterface $commerce_shipment) {
    $this->commerceShipment = $commerce_shipment;
    $this->uspsPackage = new RatePackage();

    $this->setService();
    $this->setShipFrom();
    $this->setShipTo();
    $this->setWeight();
    $this->setContainer();
    $this->setPackageSize();
    $this->setExtraOptions();

    return $this->uspsPackage;
  }

  /**
   * Sets the ship to for a given shipment.
   */
  protected function setShipTo() {
    $address = $this->commerceShipment->getShippingProfile()->address;
    $to_address = new Address();
    $to_address->setAddress($address->address_line1);
    $to_address->setApt($address->address_line2);
    $to_address->setCity($address->locality);
    $to_address->setState($address->administrative_area);
    $to_address->setZip5($address->postal_code);

    $this->uspsPackage->setZipDestination($address->postal_code);
  }

  /**
   * Sets the ship from for a given shipment.
   */
  protected function setShipFrom() {
    $address = $this->commerceShipment->getOrder()->getStore()->getAddress();
    $from_address = new Address();
    $from_address->setAddress($address->getAddressLine1());
    $from_address->setCity($address->getLocality());
    $from_address->setState($address->getAdministrativeArea());
    $from_address->setZip5($address->getPostalCode());
    $from_address->setZip4($address->getPostalCode());
    $from_address->setFirmName($address->getName());

    $this->uspsPackage->setZipOrigination($address->getPostalCode());
  }

  /**
   * Sets the package size.
   */
  protected function setPackageSize() {
    $this->uspsPackage->setSize(RatePackage::SIZE_REGULAR);
  }

  /**
   * Sets the package weight.
   */
  protected function setWeight() {
    $weight = $this->commerceShipment->getWeight();

    if ($weight->getNumber() > 0) {
      $ounces = $weight->convert('oz')->getNumber();

      $this->uspsPackage->setPounds(floor($ounces / 16));
      $this->uspsPackage->setOunces($ounces % 16);
    }
  }

  /**
   * Sets the services for the shipment.
   */
  protected function setService() {
    $this->uspsPackage->setService(RatePackage::SERVICE_ALL);
  }

  /**
   * Sets the package container for the shipment.
   */
  protected function setContainer() {
    $this->uspsPackage->setContainer(RatePackage::CONTAINER_VARIABLE);
  }

  /**
   * Sets any extra options specific to the shipment like ship date etc.
   */
  protected function setExtraOptions() {
    $this->uspsPackage->setField('Machinable', TRUE);
    $this->uspsPackage->setField('ShipDate', $this->getProductionDate());
  }

  /**
   * Returns the current date.
   *
   * @return string
   *   The current date, formatted.
   */
  protected function getProductionDate() {
    $date = date('Y-m-d', strtotime("now"));

    return $date;
  }

}
