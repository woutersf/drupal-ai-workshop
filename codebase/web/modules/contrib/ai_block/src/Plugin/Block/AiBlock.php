<?php

namespace Drupal\ai_block\Plugin\Block;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configurable AI Block.
 *
 * @Block(
 *   id = "ai_block",
 *   admin_label = @Translation("AI Block"),
 * )
 */
class AiBlock extends BlockBase implements ContainerFactoryPluginInterface {


  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): object {
    $plugin = new static($configuration, $plugin_id, $plugin_definition);
    $plugin->aiProviderManager = $container->get('ai.provider');
    $plugin->moduleHandler = $container->get('module_handler');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $prompt = $config['prompt'];
    $ai_provider_model = $config['llm_model'];
    $ai_model_to_use = '';
    if ($ai_provider_model === '') {
      $default_provider = $this->aiProviderManager->getDefaultProviderForOperationType('chat');
      $ai_provider_model = $default_provider['provider_id'] . '__' . $default_provider['model_id'];
      $ai_model_to_use = $default_provider['model_id'];
    }
    else {
      $parts = explode('__', $ai_provider_model);
      $ai_model_to_use = $parts[1];
    }
    $provider = $this->aiProviderManager->loadProviderFromSimpleOption($ai_provider_model);

    $prompt = \Drupal::token()->replace($prompt, [
      'node' => \Drupal::routeMatch()->getParameter('node'),
      'user' => \Drupal::currentUser(),
    ]);

    //Allow altering of the prompt.
    $this->moduleHandler->alter('ai_block_prompt', $prompt, $config['block_id']);

    $input = new ChatInput([
      new ChatMessage('user', $prompt),
    ]);
    $output = $provider->chat($input, $ai_model_to_use, ['ai_search_block']);
    $response = $output->getNormalized()->getText() . "\n";

    //Maybe allow altering of the output.
    $this->moduleHandler->alter('ai_block_response', $output, $config['block_id']);
    $block = [];
    $block['#settings'] = $this->configuration;
    $block['#theme'] = 'ai_block_response';
    $block['#output'] = $response;
    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();
    $form['usage'] = [
      '#type' => 'select',
      '#title' => $this->t('Execute prompt every'),
      '#options' => [
        'every_time' => t('Every page load (might be costly)'),
        'time' => t('Once per day (cache)'),
      ],
      '#default_value' => $config['usage'] ?? '',
    ];
    $llm_model_options = $this->aiProviderManager->getSimpleProviderModelOptions('chat');
    array_shift($llm_model_options);
    array_splice($llm_model_options, 0, 1);
    $form['llm_model'] = [
      '#type' => 'select',
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
      '#title' => $this->t('RAG LLM Model'),
      '#default_value' => $this->configuration['llm_model'],
      '#options' => $llm_model_options,
      '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $config['prompt'] ?? '',
      '#token_types' => array('node', 'user', 'ai-translate'),
      '#element_validate' => array('token_element_validate'),
    ];
    $form['token_tree'] = array(
      '#theme' => 'token_tree_link',
      '#token_types' => array('node', 'user'),
      '#show_restricted' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('prompt', $form_state->getValue('prompt'));
    $this->setConfigurationValue('llm_model', $form_state->getValue('llm_model'));
    $this->setConfigurationValue('usage', $form_state->getValue('usage'));

    // llm_model.
    if (method_exists($form_state->getBuildInfo()['callback_object'], 'getEntity')) {
      // Likely this is the stock drupal block layout config.
      $this->configuration['block_id'] = $form_state->getBuildInfo()['callback_object']->getEntity()
        ->id();
      $this->configuration['block_offset'] = '';
    }
    else {
      $callback_obj = $form_state->getBuildInfo()['callback_object'];
      // Likely this is Layout builder.
      $current_component = $callback_obj->getCurrentComponent();
      $uuid = $current_component->getUuid();
      $region = $current_component->getRegion();
      $weight = $current_component->getWeight();
      $layout_offset = $weight . '/' . $region;
      $this->configuration['block_id'] = $uuid;
      $this->configuration['block_offset'] = $layout_offset;
    }
  }
}
