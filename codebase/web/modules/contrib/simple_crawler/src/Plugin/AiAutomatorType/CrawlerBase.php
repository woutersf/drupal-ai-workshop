<?php

namespace Drupal\simple_crawler\Plugin\AiAutomatorType;

use Drupal\ai_automators\PluginBaseClasses\ExternalBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\simple_crawler\Crawler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rules for a long string field.
 */
class CrawlerBase extends ExternalBase implements ContainerFactoryPluginInterface {

  /**
   * The crawler.
   */
  public Crawler $crawler;

  /**
   * Construct a boolean field.
   *
   * @param array $configuration
   *   Inherited configuration.
   * @param string $plugin_id
   *   Inherited plugin id.
   * @param mixed $plugin_definition
   *   Inherited plugin definition.
   * @param \Drupal\simple_crawler\Crawler $crawler
   *   The crawler requester.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Crawler $crawler) {
    $this->crawler = $crawler;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_crawler.crawler')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $form_state, array $defaultvalues = []) {
    $form['automator_rate_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Rate Limit Wait'),
      '#description' => $this->t("On multifields you can add a wait between each request."),
      '#default_value' => $defaultValues['automator_rate_limit'] ?? 0,
      '#weight' => -10,
    ];

    $form['automator_user_agent'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User-Agent'),
      '#description' => $this->t("User-Agent to crawl the pages as."),
      '#default_value' => $defaultValues['automator_user_agent'] ?? '',
      '#weight' => -10,
    ];

    $form['automator_basic_auth_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basic Auth Username'),
      '#description' => $this->t("Username for basic auth, if needed."),
      '#default_value' => $defaultValues['automator_basic_auth_username'] ?? '',
      '#weight' => -10,
    ];

    $form['automator_basic_auth_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basic Auth Password'),
      '#description' => $this->t("Password for basic auth, if needed."),
      '#default_value' => $defaultValues['automator_basic_auth_password'] ?? '',
      '#weight' => -10,
    ];

    $form['automator_custom_headers'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom Headers'),
      '#description' => $this->t("Custom headers to send with the request. Do a new line separated list of headers. Example:\n 'N\nContent-Type: application/json"),
      '#default_value' => $defaultValues['automator_custom_headers'] ?? '',
      '#weight' => -10,
    ];

    $form['automator_custom_cookies'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom Cookies'),
      '#description' => $this->t("Custom cookies to send with the request. Do a new line separated list of cookies. Example:\n 'N\ncookie1=value1\ncookie2=value2"),
      '#default_value' => $defaultValues['automator_custom_cookies'] ?? '',
      '#weight' => -10,
    ];

    return $form;
  }

}
