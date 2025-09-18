<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager;
use Drupal\ai_agents\Entity\AiAgent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * AI Agent form.
 */
final class AiAgentForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\ai_agents\Entity\AiAgent
   */
  protected $entity;

  /**
   * Constructs a new AiAgentForm object.
   *
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The function call plugin manager.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager $functionGroupPluginManager
   *   The function group plugin manager.
   * @param mixed $modelerApi
   *   If the submodule for Modeler API is enabled, this will contain the
   *   modeler api service.
   */
  public function __construct(
    protected FunctionCallPluginManager $functionCallPluginManager,
    protected FunctionGroupPluginManager $functionGroupPluginManager,
    protected mixed $modelerApi,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai.function_calls'),
      $container->get('plugin.manager.ai.function_groups'),
      $container->has('modeler_api.service') && $container->get('module_handler')
        ->moduleExists('ai_agents_modeler_api') ? $container->get('modeler_api.service') : NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    if ($this->modelerApi !== NULL) {
      $form['#title'] = $this->t('AI agent: %label', [
        '%label' => $this->entity->label() ?? $this->t('Create new AI agent'),
      ]);
      $form['canvas'] = $this->modelerApi->embedIntoForm($form, $form_state, $this->entity, 'bpmn_io');
      return $form;
    }
    $form['#attached']['library'][] = 'ai_agents/agents_form';

    $form['#title'] = $this->t('AI agent: %label', [
      '%label' => $this->entity->label() ?? $this->t('Create new AI agent'),
    ]);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [AiAgent::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('A description of the AI agent. This is really important, because triage agents or orchestration tools will base their decisions to pick the right agent on this.'),
      '#required' => TRUE,
      '#default_value' => $this->entity->get('description'),
      '#attributes' => [
        'rows' => 2,
      ],
    ];

    $form['orchestration_agent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Swarm orchestration agent'),
      '#description' => $this->t('Check this box if this AI agent is a swarm orchestration agent. Swarm orchestration agents are usually a direct agent a UI can talk to that collects information and sets up tasks for other agents. Note that orchestration agents usually only work with context and agent tools and should have a least one agent tool.'),
      '#default_value' => $this->entity->get('orchestration_agent'),
    ];

    $form['triage_agent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Project manager agent'),
      '#description' => $this->t('Check this box if this AI agent is a project manager agent that usually runs first. Only an recommendation and will not be used by all swarm tools.'),
      '#default_value' => $this->entity->get('triage_agent'),
    ];

    $form['max_loops'] = [
      '#type' => 'number',
      '#title' => $this->t('Max loops'),
      '#description' => $this->t('The maximum amount of loops that the AI agent can run to feed itself with new context before giving up. This is a security feature to prevent infinite loops.'),
      '#default_value' => $this->entity->get('max_loops') ?? 3,
      '#required' => TRUE,
    ];

    // Hide it for now.
    $form['permissions'] = [
      '#type' => 'details',
      '#title' => $this->t('Agent role masquerading'),
      '#open' => FALSE,
      '#access' => FALSE,
    ];

    $user_roles = [];
    foreach ($this->entityTypeManager->getStorage('user_role')->loadMultiple() as $role_key => $role) {
      // Do not add the anonymous role.
      if ($role_key === 'anonymous') {
        continue;
      }
      $user_roles[$role_key] = $role->label();
    }

    $user_role_link = Link::createFromRoute($this->t('user roles'), 'entity.user_role.collection', [], [
      'attributes' => [
        'target' => '_blank',
      ],
    ])->toString();
    $form['permissions']['user_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Agent user roles'),
      '#description' => $this->t('The %user_role this agent performs its actions as, if they should differ or be mixed with the users. Please only check anything here, if you know what you are doing. This will have security implications.', [
        '%user_role' => $user_role_link,
      ]),
      '#options' => $user_roles,
      '#default_value' => $this->entity->get('masquerade_roles'),
    ];

    $form['permissions']['exclude_users_role'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude end users role'),
      '#description' => $this->t('Check this box if you want to only use the agents user roles and not the end users.'),
      '#default_value' => $this->entity->get('exclude_users_role'),
    ];

    $form['prompt_detail'] = [
      '#type' => 'details',
      '#title' => $this->t('Usage details'),
      '#open' => TRUE,
    ];

    // Show the token browser if the module is enabled.
    if ($this->moduleHandler->moduleExists('token')) {
      $form['prompt_detail']['#description'] = $this->t('The prompt detail is the prompt that the AI agent will use to start the conversation. Please be descriptive and clear in how the agent should behave. You can use tokens in the system prompt and default information tools. The token browser will help you to find the right tokens to use. They can be used in the System Prompt, Default Information Tools and tool usage.');

      $form['prompt_detail']['token_help'] = [
        '#theme' => 'token_tree_link',
        // Other modules may provide token types.
        '#token_types' => [
          'ai_agent',
        ],
      ];
    }
    else {
      $form['prompt_detail']['#description'] = $this->t('The prompt detail is the prompt that the AI agent will use to start the conversation. Please be descriptive and clear in how the agent should behave. You can use tokens in the system prompt and default information tools. If you want to be able to use the token browser, please enable the token module to use this feature. Tokens will still work if you manually add them. You can use tokens in the system prompt, default information tools and detail tool usage.');
    }

    $form['prompt_detail']['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Agent Instructions'),
      '#description' => $this->t('Specific instructions that define how the AI agent should behave and respond to tasks for a particular interaction.'),
      '#required' => TRUE,
      '#default_value' => $this->entity->get('system_prompt'),
      '#attributes' => [
        'rows' => 10,
      ],
    ];

    // Show the secured system prompt only if configured in settings.php.
    if (Settings::get('show_secured_ai_agent_system_prompt', FALSE)) {
      $form['prompt_detail']['secured_system_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('System Prompt'),
        '#description' => $this->t('Expert configuration: This field contains the full system prompt sent to the AI, including any fixed behaviors not editable by regular users. You can use [ai_agent:agent_instructions] token to include the Agent Instructions field above. If left empty, only Agent Instructions will be used.'),
        // Set the full agent instructions as default value.
        '#default_value' => $this->entity->get('secured_system_prompt') ?? '[ai_agent:agent_instructions]',
        '#attributes' => [
          'rows' => 10,
        ],
      ];
    }

    $form['prompt_detail']['default_information_tools'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Default information tools'),
      '#description' => $this->t('A list of default information tools that can be used by the AI agent. You can either give an empty value, hardcoded value or dynamic value to parameters. If a dynamic value is set, an LLM will try to figure out how to fill in the value.'),
      '#default_value' => $this->entity->get('default_information_tools') ? Yaml::dump(Yaml::parse($this->entity->get('default_information_tools') ?? ''), 10, 2) : NULL,
    ];

    $other = [];
    $form['prompt_detail']['tools_box'] = [
      '#type' => 'details',
      '#title' => $this->t('Tools'),
      '#description' => $this->t('These are the tools that the Agent can use to get information, modify content/configs, call other agents, etc.'),
      '#open' => TRUE,
    ];

    $form['prompt_detail']['tools_box']['filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter'),
      '#description' => $this->t('Filter the tools by name.'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
      '#weight' => -10000,
    ];

    $function_call_plugin_manager = $this->functionCallPluginManager;
    $function_group_plugin_manager = $this->functionGroupPluginManager;

    foreach ($function_call_plugin_manager->getDefinitions() as $plugin_id => $definition) {
      // Check so the class implements ExecutableFunctionCallInterface.
      if (!isset($definition['class']) || !is_subclass_of($definition['class'], ExecutableFunctionCallInterface::class)) {
        continue;
      }
      $group = $definition['group'];
      if ($group && $function_group_plugin_manager->hasDefinition($group)) {
        $group_details = $function_group_plugin_manager->getDefinition($group);
        if (!isset($form['prompt_detail']['tools_box'][$group])) {
          $form['prompt_detail']['tools_box'][$group] = [
            '#type' => 'details',
            '#title' => $group_details['group_name'],
            '#description' => $group_details['description'],
            '#weight' => $group_details['weight'] ?? 0,
            '#open' => TRUE,
          ];

          $form['prompt_detail']['tools_box'][$group]['wrapper'] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['tool-wrapper-container'],
            ],
          ];
        }
        // Special rules as well to not show it self.
        if ($this->entity && $plugin_id === 'ai_agent:' . $this->entity->id()) {
          continue;
        }
        $form['prompt_detail']['tools_box'][$group]['wrapper']['tool__' . $plugin_id] = [
          '#prefix' => '<div class="tool-wrapper" data-id="' . $plugin_id . '">',
          '#suffix' => '</div>',
          '#type' => 'checkbox',
          '#title' => $definition['name'],
          '#default_value' => $this->entity->get('tools')[$plugin_id] ?? '',
          '#ajax' => [
            'callback' => '::modifyToolDescription',
            'wrapper' => 'tool-usage',
            'event' => 'change',
          ],
        ];
        $description = '';
        if ($this->moduleHandler->moduleExists('ai_api_explorer')) {
          $description .= '[' . Link::createFromRoute($this->t('Test this tool'), 'ai_api_explorer.form.tools_explorer', [], [
            'query' => [
              'tool' => $plugin_id,
            ],
            'attributes' => [
              'target' => '_blank',
            ],
          ])->toString() . ']<br>';
        }
        $description .= $definition['description'] ?? '';
        if ($definition['description']) {
          $form['prompt_detail']['tools_box'][$group]['wrapper']['tool__' . $plugin_id]['#description'] = $description;
        }
      }
      else {
        $other['tool__' . $plugin_id] = [
          '#type' => 'checkbox',
          '#title' => $definition['name'],
          '#default_value' => $this->entity->get('tools')[$plugin_id] ?? '',
        ];
        if ($definition['description']) {
          $other['tool__' . $plugin_id]['#description'] = $definition['description'];
        }
      }
    }

    if (count($other)) {
      $form['prompt_detail']['tools_box']['other'] = [
        '#type' => 'details',
        '#title' => $this->t('Other tools'),
        '#description' => $this->t('A list of tools that are not grouped.'),
        '#open' => TRUE,
      ];
      $form['prompt_detail']['tools_box']['other'] += $other;
    }

    // Selected tools.
    $selected_tools = [];
    if ($form_state->isRebuilding()) {
      foreach ($form_state->getValues() as $key => $value) {
        if (str_starts_with($key, 'tool__') && $value) {
          $selected_tools[substr($key, 6)] = TRUE;
        }
      }
    }
    else {
      $selected_tools = $this->entity->get('tools') ?? [];
    }

    // Show the selected tools, if they are picked.
    if (count($selected_tools)) {
      $form['prompt_detail']['tool_usage'] = [
        '#type' => 'details',
        '#title' => $this->t('Detailed tool usage'),
        '#open' => TRUE,
        '#prefix' => '<div id="tool-usage">',
        '#suffix' => '</div>',
        '#tree' => TRUE,
      ];

      // Show the token browser if the module is enabled.
      if ($this->moduleHandler->moduleExists('token')) {
        $form['prompt_detail']['tool_usage']['#description'] = $this->t('The token browser can be used for the values you set in the Detail Tool Usage.');

        $form['prompt_detail']['tool_usage']['token_help'] = [
          '#theme' => 'token_tree_link',
          '#token_types' => [],
        ];
      }

      foreach (array_keys($selected_tools) as $tool_id) {
        try {
          /** @var \Drupal\ai\Service\FunctionCalling\FunctionCallInterface $tool */
          $tool = $function_call_plugin_manager->createInstance($tool_id);
          $definition = $function_call_plugin_manager->getDefinition($tool_id);
          $this->createToolUsageForm($tool, $definition, $form, $form_state);
        }
        catch (\Exception) {
          // Do nothing.
        }
      }
    }
    else {

      // The tool-usage element needs to exist or the AJAX will have nothing
      // to replace.
      $form['prompt_detail']['tool_usage'] = [
        '#markup' => '<div id="tool-usage"></div>',
      ];
    }
    return $form;
  }

  /**
   * Helper method to create the tool usage form.
   *
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallInterface $tool_instance
   *   The tool instance.
   * @param array $tool_definition
   *   The definition.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function createToolUsageForm(FunctionCallInterface $tool_instance, array $tool_definition, array &$form, FormStateInterface $form_state) {
    // Details.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']] = [
      '#type' => 'details',
      '#title' => $tool_definition['name'],
      '#open' => FALSE,
    ];

    // Allow to return directly.
    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['return_directly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Return directly'),
      '#description' => $this->t('Check this box if you want to return the result directly, without the LLM trying to rewrite them or use another tool. This is usually used for tools that are not used in a conversation or when its being used in an API where the tools is the structured result.'),
      '#default_value' => $this->entity->get('tool_settings')[$tool_definition['id']]['return_directly'] ?? FALSE,
    ];

    $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'] = [
      '#type' => 'details',
      '#title' => $this->t('Property restrictions'),
    ];

    // Get all the contexts.
    $properties = $tool_instance->normalize()->getProperties();
    foreach ($properties as $property) {
      $property_name = $property->getName();
      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name] = [
        '#type' => 'fieldset',
        '#attributes' => [
          'class' => ['tool-usage-container'],
        ],
      ];

      // Get the default values.
      $default_action = '';
      $default_values = '';
      $is_hidden = FALSE;
      if ($form_state->isRebuilding()) {
        $default_action = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          $property_name,
          'action',
        ]);
        $default_values = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          $property_name,
          'values',
        ]);
        $is_hidden = $form_state->getValue([
          'tool_usage',
          $tool_definition['id'],
          $property_name,
          'hide_property',
        ]);
      }
      elseif ($tool_usage_limits = $this->entity->get('tool_usage_limits')) {
        if (isset($tool_usage_limits[$tool_definition['id']][$property_name])) {
          $default_action = $tool_usage_limits[$tool_definition['id']][$property_name]['action'] ?? "";
          $values = is_array($tool_usage_limits[$tool_definition['id']][$property_name]['values']) ? $tool_usage_limits[$tool_definition['id']][$property_name]['values'] : [];
          $default_values = implode("\n", $values);
          $is_hidden = $tool_usage_limits[$tool_definition['id']][$property_name]['hide_property'] ?? FALSE;
        }
      }

      // Make sure to open if there is a value set.
      if ($default_action || $default_values) {
        $form['prompt_detail']['tool_usage'][$tool_definition['id']]['#open'] = TRUE;
      }

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name]['action'] = [
        '#type' => 'select',
        '#title' => $this->t('Restrictions for property %name', [
          '%name' => $property_name,
        ]),
        '#options' => [
          '' => $this->t('Allow all'),
          'only_allow' => $this->t('Only allow certain values'),
          'force_value' => $this->t('Force value'),
        ],
        '#description' => $this->t('Restrict the allowed values or enforce a value.'),
        '#default_value' => $default_action,
      ];

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name]['hide_property'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Hide property'),
        '#description' => $this->t('Check this box if you want to hide this property from being sent to the LLM or from being logged. For instance for API keys.'),
        '#default_value' => $is_hidden,
        '#states' => [
          'visible' => [
            ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][' . $property_name . '][action]"]' => [
              ['value' => 'force_value'],
            ],
          ],
        ],
      ];

      $form['prompt_detail']['tool_usage'][$tool_definition['id']]['property_restrictions'][$property_name]['values'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Values'),
        '#description' => $this->t('The values that are allowed or the value that should be set. If you pick to only allow certain values, you can set the allowed values new line separated if there are more then one. If you pick to force a value, you can set the value that should be set.'),
        '#default_value' => $default_values,
        '#rows' => 2,
        '#states' => [
          'visible' => [
            ':input[name="tool_usage[' . $tool_definition['id'] . '][property_restrictions][' . $property_name . '][action]"]' => [
              ['value' => 'only_allow'],
              'or',
              ['value' => 'force_value'],
            ],
          ],
        ],
      ];
    }
  }

  /**
   * Ajax callback to add more information about the tool.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function modifyToolDescription(&$form, FormStateInterface $form_state) {
    return $form['prompt_detail']['tool_usage'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(&$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // If its a new entity, we do this check.
    if ($this->entity->isNew()) {
      // Check so the function name does not exist.
      if ($this->functionCallPluginManager->functionExists($this->entity->id())) {
        $form_state->setErrorByName('id', $this->t('The function name already exists.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    if ($this->modelerApi === NULL) {
      $tools = [];
      foreach ($form_state->getValues() as $key => $value) {
        if (str_starts_with($key, 'tool__') && $value) {
          $tools[substr($key, 6)] = TRUE;
        }
      }
      $dependencies = [];
      // Remove unchecked values.
      foreach ($tools as $key => $value) {
        $tool = $this->functionCallPluginManager->getDefinition($key);
        $dependencies[] = $tool['provider'];
      }
      // Tool usage limits.
      $tool_usage_limits = [];

      // Save tools settings.
      $tool_settings = [];
      if (!empty($form_state->getValue('tool_usage'))) {
        foreach ($form_state->getValue('tool_usage') as $tool_id => $tool_usage) {
          // Check if it should return directly.
          $tool_settings[$tool_id]['return_directly'] = $tool_usage['return_directly'] ?? FALSE;
          if (isset($tool_usage['property_restrictions'])) {
            foreach ($tool_usage['property_restrictions'] as $property_name => $values) {
              // Only set if an action is set.
              if ($values['action']) {
                $cleaned_values = str_replace("\r\n", "\n", $values['values'] ?? '');
                // Trim and remove all empty values.
                $all_values = array_filter(array_map('trim', explode("\n", $cleaned_values)));
                $tool_usage['property_restrictions'][$property_name]['values'] = $all_values;
              }
              else {
                unset($tool_usage[$property_name]);
              }
            }
          }
          if (count($tool_usage)) {
            $tool_usage_limits[$tool_id] = $tool_usage['property_restrictions'];
          }
        }
      }

      // Handle the secured system prompt.
      if (Settings::get('show_secured_ai_agent_system_prompt', FALSE)) {
        $secured_system_prompt = $form_state->getValue('secured_system_prompt');
        $this->entity->set('secured_system_prompt', $secured_system_prompt);
      }
      else {
        $secured_system_prompt = $this->entity->get('secured_system_prompt');
        if (empty($secured_system_prompt)) {
          // Set default value to [ai_agent:agent_instructions] if empty.
          $this->entity->set('secured_system_prompt', '[ai_agent:agent_instructions]');
        }
      }

      // Make sure to set dependencies on the tools.
      $this->entity->set('dependencies', array_unique($dependencies));
      $this->entity->set('tool_usage_limits', $tool_usage_limits);
      $this->entity->set('tool_settings', $tool_settings);
      $this->entity->set('tools', $tools);
      // Make sure to remove \r characters from the system prompt for nice YAML.
      // See: https://www.drupal.org/project/drupal/issues/3202796.
      $system_prompt = str_replace("\r\n", "\n", $form_state->getValue('system_prompt') ?? '');
      $this->entity->set('system_prompt', $system_prompt);
      $default_information_tools = str_replace("\r\n", "\n", $form_state->getValue('default_information_tools') ?? '');
      $this->entity->set('default_information_tools', $default_information_tools);
    }
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl(Url::fromRoute('ai_agents.settings_form'));
    return $result;
  }

}
