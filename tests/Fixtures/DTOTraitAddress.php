<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Fixtures;

use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

final class DTOTraitAddress
{
    use DTOTrait;

    public string $city = '';
}
