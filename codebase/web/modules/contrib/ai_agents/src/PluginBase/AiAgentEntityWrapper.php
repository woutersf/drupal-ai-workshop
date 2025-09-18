<?php

namespace Drupal\ai_agents\PluginBase;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Exception\AiFunctionCallingExecutionError;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_agents\AiAgentInterface;
use Drupal\ai_agents\Event\AgentResponseEvent;
use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Drupal\ai_agents\Output\StructuredResultData;
use Drupal\ai_agents\Output\StructuredResultDataInterface;
use Drupal\ai_agents\Plugin\AiFunctionCall\AiAgentWrapper;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface as PluginInterfacesAiAgentInterface;
use Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface;
use Drupal\ai_agents\Service\AgentHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * AI Agent Entity Wrapper.
 */
class AiAgentEntityWrapper implements PluginInterfacesAiAgentInterface, ConfigAiAgentInterface {

  /**
   * The AI Provider.
   *
   * @var \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy
   */
  protected $aiProvider;

  /**
   * The model.
   *
   * @var string
   */
  protected $modelName;

  /**
   * The AI configuration.
   *
   * @var array
   */
  protected $aiConfiguration;

  /**
   * The Task.
   *
   * @var \Drupal\ai_agents\Task\TaskInterface
   */
  protected $task;

  /**
   * The chat input.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatInput
   *   The chat input.
   */
  protected $chatInput;

  /**
   * Create directly.
   *
   * @var bool
   */
  protected $createDirectly;

  /**
   * The runner ID.
   *
   * @var string
   */
  protected $runnerId;

  /**
   * The tools used for action.
   *
   * @var array
   */
  protected $actionTools = [];

  /**
   * The tools used for context.
   *
   * @var array
   */
  protected $contextTools = [];

  /**
   * The question answered.
   *
   * @var string
   */
  protected $question;

  /**
   * Chat history.
   *
   * @var array
   */
  protected $chatHistory = [];

  /**
   * Tool results.
   *
   * @var array
   */
  protected $toolResults = [];

  /**
   * Amount of times looped.
   *
   * @var int
   */
  protected $looped = 0;

  /**
   * Looped enabled.
   *
   * @var bool
   */
  protected $loopedEnabled = TRUE;

  /**
   * The tokens.
   *
   * @var array
   */
  protected $tokens = [];

  /**
   * Set if the agent is finished.
   *
   * @var bool
   */
  protected $finished = FALSE;

  /**
   * An overridden set of function definitions.
   *
   * @var array|null
   */
  private ?array $functionsOverride = NULL;

  /**
   * The constructor.
   *
   * @param \Drupal\ai_agents\AiAgentInterface $aiAgent
   *   The AI agent interface.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The function call plugin manager.
   * @param \Drupal\ai_agents\Service\AgentHelper $agentHelper
   *   The agent helper.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderPluginManager
   *   The AI provider plugin manager.
   */
  public function __construct(
    protected AiAgentInterface $aiAgent,
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FunctionCallPluginManager $functionCallPluginManager,
    protected AgentHelper $agentHelper,
    protected Token $token,
    protected EventDispatcherInterface $eventDispatcher,
    protected AiProviderPluginManager $aiProviderPluginManager,
  ) {
  }

  /**
   * Get the AI agent entity.
   *
   * @return \Drupal\ai_agents\AiAgentInterface
   *   The AI agent interface.
   */
  public function getAiAgentEntity() {
    return $this->aiAgent;
  }

  /**
   * Set the AI agent interface.
   *
   * @param \Drupal\ai_agents\AiAgentInterface $aiAgent
   *   The AI agent interface.
   */
  public function setAiAgentEntity(AiAgentInterface $aiAgent) {
    $this->aiAgent = $aiAgent;
  }

  /**
   * {@inheritDoc}
   */
  public function setAiProvider($provider) {
    $this->aiProvider = $provider;
  }

