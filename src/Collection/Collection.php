<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

/**
 * Class BucketCollection
 *
 * A simple array-based collection that implements common
 * interfaces (ArrayAccess, Iterator, Countable, JsonSerializable).
 * Inherits most of its behavior from BaseCollectionTrait.
 */
class Collection implements ArrayAccess, Countable, Iterator, JsonSerializable
{
    use BaseCollectionTrait;
}
