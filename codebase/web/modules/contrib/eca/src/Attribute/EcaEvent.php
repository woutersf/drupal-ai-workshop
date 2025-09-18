<?php

namespace Drupal\eca\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an ECA Condition attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EcaEvent extends Plugin {

  /**
   * Constructs an ECA Event attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param string $event_name
   *   Name of the event being covered.
   * @param string $event_class
   *   Event class to which this ECA event subscribes.
   * @param int|null $subscriber_priority
   *   Priority when subscribing to the covered event.
   * @param int|null $tags
   *   Tags for event characterization.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The label of the event.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $category
   *   (optional) The category under which the event should be listed in the UI.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The description of the event.
   * @param bool $no_docs
   *   If set to TRUE, do not expose this event in the ECA Guide.
   * @param string|null $version_introduced
   *   A version string indicating when this event was first introduced.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?string $event_name = NULL,
    public readonly ?string $event_class = NULL,
    public readonly ?int $subscriber_priority = NULL,
    public readonly ?int $tags = NULL,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $category = NULL,
    public readonly ?string $deriver = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly bool $no_docs = FALSE,
    public readonly ?string $version_introduced = NULL,
  ) {}

}
