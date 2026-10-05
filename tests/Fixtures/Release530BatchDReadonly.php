<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Fixtures;

use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

final class Release530BatchDReadonly
{
    use DTOTrait;

    public readonly string $name;
}
