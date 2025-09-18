<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests for ECA action eca_list_remove plugin.
 *
 * @group eca
 * @group eca_base
 */
class ListRemoveTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_base',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(static::$modules);
  }

  /**
   * Tests list remove action by value.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function testListRemoveByValue(): void {
    /** @var \Drupal\eca\Token\TokenInterface $tokenServices */
    $tokenServices = \Drupal::service('eca.token_services');
    /** @var \Drupal\Core\Action\ActionManager $actionManager */
    $actionManager = \Drupal::service('plugin.manager.action');

    $tokenServices->addTokenData('list', ['a', 'b', 'c']);
    $config = [
      'list_token' => 'list',
      'method' => 'value',
      'value' => 'b',
      'token_name' => 'item',
    ];
    /** @var \Drupal\eca_base\Plugin\Action\TokenSetValue $action */
    $action = $actionManager->createInstance('eca_list_remove', $config);
    $this->assertTrue($action->access(NULL));
    $action->execute(NULL);
    $this->assertEquals(Yaml::encode([
      'a',
      'c',
    ]), $tokenServices->replaceClear('[list]'));
    $this->assertEquals('b', $tokenServices->replaceClear('[item]'));

    $tokenServices->addTokenData('list', ['a', 'b', 'c']);
    $tokenServices->addTokenData('item_to_remove', 'a');
    $config = [
      'list_token' => 'list',
      'method' => 'value',
      'value' => '[item_to_remove]',
      'token_name' => 'item',
    ];
    /** @var \Drupal\eca_base\Plugin\Action\TokenSetValue $action */
    $action = $actionManager->createInstance('eca_list_remove', $config);
    $this->assertTrue($action->access(NULL));
    $action->execute();
    $this->assertEquals(Yaml::encode([
      'b',
      'c',
    ]), $tokenServices->replace('[list]'));
    $this->assertEquals('a', $tokenServices->replaceClear('[item]'));
  }

}
