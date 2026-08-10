<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Array\DotNotation;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[ParamProviders('providePaths')]
final class DotNotationBench
{
    private int $coldPath = 0;

    private string $path = '';

    /** @var array<array-key, mixed> */
    private array $target = [];

    /** @param array{path:string} $params */
    public function setUp(array $params): void
    {
        $this->path = $params['path'];
        $this->target = [
            'plain' => 1,
            'one' => ['two' => ['three' => ['four' => ['five' => ['six' => ['seven' => ['eight' => ['nine' => ['ten' => 10]]]]]]]]],
            'service.name' => 'escaped',
            'users' => [
                ['name' => 'Alice', 'teams' => [['name' => 'A'], ['name' => 'B']]],
                ['name' => 'Bob', 'teams' => [['name' => 'C'], ['name' => 'D']]],
            ],
        ];

        DotNotation::get($this->target, $this->path);
    }

    public function benchColdMissingPath(): void
    {
        DotNotation::get($this->target, 'cold.' . $this->coldPath++ . '.missing');
    }

    public function benchFill(): void
    {
        $target = $this->target;
        DotNotation::fill($target, 'one.two.filled', true);
    }

    public function benchForget(): void
    {
        $target = $this->target;
        DotNotation::forget($target, 'users.*.teams.*.name');
    }

    public function benchNativeNestedGet(): void
    {
        $value = $this->target['one']['two']['three']['four']['five'] ?? null;
    }

    public function benchSet(): void
    {
        $target = $this->target;
        DotNotation::set($target, 'users.*.teams.*.active', true);
    }

    public function benchWarmGet(): void
    {
        DotNotation::get($this->target, $this->path);
    }

    /** @return array<string, array{path:string}> */
    public function providePaths(): array
    {
        return [
            'plain' => ['path' => 'plain'],
            'one-segment' => ['path' => 'one'],
            'three-segments' => ['path' => 'one.two.three'],
            'five-segments' => ['path' => 'one.two.three.four.five'],
            'ten-segments' => ['path' => 'one.two.three.four.five.six.seven.eight.nine.ten'],
            'escaped' => ['path' => 'service\\.name'],
            'wildcard' => ['path' => 'users.*.name'],
            'nested-wildcard' => ['path' => 'users.*.teams.*.name'],
            'first' => ['path' => 'users.{first}.name'],
            'last' => ['path' => 'users.{last}.name'],
        ];
    }
}
