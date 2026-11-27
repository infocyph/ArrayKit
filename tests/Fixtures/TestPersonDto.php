<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Fixtures;

use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

final class TestPersonDto
{
    use DTOTrait;

    public string $name = '';

    public int $age = 0;
}
