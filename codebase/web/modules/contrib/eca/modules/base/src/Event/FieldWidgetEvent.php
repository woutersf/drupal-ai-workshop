<?php

namespace Drupal\eca_base\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides a field widget event.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 *
 * @package Drupal\eca_base\Event
 */
class FieldWidgetEvent extends Event {

  /**
   * The value of the field widget.
   *
   * @var array|string|null
   */
  protected array|string|null $widgetValue = NULL;

  /**
   * Constructs a FieldWidgetEvent.
   */
  public function __construct(
    protected string $eventId,
    protected ContentEntityInterface $entity,
    protected string $fieldName,
    protected string $fieldKey,
  ) {}

  /**
   * Gets the field widget ID.
   *
   * @return string
   *   The field widget ID.
   */
  public function getEventId(): string {
    return $this->eventId;
  }

  /**
   * Gets the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Gets the field name.
   *
   * @return string
   *   The field name.
   */
  public function getFieldName(): string {
    return $this->fieldName;
  }

  /**
   * Gets the field key.
   *
   * @return string
   *   The field key.
   */
  public function getFieldKey(): string {
    return $this->fieldKey;
  }

  /**
   * Get the field widget value.
   *
   * @return array|string|null
   *   The field widget value.
   */
  public function getWidgetValue(): array|string|null {
    return $this->widgetValue;
  }

  /**
   * Set the field widget value.
   *
   * @param string $widgetValue
   *   The field widget value.
   */
  public function setWidgetValue(array|string $widgetValue): void {
    $this->widgetValue = $widgetValue;
  }

}
