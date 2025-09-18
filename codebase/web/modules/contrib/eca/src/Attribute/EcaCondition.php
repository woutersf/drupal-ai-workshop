<?php

namespace Drupal\eca\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an ECA Condition attribute object.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class EcaCondition extends Plugin {

  /**
   * Constructs an ECA Condition attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The label of the condition.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $category
   *   (optional) The category under which the condition should be listed in the
   *   UI.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param array $context_definitions
   *   An array of context definitions describing the context used by the
   *   plugin. The array is keyed by context names.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   The description of the condition.
   * @param bool $no_docs
   *   If set to TRUE, do not expose this condition in the ECA Guide.
   * @param string|null $version_introduced
   *   A version string indicating when this condition was first introduced.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $category = NULL,
    public readonly ?string $deriver = NULL,
    public readonly array $context_definitions = [],
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly bool $no_docs = FALSE,
    public readonly ?string $version_introduced = NULL,
  ) {}

}
