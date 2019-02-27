<?php

namespace Drupal\social_hub;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for platform integration plugins.
 *
 * @phpcs:disable Drupal.Commenting.InlineComment.InvalidEndChar
 * @phpcs:disable Drupal.Commenting.PostStatementComment.Found
 */
abstract class PlatformIntegrationPluginBase extends PluginBase implements
    PlatformIntegrationPluginInterface,
    ContainerFactoryPluginInterface {

  use PluginWithFormsTrait;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The render metadata.
   *
   * @var \Drupal\Core\Render\BubbleableMetadata
   */
  protected $metadata;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs PlatformPluginBase instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The current matched route.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CurrentRouteMatch $route_match,
    AccountInterface $current_user,
    Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('token')
    );
  }

  /**
   * Prepare context.
   *
   * @param array $context
   *   The context array.
   *
   * @return array
   *   The context array.
   */
  protected function prepareContext(array $context = []) {
    if (!isset($context['entity']) ||
      !($context['entity'] instanceof EntityInterface)) {
      $route = $this->routeMatch->getRouteObject();

      if ($route !== NULL) {
        $parameters = $route->getOption('parameters');

        if (!empty($parameters)) {
          // Determine if the current route represents an entity.
          foreach ($parameters as $name => $options) {
            if (isset($options['type']) && strpos($options['type'], 'entity:') === 0) {
              $entity = $this->routeMatch->getParameter($name);
              if ($entity instanceof ContentEntityInterface && $entity->hasLinkTemplate('canonical')) {
                $context[$entity->getEntityTypeId()] = $entity;
              }
            }
          }
        }
      }
    }

    if (!isset($context['user']) ||
      !($context['user'] instanceof AccountInterface)) {
      $context['user'] = $this->currentUser;
    }

    return $context;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $context = []) {
    $this->prepareContext($context);

    return [
      '#markup' => '<!-- The ' . static::class . '::build() is not implemented. Called from ' . $context['platform']->id() . ' platform. -->', // NOSONAR
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
