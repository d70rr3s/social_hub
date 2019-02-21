<?php

namespace Drupal\social_hub;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;

/**
 * Defines the interface for platform plugins.
 */
interface PlatformIntegrationPluginInterface extends PluginWithFormsInterface, PluginFormInterface, ConfigurablePluginInterface {

  /**
   * Return the name of the platform.
   *
   * @return string
   *   The name of the platform.
   */
  public function getLabel();

  /**
   * Build the plugin output.
   *
   * @return array
   *   A render array for the output.
   */
  public function build();

}
