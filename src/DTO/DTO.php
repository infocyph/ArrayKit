<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\DTO;

use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

/**
 * Optional abstract base for DTOs that expose their own public value properties.
 *
 * @phpstan-consistent-constructor
 */
abstract class DTO
{
    use DTOTrait;
}
