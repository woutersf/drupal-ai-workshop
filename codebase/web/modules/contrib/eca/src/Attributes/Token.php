<?php

declare(strict_types=1);

namespace Drupal\eca\Attributes;

use Drupal\eca\Attribute\Token as NewToken;

/**
 * Provides the token attribute for ECA token providers.
 *
 * @deprecated in eca:2.0.0 and is removed from eca:3.0.0. These attributes
 * moved into the correct namespace Drupal\eca\Attribute.
 *
 * @see https://www.drupal.org/project/eca/issues/3531792
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Token extends NewToken {

  /**
   * Constructor for the ECA token attribute.
   *
   * @param string $name
   *   The token name.
   * @param string $description
   *   A one line description.
   * @param string[] $classes
   *   The list of event classes that provide that token. Leave empty if all
   *   derivations of the same base plugin are supporting that token.
   * @param \Drupal\eca\Attributes\Token[] $properties
   *   The list of optional token properties.
   * @param string[] $aliases
   *   The list of optional token name aliases.
   */
  public function __construct(
    public string $name,
    public string $description,
    public array $classes = [],
    public array $properties = [],
    public array $aliases = [],
  ) {
  }

}
