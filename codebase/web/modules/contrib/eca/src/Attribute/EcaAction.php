<?php

namespace Drupal\eca\Attribute;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an extra Action attribute object for ECA.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EcaAction {

  /**
   * Constructs an ECA Action attribute.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The description of the action.
   * @param bool $no_docs
   *   If set to TRUE, do not expose this action in the ECA Guide.
   * @param string|null $version_introduced
   *   A version string indicating when this action was first introduced.
   */
  public function __construct(
    public ?TranslatableMarkup $description = NULL,
    public bool $no_docs = FALSE,
    public ?string $version_introduced = NULL,
  ) {}

  /**
   * Get an array with all defined properties.
   *
   * @return array
   *   The array with all defined properties.
   */
  public function properties(): array {
    $properties = [];
    if ($this->description) {
      $properties['description'] = $this->description;
    }
    if ($this->version_introduced) {
      $properties['version_introduced'] = $this->version_introduced;
    }
    $properties['no_docs'] = $this->no_docs;
    return $properties;
  }

}
