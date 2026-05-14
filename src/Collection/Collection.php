<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * Class BucketCollection
 *
 * A simple array-based collection that implements common
 * interfaces (ArrayAccess, IteratorAggregate, Countable, JsonSerializable).
 * Inherits most of its behavior from BaseCollectionTrait.
 *
 * @phpstan-consistent-constructor
 * @implements ArrayAccess<array-key, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    use BaseCollectionTrait;
}
