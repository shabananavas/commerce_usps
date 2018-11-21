Commerce USPS
=================

Provides USPS shipping rates for Drupal Commerce.

## Development Setup

1. Clone this repository in to Drupal modules folder.

2. Manually get dependencies to installed Drupal.

`composer require vinceg/usps-php-api:~1.0`

3. Enable module.

4. Go to /admin/commerce/config/shipping-methods/add:
  - Select 'USPS' as the Plugin
  - Enter the USPS API details
  - Select a default package type
  - Select all the shipping services that should be disabled
  - Fill out any of the optional configs and save configuration
