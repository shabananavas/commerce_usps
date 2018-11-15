<?php

namespace Drupal\commerce_usps;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use USPS\Rate;
use USPS\ServiceDeliveryCalculator;

/**
 * Class USPSRateRequest.
 *
 * @package Drupal\commerce_usps
 */
class USPSRateRequest extends USPSRequest {

  /**
   * The commerce shipment entity.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $commerceShipment;

  /**
   * The configuration array from a CommerceShippingMethod.
   *
   * @var array
   */
  protected $configuration;

  /**
   * The USPS rate request API.
   *
   * @var \USPS\Rate
   */
  protected $uspsRequest;

  /**
   * Initialize the rate request.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   *   A Drupal Commerce shipment entity.
   */
  public function initRequest(ShipmentInterface $commerce_shipment) {
    $this->setShipment($commerce_shipment);

    $this->uspsRequest = new Rate(
      $this->configuration['api_information']['user_id']
    );
    $this->setMode();
  }

  /**
   * Fetch rates from the USPS API.
   */
  public function getRates() {
    // Validate a commerce shipment has been provided.
    if (empty($this->commerceShipment)) {
      throw new \Exception('Shipment not provided');
    }

    $rates = [];

    // Add each package to the request.
    foreach ($this->getPackages() as $package) {
      $this->uspsRequest->addPackage($package);
    }

    // Fetch the rates.
    $this->uspsRequest->getRate();
    $response = $this->uspsRequest->getArrayResponse();

    // Parse the rate response and create shipping rates array.
    if (!empty($response['RateV4Response']['Package']['Postage'])) {
      foreach ($response['RateV4Response']['Package']['Postage'] as $rate) {
        $price = $rate['Rate'];
        $service_code = $rate['@attributes']['CLASSID'];
        $service_name = $this->cleanServiceName($rate['MailService']);

        // Only add the rate if this service is not in the excluded list.
        if (in_array($service_code, $this->configuration['conditions']['conditions'])) {
          continue;
        }

        $shipping_service = new ShippingService(
          $service_code,
          $service_name
        );

        $rates[] = new ShippingRate(
          $service_code,
          $shipping_service,
          new Price($price, 'USD')
        );
      }
    }

    return $rates;
  }

  /**
   * Checks the delivery date of a USPS shipment.
   *
   * @return array
   *   The delivery rate response.
   */
  public function checkDeliveryDate() {
    $to_address = $this->commerce_shipment->getShippingProfile()
      ->get('address');
    $from_address = $this->commerce_shipment->getOrder()
      ->getStore()
      ->getAddress();

    // Initiate and set the username provided from usps.
    $delivery = new ServiceDeliveryCalculator($this->configuration['api_information']['user_id']);
    // Add the zip code we want to lookup the city and state.
    $delivery->addRoute(3, $from_address->getPostalCode(), $to_address->postal_code);
    // Perform the call and print out the results.
    $delivery->getServiceDeliveryCalculation();

    return $delivery->getArrayResponse();
  }

  /**
   * Utility function to translate service labels.
   *
   * @param string $serviceCode
   *   The service code.
   *
   * @return string
   *   The translated service code.
   */
  protected function translateServiceLables($serviceCode) {
    $label = '';
    if (strtolower($serviceCode) == 'parcel') {
      $label = 'ground';
    }

    return $label;
  }

  /**
   * Set the shipment for rate requests.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   *   A Drupal Commerce shipment entity.
   */
  protected function setShipment(ShipmentInterface $commerce_shipment) {
    $this->commerceShipment = $commerce_shipment;
  }

  /**
   * Set the mode to either test/live.
   */
  protected function setMode() {
    $this->uspsRequest->setTestMode($this->isTestMode());
  }

  /**
   * Get an array of USPS packages.
   *
   * @return array
   *   An array of USPS packages.
   */
  protected function getPackages() {
    // @todo: Support multiple packages.
    $shipment = new USPSShipment($this->commerceShipment);

    return [$shipment->getPackage()];
  }

  /**
   * Utility function to clean the USPS service name.
   *
   * @param string $service
   *   The service id.
   *
   * @return string
   *   The cleaned up service id.
   */
  protected function cleanServiceName($service) {
    // Remove the html encoded trademark markup since it's
    // not supported in radio labels.
    return str_replace('&lt;sup&gt;&#8482;&lt;/sup&gt;', '', $service);
  }

  /**
   * Utility function to validate a USA zip code.
   *
   * @param string $zip_code
   *   The zip code.
   *
   * @return bool
   *   Returns TRUE if the zip code was validated.
   */
  protected function validateUsaZip($zip_code) {
    return preg_match("/^([0-9]{5})(-[0-9]{4})?$/i", $zip_code);
  }

}
