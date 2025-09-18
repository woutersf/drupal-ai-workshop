<?php

namespace Drupal\simple_crawler\Plugin\AiAutomatorType;

use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\ExternalBase;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\simple_crawler\Crawler;
use Drupal\simple_crawler\CrawlerHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The rule to get links from a link.
 */
#[AiAutomatorType(
  id: 'simple_crawler_link',
  label: new TranslatableMarkup('Simple Crawler: Deep Link Crawler'),
  field_rule: 'link',
  target: '',
)]
class LinkCrawler extends ExternalBase implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * Simple Crawler.
   */
  private Crawler $crawler;

  /**
   * The Crawler Helper.
   */
  private CrawlerHelper $crawlerHelper;

  /**
   * The links found so far, so it doesn't rerun links.
   */
  private array $foundLinks = [];

  /**
   * We need the Interpolator config globally.
   */
  private array $automatorConfig;

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
   *   The Crawler requester.
   * @param \Drupal\simple_crawler\CrawlerHelper $crawlerHelper
   *   The Crawler Helper.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Crawler $crawler, CrawlerHelper $crawlerHelper) {
    $this->crawler = $crawler;
    $this->crawlerHelper = $crawlerHelper;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_crawler.crawler'),
      $container->get('simple_crawler.crawler_helper')
    );
  }

  /**
   * {@inheritDoc}
   */
  public $title = 'Simple Crawler: Deep Link Crawler';

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return ['link'];
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form['automator_depth'] = [
      '#type' => 'number',
      '#title' => $this->t('Depth'),
      '#description' => $this->t('How many levels deep should the crawler go.'),
      '#default_value' => $defaultValues['automator_depth'] ?? 1,
      '#weight' => -20,
    ];

    $form['automator_include_source_url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include Source URL'),
      '#description' => $this->t('Include the source URL as one of the links.'),
      '#default_value' => $defaultValues['automator_include_source_url'] ?? TRUE,
      '#weight' => -19,
    ];

    $form['automator_host_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Host Only'),
      '#description' => $this->t('Only crawl the host of the base link. DO NOT UNCHECK THIS UNLESS YOU KNOW WHAT YOU ARE DOING.'),
      '#default_value' => $defaultValues['automator_host_only'] ?? TRUE,
      '#weight' => -19,
    ];

    $form['automator_body_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Body Only'),
      '#description' => $this->t('Only crawl the body of the base link.'),
      '#default_value' => $defaultValues['automator_body_only'] ?? TRUE,
      '#weight' => -19,
    ];

    $defaultPages = [
      'privacy',
      'privacy-policy',
      'privacy_policy',
      'terms',
      'terms-of-service',
      'terms_of_service',
      'terms-and-conditions',
      'terms_and_conditions',
      'disclaimers',
      'disclaimer',
      'cookies',
      'cookie-policy',
      'cookie_policy',
      'login',
      'register',
    ];
    $form['automator_exclude_pages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude Pages'),
      '#description' => $this->t('Comma separated list of pages to exclude.'),
      '#default_value' => $defaultValues['automator_exclude_pages'] ?? implode(', ', $defaultPages),
      '#weight' => -19,
    ];

    $form['automator_include_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Include Pattern'),
      '#description' => $this->t('Only include links that match this regex pattern. Leave empty to include all.'),
      '#attributes' => [
        'placeholder' => '\/documentation\/.*',
      ],
      '#default_value' => $defaultValues['automator_include_pattern'] ?? '',
      '#weight' => -19,
    ];

    $form['automator_exclude_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exclude Pattern'),
      '#description' => $this->t('Exclude links that match this regex pattern. Leave empty to exclude none.'),
      '#attributes' => [
        'placeholder' => '(?!privacy|terms|disclaimers|cookies|login|register).*',
      ],
      '#default_value' => $defaultValues['automator_exclude_pattern'] ?? '',
      '#weight' => -19,
    ];

    $form['automator_types_to_scrape'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Types to Scrape'),
      '#description' => $this->t('What types of links should be scraped.'),
      '#options' => [
        'webpages' => 'Webpages (/, html, htm, asp, php)',
        'images' => 'LINKED Images (jpg, jpeg, png, gif)',
        'pdfs' => 'PDFs',
        'docs' => 'Docs (doc, docx)',
        'videos' => 'Videos (mp4, avi, mov)',
        'audios' => 'Audios (mp3, wav)',
        'archives' => 'Archives (zip, rar, 7z)',
        'scripts' => 'Scripts (js, css)',
        'others' => 'Others',
      ],
      '#default_value' => $defaultValues['automator_types_to_scrape'] ?? ['webpages'],
      '#weight' => -18,
    ];

    $form['automator_cool_down'] = [
      '#type' => 'number',
      '#title' => $this->t('Cool Down'),
      '#description' => $this->t('How many milliseconds to wait between each request. Don\'t take down websites by spamming them.'),
      '#default_value' => $defaultValues['automator_cool_down'] ?? 500,
      '#weight' => -11,
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

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Set the config.
    $this->automatorConfig = $automatorConfig;

    // Take all input links.
    foreach ($entity->{$automatorConfig['base_field']} as $link) {
      // A link is found.
      if (!empty($link->uri)) {
        // If its batch mode.
        if ($automatorConfig['worker_type'] == 'batch') {
          $batch = \batch_get();
          $batch['operations'][] = [
            'Drupal\simple_crawler\Batch\LinkCrawler::startCrawl',
            [$entity, $link->uri, $automatorConfig, $fieldDefinition],
          ];
        } else {
          $this->scrapeLink($link->uri, $automatorConfig['depth']);
        }
      }
    }
    if ($automatorConfig['worker_type'] == 'batch' && !empty($batch)) {
      \batch_set($batch);
      return [];
    } else {
      return $this->foundLinks;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Has to have a link an be valid.
    if (empty($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
      return FALSE;
    }

    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $config = $fieldDefinition->getConfig($entity->bundle())->getSettings();
    foreach ($values as $key => $value) {
      $new['uri'] = $value;
      if ($config['title'] == 0) {
        $new['title'] = '';
      }
      $values[$key] = $new;
    }
    $entity->set($fieldDefinition->getName(), $values);
  }

  /**
   * Recursive function to scrape links.
   *
   * @param string $link
   *   The link to scrape.
   * @param int $depth
   *   The current depth.
   */
  private function scrapeLink($link, $depth) {
    if (!empty($this->automatorConfig['cool_down'])) {
      // Milliseconds.
      usleep($this->automatorConfig['cool_down'] * 1000);
    }
    // If we have already scraped this link, return.
    if (in_array($link, $this->foundLinks)) {
      return;
    }
    // Scrape the link.
    $rawHtml = $this->crawler->request($link, $this->automatorConfig);
    $rawHtml = mb_convert_encoding((string) $rawHtml, 'utf-8', 'utf-8');

    // If we are at the end, return.
    if ($depth == 0) {
      return;
    }
    // If its wanted to just do inside the body, we get the body only using regex.
    if ($this->automatorConfig['body_only']) {
      preg_match('/<body[^>]*>(.*?)<\/body>/is', $rawHtml, $body);
      if (!empty($body[1])) {
        $rawHtml = $body[1];
      }
    }
    // Parse the html, collecting links starting with http* or / using regex.
    preg_match_all('/href=["\']?([^"\'>]+)["\']?/', $rawHtml, $matches);
    if (!empty($matches[1])) {
      $links = $matches[1];
      $links = $this->crawlerHelper->cleanLinks($links, $link, $this->automatorConfig);

      // If we have links and they fit the html part, scrape them.
      $formats = $this->crawlerHelper->getFormats($this->automatorConfig);
      foreach ($links as $link) {
        // Get the extension if it has one.
        $extension = pathinfo($link, PATHINFO_EXTENSION);
        // Check if we should save the link.
        if (in_array($extension, $formats) && !in_array($link, $this->foundLinks)) {
          $this->foundLinks[] = $link;
        }
        // If it has no extension or if it is a web page, we scrape it.
        if (in_array($extension, ['html', 'htm', 'asp', 'php']) || empty($extension)) {
          $this->scrapeLink($link, $depth - 1);
        }
      }
    }
  }

}
