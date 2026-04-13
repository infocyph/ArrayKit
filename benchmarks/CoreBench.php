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
    private array $single = [];

    public function setUp(): void
    {
        $this->single = [
            10, 20, 30, 40, 50, 10, 20, 30, 99, 120, 44, 55,
            8, 15, 16, 23, 42, 7, 11, 13, 17, 19, 21, 34,
            50, 51, 52, 53, 54, 55, 56, 57,
        ];

        $this->nested = [
            ['id' => 1, 'name' => 'Alice', 'scores' => [9, 8, 7]],
            ['id' => 2, 'name' => 'Bob', 'scores' => [6, 7, 8]],
            ['id' => 3, 'name' => 'Cara', 'scores' => [10, 9, 9]],
            ['id' => 4, 'name' => 'Dina', 'scores' => [7, 7, 7]],
            ['id' => 5, 'name' => 'Evan', 'scores' => [8, 8, 8]],
        ];

        $this->dot = [
            'app' => ['name' => 'ArrayKit', 'env' => 'local'],
            'db' => [
                'host' => 'localhost',
                'port' => 3306,
                'options' => ['timeout' => 5, 'ssl' => false],
            ],
            'cache' => ['driver' => 'file', 'prefix' => 'arraykit'],
        ];

        $this->config = new Config();
        $this->config->loadArray($this->dot);
    }

    #[Subject]
    public function benchArrayMultiFlatten(): void
    {
        ArrayMulti::flatten($this->nested);
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
    public function benchDotNotationGet(): void
    {
        DotNotation::get($this->dot, 'db.options.timeout');
    }
}
