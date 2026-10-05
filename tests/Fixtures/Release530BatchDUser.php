<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Fixtures;

use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

final class Release530BatchDUser
{
    use DTOTrait;

    public Release530BatchDAddress $address;

    public mixed $payload = null;
}
