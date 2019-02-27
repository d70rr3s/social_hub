<?php

namespace Drupal\social_hub\Plugin\SocialHub\PlatformIntegration;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\social_hub\PlatformIntegrationPluginBase;
use Drupal\social_hub\PlatformInterface;
use Drupal\social_hub\Utils\ChainedLibrariesResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the social_platform.
 *
 * @PlatformIntegration(
 *   id = "share",
 *   label = @Translation("Share"),
 *   description = @Translation("Allow platforms to be used to share content.")
 * )
 *
 * @internal
 *   Plugin classes are internal.
 *
 * @phpcs:disable Drupal.Commenting.InlineComment.InvalidEndChar
 * @phpcs:disable Drupal.Commenting.PostStatementComment.Found
 */
class Share extends PlatformIntegrationPluginBase {

  const SCRIPT_TYPE_NONE = '_none';

  const SCRIPT_TYPE_INLINE = 'inline';

  const SCRIPT_TYPE_LIBRARY = 'library';

  const SCRIPT_TYPE_EXTERNAL = 'external';

  /**
   * The chain-resolver for libraries.
   *
   * @var \Drupal\social_hub\Utils\ChainedLibrariesResolverInterface
   */
  private $librariesResolver;

  /**
   * Constructs Share instance.
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
   * @param \Drupal\social_hub\Utils\ChainedLibrariesResolverInterface $libraries_resolver
   *   The chain-resolver for libraries.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CurrentRouteMatch $route_match,
    AccountInterface $current_user,
    Token $token,
    ChainedLibrariesResolverInterface $libraries_resolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match, $current_user, $token);
    $this->librariesResolver = $libraries_resolver;
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
      $container->get('token'),
      $container->get('social_hub.chained_libraries_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $defaults = [
      'share_url' => NULL,
      'share_parameters' => NULL,
      'script_type' => '_none',
      'inline' => NULL,
      'library' => NULL,
      'external' => [
        'url' => NULL,
        'attributes' => [
          'async' => TRUE,
          'minified' => FALSE,
        ],
        'preprocess' => FALSE,
        'browsers' => NULL,
      ],
      'link_text' => NULL,
      'link_title' => NULL,
      'link_classes' => NULL,
    ];

    return $defaults + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['share_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Share URL'),
      '#description' => $this->t('The platform share URL without any parameters aka query-string. E.g. https://example.com/shareArticle'), // NOSONAR
      '#required' => TRUE,
      '#default_value' => $this->configuration['share_url'] ?? NULL,
    ];

    $form['share_parameters'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Share parameters'),
      '#description' => $this->t('The parameters aka query-string with the share values. E.g. u=[node:url]&p=[node:title]'), // NOSONAR
      '#required' => TRUE,
      '#default_value' => $this->configuration['share_parameters'] ?? NULL,
      '#field_suffix' => [
        '#theme' => 'token_tree_link',
        '#text' => $this->t('Tokens'),
        '#token_types' => 'all',
        '#theme_wrappers' => ['container'],
      ],
    ];

    $form['script_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Script type'),
      '#description' => $this->t('If this integration requires an JS script select the proper method to attach that script. Options marked with * are not implemented yet.'), // NOSONAR
      '#options' => [
        self::SCRIPT_TYPE_NONE => $this->t('None'),
        self::SCRIPT_TYPE_INLINE => $this->t('Inline'),
        self::SCRIPT_TYPE_LIBRARY => $this->t('Library*'),
        self::SCRIPT_TYPE_EXTERNAL => $this->t('External*'),
      ],
      '#default_value' => $this->configuration['script_type'] ?? '_none',
    ];

    $form[self::SCRIPT_TYPE_INLINE] = [
      '#type' => 'textarea',
      '#title' => $this->t('Inline script'),
      '#description' => $this->t('Enter here the script code without the script tag (it will be added by Drupal). Keep in mind that inline scripts cannot depend on libraries since we cannot assure they will be loaded when the script is being parsed by the browser.'), // NOSONAR
      '#default_value' => $this->configuration[self::SCRIPT_TYPE_INLINE] ?? NULL,
      '#field_suffix' => [
        '#theme' => 'token_tree_link',
        '#text' => $this->t('Tokens'),
        '#token_types' => 'all',
      ],
      '#states' => [
        'visible' => [
          'select[name*="script_type"]' => ['value' => self::SCRIPT_TYPE_INLINE],
        ],
        'required' => [
          'select[name*="script_type"]' => ['value' => self::SCRIPT_TYPE_INLINE],
        ],
      ],
    ];

    $form[self::SCRIPT_TYPE_LIBRARY] = [
      '#type' => 'select',
      '#title' => $this->t('Installed libraries'),
      '#description' => $this->t('Select one the libraries defined in *.libraries.yml of installed modules.'), // NOSONAR
      '#default_value' => $this->configuration[self::SCRIPT_TYPE_LIBRARY] ?? '',
      '#options' => $this->getInstalledLibraries(),
      '#empty_value' => '',
      '#states' => [
        'visible' => [
          'select[name*="script_type"]' => ['value' => self::SCRIPT_TYPE_LIBRARY],
        ],
        'required' => [
          'select[name*="script_type"]' => ['value' => self::SCRIPT_TYPE_LIBRARY],
        ],
      ],
    ];

    $form += $this->buildExternalSectionForm();

    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#description' => $this->t('The text used when rendering the link. Leave it empty to rely on CSS classes to render the link.'), // NOSONAR
      '#default_value' => $this->configuration['link_text'] ?? NULL,
      '#field_suffix' => [
        '#theme' => 'token_tree_link',
        '#text' => $this->t('Tokens'),
        '#token_types' => 'all',
        '#theme_wrappers' => ['container'],
      ],
    ];

    $form['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link title'),
      '#description' => $this->t('The text used for the title attribute which is used by screen readers. This setting should set for full accessibility support.'), // NOSONAR
      '#default_value' => $this->configuration['link_title'] ?? NULL,
      '#field_suffix' => [
        '#theme' => 'token_tree_link',
        '#text' => $this->t('Tokens'),
        '#token_types' => 'all',
        '#theme_wrappers' => ['container'],
      ],
    ];

    $form['link_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link CSS classes'),
      '#description' => $this->t('A list of space-separated CSS classes to apply to the link element. E.g. "class-1 class-2".'), // NOSONAR
      '#default_value' => $this->configuration['link_classes'] ?? NULL,
    ];

    return $form;
  }

  /**
   * Get the installed libraries.
   *
   * @return array
   *   An array of libraries keyed by library id.
   */
  private function getInstalledLibraries() {
    $options = [];

    foreach ($this->librariesResolver->resolve() as $extension) {
      $options[$extension['name']] = array_combine(array_keys($extension['libraries']), array_keys($extension['libraries']));
    }

    return $options;
  }

