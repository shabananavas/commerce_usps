<?php

namespace Drupal\commerce_usps\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_usps\USPSRateRequest;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * @CommerceShippingMethod(
 *  id = "usps",
 *  label = @Translation("USPS"),
 * )
 */
class USPS extends ShippingMethodBase {

  /**
   * The USPSRateRequest class.
   *
   * @var \Drupal\commerce_usps\USPSRateRequest
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
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $packageTypeManager
   *   The package type manager.
   * @param \Drupal\commerce_usps\USPSRateRequest $usps_rate_request
   *   The rate request service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PackageTypeManagerInterface $packageTypeManager,
    USPSRateRequest $usps_rate_request
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $packageTypeManager
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
      'conditions' => [
        'conditions' => [],
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['api_information'] = [
      '#type' => 'details',
      '#title' => $this->t('API information'),
      '#description' => $this->isConfigured()
      ? $this->t('Update your USPS API information.')
      : $this->t('Fill in your USPS API information.'),
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
      '#description' => $this->t('Choose whether to use the test or live mode.'),
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

    $form['conditions'] = [
      '#type' => 'details',
      '#title' => $this->t('USPS rate conditions'),
    ];

    $form['conditions']['conditions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude USPS Rates'),
      '#description' => $this->t('Set which USPS Rates should be excluded.'),
      '#options' => [
        'domestic' => $this->t('Domestic Shipment to Lower 48 States'),
        'domestic_plus' => $this->t('Domestic Shipment to Alaska & Hawaii'),
        'domestic_mil' => $this->t('Miliary State Codes: AP, AA, AE'),
        'international_ca' => $this->t('International Shipment to Canada'),
        'international_eu' => $this->t('International Shipment to Europe'),
        'international_as' => $this->t('International Shipment to Asia'),
      ],
      '#default_value' => $this->configuration['conditions']['conditions'],
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
      $this->configuration['conditions']['conditions'] = $values['conditions']['conditions'];

      // This is in ShippingMethodBase but it's not run because we are not
      // using 'services'.
      $this->configuration['default_package_type'] = $values['default_package_type'];
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

    $this->uspsRateService->initRequest($shipment);

    return $this->uspsRateService->getRates();
  }

  /**
   * Determine if we have the minimum information to connect to USPS.
   *
   * @return bool
   *   TRUE if there is enough information to connect, FALSE otherwise.
   */
  protected function isConfigured() {
    $api_information = $this->configuration['api_information'];

    return (
      !empty($api_information['user_id'])
      && !empty($api_information['password'])
    );
  }

}

