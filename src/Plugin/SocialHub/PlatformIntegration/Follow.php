<?php

namespace Drupal\social_hub\Plugin\SocialHub\PlatformIntegration;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\social_hub\PlatformIntegrationPluginBase;

/**
 * Plugin implementation of the social_platform.
 *
 * @PlatformIntegration(
 *   id = "follow",
 *   label = @Translation("Follow"),
 *   description = @Translation("Allow platforms to be rendered as 'Follow' links.")
 * )
 *
 * @internal
 *   Plugin classes are internal.
 *
 * @phpcs:disable Drupal.Commenting.InlineComment.InvalidEndChar
 * @phpcs:disable Drupal.Commenting.PostStatementComment.Found
 */
class Follow extends PlatformIntegrationPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['platform_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Platform URL'),
      '#description' => $this->t('The platform full URL, shall include the protocol and no trailing slash. E.g. https://example.com'), // NOSONAR
      '#required' => TRUE,
      '#default_value' => $this->configuration['platform_url'],
    ];

    $form['follow_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Follow path'),
      '#description' => $this->t('The follow path in the platform, with no preceding slash. E.g. user/my_id.'), // NOSONAR
      '#required' => TRUE,
      '#default_value' => $this->configuration['follow_path'],
      '#field_suffix' => [
        '#theme' => 'token_tree_link',
        '#text' => $this->t('Tokens'),
        '#token_types' => 'all',
        '#theme_wrappers' => ['container'],
      ],
    ];

    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#description' => $this->t('The text used when rendering the link. Leave it empty to rely on CSS classes to render the link.'),
      // NOSONAR
      '#default_value' => $this->configuration['link_text'],
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
      '#default_value' => $this->configuration['link_title'],
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
      '#default_value' => $this->configuration['link_classes'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $context = []) {
    $options = ['absolute' => TRUE, 'external' => TRUE];
    $uri = sprintf('%s/%s', $this->configuration['platform_url'], $this->configuration['follow_path']);
    $build = [
      '#type' => 'link',
      '#url' => Url::fromUri($uri, $options),
      '#title' => '',
      '#attributes' => [
        'class' => [],
        'target' => '_blank',
      ],
    ];

    if (!empty($this->configuration['link_text'])) {
      $build['#title'] = $this->configuration['link_text'];
    }

    if (!empty($this->configuration['link_title'])) {
      $build['#attributes']['title'] = $this->configuration['link_title'];
    }

    if (!empty(trim($this->configuration['link_classes']))) {
      $classes = explode(' ', trim($this->configuration['link_classes']));
      $build['#attributes']['class'] = $classes;
    }

    return $build;
  }

}
