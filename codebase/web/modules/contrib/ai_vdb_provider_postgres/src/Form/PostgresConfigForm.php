<?php

namespace Drupal\ai_vdb_provider_postgres\Form;

use Drupal\ai\AiVdbProviderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Postgres vector DB config form.
 */
class PostgresConfigForm extends ConfigFormBase {

  /**
   * Constructor of the Postgres vector DB config form.
   *
   * @param \Drupal\ai\AiVdbProviderPluginManager $vdbProviderPluginManager
   *   The VDB Provider plugin manager.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected AiVdbProviderPluginManager $vdbProviderPluginManager,
    protected KeyRepositoryInterface $keyRepository
  ) {
    $this->vdbProviderPluginManager = $vdbProviderPluginManager;
    $this->keyRepository = $keyRepository;
    parent::__construct($configFactory, $typedConfigManager);
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('ai.vdb_provider'),
      $container->get('key.repository'),
    );
  }

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_vdb_provider_postgres.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_vdb_provider_postgres_settings';
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

    $form['host'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Host'),
      '#description' => $this->t('The server host to connect to.'),
      '#default_value' => $config->get('host'),
    ];

    $form['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#description' => $this->t('The server port to connect to. postgres\' default port is 5432'),
      '#default_value' => $config->get('port') ?? '5432',
    ];

    $form['username'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Username'),
      '#description' => $this->t('The username used to connect to the postgres database host.'),
      '#default_value' => $config->get('username'),
    ];

    $form['password'] = [
      '#type' => 'key_select',
      '#required' => TRUE,
      '#title' => $this->t('Password'),
      '#description' => $this->t('The password used to connect to the postgres database host.'),
      '#default_value' => $config->get('password'),
    ];

    $form['default_database'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Default database'),
      '#description' => $this->t('The default database to connect to. postgres requires a database to make a connection, so a default has to be specified. This can be overridden by search backends that use this vdb provider.'),
      '#default_value' => $config->get('default_database'),
    ];


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $host = $form_state->getValue('host');
//    if (!filter_var($host, FILTER_VALIDATE_URL)) {
//      $form_state->setErrorByName('host', $this->t('The host must be a valid URL.'));
//    }

    $port = $form_state->getValue('port');
    if (!empty($port) && !is_numeric($port)) {
      $form_state->setErrorByName('port', $this->t('The port must be a number.'));
    }

    // Test the connection.
    $postgresConnector = $this->vdbProviderPluginManager->createInstance('postgres');
    $password = $form_state->getValue('password');
    if (!empty($password)) {
      $password = $this->keyRepository->getKey($password)->getKeyValue();
    }
    $postgresConnector->setCustomConfig([
      'host' => $host,
      'port' => $port,
      'username' => $form_state->getValue('username'),
      'password' => $password,
      'default_database' => $form_state->getValue('default_database'),
    ]);

    if (!$postgresConnector->ping()) {
      $form_state->setErrorByName('host', $this->t('Could not connect to the server.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(static::CONFIG_NAME)
      ->set('host', rtrim($form_state->getValue('host'), '/'))
      ->set('port', $form_state->getValue('port'))
      ->set('username', $form_state->getValue('username'))
      ->set('password', $form_state->getValue('password'))
      ->set('default_database', $form_state->getValue('default_database'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
