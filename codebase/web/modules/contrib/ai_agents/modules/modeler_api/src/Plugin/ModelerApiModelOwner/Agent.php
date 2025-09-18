<?php

namespace Drupal\ai_agents_modeler_api\Plugin\ModelerApiModelOwner;

use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_agents\Entity\AiAgent;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Attribute\ModelOwner;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Form\Settings as ModelerApiSettings;
use Drupal\modeler_api\Plugin\ComponentWrapperPlugin;
use Drupal\modeler_api\Plugin\ComponentWrapperPluginInterface;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Model owner plugin implementation for AI Agents.
 */
#[ModelOwner(
  id: "ai_agents_agent",
  label: new TranslatableMarkup("AI Agent"),
  description: new TranslatableMarkup("Configure AI Agents")
)]
class Agent extends ModelOwnerBase {

  public const array SUPPORTED_COMPONENT_TYPES = [
    Api::COMPONENT_TYPE_START => 'agent',
    Api::COMPONENT_TYPE_SUBPROCESS => 'wrapper',
    Api::COMPONENT_TYPE_ELEMENT => 'tool',
    Api::COMPONENT_TYPE_LINK => 'link',
  ];

  /**
   * The list of sub models.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface[]
   */
  protected array $subModels = [];

  /**
   * Dependency Injection container.
   *
   * Used for getter injection.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|null
   */
  protected ?ContainerInterface $container;

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected FunctionCallPluginManager $functionCallPluginManager;

  /**
   * The random generator.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected Random $random;

  /**
   * Get Dependency Injection container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   Current Dependency Injection container.
   */
  protected function getContainer(): ContainerInterface {
    if (!isset($this->container)) {
      // @phpstan-ignore-next-line
      $this->container = \Drupal::getContainer();
    }
    return $this->container;
  }

  /**
   * Get the function call plugin manager.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   *   The function call plugin manager.
   */
  protected function functionCallPluginManager(): FunctionCallPluginManager {
    if (!isset($this->functionCallPluginManager)) {
      $this->functionCallPluginManager = $this->getContainer()->get('plugin.manager.ai.function_calls');
    }
    return $this->functionCallPluginManager;
  }

  /**
   * Get the random generator.
   *
   * @return \Drupal\Component\Utility\Random
   *   The random generator.
   */
  protected function random(): Random {
    if (!isset($this->random)) {
      $this->random = new Random();
    }
    return $this->random;
  }

