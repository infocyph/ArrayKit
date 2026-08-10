<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Array\ArrayMulti;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[ParamProviders('provideWorkloads')]
final class ArrayMultiBench
{
    /** @var array<int, int> */
    private array $needles = [];

    /** @var array<int, array{id:int, group:string, score:int, text:string, derived:array{id:int, payload:string}}> */
    private array $rows = [];

    /** @param array{size:int, needles:int} $params */
    public function setUp(array $params): void
    {
        $this->rows = [];
        for ($index = 0; $index < $params['size']; $index++) {
            $this->rows[] = [
                'id' => $index,
                'group' => 'group-' . ($index % 100),
                'score' => $index % 1000,
                'text' => 'row-' . $index,
                'derived' => [
                    'id' => $index % max(1, intdiv($params['size'], 2)),
                    'payload' => str_repeat('x', 96),
                ],
            ];
        }

        $this->needles = range(0, $params['needles'] - 1);
    }

    public function benchCountBy(): void
    {
        ArrayMulti::countBy($this->rows, 'group');
    }

    public function benchDuplicatesByStrict(): void
    {
        ArrayMulti::duplicatesBy($this->rows, 'derived', true);
    }

    public function benchFlatten(): void
    {
        ArrayMulti::flatten($this->rows);
    }

    public function benchGroupBy(): void
    {
        ArrayMulti::groupBy($this->rows, 'group');
    }

    public function benchKeyBy(): void
    {
        ArrayMulti::keyBy($this->rows, 'id');
    }

    public function benchKeyByNative(): void
    {
        $result = [];
        foreach ($this->rows as $row) {
            $result[$row['id']] = $row;
        }
    }

    public function benchPluck(): void
    {
        ArrayMulti::pluck($this->rows, 'score', 'id');
    }

    public function benchSortBy(): void
    {
        ArrayMulti::sortBy($this->rows, 'score', true, SORT_NUMERIC);
    }

    public function benchSortByMany(): void
    {
        ArrayMulti::sortByMany($this->rows, [
            ['group', 'asc', SORT_STRING],
            ['score', 'desc', SORT_NUMERIC],
        ]);
    }

    public function benchSum(): void
    {
        ArrayMulti::sum($this->rows, 'score');
    }

    public function benchUniqueByStrict(): void
    {
        ArrayMulti::uniqueBy($this->rows, 'derived', true);
    }

    public function benchWhere(): void
    {
        ArrayMulti::where($this->rows, 'score', '>=', 500);
    }

    public function benchWhereIn(): void
    {
        ArrayMulti::whereIn($this->rows, 'score', $this->needles, true);
    }

    public function benchWhereInNative(): void
    {
        $result = [];
        foreach ($this->rows as $key => $row) {
            if (in_array($row['score'], $this->needles, true)) {
                $result[$key] = $row;
            }
        }
    }

    public function benchWhereNotIn(): void
    {
        ArrayMulti::whereNotIn($this->rows, 'score', $this->needles, true);
    }

    /** @return array<string, array{size:int, needles:int}> */
    public function provideWorkloads(): array
    {
        return [
            '100x2' => ['size' => 100, 'needles' => 2],
            '1kx10' => ['size' => 1000, 'needles' => 10],
            '10kx2' => ['size' => 10000, 'needles' => 2],
            '10kx10' => ['size' => 10000, 'needles' => 10],
            '10kx100' => ['size' => 10000, 'needles' => 100],
            '100kx100' => ['size' => 100000, 'needles' => 100],
        ];
    }
}
