<?php

namespace Drupal\social_hub;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for platform integration plugins.
 */
abstract class PlatformIntegrationPluginBase extends PluginBase implements PlatformIntegrationPluginInterface, ContainerFactoryPluginInterface {

  use PluginWithFormsTrait;

  /**
   * Constructs PlatformPluginBase instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '<!-- The ' . static::class . '::build() is not implemented. -->',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) ($this->configuration['label'] ?? $this->pluginDefinition['label']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'id' => $this->pluginId,
      'label' => $this->pluginDefinition['label'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form += [
      '#type' => 'details',
      '#title' => $this->getPluginDefinition()['label'],
      '#description' => $this->getPluginDefinition()['description'] ?? NULL,
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = NestedArray::getValue($form_state->getValues(), $form['#parents']);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

}
