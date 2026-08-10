<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Array\ArraySingle;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[ParamProviders('provideSizes')]
final class ArraySingleBench
{
    /** @var array<int, int> */
    private array $data = [];

    /** @var array<string, int> */
    private array $keyed = [];

    /** @var array<int, int> */
    private array $needles = [];

    /** @param array{size:int} $params */
    public function setUp(array $params): void
    {
        $uniqueValues = max(1, intdiv($params['size'], 2));
        $this->data = [];
        $this->keyed = [];

        for ($index = 0; $index < $params['size']; $index++) {
            $value = $index % $uniqueValues;
            $this->data[] = $value;
            $this->keyed['key-' . $index] = $value;
        }

        $this->needles = array_slice($this->data, 0, min(100, $params['size']));
    }

    public function benchContains(array $params): void
    {
        ArraySingle::contains($this->data, $params['size'] - 1, true);
    }

    public function benchContainsAll(): void
    {
        ArraySingle::containsAll($this->data, $this->needles, true);
    }

    public function benchContainsAny(array $params): void
    {
        ArraySingle::containsAny($this->data, [$params['size'], $this->needles[0]], true);
    }

    public function benchContainsNative(array $params): void
    {
        in_array($params['size'] - 1, $this->data, true);
    }

    public function benchDiff(): void
    {
        ArraySingle::diff($this->data, $this->needles, true);
    }

    public function benchDuplicates(): void
    {
        ArraySingle::duplicates($this->data);
    }

    public function benchIntersect(): void
    {
        ArraySingle::intersect($this->data, $this->needles, true);
    }

    public function benchMap(): void
    {
        ArraySingle::map($this->data, static fn(int $value): int => $value + 1);
    }

    public function benchMapArrayMap(): void
    {
        array_map(static fn(int $value): int => $value + 1, $this->data);
    }

    public function benchMapForeach(): void
    {
        $result = [];
        foreach ($this->data as $key => $value) {
            $result[$key] = $value + 1;
        }
    }

    public function benchMedian(): void
    {
        ArraySingle::median($this->data);
    }

    public function benchMode(): void
    {
        ArraySingle::mode($this->data);
    }

    public function benchNth(): void
    {
        ArraySingle::nth($this->data, 10, 3);
    }

    public function benchPartition(): void
    {
        ArraySingle::partition($this->keyed, static fn(int $value): bool => ($value % 2) === 0);
    }

    public function benchSame(): void
    {
        ArraySingle::same($this->data, $this->data, true);
    }

    public function benchSeededShuffle(): void
    {
        ArraySingle::shuffle($this->data, 12345);
    }

    public function benchSum(): void
    {
        ArraySingle::sum($this->data);
    }

    public function benchUnique(): void
    {
        ArraySingle::unique($this->data, true);
    }

    public function benchUnseededShuffle(): void
    {
        ArraySingle::shuffle($this->data);
    }

    public function benchWhere(): void
    {
        ArraySingle::where($this->keyed, static fn(int $value): bool => ($value % 2) === 0);
    }

    /** @return array<string, array{size:int}> */
    public function provideSizes(): array
    {
        return [
            '10' => ['size' => 10],
            '100' => ['size' => 100],
            '1k' => ['size' => 1000],
            '10k' => ['size' => 10000],
            '100k' => ['size' => 100000],
        ];
    }
}
