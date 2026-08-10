<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\ArraySingle;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\RetryThreshold;
use PhpBench\Attributes\Revs;

#[Revs(3)]
#[Iterations(5)]
#[RetryThreshold(100)]
#[BeforeMethods('setUp')]
#[ParamProviders('provideMembershipWorkloads')]
final class MembershipCrossoverBench
{
    /** @var array<int, int> */
    private array $allNeedles = [];

    /** @var array<int, int> */
    private array $haystack = [];

    /** @var array<int, int> */
    private array $needles = [];

    /** @var array<int, array{id:int}> */
    private array $rows = [];

    /** @param array{size:int, distribution:string} $params */
    public function setUp(array $params): void
    {
        $this->haystack = range(0, 9999);
        $this->rows = array_map(
            static fn(int $id): array => ['id' => $id],
            $this->haystack,
        );
        $this->needles = $this->makeNeedles($params['size'], $params['distribution']);
        $this->allNeedles = $this->makeAllNeedles($params['size'], $params['distribution']);

        // Keep autoloading outside the timed subjects so native and adaptive
        // membership paths measure only repeated-operation cost.
        ArraySingle::containsAll([], [], true);
        ArraySingle::containsAny([], [], true);
        ArraySingle::diff([], [], true);
        ArraySingle::intersect([], [], true);
        ArrayMulti::whereIn([], 'id', [], true);
    }

    public function benchContainsAllAdaptive(): void
    {
        ArraySingle::containsAll($this->haystack, $this->allNeedles, true);
    }

    public function benchContainsAllScan(): void
    {
        array_all(
            $this->allNeedles,
            fn(int $needle): bool => in_array($needle, $this->haystack, true),
        );
    }

    public function benchContainsAnyAdaptive(): void
    {
        ArraySingle::containsAny($this->haystack, $this->needles, true);
    }

    public function benchContainsAnyScan(): void
    {
        array_any(
            $this->needles,
            fn(int $needle): bool => in_array($needle, $this->haystack, true),
        );
    }

    public function benchDiffAdaptive(): void
    {
        ArraySingle::diff($this->haystack, $this->needles, true);
    }

    public function benchDiffScan(): void
    {
        $results = [];
        foreach ($this->haystack as $key => $value) {
            if (!in_array($value, $this->needles, true)) {
                $results[$key] = $value;
            }
        }
    }

    public function benchIntersectAdaptive(): void
    {
        ArraySingle::intersect($this->haystack, $this->needles, true);
    }

    public function benchIntersectScan(): void
    {
        $results = [];
        foreach ($this->haystack as $key => $value) {
            if (in_array($value, $this->needles, true)) {
                $results[$key] = $value;
            }
        }
    }

    public function benchWhereInAdaptive(): void
    {
        ArrayMulti::whereIn($this->rows, 'id', $this->needles, true);
    }

    public function benchWhereInScan(): void
    {
        $results = [];
        foreach ($this->rows as $key => $row) {
            if (in_array($row['id'], $this->needles, true)) {
                $results[$key] = $row;
            }
        }
    }

    /** @return array<string, array{size:int, distribution:string}> */
    public function provideMembershipWorkloads(): array
    {
        $workloads = [];

        foreach ([128, 192, 256, 512] as $size) {
            foreach (['hit-first', 'hit-last', 'miss'] as $distribution) {
                $workloads[$size . '-' . $distribution] = [
                    'size' => $size,
                    'distribution' => $distribution,
                ];
            }
        }

        return $workloads;
    }

    /**
     * @param int $size Number of values in the membership set.
     * @param string $distribution Hit placement for the workload.
     * @return array<int, int>
     */
    private function makeAllNeedles(int $size, string $distribution): array
    {
        return match ($distribution) {
            'hit-first' => range(0, $size - 1),
            'hit-last' => range(10000 - $size, 9999),
            default => range(10000, 10000 + $size - 1),
        };
    }

    /**
     * @param int $size Number of values in the membership set.
     * @param string $distribution Hit placement for the workload.
     * @return array<int, int>
     */
    private function makeNeedles(int $size, string $distribution): array
    {
        $misses = range(10000, 10000 + $size - 1);

        return match ($distribution) {
            'hit-first' => [0, ...array_slice($misses, 1)],
            'hit-last' => [...array_slice($misses, 0, -1), 9999],
            default => $misses,
        };
    }
}
