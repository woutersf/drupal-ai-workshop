<?php

namespace Drupal\ai_vdb_provider_pinecone\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiVdbProviderPluginManager;
use Drupal\key\KeyRepositoryInterface;
use Probots\Pinecone\Client as Pinecone;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Pinecone DB config form.
 */
class PineconeConfigForm extends ConfigFormBase {

  /**
   * Constructor of the Pinecone DB config form.
   *
   * @param \Drupal\ai\AiVdbProviderPluginManager $vdbProviderPluginManager
   *   The VDB Provider plugin manager.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   */
  public function __construct(
    protected AiVdbProviderPluginManager $vdbProviderPluginManager,
    protected KeyRepositoryInterface $keyRepository,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.vdb_provider'),
      $container->get('key.repository'),
    );
  }

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_vdb_provider_pinecone.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pinecone_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#description' => $this->t('The API key to use for authentication. This can be created and found under "API keys" at <a href="https://app.pinecone.io/" target="_blank">https://app.pinecone.io/</a>.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $key = $form_state->getValue('api_key');
    if (!empty($key)) {
      $key = $this->keyRepository->getKey($key)->getKeyValue();
    }
    if (!empty($key)) {
      try {
        $pinecone = new Pinecone($key);
        $response = $pinecone->control()->index()->list();
        if (!$response->successful()) {
          $form_state->setErrorByName('api_key', $this->t('Attempting to retrieve the list of indexes via that Pinecone API key failed.'));
        }
        elseif (empty($response->array()['indexes'])) {
          $form_state->setErrorByName('api_key', $this->t('No indexes were found in Pinecone when testing the connection. Please create an index first.'));
        }
      }
      catch (\Exception $exception) {
        $form_state->setErrorByName('api_key', $this->t('An error occurred attempting to connect to the Pinecone API: @error', [
          '@error' => $exception->getMessage(),
        ]));
      }
    }
    else {
      $form_state->setErrorByName('api_key', $this->t('Please ensure you select a valid API key.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
