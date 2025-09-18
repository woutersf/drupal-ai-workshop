<?php

namespace Drupal\ai_provider_litellm\Form;

use Drupal\ai_provider_litellm\LiteLLM\LiteLlmAiClient;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_provider_openai\OpenAiHelper;
use Drupal\Core\Utility\Error;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure LiteLLM proxy access.
 */
class LiteLlmAiConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_provider_litellm.settings';

  /**
   * Constructs a new LiteLlmAiConfigForm object.
   */
  public function __construct(
    protected AiProviderPluginManager $aiProviderManager,
    protected KeyRepositoryInterface $keyRepository,
    protected OpenAiHelper $openAiHelper,
    protected Client $client,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('key.repository'),
      $container->get('ai_provider_openai.helper'),
      $container->get('http_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'litellm_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('LiteLLM API Key'),
      '#description' => $this->t('The API Key.'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host'),
      '#description' => $this->t('The base URL that API requests should be made against.'),
      '#default_value' => $config->get('host') ?? '',
    ];

    $form['moderation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable moderation'),
      '#description' => $this->t('If OpenAI compatible moderation is not enabled, you should have another form of moderation enabled in LiteLLM.'),
      '#default_value' => (bool) $config->get('moderation'),
    ];

    $host = $form_state->getValue('host') ?? $config->get('host') ?? NULL;
    $api_key = $form_state->getValue('api_key') ?? $config->get('api_key') ?? NULL;
    if ($host && $api_key) {
      $form['key_details'] = [
        '#type' => 'details',
        '#weight' => 1000,
        '#title' => $this->t('Key details'),
        '#open' => TRUE,
        'table' => [
          '#theme' => 'table',
          '#rows' => [],
        ],
      ];

      try {
        $client = new LiteLlmAiClient($this->client, $this->keyRepository, $host, $api_key);
        $keys = $client->keyInfo();
        $key_info = reset($keys);
        if ($key_info) {
          if ($key_info->info->key_alias) {
            $form['key_details']['table']['#rows'][] = [
              $this->t('Name'),
              $key_info->info->key_alias,
            ];
          }

          $form['key_details']['table']['#rows'][] = [
            $this->t('Key'),
            $key_info->info->key_name,
          ];

          $form['key_details']['table']['#rows'][] = [
            $this->t('Spend ($)'),
            number_format($key_info->info->spend, 5),
          ];

          $form['key_details']['table']['#rows'][] = [
            $this->t('Max budget ($)'),
            $key_info->info->max_budget === NULL ? $this->t('N/A') : number_format($key_info->info->max_budget, 2),
          ];

          $form['key_details']['table']['#rows'][] = [
            $this->t('Blocked'),
            $key_info->info->blocked ? $this->t('Yes') : $this->t('No'),
          ];

          foreach ($form['key_details']['table']['#rows'] as &$row) {
            $row[0] = [
              'data' => ['#markup' => $row[0]],
              'header' => TRUE,
            ];
          }
        }
      }
      catch (GuzzleException $e) {
        Error::logException(
          $this->logger('ai_provider_litellm'),
          $e,
        );
        $response = json_decode($e->getResponse()->getBody()->getContents());
        if ($e->getCode() === 400 && $response?->error?->type === 'budget_exceeded') {
          $this->messenger()->addError($response?->error?->message ?? $this->t('You have exceeded your budget.'));
        }
        elseif ($e->getCode() === 401) {
          $this->messenger()->addError($this->t('Invalid API key.'));
        }
        else {
          $this->messenger()->addError($this->t('Unable to retrieve key information.'));
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_NAME);

    // Validate the API key against model listing.
    $key = $form_state->getValue('api_key');
    if (empty($key)) {
      $form_state->setErrorByName('api_key', $this->t('The API Key is required.'));
      return;
    }
    $api_key = $this->keyRepository->getKey($key)->getKeyValue();
    if (!$api_key) {
      $form_state->setErrorByName('api_key', $this->t('The API Key is invalid.'));
      return;
    }

    // Validate the host.
    $host = $form_state->getValue('host');
    if (empty($host)) {
      $form_state->setErrorByName('host', $this->t('The host is required.'));
      return;
    }
    if (!filter_var($host, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('host', $this->t('The host is invalid.'));
      return;
    }
    if (str_ends_with($host, '/')) {
      $form_state->setErrorByName('host', $this->t('The host must not end with a trailing slash.'));
      return;
    }

    // Make a call to the API to validate the API key.
    $client = new LiteLlmAiClient(
      $this->client,
      $this->keyRepository,
      $host,
      $key,
      $config->get('moderation') ?? TRUE,
    );
    try {
      if (empty($client->models())) {
        $this->logger('ai_provider_litellm')->error('Connected to LiteLLM API but there were no models in the response.',);
        $form_state->setErrorByName('api_key', $this->t('The API Key is not working.'));
      }
    }
    catch (\Exception $e) {
      if (
        $e instanceof BadResponseException
        && $e->getCode() === 500
      ) {
        $body = json_decode($e->getResponse()->getBody()->getContents());
        if (str_starts_with($body?->detail?->error ?? '', 'LLM Model List not loaded in.')) {
          $this->messenger()->addWarning($this->t('LiteLLM Model List not loaded - unable to retrieve dynamic model list.'));
          return;
        }
      }

      $this->logger('ai_provider_litellm')->error('Error connecting to LiteLLM API: @error', ['@error' => $e->getMessage()]);
      $form_state->setErrorByName('api_key', $this->t('The API Key is not working.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('host', $form_state->getValue('host'))
      ->set('moderation', $form_state->getValue('moderation'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
