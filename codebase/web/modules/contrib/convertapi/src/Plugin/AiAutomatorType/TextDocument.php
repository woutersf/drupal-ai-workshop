<?php

namespace Drupal\convertapi\Plugin\AiAutomatorType;

use ConvertApi\ConvertApi;
use Drupal\ai_automators\PluginBaseClasses\ExternalBase;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\convertapi\Form\ConvertApiConfigForm;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileRepositoryInterface;
use Drupal\key\KeyRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class for the similar document readers.
 */
class TextDocument extends ExternalBase implements AiAutomatorTypeInterface, ContainerFactoryPluginInterface {

  /**
   * The api key.
   */
  public string $apiKey;

  /**
   * The entity type manager.
   */
  public EntityTypeManagerInterface $entityManager;

  /**
   * The File System interface.
   */
  public FileSystemInterface $fileSystem;

  /**
   * The File Repo.
   */
  public FileRepositoryInterface $fileRepo;

  /**
   * The token system to replace and generate paths.
   */
  public Token $token;

  /**
   * The current user.
   */
  public AccountProxyInterface $currentUser;

  /**
   * The logger channel.
   */
  public LoggerChannelFactoryInterface $loggerChannel;

  /**
   * Construct an Text Long field.
   *
   * @param array $configuration
   *   Inherited configuration.
   * @param string $plugin_id
   *   Inherited plugin id.
   * @param mixed $plugin_definition
   *   Inherited plugin definition.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory.
   * @param \Drupal\key\KeyRepository $keyRepository
   *   The key repository.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactory $configFactory,
    KeyRepository $keyRepository
  ) {
    $key = $configFactory->get(ConvertApiConfigForm::CONFIG_NAME)->get('api_key') ?? '';
    if ($key) {
      $this->apiKey = $keyRepository->getKey($key)->getKeyValue();
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('key.repository')
    );
  }

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
  public function allowedInputs() {
    return [
      'file',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return $this->t("Get the text from a document (PDF/Word/Excel).");
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
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Field and api key has to be set.
    if (empty($entity->{$automatorConfig['base_field']}) || !$this->apiKey) {
      return [];
    }
    $values = [];
    ConvertApi::setApiSecret($this->apiKey);
    foreach ($entity->{$automatorConfig['base_field']} as $wrapperEntity) {
      $fileEntity = $wrapperEntity->entity;
      // Just allow PDF, Excel and Word files.
      if (in_array($fileEntity->getMimeType(), [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/pdf',
      ])) {
        $result = ConvertApi::convert('txt', ['File' => $fileEntity->getFileUri()]);
        $values[] = $result->getFile()->getContents();
      }
    }
    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    return is_string($value);
  }

}