  /**
   * {@inheritdoc}
   */
  public function modelIdExistsCallback(): array {
    return [AiAgent::class, 'load'];
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityProviderId(): string {
    return 'ai_agents';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityTypeId(): string {
    return 'ai_agent';
  }

  /**
   * {@inheritdoc}
   */
  public function configEntityBasePath(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function modelConfigFormAlter(array &$form): void {
    $form['label']['#access'] = FALSE;
    $form['model_id']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponents(ConfigEntityInterface $model, ?string $parentId = NULL): array {
    assert($model instanceof AiAgent);
    $components = [];
    $config = [
      'label' => $model->get('label'),
      'agent_id' => $model->get('id'),
      'description' => $model->get('description'),
      'orchestration_agent' => $model->get('orchestration_agent'),
      'triage_agent' => $model->get('triage_agent'),
      'max_loops' => (string) $model->get('max_loops'),
      'system_prompt' => $model->get('system_prompt'),
      'secured_system_prompt' => $model->get('secured_system_prompt'),
      'default_information_tools' => $model->get('default_information_tools'),
    ];
    $modelComponent = new Component(
      $this,
      $this->random()->name(20, TRUE),
      Api::COMPONENT_TYPE_START,
      $model->get('id'),
      $model->get('label') ?? '',
      $config,
      [],
      $parentId,
    );
    $components[] = $modelComponent;
    $successors = [];
    foreach ($model->get('tools') ?? [] as $id => $flag) {
      if (!$flag) {
        continue;
      }
      if (str_starts_with($id, 'ai_agents::ai_agent::')) {
        $id = substr($id, strlen('ai_agents::ai_agent::'));
        if ($subModel = AiAgent::load($id)) {
          $successors[] = new ComponentSuccessor($id, '');
          $components[] = new Component(
            $this,
            $id,
            Api::COMPONENT_TYPE_SUBPROCESS,
            $id,
            $subModel->get('label'),
            [],
            [],
            $parentId,
          );
          foreach ($this->usedComponents($subModel, $id) as $component) {
            $components[] = $component;
          }
        }
      }
      else {
        try {
          $plugin = $this->functionCallPluginManager()->createInstance($id);
        }
        catch (PluginException) {
          continue;
        }
        $componentId = $this->random()->name(20, TRUE);
        $successors[] = new ComponentSuccessor($componentId, '');
        $label = $plugin->pluginDefinition['label'] ?? $plugin->pluginDefinition['name'] ?? $id;
        $config = [];
        foreach ($model->get('tool_usage_limits')[$id] ?? [] as $key => $values) {
          $key = str_replace(':', '__colon__', $key);
          foreach ($values as $valueKey => $value) {
            if (is_numeric($value)) {
              $value = (bool) $value;
            }
            $config[$key . '___' . $valueKey] = is_array($value) ? implode("\n", $value) : $value;
          }
        }
        $config['return_directly'] = (bool) ($model->get('tool_settings')[$id]['return_directly'] ?? 0);
        $components[] = new Component(
          $this,
          $componentId,
          Api::COMPONENT_TYPE_ELEMENT,
          $id,
          $label,
          $config,
          [],
          $parentId,
        );
      }
    }
    $modelComponent->setSuccessors($successors);
    return $components;
  }

  /**
   * {@inheritdoc}
   */
  public function supportedOwnerComponentTypes(): array {
    return self::SUPPORTED_COMPONENT_TYPES;
  }

  /**
   * {@inheritdoc}
   */
  public function availableOwnerComponents(int $type): array {
    return match($type) {
      Api::COMPONENT_TYPE_START => [new ComponentWrapperPlugin(Api::COMPONENT_TYPE_START, '')],
      Api::COMPONENT_TYPE_ELEMENT => $this->createAllInstances(),
      Api::COMPONENT_TYPE_SUBPROCESS => $this->getAllAgents(),
      default => [],
    };
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentId(int $type): string {
    return self::SUPPORTED_COMPONENT_TYPES[$type] ?? 'unsupported';
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentDefaultConfig(int $type, string $id): array {
    $config = [];
    $plugin = $this->ownerComponent($type, $id);
    if ($plugin instanceof FunctionCallInterface) {
      $config['return_directly'] = FALSE;
      $properties = $plugin->normalize()->getProperties();
      foreach ($properties as $property) {
        $property_name = $property->getName();
        $config[$property_name . '___action'] = '';
        $config[$property_name . '___hide_property'] = FALSE;
        $config[$property_name . '___values'] = '';
      }
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentEditable(PluginInspectionInterface $plugin): bool {
    if ($plugin instanceof ComponentWrapperPluginInterface && $plugin->getType() === Api::COMPONENT_TYPE_SUBPROCESS) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponentPluginChangeable(PluginInspectionInterface $plugin): bool {
    if ($plugin instanceof ComponentWrapperPluginInterface) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function ownerComponent(int $type, string $id, array $config = []): ?PluginInspectionInterface {
    return match($type) {
      Api::COMPONENT_TYPE_START => new ComponentWrapperPlugin(Api::COMPONENT_TYPE_START, $id, $config),
      Api::COMPONENT_TYPE_ELEMENT => $this->functionCallPluginManager()->createInstance($id, $config),
      Api::COMPONENT_TYPE_SUBPROCESS => new ComponentWrapperPlugin(Api::COMPONENT_TYPE_SUBPROCESS, $id, $config),
      default => NULL,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(PluginInspectionInterface $plugin, ?string $modelId = NULL, bool $modelIsNew = TRUE): array {
    $form = [];
    if ($plugin instanceof ComponentWrapperPluginInterface && $plugin->getType() === Api::COMPONENT_TYPE_START) {
      $config = $plugin->getConfiguration();
      $form['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#maxlength' => 255,
        '#default_value' => $config['label'] ?? '',
        '#required' => TRUE,
      ];
      $form['agent_id'] = [
        '#type' => 'machine_name',
        '#default_value' => $modelIsNew ? '' : $config['agent_id'],
        '#machine_name' => [
          'exists' => [AiAgent::class, 'load'],
        ],
        '#disabled' => !$modelIsNew && $config['agent_id'] !== '',
      ];
      $form['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#description' => $this->t('A description of the AI agent. This is really important, because triage agents or orchestration tools will base their decisions to pick the right agent on this.'),
        '#required' => TRUE,
        '#default_value' => $config['description'] ?? '',
        '#attributes' => [
          'rows' => 2,
        ],
      ];
      $form['max_loops'] = [
        '#type' => 'number',
        '#title' => $this->t('Max loops'),
        '#description' => $this->t('The maximum amount of loops that the AI agent can run to feed itself with new context before giving up. This is a security feature to prevent infinite loops.'),
        '#default_value' => $config['max_loops'] ?? 3,
        '#required' => TRUE,
      ];
      $form['system_prompt'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Agent Instructions'),
        '#description' => $this->t('Specific instructions that define how the AI agent should behave and respond to tasks for a particular interaction.'),
        '#required' => TRUE,
        '#default_value' => $config['system_prompt'] ?? '',
        '#attributes' => [
          'rows' => 10,
        ],
      ];
      // Show the secured system prompt only if configured in settings.php.
      if (Settings::get('show_secured_ai_agent_system_prompt', FALSE)) {
        $form['secured_system_prompt'] = [
          '#type' => 'textarea',
          '#title' => $this->t('System Prompt'),
          '#description' => $this->t('Expert configuration: This field contains the full system prompt sent to the AI, including any fixed behaviors not editable by regular users. You can use [ai_agent:agent_instructions] token to include the Agent Instructions field above. If left empty, only Agent Instructions will be used.'),
          // Set the full agent instructions as default value.
          '#default_value' => $config['secured_system_prompt'] ?? '[ai_agent:agent_instructions]',
          '#attributes' => [
            'rows' => 10,
          ],
        ];
      }
      $form['default_information_tools'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Default information tools'),
        '#description' => $this->t('A list of default information tools that can be used by the AI agent. You can either give an empty value, hardcoded value or dynamic value to parameters. If a dynamic value is set, an LLM will try to figure out how to fill in the value.'),
        '#default_value' => $config['default_information_tools'] ?? '',
      ];
    }
    elseif ($plugin instanceof FunctionCallInterface) {
      $form['return_directly'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Return directly'),
        '#description' => $this->t('Check this box if you want to return the result directly, without the LLM trying to rewrite them or use another tool. This is usually used for tools that are not used in a conversation or when its being used in an API where the tools is the structured result.'),
        '#default_value' => FALSE,
      ];
      $properties = $plugin->normalize()->getProperties();
      foreach ($properties as $property) {
        $property_name = $property->getName();

        $form[$property_name . '___action'] = [
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
          '#default_value' => '',
        ];

        $form[$property_name . '___hide_property'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Hide property'),
          '#description' => $this->t('Check this box if you want to hide this property from being sent to the LLM or from being logged. For instance for API keys.'),
          '#default_value' => FALSE,
        ];

        $form[$property_name . '___values'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Values'),
          '#description' => $this->t('The values that are allowed or the value that should be set. If you pick to only allow certain values, you can set the allowed values new line separated if there are more then one. If you pick to force a value, you can set the value that should be set.'),
          '#default_value' => '',
          '#rows' => 2,
          '#states' => [
            'visible' => [
              ':input[name="' . $property_name . '___action"]' => [
                ['value' => 'only_allow'],
                ['value' => 'force_value'],
              ],
            ],
          ],
        ];
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function resetComponents(ConfigEntityInterface $model): ModelOwnerInterface {
    assert($model instanceof AiAgent);
    $this->subModels = [];
    $model->set('tools', []);
    $model->set('tool_usage_limits', []);
    $model->set('tool_settings', []);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addComponent(ConfigEntityInterface $model, Component $component): bool {
    if ($component->getType() === Api::COMPONENT_TYPE_LINK) {
      return TRUE;
    }
    if ($component->getParentId() !== NULL) {
      if (!isset($this->subModels[$component->getParentId()])) {
        $model = AiAgent::load($component->getParentId());
        if ($model === NULL) {
          $model = AiAgent::create([
            'id' => $component->getParentId(),
            'label' => 'placeholder',
            'description' => 'placeholder',
            'system_prompt' => 'placeholder',
            'orchestration_agent' => FALSE,
            'triage_agent' => FALSE,
            'max_loops' => 3,
            'tools' => [],
          ]);
        }
        $this->subModels[$component->getParentId()] = $model;
      }
      $model = $this->subModels[$component->getParentId()];
    }

    switch ($component->getType()) {
      case Api::COMPONENT_TYPE_SUBPROCESS:
        // The sub-agents get added in ::finalizeAddingComponents.
        $id = $component->getPluginId();
        if ($id !== '') {
          $this->subModels[$id] = AiAgent::load($id);
        }
        break;

      case Api::COMPONENT_TYPE_START:
        $config = $component->getConfiguration();
        $model->set('label', $config['label']);
        $model->set('id', $config['agent_id']);
        $model->set('description', $config['description']);
        $model->set('orchestration_agent', $config['orchestration_agent'] ?? FALSE);
        $model->set('triage_agent', $config['triage_agent'] ?? FALSE);
        $model->set('max_loops', (int) $config['max_loops']);
        $model->set('system_prompt', $config['system_prompt']);
        // Handle the secured system prompt.
        if (Settings::get('show_secured_ai_agent_system_prompt', FALSE)) {
          $model->set('secured_system_prompt', $config['secured_system_prompt']);
        }
        else {
          $model->set('secured_system_prompt', '[ai_agent:agent_instructions]');
        }
        $model->set('default_information_tools', $config['default_information_tools']);
        break;

      case API::COMPONENT_TYPE_ELEMENT:
        $id = $component->getPluginId();
        $config = $component->getConfiguration();
        $elements = $model->get('tools');
        $elementUsageLimits = $model->get('tool_usage_limits');
        $elementSettings = $model->get('tool_settings');

        $elements[$id] = TRUE;
        $elementSettings[$id] = ['return_directly' => $config['return_directly'] ?? FALSE];
        $config += $this->ownerComponentDefaultConfig(Api::COMPONENT_TYPE_ELEMENT, $id);
        unset($config['return_directly']);
        foreach ($config as $key => $value) {
          [$plugin, $field] = explode('___', $key);
          $plugin = str_replace('__colon__', ':', $plugin);
          $value = match ($field) {
            'values' => empty($value) ? '' : explode("\n", $value),
            default => $value,
          };
          $elementUsageLimits[$id][$plugin][$field] = $value;
        }

        $model->set('tools', $elements);
        $model->set('tool_usage_limits', $elementUsageLimits);
        $model->set('tool_settings', $elementSettings);
        break;

    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeAddingComponents(ConfigEntityInterface $model): void {
    $elements = $model->get('tools') ?? [];
    foreach ($this->subModels as $subModel) {
      $subModel->save();
      $elements['ai_agents::ai_agent::' . $subModel->id()] = TRUE;
    }
    $model->set('tools', $elements);
  }

  /**
   * {@inheritdoc}
   */
  public function updateComponent(ConfigEntityInterface $model, Component $component): bool {
    return $this->addComponent($model, $component);
  }

  /**
   * {@inheritdoc}
   */
  public function usedComponentsInfo(ConfigEntityInterface $model): array {
    assert($model instanceof AiAgent);
    return [];
  }

  /**
   * Provides a list of all available function call plugins.
   *
   * @return array
   *   The list of all available function call plugins.
   */
  public function createAllInstances(): array {
    // @todo This should go into the FunctionCallPluginManager.
    static $instances;
    if (!isset($instances)) {
      $instances = [];
      foreach ($this->functionCallPluginManager()->getDefinitions() as $definition) {
        try {
          $instances[$definition['id']] = $this->functionCallPluginManager()
            ->createInstance($definition['id']);
        }
        catch (PluginException) {
          // Deliberately ignored.
        }
      }
    }
    return $instances;
  }

  /**
   * Provides a list of all available agents.
   *
   * @return array
   *   The list of all available agents.
   */
  public function getAllAgents(): array {
    static $agents;
    if (!isset($agents)) {
      $agents = [];
      foreach (AiAgent::loadMultiple() as $agent) {
        $agents[] = new ComponentWrapperPlugin(Api::COMPONENT_TYPE_SUBPROCESS, $agent->id(), [], $agent->label());
      }
    }
    return $agents;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultStorageMethod(): string {
    return ModelerApiSettings::STORAGE_OPTION_NONE;
  }

  /**
   * {@inheritdoc}
   */
  public function enforceDefaultStorageMethod(): bool {
    return TRUE;
  }

}
