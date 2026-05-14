<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\Config\Config;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;

#[Revs(500)]
#[Iterations(5)]
#[BeforeMethods('setUp')]
final class CoreBench
{
    private Config $config;

    private array $dot = [];

    private array $nested = [];

    private array $queryRows = [];

    private array $single = [];

    private array $singleAssoc = [];

    public function setUp(): void
    {
        $this->single = [
            10, 20, 30, 40, 50, 10, 20, 30, 99, 120, 44, 55,
            8, 15, 16, 23, 42, 7, 11, 13, 17, 19, 21, 34,
            50, 51, 52, 53, 54, 55, 56, 57,
        ];

        $this->singleAssoc = [
            'a' => 10,
            'b' => 20,
            'c' => 30,
            'd' => 40,
            'e' => 50,
            'f' => 60,
            'g' => 70,
            'h' => 80,
        ];

        $this->nested = [
            ['id' => 1, 'name' => 'Alice', 'scores' => [9, 8, 7]],
            ['id' => 2, 'name' => 'Bob', 'scores' => [6, 7, 8]],
            ['id' => 3, 'name' => 'Cara', 'scores' => [10, 9, 9]],
            ['id' => 4, 'name' => 'Dina', 'scores' => [7, 7, 7]],
            ['id' => 5, 'name' => 'Evan', 'scores' => [8, 8, 8]],
        ];

        $this->queryRows = [
            ['id' => 1, 'role' => 'admin'],
            ['id' => 2, 'role' => null],
            ['id' => 3, 'role' => 'editor'],
            ['id' => 4],
            ['id' => 5, 'role' => 'viewer'],
        ];

        $this->dot = [
            'app' => ['name' => 'ArrayKit', 'env' => 'local'],
            'db' => [
                'host' => 'localhost',
                'port' => 3306,
                'options' => ['timeout' => 5, 'ssl' => false],
            ],
            'cache' => ['driver' => 'file', 'prefix' => 'arraykit'],
            'service.name' => 'arraykit-service',
        ];

        $this->config = new Config();
        $this->config->loadArray($this->dot);
    }

    #[Subject]
    public function benchArrayMultiEvery(): void
    {
        ArrayMulti::every($this->nested, static fn(array $row): bool => isset($row['id']));
    }

    #[Subject]
    public function benchArrayMultiFlatten(): void
    {
        ArrayMulti::flatten($this->nested);
    }

    #[Subject]
    public function benchArrayMultiKeyBy(): void
    {
        ArrayMulti::keyBy($this->nested, 'id');
    }

    #[Subject]
    public function benchArrayMultiSkipUntil(): void
    {
        ArrayMulti::skipUntil($this->nested, static fn(array $row): bool => ($row['id'] ?? 0) >= 3);
    }

    #[Subject]
    public function benchArrayMultiWhereInNull(): void
    {
        ArrayMulti::whereIn($this->queryRows, 'role', [null], true);
    }

    #[Subject]
    public function benchArraySingleCountBy(): void
    {
        ArraySingle::countBy($this->single);
    }

    #[Subject]
    public function benchArraySingleNth(): void
    {
        ArraySingle::nth($this->single, 3, 2);
    }

    #[Subject]
    public function benchArraySinglePartition(): void
    {
        ArraySingle::partition($this->singleAssoc, static fn(int $value): bool => $value >= 40);
    }

    #[Subject]
    public function benchArraySingleSkipWhile(): void
    {
        ArraySingle::skipWhile($this->single, static fn(int $value): bool => $value < 50);
    }

    #[Subject]
    public function benchArraySingleUnique(): void
    {
        ArraySingle::unique($this->single);
    }

    #[Subject]
    public function benchConfigGet(): void
    {
        $this->config->get('db.options.timeout');
    }

    #[Subject]
    public function benchDotNotationEscapedKeyGet(): void
    {
        DotNotation::get($this->dot, 'service\\.name');
    }

    #[Subject]
    public function benchDotNotationGet(): void
    {
        DotNotation::get($this->dot, 'db.options.timeout');
    }
}
