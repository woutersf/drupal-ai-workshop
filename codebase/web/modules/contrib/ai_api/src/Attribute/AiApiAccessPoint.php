<?php

declare(strict_types=1);

namespace Drupal\ai_api\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The ai access point attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AiApiAccessPoint extends AttributeBase {

  /**
   * Constructs a new AiApiAccessPoint instance.
   *
   * @param string $id
   *   The plugin ID. There are some implementation bugs that make the plugin
   *   available only if the ID follows a specific pattern. It must be either
   *   identical to group or prefixed with the group. E.g. if the group is "foo"
   *   the ID must be either "foo" or "foo:bar".
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the plugin.
   * @param string $operation_type
   *   The operation type that this plugin is associated with.
   * @param array $methods
   *   The methods that this plugin supports.
   * @param string $endpoint
   *   The endpoint that this plugin is associated with.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly string $operation_type,
    public readonly array $methods,
    public readonly string $endpoint,
    public readonly ?string $deriver = NULL,
  ) {}

}
