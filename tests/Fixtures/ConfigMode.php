<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Fixtures;

enum ConfigMode: string
{
    case Local = 'local';
    case Prod = 'prod';
}
