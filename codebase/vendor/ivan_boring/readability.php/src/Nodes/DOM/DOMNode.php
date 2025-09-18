<?php

namespace ivan_boring\Readability\Nodes\DOM;

use ivan_boring\Readability\Nodes\NodeTrait;

/**
 * @method getAttribute($attribute)
 * @method hasAttribute($attribute)
 */
class DOMNode extends \DOMNode
{
    use NodeTrait;
}
