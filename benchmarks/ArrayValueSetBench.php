<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\ArraySingleOps;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[ParamProviders('provideValueSets')]
final class ArrayValueSetBench
{
    /** @var array<int, mixed> */
    private array $values = [];

    /** @param array{bytes:int, distribution:string} $params */
    public function setUp(array $params): void
    {
        $this->values = [];
        $uniqueValues = $params['distribution'] === 'duplicate-heavy' ? 20 : 900;

        for ($index = 0; $index < 1000; $index++) {
            $id = $index % $uniqueValues;
            $this->values[] = [
                'id' => $id,
                'payload' => str_repeat(chr(65 + ($id % 26)), $params['bytes']),
                'nested' => ['enabled' => ($id % 2) === 0, 'score' => (float) $id],
            ];
        }
    }

    public function benchCanonicalFingerprintSet(): void
    {
        $seen = [];
        foreach ($this->values as $value) {
            $seen[ArraySingleOps::fingerprint($value, true)] = true;
        }
    }

    public function benchDigestWithVerifiedBuckets(): void
    {
        $buckets = [];
        foreach ($this->values as $value) {
            $fingerprint = ArraySingleOps::fingerprint($value, true);
            $digest = hash('xxh128', $fingerprint, true);
            if (!isset($buckets[$digest])) {
                $buckets[$digest] = [$fingerprint];

                continue;
            }

            if (!in_array($fingerprint, $buckets[$digest], true)) {
                $buckets[$digest][] = $fingerprint;
            }
        }
    }

    public function benchLooseNestedUnique(): void
    {
        ArraySingle::unique($this->values);
    }

    public function benchMixedStrictUnique(): void
    {
        $object = new \stdClass();
        ArraySingle::unique([
            ...$this->values,
            null,
            false,
            0,
            0.0,
            '0',
            INF,
            -INF,
            NAN,
            $object,
            $object,
        ], true);
    }

    public function benchStrictNestedScan(): void
    {
        $seen = [];
        foreach ($this->values as $value) {
            if (!in_array($value, $seen, true)) {
                $seen[] = $value;
            }
        }
    }

    public function benchStrictNestedUnique(): void
    {
        ArraySingle::unique($this->values, true);
    }

    /** @return array<string, array{bytes:int, distribution:string}> */
    public function provideValueSets(): array
    {
        $workloads = [];
        foreach ([16, 32, 48, 64, 96, 128, 256, 512] as $bytes) {
            $workloads[$bytes . 'b-duplicates'] = [
                'bytes' => $bytes,
                'distribution' => 'duplicate-heavy',
            ];
            $workloads[$bytes . 'b-unique'] = [
                'bytes' => $bytes,
                'distribution' => 'mostly-unique',
            ];
        }

        return $workloads;
    }
}