  /**
   * {@inheritDoc}
   */
  public function getAiProvider() {
    return $this->aiProvider;
  }

  /**
   * {@inheritDoc}
   */
  public function getModelName() {
    return $this->modelName;
  }

  /**
   * {@inheritDoc}
   */
  public function setModelName($modelName) {
    $this->modelName = $modelName;
  }

  /**
   * {@inheritDoc}
   */
  public function getAiConfiguration() {
    return $this->aiConfiguration;
  }

  /**
   * {@inheritDoc}
   */
  public function setAiConfiguration($configuration) {
    $this->aiConfiguration = $configuration;
  }

  /**
   * {@inheritDoc}
   */
  public function getData() {
    return $this->contextTools;
  }

  /**
   * {@inheritDoc}
   */
  public function setData($data) {
    $this->contextTools = $data;
  }

  /**
   * {@inheritDoc}
   */
  public function getChatInput(): ChatInput {
    return $this->chatInput;
  }

  /**
   * {@inheritDoc}
   */
  public function setChatInput(ChatInput $chatInput) {
    $this->chatInput = $chatInput;
  }

  /**
   * {@inheritDoc}
   */
  public function getTask() {
    return $this->task;
  }

  /**
   * {@inheritDoc}
   */
  public function setTask($task) {
    $this->task = $task;
  }

  /**
   * {@inheritDoc}
   */
  public function getCreateDirectly() {
    return $this->createDirectly;
  }

  /**
   * {@inheritDoc}
   */
  public function setCreateDirectly($createDirectly) {
    $this->createDirectly = $createDirectly;
  }

  /**
   * Set if you want to loop.
   *
   * @param bool $enabled
   *   If you want to loop.
   */
  public function setLooped($enabled) {
    $this->loopedEnabled = $enabled;
  }

