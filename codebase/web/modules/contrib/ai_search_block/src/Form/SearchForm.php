<?php

namespace Drupal\ai_search_block\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an AI search form.
 */
class SearchForm extends FormBase {

  use DependencySerializationTrait;

  /**
   * Construct the chat.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatcher
   *   The route match.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): SearchForm|static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_search_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $response_id = Html::getId($form_state->getBuildInfo()['block_id'] . '-response');
    $search_block_config = $form_state->getBuildInfo()['search_config'];
    $form['stream'] = [
      '#type' => 'hidden',
      '#value' => $search_block_config['stream'] == 1 ? 'true' : 'false',
    ];
    $form['block_id'] = [
      '#type' => 'hidden',
      '#value' => $search_block_config['block_id'],
    ];
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'ai-search-form-wrapper',
        'class' => ['ai-search-form-wrapper'],
      ],
    ];
    $form['wrapper']['query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ask me a question'),
      '#title_display' => 'invisible',
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $search_block_config['placeholder'],
        'class' => ['chat-form-query'],
        'autocomplete' => 'off',
      ],

      '#rows' => 1,
    ];

    $form['wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $search_block_config['submit_text'],
      '#attributes' => [
        'data-ai-ajax' => $response_id,
        'class' => ['search-form-send'],
      ],
    ];

    $form['message'] = [
      '#type' => 'markup',
      '#markup' => '<div class="ai-search-block-result-message"> </div>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
