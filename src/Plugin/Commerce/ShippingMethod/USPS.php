<?php

namespace Drupal\commerce_usps\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_usps\USPSRateRequestInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Provides the USPS shipping method.
 *
 * @CommerceShippingMethod(
 *  id = "usps",
 *  label = @Translation("USPS"),
 *  services = {
 *    "_1" = @translation("Priority Mail 1-Day"),
 *    "_17" = @translation("Priority Mail 1-Day Medium Flat Rate Box"),
 *    "_22" = @translation("Priority Mail 1-Day Large Flat Rate Box"),
 *    "_28" = @translation("Priority Mail 1-Day Small Flat Rate Box"),
 *    "_16" = @translation("Priority Mail 1-Day Flat Rate Envelope"),
 *    "_38" = @translation("Priority Mail 1-Day Gift Card Flat Rate Envelope"),
 *    "_44" = @translation("Priority Mail 1-Day Legal Flat Rate Envelope"),
 *    "_29" = @translation("Priority Mail 1-Day Padded Flat Rate Envelope"),
 *    "_42" = @translation("Priority Mail 1-Day Small Flat Rate Envelope"),
 *    "_40" = @translation("Priority Mail 1-Day Window Flat Rate Envelope"),
 *    "_3" = @translation("Priority Mail Express 2-Day"),
 *    "_2" = @translation("Priority Mail Express 2-Day Hold For Pickup"),
 *    "_13" = @translation("Priority Mail Express 2-Day Flat Rate Envelope"),
 *    "_27" = @translation("Priority Mail Express 2-Day Flat Rate Envelope Hold For Pickup"),
 *    "_30" = @translation("Priority Mail Express 2-Day Legal Flat Rate Envelope"),
 *    "_31" = @translation("Priority Mail Express 2-Day Legal Flat Rate Envelope Hold For Pickup"),
 *    "_62" = @translation("Priority Mail Express 2-Day Padded Flat Rate Envelope"),
 *    "_63" = @translation("Priority Mail Express 2-Day Padded Flat Rate Envelope Hold For Pickup"),
 *    "_7" = @translation("Library Mail Parcel"),
 *    "_6" = @translation("Media Mail Parcel"),
 *  }
 * )
 */
class USPS extends ShippingMethodBase {

  /**
   * The USPSRateRequest class.
   *
   * @var \Drupal\commerce_usps\USPSRateRequestInterface
   */
  protected $uspsRateService;

  /**
   * Constructs a new ShippingMethodBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\commerce_usps\USPSRateRequestInterface $usps_rate_request
   *   The rate request service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PackageTypeManagerInterface $package_type_manager,
    USPSRateRequestInterface $usps_rate_request
  ) {
    // Rewrite the service keys to be integers.
    $plugin_definition = $this->preparePluginDefinition($plugin_definition);

    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $package_type_manager
    );

    $this->uspsRateService = $usps_rate_request;
    $this->uspsRateService->setConfig($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('commerce_usps.usps_rate_request')
    );
  }

  /**
   * Prepares the service array keys to support integer values.
   *
   * See https://www.drupal.org/node/2904467 for more information.
   *
   * @TODO: Remove once core issue has been addressed.
   *
   * @param array $plugin_definition
   *   The plugin definition provided to the class.
   *
   * @return array
   *   The prepared plugin definition.
   */
  private function preparePluginDefinition(array $plugin_definition) {
    // Cache and unset the parsed plugin definitions for services.
    $services = $plugin_definition['services'];
    unset($plugin_definition['services']);

    // Loop over each service definition and redefine them with
    // integer keys that match the UPS API.
    foreach ($services as $key => $service) {
      // Remove the "_" from the service key.
      $key_trimmed = str_replace('_', '', $key);
      $plugin_definition['services'][$key_trimmed] = $service;
    }

    return $plugin_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_information' => [
        'user_id' => '',
        'password' => '',
        'mode' => 'test',
      ],
      'options' => [
        'log' => [],
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Select all services by default.
    if (empty($this->configuration['services'])) {
      $service_ids = array_keys($this->services);
      $this->configuration['services'] = array_combine($service_ids, $service_ids);
    }

    $description = $this->t('Update your USPS API information.');
    if (!$this->isConfigured()) {
      $description = $this->t('Fill in your USPS API information.');
    }
    $form['api_information'] = [
      '#type' => 'details',
      '#title' => $this->t('API information'),
      '#description' => $description,
      '#weight' => $this->isConfigured() ? 10 : -10,
      '#open' => !$this->isConfigured(),
    ];

    $form['api_information']['user_id'] = [
      '#type' => 'textfield',
      '#title' => t('User ID'),
      '#default_value' => $this->configuration['api_information']['user_id'],
      '#required' => TRUE,
    ];

    $form['api_information']['password'] = [
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#default_value' => $this->configuration['api_information']['password'],
      '#required' => TRUE,
    ];

    $form['api_information']['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#description' => $this->t('Choose whether to use test or live mode.'),
      '#options' => [
        'test' => $this->t('Test'),
        'live' => $this->t('Live'),
      ],
      '#default_value' => $this->configuration['api_information']['mode'],
    ];

    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('USPS Options'),
      '#description' => $this->t('Additional options for USPS'),
    ];

    $form['options']['log'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Log the following messages for debugging'),
      '#options' => [
        'request' => $this->t('API request messages'),
        'response' => $this->t('API response messages'),
      ],
      '#default_value' => $this->configuration['options']['log'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration['api_information']['user_id'] = $values['api_information']['user_id'];
      $this->configuration['api_information']['password'] = $values['api_information']['password'];
      $this->configuration['api_information']['mode'] = $values['api_information']['mode'];
      $this->configuration['options']['log'] = $values['options']['log'];
    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // Only attempt to collect rates if an address exists on the shipment.
    if ($shipment->getShippingProfile()->get('address')->isEmpty()) {
      return [];
    }

    return $this->uspsRateService->getRates($shipment);
  }

  /**
   * Determine if we have the minimum information to connect to USPS.
   *
   * @return bool
   *   TRUE if there is enough information to connect, FALSE otherwise.
   */
  protected function isConfigured() {
    $api_config = $this->configuration['api_information'];

    if (empty($api_config['user_id']) || empty($api_config['password'])) {
      return FALSE;
    }

    return TRUE;
  }

}

