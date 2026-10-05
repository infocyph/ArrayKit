<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Fixtures;

use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

final class Release530BatchDInherited extends Release530BatchDBase
{
    use DTOTrait;

    public string $name = 'child';
}
