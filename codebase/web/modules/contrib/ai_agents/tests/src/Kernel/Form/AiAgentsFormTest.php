<?php

namespace Drupal\Tests\ai_agents\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_agents\Entity\AiAgent;
use Drupal\ai_agents\Form\AiAgentForm;

/**
 * Testing the AiAgent form submission and saving functionality.
 */
class AiAgentsFormTest extends KernelTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'ai',
    'ai_agents',
  ];

  /**
   * The agent base configuration for testing.
   *
   * @var array
   */
  protected $agentBase = [
    'id' => 'test_agent',
    'label' => 'Test Agent',
    'description' => 'This is a test agent.',
    'orchestration_agent' => FALSE,
    'system_prompt' => 'hello',
    'tools' => [],
    'triage_agent' => FALSE,
    'max_loops' => 5,
    'masquerade_roles' => [],
    'exclude_users_role' => FALSE,
  ];

  /**
   * Sets up the test environment.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('ai_agent');
  }

  /**
   * Tests system prompt saving with newlines.
   *
   * This test checks if the system prompt and default information tools
   * are saved correctly when they contain newlines.
   */
  public function testSaveWithNewlines() {
    // Create the entity to edit.
    $entity = AiAgent::create($this->agentBase);

    // Instantiate your custom form handler.
    $form_object = AiAgentForm::create(\Drupal::getContainer());
    $form_object->setEntity($entity);

    // Optionally build the form first (if needed).
    $form = [];
    /** @var \Drupal\Core\Form\FormStateInterface $form_state */
    $form_state = new FormState();

    // Actual testing.
    $form_state->setValue('system_prompt', "Adding a system prompt.\r\nOver two rows.");
    $form_state->setValue('default_information_tools', "get_drush_commands:\r\n  label: 'Drush Commands'\r\n  description: 'The list of Drush command'\r\n");

    // Now call save().
    $form_object->save($form, $form_state);

    // Reload entity from storage to verify it was saved.
    $saved = \Drupal::entityTypeManager()->getStorage('ai_agent')->load($entity->id());

    $this->assertNotEmpty($saved, 'Entity was saved.');
    $this->assertEquals('Test Agent', $saved->label(), 'Agent was saved correctly.');
    $this->assertEquals("Adding a system prompt.\nOver two rows.", $saved->get('system_prompt'), 'System prompt was saved correctly with newlines.');
    $this->assertEquals("get_drush_commands:\n  label: 'Drush Commands'\n  description: 'The list of Drush command'\n", $saved->get('default_information_tools'), 'Default information tools were saved correctly with newlines.');
  }

  /**
   * Testing to set tool usage limits.
   */
  public function testSetToolUsageLimits() {
    // Create the entity to edit.
    $entity = AiAgent::create($this->agentBase);

    // Instantiate your custom form handler.
    $form_object = AiAgentForm::create(\Drupal::getContainer());
    $form_object->setEntity($entity);

    // Optionally build the form first (if needed).
    $form = [];
    /** @var \Drupal\Core\Form\FormStateInterface $form_state */
    $form_state = new FormState();

    // Set a tool usage.
    $tool_usage = [
      'mock_tool' => [
        'return_directly' => 0,
        'property_restrictions' => [
          // Do nothing with this property.
          'property1' => [
            'action' => NULL,
            'hide_property' => 0,
            'values' => NULL,
          ],
          // Test so that these are split up correctly.
          'property2' => [
            'action' => 'only_allow',
            'hide_property' => 0,
            'values' => "value1\r\nvalue2\r\n",
          ],
        ],
      ],
      'another_tool' => [
        'return_directly' => 1,
        'property_restrictions' => [
          'property3' => [
            'action' => 'only_allow',
            'hide_property' => 0,
            // Try adding some empty multiple values.
            'values' => "value3\r\nvalue4\r\n\r\n",
          ],
        ],
      ],
    ];
    $form_state->setValue('tool_usage', $tool_usage);

    // Now call save().
    $form_object->save($form, $form_state);

    // Reload entity from storage to verify it was saved.
    $saved = \Drupal::entityTypeManager()->getStorage('ai_agent')->load($entity->id());

    // Make sure everything was saved correctly.
    $this->assertNotEmpty($saved, 'Entity was saved.');
    $this->assertEquals('value1', $saved->get('tool_usage_limits')['mock_tool']['property2']['values'][0], 'Tool usage limits were saved correctly with newlines.');
    $this->assertEquals('value2', $saved->get('tool_usage_limits')['mock_tool']['property2']['values'][1], 'Tool usage limits were saved correctly with newlines.');
    $this->assertEquals('value3', $saved->get('tool_usage_limits')['another_tool']['property3']['values'][0], 'Tool usage limits were saved correctly with newlines.');
    $this->assertEquals('value4', $saved->get('tool_usage_limits')['another_tool']['property3']['values'][1], 'Tool usage limits were saved correctly with newlines.');
    $this->assertCount(2, $saved->get('tool_usage_limits')['mock_tool']['property2']['values'], 'Tool usage limits count is correct for property2.');
    $this->assertCount(2, $saved->get('tool_usage_limits')['another_tool']['property3']['values'], 'Tool usage limits count is correct for property3.');
  }

}
