<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Config\Config;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[ParamProviders('provideReads')]
final class ConfigBench
{
    private Config $cached;

    private string $path = '';

    private int $reads = 1;

    private Config $uncached;

    /** @param array{path:string, reads:int} $params */
    public function setUp(array $params): void
    {
        $items = [
            'debug' => false,
            'one' => [
                'two' => [
                    'three' => [
                        'four' => [
                            'five' => 5,
                        ],
                    ],
                ],
            ],
            'service.name' => 'escaped',
            'users' => [['name' => 'Alice'], ['name' => 'Bob']],
        ];

        $this->path = $params['path'];
        $this->reads = $params['reads'];
        $this->cached = new Config();
        $this->cached->loadArray($items);
        $this->uncached = new Config();
        $this->uncached->loadArray($items);
        $this->uncached->readCache(false);
        $this->cached->get($this->path);
    }

    public function benchCachedGet(): void
    {
        for ($index = 0; $index < $this->reads; $index++) {
            $this->cached->get($this->path);
        }
    }

    public function benchFill(): void
    {
        $this->cached->fill('one.two.added', 1);
    }

    public function benchForget(): void
    {
        $this->cached->forget('one.two.three');
    }

    public function benchSet(): void
    {
        $this->cached->set('one.two.value', 1);
    }

    public function benchTopLevelGet(): void
    {
        for ($index = 0; $index < $this->reads; $index++) {
            $this->cached->get('debug');
        }
    }

    public function benchTypedGetter(): void
    {
        $this->cached->getInt('one.two.three.four.five');
    }

    public function benchUncachedGet(): void
    {
        for ($index = 0; $index < $this->reads; $index++) {
            $this->uncached->get($this->path);
        }
    }

    /** @return array<string, array{path:string, reads:int}> */
    public function provideReads(): array
    {
        $paths = [
            'plain' => 'debug',
            'one' => 'one',
            'three' => 'one.two.three',
            'five' => 'one.two.three.four.five',
            'escaped' => 'service\\.name',
            'wildcard' => 'users.*.name',
        ];
        $workloads = [];

        foreach ($paths as $name => $path) {
            foreach ([1, 2, 5, 10, 100, 1000] as $reads) {
                $workloads[$name . '-' . $reads] = ['path' => $path, 'reads' => $reads];
            }
        }

        return $workloads;
    }
}
