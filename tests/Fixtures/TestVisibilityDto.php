<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Tests\Fixtures;

use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;

final class TestVisibilityDto
{
    use DTOTrait;

    public static string $shared = 'original';

    public ?string $nickname = 'before';

    protected string $protectedValue = 'protected';

    private string $privateValue = 'private';

    /** @return array{string, string} */
    public function nonPublicValues(): array
    {
        return [$this->protectedValue, $this->privateValue];
    }
}
