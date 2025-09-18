<?php

namespace Drupal\eca_base\Plugin\FieldWidgetAction;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Event\TriggerEvent;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base field widget for ECA.
 */
#[FieldWidgetAction(
  id: 'eca_field_widget',
  label: new TranslatableMarkup('ECA Field Widget'),
  widget_types: [],
  field_types: [],
  category: new TranslatableMarkup('ECA Field Widgets'),
  deriver: 'Drupal\eca_base\Plugin\FieldWidgetAction\EcaFieldWidgetDeriver',
)]
class EcaFieldWidget extends FieldWidgetActionBase {

  /**
   * The event dispatcher.
   *
   * @var \Drupal\eca\Event\TriggerEvent
   */
  protected TriggerEvent $triggerEvent;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->triggerEvent = $container->get('eca.trigger_event');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'executeWidget';
  }

  /**
   * Ajax handler for ECA field widget.
   */
  public function executeWidget(array &$form, FormStateInterface $form_state): array|AjaxResponse {
    $id = substr($this->configuration['plugin_id'], 17);
    $array_parents = $form_state->getTriggeringElement()['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = static::FORM_ELEMENT_PROPERTY;
    $target_element = NestedArray::getValue($form, $array_parents);
    $selector = $target_element ? $target_element['#attributes']['data-drupal-selector'] : '';
    $fieldName = $array_parents[0];
    $fieldKey = $array_parents[2] ?? 0;
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = static::buildEntity($form, $form_state);

    // Run the ECA model for the entity.
    /** @var \Drupal\eca_base\Event\FieldWidgetEvent $event */
    $event = $this->triggerEvent->dispatchFromPlugin('eca_base:eca_field_widget', $id, $entity, $fieldName, $fieldKey);

    $value = $event->getWidgetValue();
    if (method_exists($this, 'returnSuggestions')) {
      return $this->returnSuggestions($value, $selector);
    }
    if ($value === NULL) {
      // Ensure the widget has enough elements for all values.
      $form[$fieldName]['widget']['#items_count'] = count($entity->{$fieldName});
      if (isset($entity->{$fieldName}[$fieldKey])) {
        $item = $entity->{$fieldName}[$fieldKey];
        if ($item->value) {
          $value = $item->value;
        }
      }
    }
    if ($value) {
      $form[$fieldName]['widget'][$fieldKey]['value']['#value'] = $value;
    }
    return $form[$fieldName];
  }

}
