<?php

namespace Drupal\ai_content_suggestions\Hook;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Contains hooks to inject AI content suggestions for form element.
 */
class AiContentSuggestions {

  use StringTranslationTrait;

  /**
   * Constructs AiContentSuggestions hooks.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    #[Autowire(service: 'ai.provider')]
    protected AiProviderPluginManager $aiProvider,
    protected AccountProxyInterface $account,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {

  }

  /**
   * Implements hook_field_widget_third_party_settings_form().
   */
  #[Hook('field_widget_third_party_settings_form')]
  public function fieldWidgetThirdPartySettingsForm(WidgetInterface $plugin, FieldDefinitionInterface $field_definition, $form_mode, array $form, FormStateInterface $form_state) {
    $element = [];
    if (in_array($field_definition->getType(), ['string', 'string_long', 'text', 'text_long', 'text_with_summary'])) {
      $element['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable AI suggestions'),
        '#default_value' => $plugin->getThirdPartySetting('ai_content_suggestions', 'enabled'),
      ];
      $element['settings'] = [
        '#type' => 'details',
        '#title' => $this->t('AI Suggestions Settings'),
        '#states' => [
          'visible' => [
            ':input[name="fields[' . $field_definition->getName() . '][settings_edit_form][third_party_settings][ai_content_suggestions][enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];
      $settings = $plugin->getThirdPartySetting('ai_content_suggestions', 'settings');
      $models = $this->aiProvider->getSimpleProviderModelOptions('chat', FALSE);
      $default_model = $this->aiProvider->getDefaultProviderForOperationType('chat');
      $element['settings']['model'] = [
        '#title' => $this->t('AI model to use for suggestions'),
        '#type' => 'select',
        '#options' => $models,
        '#empty_option' => $this->t('- Use default model for chat operation -'),
        '#default_value' => $settings['model'] ?? '',
        '#description' => $this->t('The default models could be set <a href="@url" target="_blank">here</a>. Current default model is @provider @model.', [
          '@url' => Url::fromRoute('ai.settings_form')->toString(),
          '@provider' => $default_model['provider_id'] ?? $this->t('not set'),
          '@model' => $default_model['model_id'] ?? '',
        ]),
      ];
      $field_entity_type = $field_definition->getTargetEntityTypeId();
      $entity_type = $this->entityTypeManager->getDefinition($field_entity_type);
      $element['settings']['prompt'] = [
        '#title' => $this->t('AI Suggestion prompt'),
        '#type' => 'textarea',
        '#default_value' => $settings['prompt'] ?? '',
      ];
      if ($this->moduleHandler->moduleExists('token')) {
        $element['settings']['prompt']['#description'] = $this->t('It is allowed to use the tokens for entity type "@label", for example: <em>[@id:field_name]</em>. If none tokens for entity type @id is used, the entity is added to user input in view mode "Full".', [
          '@id' => $field_entity_type,
          '@label' => $entity_type ? $entity_type->getLabel() : $field_entity_type,
        ]);
        $element['settings']['token_help'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => [$field_entity_type],
        ];
      }
      $element['settings']['button'] = [
        '#title' => $this->t('Button label'),
        '#description' => $this->t('Button will appear near the form element. Default label is "AI Suggestions"'),
        '#type' => 'textfield',
        '#default_value' => $settings['button'] ?? '',
      ];
    }
    return $element;
  }

  /**
   * Implements hook_field_widget_complete_form_alter().
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context) {
    // Only show if the user has permission to use ai content suggestions tools.
    if (!$this->account->hasPermission('access ai content suggestion tools')) {
      return;
    }
    if ($context['widget']->getThirdPartySetting('ai_content_suggestions', 'enabled', FALSE)) {
      $field_widget_complete_form['#attributes']['class'][] = 'ai-content-suggestions--enabled';
      $field_widget_complete_form['#attached']['library'][] = 'ai_content_suggestions/field_widget';
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context) {
    // Only show if the user has permission to use ai content suggestions tools.
    if (!$this->account->hasPermission('access ai content suggestion tools')) {
      return;
    }
    if ($context['widget']->getThirdPartySetting('ai_content_suggestions', 'enabled', FALSE)) {
      $settings = $context['widget']->getThirdPartySetting('ai_content_suggestions', 'settings');
      $element['ai_content_suggestions'] = [
        '#weight' => 100,
        '#name' => $context['items']->getName() . '_ai_content_suggestions',
        '#type' => 'button',
        '#value' => $settings['button'] ?? $this->t('AI Suggestions'),
        '#prefix' => '<div class="ai-content-suggestions-wrapper">',
        '#suffix' => '</div>',
        '#attributes' => [
          'class' => ['ai-content-suggestions'],
        ],
        '#ajax' => [
          'callback' => '\Drupal\ai_content_suggestions\Hook\AiContentSuggestions::aiContentSuggestionsAjax',
          'suppress_required_fields_validation' => TRUE,
        ],
        '#ai_content_suggestion_settings' => $settings,
      ];
    }
  }

  /**
   * Ajax handler for AI content suggestions.
   */
  public static function aiContentSuggestionsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = 'value';
    $target_element = NestedArray::getValue($form, $array_parents);
    $selector = $target_element ? $target_element['#attributes']['data-drupal-selector'] : '';
    // Set provider for AI Suggestions.
    $provider_config = \Drupal::service('ai.provider')->getSetProvider('chat', $triggering_element['#ai_content_suggestion_settings']['model']);
    $prompt = $triggering_element['#ai_content_suggestion_settings']['prompt'];
    // Get the content entity from form object.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->getFormObject()->buildEntity($form, $form_state);
    if ($entity->isNew()) {
      $entity->in_preview = TRUE;
    }
    $tokens = \Drupal::token()->scan($prompt);
    $markdown_options = [
      'header_style' => 'atx',
      'strip_tags' => TRUE,
      'strip_whitespace' => TRUE,
      'strip_placeholder_links' => TRUE,
    ];
    $converter = new HtmlConverter($markdown_options);
    // If no tokens found attach the full entity.
    if (empty($tokens[$entity->getEntityTypeId()])) {
      // Replace any other token type.
      $prompt = \Drupal::token()->replacePlain($prompt);
      // Convert entity to markdown.
      // Create render array for the entity.
      $render_array = \Drupal::entityTypeManager()->getViewBuilder($entity->getEntityTypeId())->view($entity);
      // Create HTML markup.
      $html = \Drupal::service('renderer')->renderInIsolation($render_array);
      $prompt .= $converter->convert($html);
    }
    else {
      $prompt = \Drupal::token()
        ->replace($prompt, [$entity->getEntityTypeId() => $entity], ['clear' => TRUE]);
      $prompt = $converter->convert($prompt);
    }
    /** @var \Drupal\ai\AiProviderInterface $ai_provider */
    $ai_provider = $provider_config['provider_id'];
    try {
      $messages = new ChatInput([
        new ChatMessage('user', $prompt),
      ]);
      $config = \Drupal::config('ai_content_suggestions.settings');
      $ai_provider->setChatSystemRole($config->get('field_widget_prompt') ?? t('You are helpful assistant.'));

      /** @var \Drupal\ai\OperationType\Chat\ChatMessage $response */
      $response = $ai_provider->chat($messages, $provider_config['model_id'], [
        'ai_content_suggestions',
      ])->getNormalized();
      $message = trim($response->getText()) ?? t('No result could be generated.');
    }
    catch (\Exception $e) {
      $message = t('There was an error obtaining a response from the LLM.');
    }
    $response = new AjaxResponse();
    if (!empty($selector)) {
      $response->addCommand(new SettingsCommand(['ai_cs_target' => ['target' => $selector]], TRUE));
    }
    $response->addCommand(new OpenModalDialogCommand(t('AI Suggestions'), $message, [
      'width' => '80%',
      'dialogClass' => 'ui-dialog-ai-suggestions',
    ]));
    return $response;
  }

}
