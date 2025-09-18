<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\Action\ListOperationBase;

/**
 * Action to compare items in two lists.
 *
 * @Action(
 *   id = "eca_list_compare",
 *   label = @Translation("List: compare items"),
 *   description = @Translation("Compares the items in two simple lists (contained in tokens), returning the array of results."),
 * )
 */
class ListCompare extends ListOperationBase {

  /**
   * Prepares the list elements to make sure they are comparable.
   *
   * @param iterable $list
   *   The list of elements.
   *
   * @return array
   *   The list with prepared elements.
   */
  protected function prepareList(iterable $list): array {
    $result = [];
    foreach ($list as $value) {
      if ($value instanceof TypedDataInterface) {
        $result[] = $value->getValue();
      }
      else {
        $result[] = $value;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $result = [];
    $method = $this->configuration['method'];
    $name1 = $this->configuration['list_token'];
    $name2 = $this->configuration['secondary_list_token'];
    if ($this->tokenService->hasTokenData($name1) && $this->tokenService->hasTokenData($name2)) {
      $list1 = $this->tokenService->getTokenData($name1);
      if (is_iterable($list1)) {
        $list2 = $this->tokenService->getTokenData($name2);
        if (is_iterable($list2)) {
          $list1 = $this->prepareList($list1);
          $list2 = $this->prepareList($list2);
          switch ($method) {
            case 'array_diff':
              $result = $this->getDiff($list1, $list2);
              break;

            case 'array_intersect':
              $result = $this->getIntersect($list1, $list2);
              break;

          }
        }
      }
    }
    $this->tokenService->addTokenData($this->configuration['result_token_name'], $result);
  }

  /**
   * Receives a token and counts the contained items.
   *
   * @param iterable $list1
   *   First list to compare.
   * @param iterable $list2
   *   Secondary list to compare.
   *
   * @return array
   *   Result of the array_diff
   */
  protected function getDiff(iterable $list1, iterable $list2): array {
    $result = [];
    foreach ($list1 as $value1) {
      $found = FALSE;
      foreach ($list2 as $value2) {
        if ($value1 === $value2) {
          $found = TRUE;
          break;
        }
      }
      if (!$found) {
        $result[] = $value1;
      }
    }
    return $result;
  }

  /**
   * Receives a token and counts the contained items.
   *
   * @param iterable $list1
   *   First list to compare.
   * @param iterable $list2
   *   Secondary list to compare.
   *
   * @return array
   *   Result of the array_intersect
   */
  protected function getIntersect(iterable $list1, iterable $list2): array {
    $result = [];
    foreach ($list1 as $value1) {
      foreach ($list2 as $value2) {
        if ($value1 === $value2) {
          $result[] = $value1;
          break;
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'secondary_list_token' => '',
      'method' => 'array_diff',
      'result_token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['secondary_list_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token containing the secondary list'),
      '#description' => $this->t('Provide the name of the token that contains the secondary list in the comparison.'),
      '#default_value' => $this->configuration['secondary_list_token'],
      '#required' => TRUE,
      '#weight' => 5,
      '#eca_token_reference' => TRUE,
    ];
    $form['method'] = [
      '#type' => 'select',
      '#title' => $this->t('Array Function'),
      '#description' => $this->t('Returns an array of items found by the <a href="https://www.php.net/manual/en/ref.array.php">Array Function</a> selected.'),
      '#default_value' => $this->configuration['method'],
      '#required' => TRUE,
      '#weight' => 10,
      '#options' => [
        'array_diff' => $this->t('Find differences (array_diff)'),
        'array_intersect' => $this->t('Find common items (array_intersect)'),
      ],
    ];
    $form['result_token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of result token'),
      '#description' => $this->t('Provide the name of a new token where the resulting array should be stored.'),
      '#default_value' => $this->configuration['result_token_name'],
      '#required' => TRUE,
      '#weight' => 20,
      '#eca_token_reference' => TRUE,
    ];
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['list_token']['#title'] = $this->t('Token containing the primary list');
    $form['list_token']['#required'] = TRUE;
    $form['list_token']['#description'] = $this->t('Provide the name of the token that contains the primary list in the comparison.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['secondary_list_token'] = $form_state->getValue('secondary_list_token');
    $this->configuration['method'] = $form_state->getValue('method');
    $this->configuration['result_token_name'] = $form_state->getValue('result_token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