  /**
   * Build 'external' form section.
   *
   * @return array
   *   The form section render array.
   */
  private function buildExternalSectionForm() {
    $form = [
      self::SCRIPT_TYPE_EXTERNAL => [
        '#type' => 'fieldset',
        '#title' => $this->t('External'),
        '#states' => [
          'visible' => [
            'select[name*="script_type"]' => ['value' => self::SCRIPT_TYPE_EXTERNAL],
          ],
        ],
        '#tree' => TRUE,
      ],
    ];

    $form[self::SCRIPT_TYPE_EXTERNAL]['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The script external URL.'),
      '#default_value' => $this->configuration[self::SCRIPT_TYPE_EXTERNAL]['url'] ?? NULL,
      '#states' => [
        'required' => [
          'select[name*="script_type"]' => ['value' => self::SCRIPT_TYPE_EXTERNAL],
        ],
      ],
    ];

    $form[self::SCRIPT_TYPE_EXTERNAL]['attributes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Attributes'),
      '#tree' => TRUE,
    ];

    $form[self::SCRIPT_TYPE_EXTERNAL]['attributes']['async'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load asynchronously'),
      '#description' => $this->t("Check 'Yes' if you want the script to be loaded after all other script are loaded."),
      '#options' => [
        $this->t('No'),
        $this->t('Yes'),
      ],
      '#default_value' => (bool) $this->configuration[self::SCRIPT_TYPE_EXTERNAL]['attributes']['async'],
    ];

    $form[self::SCRIPT_TYPE_EXTERNAL]['attributes']['minified'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Minified'),
      '#description' => $this->t("Check 'Yes' if the script is already minified by the external source."),
      '#options' => [
        $this->t('No'),
        $this->t('Yes'),
      ],
      '#default_value' => (bool) $this->configuration[self::SCRIPT_TYPE_EXTERNAL]['attributes']['minified'],
    ];

    $form[self::SCRIPT_TYPE_EXTERNAL]['preprocess'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preprocess'),
      '#description' => $this->t("Check 'Yes' you want Drupal to preprocess this script before embed it on the page."),
      '#options' => [
        $this->t('No'),
        $this->t('Yes'),
      ],
      '#default_value' => (bool) $this->configuration[self::SCRIPT_TYPE_EXTERNAL]['preprocess'],
    ];

    $form[self::SCRIPT_TYPE_EXTERNAL]['browsers'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Supported browsers'),
      '#description' => $this->t('Type key/values text separated by colons and commas. E.g.: IE:lte IE 9,!IE:false'),
      '#default_value' => $this->configuration[self::SCRIPT_TYPE_EXTERNAL]['browsers'] ?? NULL,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->cleanValues($form, $form_state);
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['share_url'] = trim($this->configuration['share_url'], "? \t\n\r\0\x0B");
    $this->configuration['share_parameters'] = trim($this->configuration['share_parameters'], "? \t\n\r\0\x0B");
    // Force libraries cache to be rebuild
    Cache::invalidateTags(['library_info']);
  }

  /**
   * Clean submitted values.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function cleanValues(array $form, FormStateInterface $form_state) {
    $configuration = NestedArray::getValue($form_state->getValues(), $form['#parents']);

    switch ($configuration['script_type']) {
      case self::SCRIPT_TYPE_INLINE:
        unset($configuration[self::SCRIPT_TYPE_EXTERNAL], $configuration[self::SCRIPT_TYPE_LIBRARY]);
        break;

      case self::SCRIPT_TYPE_LIBRARY:
        unset($configuration[self::SCRIPT_TYPE_EXTERNAL], $configuration[self::SCRIPT_TYPE_INLINE]);
        break;

      case self::SCRIPT_TYPE_EXTERNAL:
        unset($configuration[self::SCRIPT_TYPE_INLINE], $configuration[self::SCRIPT_TYPE_LIBRARY]);
        break;

      default:
        unset(
          $configuration[self::SCRIPT_TYPE_INLINE],
          $configuration[self::SCRIPT_TYPE_EXTERNAL],
          $configuration[self::SCRIPT_TYPE_LIBRARY]
        );
    }

    $values = $form_state->getValues();
    NestedArray::setValue($values, $form['#parents'], $configuration + $this->defaultConfiguration());
    $form_state->setValues($values);
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $context = []) {
    $context = $this->prepareContext($context);
    /** @var \Drupal\social_hub\PlatformInterface $platform */
    $platform = $context['platform'] ?? NULL;
    $this->metadata = new BubbleableMetadata();
    $options = [
      'absolute' => TRUE,
      self::SCRIPT_TYPE_EXTERNAL => TRUE,
      'query' => [],
    ];

    parse_str($this->token->replace($this->configuration['share_parameters'], $context, [], $this->metadata), $options['query']);

    $build = [
      '#type' => 'link',
      '#url' => Url::fromUri($this->configuration['share_url'], $options),
      '#title' => '',
      '#attributes' => [
        'class' => [],
        'target' => '_blank',
      ],
    ];

    if (!empty($this->configuration['link_text'])) {
      $build['#title'] = $this->token->replace($this->configuration['link_text'], $context, [], $this->metadata);
    }

    if (!empty($this->configuration['link_title'])) {
      $build['#attributes']['title'] = $this->token->replace($this->configuration['link_title'], $context, [], $this->metadata);
    }

    if (!empty(trim($this->configuration['link_classes']))) {
      $classes = explode(' ', trim($this->configuration['link_classes']));
      $build['#attributes']['class'] = $classes;
    }

    switch ($this->configuration['script_type']) {
      case self::SCRIPT_TYPE_INLINE:
        $build = array_merge($build, [
          '#type' => 'html_tag',
          '#tag' => 'script',
          '#value' => $this->token->replace($this->configuration[self::SCRIPT_TYPE_INLINE], $context, [], $this->metadata),
          '#attributes' => [
            'type' => 'text/javascript',
          ],
        ]);
        break;

      case self::SCRIPT_TYPE_LIBRARY:
        $this->metadata->addAttachments([
          'library' => [$this->configuration[self::SCRIPT_TYPE_LIBRARY]],
        ]);
        break;

      case self::SCRIPT_TYPE_EXTERNAL:
        if ($platform instanceof PlatformInterface) {
          $this->metadata->addAttachments([
            'library' => ["social_hub/{$platform->id()}_share"],
          ]);
        }
        break;

      default:
    }

    $this->metadata->applyTo($build);

    return $build;
  }

}
