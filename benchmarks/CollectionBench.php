<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Collection\Collection;
use Infocyph\ArrayKit\Collection\LazyCollection;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[ParamProviders('provideSizes')]
final class CollectionBench
{
    /** @var array<int, int> */
    private array $data = [];

    /** @param array{size:int} $params */
    public function setUp(array $params): void
    {
        $this->data = range(1, $params['size']);
    }

    public function benchArraySingleMap(): void
    {
        ArraySingle::map($this->data, static fn(int $value): int => $value * 2);
    }

    public function benchCollectionMap(): void
    {
        Collection::make($this->data)->map(static fn(int $value): int => $value * 2);
    }

    public function benchLazyChunkMaterialization(): void
    {
        LazyCollection::fromFactory(fn(): array => $this->data)
            ->chunkLazy(100)
            ->all();
    }

    public function benchLazyFilterMaterialization(): void
    {
        LazyCollection::fromFactory(fn(): array => $this->data)
            ->filterLazy(static fn(int $value): bool => ($value % 2) === 0)
            ->all();
    }

    public function benchLazyMapFilterTake(): void
    {
        LazyCollection::fromFactory(fn(): array => $this->data)
            ->mapLazy(static fn(int $value): int => $value * 2)
            ->filterLazy(static fn(int $value): bool => ($value % 3) === 0)
            ->take(100)
            ->all();
    }

    public function benchLazyMapMaterialization(): void
    {
        LazyCollection::fromFactory(fn(): array => $this->data)
            ->mapLazy(static fn(int $value): int => $value * 2)
            ->all();
    }

    public function benchPipelineMap(): void
    {
        Collection::make($this->data)
            ->process()
            ->map(static fn(int $value): int => $value * 2);
    }

    /** @return array<string, array{size:int}> */
    public function provideSizes(): array
    {
        return [
            '1k' => ['size' => 1000],
            '10k' => ['size' => 10000],
            '100k' => ['size' => 100000],
            '1m' => ['size' => 1000000],
        ];
    }
}