  /**
   * {@inheritDoc}
   */
  public function agentsCapabilities() {
    return [
      $this->aiAgent->id() => [
        'name' => $this->aiAgent->get('label'),
        'description' => $this->aiAgent->get('description'),
        'usage_instructions' => "",
        'inputs' => [
          'free_text' => [
            'name' => 'Prompt',
            'type' => 'string',
            'description' => 'The prompt with the instructions.',
            'default_value' => '',
          ],
        ],
        'outputs' => [
          'answers' => [
            'description' => 'The answers to the questions asked about.',
            'type' => 'string',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function isAvailable() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function getRunnerId() {
    return $this->runnerId;
  }

  /**
   * {@inheritDoc}
   */
  public function setRunnerId($runnerId) {
    $this->runnerId = $runnerId;
  }

  /**
   * {@inheritDoc}
   */
  public function getExtraTags() {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function inform() {
    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function determineSolvability() {
    // We need to set the default AI Provider if not set.
    if (!$this->aiProvider) {
      $defaults = $this->aiProviderPluginManager->getDefaultProviderForOperationType('chat_with_tools');
      $this->aiProvider = $this->aiProviderPluginManager->createInstance($defaults['provider_id']);
      $this->modelName = $defaults['model_id'];
    }
    $this->looped++;
    if ($this->looped > $this->aiAgent->get('max_loops')) {
      return PluginInterfacesAiAgentInterface::JOB_NOT_SOLVABLE;
    }
    // Get the system prompt.
    $system_prompt = $this->getSystemPrompt();
    // Check if someone wants to change something.
    $event = new BuildSystemPromptEvent($system_prompt, $this->aiAgent->id(), $this->tokens);
    $this->eventDispatcher->dispatch($event, BuildSystemPromptEvent::EVENT_NAME);
    // Set possible new values.
    $system_prompt = $event->getSystemPrompt();
    $this->tokens = $event->getTokens();
    // Run tokens to replace.
    $system_prompt = $this->applyTokens($system_prompt);
    $user_prompt = '';
    if ($this->task) {
      $user_prompt = $this->agentHelper->getFullContextOfTask($this->task);
    }

    $this->aiProvider->setChatSystemRole($system_prompt);

    $functions = $this->getFunctions();

    // Add the final message.
    if ($this->looped == 1) {
      $this->setChatMessages();
    }

    // We need to append the tool results if any.
    if (count($this->contextTools)) {
      foreach ($this->contextTools as $tool) {
        try {
          $this->executeTool($tool);
          $output = $tool->getReadableOutput();
        }
        catch (ContextException $exception) {
          $output = strip_tags($exception->getMessage());
        }
        catch (AiFunctionCallingExecutionError $exception) {
          $output = strip_tags($exception->getMessage());
        }
        $this->toolResults[] = $tool;
        // We need to check so its should not be returned.
        if ($this->toolShouldReturnDirectly($tool)) {
          $this->chatHistory[] = new ChatMessage('tool', $output);
          $this->question = $output;
          return PluginInterfacesAiAgentInterface::JOB_SOLVABLE;
        }
        $message = new ChatMessage('tool', $output);
        $message->setToolsId($tool->getToolsId());
        $this->chatHistory[] = $message;
      }
    }

    // Reset all tools between runs.
    $this->actionTools = [];
    $this->contextTools = [];

    $tags = [
      'ai_agents',
      'ai_agents_' . $this->aiAgent->id(),
      'ai_agents_prompt_' . $this->aiAgent->id(),
    ];
    if ($this->runnerId) {
      $tags[] = 'ai_agents_runner_' . $this->runnerId;
    }

    $input = new ChatInput($this->chatHistory);
    if (count($functions) && count($functions['normalized'])) {
      $input->setChatTools(new ToolsInput($functions['normalized']));
    }

    $return = $this->aiProvider->chat($input, $this->modelName, $tags);
    $response = $return->getNormalized();
    // Trigger the response event.
    $event = new AgentResponseEvent(
      $this,
      $system_prompt,
      $this->aiAgent->id(),
      $user_prompt,
      $this->chatHistory,
      $return,
      $this->looped,
    );

    $this->eventDispatcher->dispatch($event, AgentResponseEvent::EVENT_NAME);

    $this->chatHistory[] = $response;

    $tools = $response->getTools();

    if (!empty($tools)) {
      foreach ($tools as $tool) {
        $function = $this->functionCallPluginManager->convertToolResponseToObject($tool);
        $this->contextTools[] = $function;
      }
      // If tools are available, we should run this again filled out.
      if ($this->loopedEnabled) {
        return $this->determineSolvability();
      }
    }
    else {
      $this->finished = TRUE;
    }
    $this->question = $response->getText();
    return PluginInterfacesAiAgentInterface::JOB_SOLVABLE;
  }

  /**
   * {@inheritDoc}
   */
  public function hasAccess() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function askQuestion() {
    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function solve() {
    return $this->question;
  }

  /**
   * {@inheritDoc}
   */
  public function approveSolution() {
    $this->solve();
  }

  /**
   * {@inheritDoc}
   */
  public function getTokenContexts(): array {
    return $this->tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function setTokenContexts(array $tokens): void {
    $this->tokens = $tokens;
  }

  /**
   * {@inheritDoc}
   */
  public function getStructuredOutput(): StructuredResultDataInterface {
    return new StructuredResultData();
  }

  /**
   * {@inheritDoc}
   */
  public function setUserInterface($userInterface, array $extraTags = []) {
  }

  /**
   * {@inheritDoc}
   */
  public function rollback() {
  }

  /**
   * {@inheritDoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritDoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritDoc}
   */
  public function getConfiguration() {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function setConfiguration(array $configuration) {
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function getId() {
    return $this->aiAgent->id();
  }

  /**
   * {@inheritDoc}
   */
  public function getModuleName() {
    return 'ai_agent';
  }

  /**
   * {@inheritDoc}
   */
  public function agentsNames() {
    return [
      $this->aiAgent->get('label'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function answerQuestion() {
    return $this->question;
  }

  /**
   * Set the input chat history.
   */
  public function setChatMessages() {
    if (!empty($this->chatInput)) {
      foreach ($this->chatInput->getMessages() as $message) {
        $this->chatHistory[] = $message;
      }
    }
    elseif (!empty($this->task)) {
      $user_prompt = $this->agentHelper->getFullContextOfTask($this->task);
      // Get possible images.
      $images = [];
      foreach ($this->task->getFiles() as $file) {
        // Check if image.
        if (strpos($file->filemime->value, 'image') !== FALSE) {
          $image = new ImageFile();
          $image->setFileFromFile($file);
          $images[] = $image;
        }
      }
      $this->chatHistory[] = new ChatMessage('user', $user_prompt, $images);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getToolResults(bool $recursive = FALSE): array {
    // Check if any of the tool results are sub agents.
    if ($recursive) {
      $tools = [];
      foreach ($this->toolResults as $tool) {
        if ($tool instanceof AiAgentWrapper) {
          // If it is an sub agent, we need to get the tool results from it.
          $sub_tools = $tool->getToolResults(TRUE);
          $tools = array_merge($tools, $sub_tools);
        }
        $tools[] = $tool;
      }
      return $tools;
    }
    return $this->toolResults;
  }

  /**
   * {@inheritDoc}
   */
  public function getToolResultsByPluginId(string $plugin_id, bool $recursive = FALSE): array {
    $results = [];
    foreach ($this->getToolResults($recursive) as $tool) {
      if ($tool instanceof ExecutableFunctionCallInterface && $tool->getPluginId() == $plugin_id) {
        $results[] = $tool;
      }
    }
    return $results;
  }

  /**
   * {@inheritDoc}
   */
  public function getToolResultsByClassName(string $class_name, bool $recursive = FALSE): array {
    $results = [];
    foreach ($this->getToolResults($recursive) as $tool) {
      if ($tool instanceof ExecutableFunctionCallInterface && $tool instanceof $class_name) {
        $results[] = $tool;
      }
    }
    return $results;
  }

  /**
   * Helper function to render the system prompt.
   *
   * @return string
   *   The system prompt.
   */
  public function getSystemPrompt() {
    $dynamic = $this->getDefaultInformationTools();
    $secured_system_prompt = $this->aiAgent->get('secured_system_prompt');
    // If its empty, we need to set the token.
    if (empty($secured_system_prompt)) {
      $secured_system_prompt = "[ai_agent:agent_instructions]";
    }
    // Apply the agent instructions token.
    $prompt = $this->applyTokens($secured_system_prompt);
    return $prompt . "\n\n" . $dynamic;
  }

  /**
   * Helper function for getting the default information.
   *
   * @return string
   *   The default information.
   */
  public function getDefaultInformationTools() {
    $tools_yaml = $this->applyTokens($this->aiAgent->get('default_information_tools') ?? '[]');
    $data = Yaml::parse($tools_yaml);
    $dynamic = "This is the ";
    if ($this->looped == 1) {
      $dynamic .= "first ";
    }
    elseif ($this->looped == 2) {
      $dynamic .= "second ";
    }
    elseif ($this->looped == 3) {
      $dynamic .= "third ";
    }
    else {
      $dynamic .= $this->looped . "th ";
    }
    $dynamic .= "time that this agent has been run. \n";

    if (isset($data)) {
      foreach ($data as $values) {
        if (isset($values['available_on_loop']) && is_array($values['available_on_loop'])) {
          if (!in_array($this->looped, $values['available_on_loop'])) {
            continue;
          }
        }
        /** @var \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface $tool */
        $tool = $this->functionCallPluginManager->createInstance($values['tool']);
        foreach ($values['parameters'] as $parameter_key => $parameter_value) {
          if ($parameter_value) {
            $tool->setContextValue($parameter_key, $parameter_value);
          }
        }
        $this->executeTool($tool);
        // Check if this should be in the dynamic prompt or in the history.
        if (!empty($values['available_on_loop']) && is_array($values['available_on_loop']) && in_array($this->looped, $values['available_on_loop'])) {
          // Add the executed tool to the chat history.
          $this->chatHistory[] = new ChatMessage('user', $this->createContextMessage($tool, $values));
        }
        else {
          // Store in the system prompt.
          $dynamic .= $this->createContextMessage($tool, $values);
        }
      }
    }

    return $dynamic;
  }

  /**
   * Helper function for getting functions.
   *
   * @return array
   *   The functions.
   */
  public function getFunctions() {
    // Use overridden functions, if set.
    $function_definitions = $this->functionsOverride['tools'] ?? $this->aiAgent->get('tools');
    $usage_limits = $this->functionsOverride['tool_usage_limits'] ?? $this->aiAgent->get('tool_usage_limits');

    $functions = [];
    foreach ($function_definitions as $function_call_name => $value) {
      if ($value) {
        /** @var \Drupal\ai\Service\FunctionCalling\FunctionCallInterface $function_call */
        $function_call = $this->functionCallPluginManager->createInstance($function_call_name);
        $this->applyToolUsageLimitsToContext($function_call);
        $functions['normalized'][$function_call->getFunctionName()] = $function_call->normalize();
        // Check if we need to hide some property from the LLM.
        if ($usage_limits[$function_call->getPluginId()] ?? NULL) {
          foreach ($usage_limits[$function_call->getPluginId()] as $property_name => $limit) {
            if ($limit['action'] == 'force_value' && !empty($limit['hide_property'])) {
              // Unset the property if it is set to be hidden.
              $functions['normalized'][$function_call->getFunctionName()]->unsetProperty($property_name);
            }
          }
        }
        $functions['object'][$function_call->getFunctionName()] = $function_call;
      }
    }
    return $functions;
  }

  /**
   * Set function overrides.
   *
   * @param array{tools: array<string, bool>, tool_usage_limits: array<string, array<string, array{action: string, hide_property: bool, values: scalar[]}>>, tool_settings: array<string, array{return_directly: bool}>} $functions
   *   An array of function overrides.
   */
  public function overrideFunctions(array $functions): void {
    $this->functionsOverride = $functions;
  }

  /**
   * Reset function overrides.
   */
  public function resetFunctions(): void {
    $this->functionsOverride = NULL;
  }

  /**
   * Helper function for checking if a tool returns early.
   *
   * @return bool
   *   True if the tool should return early.
   */
  public function toolShouldReturnDirectly(ExecutableFunctionCallInterface $tool): bool {
    // Use overridden functions, if set.
    $settings = $this->functionsOverride['tool_settings'] ?? $this->aiAgent->get('tool_settings');

    if (isset($settings[$tool->getPluginId()]['return_directly'])) {
      return $settings[$tool->getPluginId()]['return_directly'];
    }
    return FALSE;
  }

  /**
   * Applies tool usage limits to the function schema.
   *
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallInterface $function_call
   *   The function call plugin.
   */
  protected function applyToolUsageLimitsToContext(FunctionCallInterface $function_call) {
    // Use overridden functions, if set.
    $tool_limits = $this->functionsOverride['tool_usage_limits'] ?? $this->aiAgent->get('tool_usage_limits');

    // Process each property with limits.
    foreach ($tool_limits[$function_call->getPluginId()] ?? [] as $property_name => $limit) {
      $context_definition = $function_call->getContextDefinition($property_name);

      // Apply token in values if an action is set.
      if ($limit['action']) {
        $values = array_map(
          fn ($value) => $this->applyTokens($value),
          array_filter(
            $limit['values'] ?? [],
            fn ($value) => $value !== NULL && $value !== '',
          ),
        );

        // Apply restrictions based on the action.
        switch ($limit['action']) {
          // Set constant value (forced value).
          case 'force_value':
            if (isset($values[0])) {
              $context_value = $context_definition->getDataType() === 'list' ? $values : $values[0];
              $context_definition->addConstraint('FixedValue', $context_value);
              $context_definition->setDefaultValue($context_value);
            }
            $context_definition->setRequired(FALSE);
            break;

          case 'only_allow':
            $context_definition->addConstraint('Choice', $values);
            break;
        }
      }
    }
  }

  /**
   * Create a context message from the tool.
   *
   * @param \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface $tool
   *   The tool to create the message from.
   * @param array $values
   *   The values to create the message from.
   */
  public function createContextMessage(ExecutableFunctionCallInterface $tool, array $values): string {
    // Store in the system prompt.
    $message = "The following is information that is important as context: \n";
    $message .= "-----------------------------------------------\n";
    $message .= "Tool processed: " . $tool->getFunctionName() . "\n";
    $message .= "Values: " . $values['label'] . "\n";
    if (!empty($values['description'])) {
      $message .= "Description of values: " . $values['description'] . "\n";
    }

    $message .= "Results: \n";
    $message .= $tool->getReadableOutput();
    $message .= "\n";
    $message .= "-----------------------------------------------\n\n";
    return $message;
  }

  /**
   * Apply the tokens to the system prompt.
   *
   * @param string $prompt
   *   The prompt to apply the tokens to.
   *
   * @return string
   *   The prompt with the tokens applied.
   */
  public function applyTokens(string $prompt): string {
    $tokens = [
      'user' => $this->currentUser,
      'ai_agent' => $this->aiAgent,
    ];
    // Add dynamical tokens.
    $tokens = array_merge($tokens, $this->tokens);
    return $this->token->replace($prompt, $tokens);
  }

  /**
   * Helper function to execute a tool.
   *
   * @param \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface $tool
   *   The tool to execute.
   */
  public function executeTool(ExecutableFunctionCallInterface $tool) {
    // We set token context if its an AiAgentWrapper.
    if ($tool instanceof AiAgentWrapper) {
      $tool->setTokens($this->tokens);
    }
    $this->validateTool($tool);
    $tool->execute();
  }

  /**
   * Validate the tool against any possible restrictions before running.
   *
   * @param \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface $tool
   *   The tool to validate values from.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   *   Thrown when context constraints are violated.
   */
  public function validateTool(ExecutableFunctionCallInterface $tool): void {
    $violations = $tool->validateContexts();
    if (count($violations)) {
      throw new ContextException(implode("\n", array_map(
        fn (ConstraintViolationInterface $violation) => new FormattableMarkup('Invalid value for @property in @function: @violation', [
          // @todo Consider using a property name when the context validator is
          //   fixed.
          // @see https://www.drupal.org/project/drupal/issues/3153847
          '@property' => $violation->getRoot()->getDataDefinition()->getLabel(),
          '@function' => $tool->getPluginId(),
          '@violation' => $violation->getMessage(),
        ]),
        (array) $violations->getIterator(),
      )));
    }
  }

  /**
   * Change user permissions temporarily if needed.
   */
  public function changeUserPermissions() {
    // Always reload the user to make sure no one has intercepted the user.
    /** @var \Drupal\user\Entity\User $user */
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $exclude_users_role = $this->aiAgent->get('exclude_users_role');
    // If we should remove all the roles.
    if ($exclude_users_role) {
      /** @var \Drupal\user\Entity\User $user */
      $user = $this->currentUser;
      foreach ($user->getRoles() as $role) {
        if ($role != 'anonymous') {
          $user->removeRole($role);
        }
      }
    }
    // If we should add masquerade roles.
    $masquerade_roles = $this->aiAgent->get('masquerade_roles');
    if ($masquerade_roles) {
      /** @var \Drupal\user\Entity\User $user */
      $user = $this->currentUser;
      foreach ($masquerade_roles as $role) {
        $user->addRole($role);
      }
    }
    // Set the user as the current user.
    $this->currentUser->setAccount($user);
  }

  /**
   * Reset the user permissions after solving a tool.
   */
  public function resetUserPermissions() {
    // Reload the user from entity.
    /** @var \Drupal\user\Entity\User $user */
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    // Set the user as the current user.
    $this->currentUser->setAccount($user);
  }

  /**
   * Returns the chat history of the agent.
   *
   * @return array
   *   An array of chat messages and tool results.
   */
  public function getChatHistory(): array {
    return $this->chatHistory;
  }

  /**
   * Sets the chat history of the agent.
   *
   * @param array $history
   *   An array of chat messages and tool results to restore.
   */
  public function setChatHistory(array $history): void {
    $this->chatHistory = $history;
  }

  /**
   * {@inheritDoc}
   */
  public function isFinished(): bool {
    return $this->finished;
  }

  /**
   * {@inheritDoc}
   */
  public function toArray(): array {
    // For the tool results, we need to just store the readable output for now.
    $tool_results = [];
    $agent_results = [];
    foreach ($this->toolResults as $key => $tool) {
      if ($tool instanceof AiAgentWrapper) {
        /** @var \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $sub_agent */
        $sub_agent = $tool->getAgent();
        $agent_results[] = [
          'key' => $key,
          'result' => $tool->getReadableOutput(),
          'agent_id' => $sub_agent->getAiAgentEntity()->id(),
          'dump' => $sub_agent->toArray(),
        ];
      }
      if ($tool instanceof ExecutableFunctionCallInterface) {
        $tool_results[] = [
          'key' => $key,
          'result' => $tool->getReadableOutput(),
        ];
      }
    }
    // Store the context tools as well.
    $context_tools = [];
    foreach ($this->contextTools as $tool) {
      if ($tool instanceof ExecutableFunctionCallInterface) {
        $context_tools[] = [
          'tools_id' => $tool->getToolsId(),
          'function_name' => $tool->getFunctionName(),
        ];
      }
    }
    $chat_history = [];
    foreach ($this->chatHistory as $message) {
      if ($message instanceof ChatMessage) {
        $chat_history[] = $message->toArray();
      }
    }
    return [
      'chat_history' => $chat_history,
      'tool_results' => $tool_results,
      'agent_results' => $agent_results,
      'context_tools' => $context_tools,
      'looped' => $this->looped,
      'looped_enabled' => $this->loopedEnabled,
      'tokens' => $this->tokens,
      'runner_id' => $this->runnerId,
      'provider_id' => $this->aiProvider->getPluginId(),
      'model_name' => $this->modelName,
      'ai_configuration' => $this->aiConfiguration,
      'create_directly' => $this->createDirectly,
      'functions_override' => $this->functionsOverride,
      'question' => $this->question,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function fromArray(array $data): void {
    $tools = [];
    $tool_results = [];
    $chat_history = [];
    foreach ($data['chat_history'] ?? [] as $message) {
      if (is_array($message)) {
        $new_message = new ChatMessage($message['role'], $message['text'], $message['images'] ?? []);
        if ($message['tool_id'] ?? NULL) {
          $new_message->setToolsId($message['tool_id']);
        }
        if (!empty($message['tools'])) {
          $message_tools = [];
          foreach ($message['tools'] as $tool) {
            $function = $this->functionCallPluginManager->getFunctionCallFromFunctionName($tool['function']['name']);
            $function->setToolsId($tool['id'] ?? NULL);
            $normalized_tool = $function->normalize();
            $arguments = json_decode($tool['function']['arguments'] ?? '{}', TRUE);
            $message_tools[] = new ToolsFunctionOutput($normalized_tool, $tool['id'] ?? NULL, $arguments);
          }
          $new_message->setTools($message_tools);
        }
        $chat_history[] = $new_message;
      }
    }
    $data['chat_history'] = $chat_history;
    if (!empty($data['chat_history'])) {
      $tmp_tools = $this->getContextTools($data['chat_history'], $data['tool_results'] ?? []);
      foreach ($tmp_tools as $tool) {
        if ($tool['result']) {
          $tool_results[] = $tool['function'];
        }
        $tools[] = $tool['function'];
      }
    }
    // We load the context tools from the tools.
    $context_tools = [];
    foreach ($data['context_tools'] ?? [] as $tool) {
      if (!empty($tool['tools_id'])) {
        foreach ($tools as $context_tool) {
          if ($context_tool->getToolsId() == $tool['tools_id']) {
            $context_tools[] = $context_tool;
          }
        }
      }
    }
    // Agent results are special, since we have to load the whole agent.
    if (isset($data['agent_results']) && is_array($data['agent_results'])) {
      foreach ($data['agent_results'] as $agent_result) {
        if (isset($agent_result['dump']) && isset($agent_result['agent_id'])) {
          // We match on set key, since this array might be smaller then tools.
          if (isset($tools[$agent_result['key']])) {
            /** @var \Drupal\ai_agents\PluginBase\AiAgentWrapper $tool */
            $tool = $tools[$agent_result['key']];
            $tool->fromArray($agent_result['dump']);
            $tools[$agent_result['key']] = $tool;
          }
        }
      }
    }
    $this->chatHistory = $data['chat_history'] ?? [];
    $this->toolResults = $tool_results;
    $this->looped = $data['looped'] ?? 0;
    $this->loopedEnabled = $data['looped_enabled'] ?? TRUE;
    $this->tokens = $data['tokens'] ?? [];
    $this->runnerId = $data['runner_id'] ?? '';
    $this->aiProvider = $this->aiProviderPluginManager->createInstance($data['provider_id']);
    $this->modelName = $data['model_name'] ?? '';
    $this->aiConfiguration = $data['ai_configuration'] ?? [];
    $this->createDirectly = $data['create_directly'] ?? FALSE;
    $this->functionsOverride = $data['functions_override'] ?? NULL;
    $this->contextTools = $context_tools;
    $this->question = $data['question'] ?? '';
  }

  /**
   * Get a context tool from the tools array.
   *
   * @param array $messages
   *   The messages array.
   * @param array $tool_results
   *   The tool results array.
   *
   * @return \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface[]|null
   *   The tool function call or null if not found.
   */
  protected function getContextTools(array $messages, array $tool_results): array|NULL {
    $tools = [];
    $i = 0;
    foreach ($messages as $message) {
      $message = $message->toArray();
      if (!empty($message['tools_id'])) {
        // Reset the tools on tools_id, as it means we are in a new message.
        $tools = [];
      }
      if (!empty($message['tools'])) {
        foreach ($message['tools'] as $tool) {
          try {
            /** @var \Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface $function */
            $function = $this->functionCallPluginManager->getFunctionCallFromFunctionName($tool['function']['name']);
          }
          catch (\Exception $e) {
            // If the function does not exist, we return null.
            return NULL;
          }
          $function->setToolsId($tool['id'] ?? NULL);
          // Also set the arguments if they are provided.
          if (isset($tool['function']['arguments'])) {
            $arguments = Json::decode($tool['function']['arguments']);
            foreach ($arguments as $key => $value) {
              $function->setContextValue($key, $value);
            }
          }
          $result = FALSE;
          // If the tool result is available, we set it.
          if (isset($tool_results[$i])) {
            $result = TRUE;
            $function->setOutput($tool_results[$i]['result']);
          }
          $tools[] = [
            'result' => $result,
            'function' => $function,
          ];
          $i++;
        }
      }
    }
    return $tools;
  }

}
