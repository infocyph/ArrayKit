<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Fixtures;

use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

final class DTOTraitUser
{
    use DTOTrait;

    public DTOTraitAddress $address;

    public function __construct()
    {
        $this->address = new DTOTraitAddress;
    }
}
