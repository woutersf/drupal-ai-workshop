<?php

namespace Drupal\ai_search_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an AI form block.
 *
 * @Block(
 *   id = "ai_search_block_response",
 *   admin_label = @Translation("AI Search response"),
 *   category = @Translation("AI")
 * )
 */
class SearchResponseBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static($configuration, $plugin_id, $plugin_definition);
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block = [];
    $block['#settings'] = $this->configuration;
    $block['#theme'] = 'ai_search_block_response';
    $block['#output'] = ' ';
    return $block;
  }

}
