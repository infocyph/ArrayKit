<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\ArrayValueSetOps;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * PhpBench discovers this benchmark through its attributes.
 * @api
 */
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

    /** @return array<array-key, mixed> */
    public function benchCanonicalFingerprintSet(): array
    {
        $seen = [];
        foreach ($this->values as $value) {
            $seen[ArrayValueSetOps::fingerprint($value)] = true;
        }

        return $seen;
    }

    /** @return array<array-key, mixed> */
    public function benchDigestWithVerifiedBuckets(): array
    {
        $buckets = [];
        foreach ($this->values as $value) {
            $fingerprint = ArrayValueSetOps::fingerprint($value);
            $digest = hash('xxh128', $fingerprint, true);
            if (!isset($buckets[$digest])) {
                $buckets[$digest] = [$fingerprint];

                continue;
            }

            if (!in_array($fingerprint, $buckets[$digest], true)) {
                $buckets[$digest][] = $fingerprint;
            }
        }

        return $buckets;
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

    /** @return array<array-key, mixed> */
    public function benchStrictNestedScan(): array
    {
        $seen = [];
        foreach ($this->values as $value) {
            if (!in_array($value, $seen, true)) {
                $seen[] = $value;
            }
        }

        return $seen;
    }

    public function benchStrictNestedUnique(): void
    {
        ArraySingle::unique($this->values, true);
    }

    /** @return array<string, array{bytes:int, distribution:string}> */
    public function provideValueSets(): array
    {
        $workloads = [];
        foreach ([16, 32, 48, 64, 96, 128, 256, 512, 768, 1024, 1536, 2048, 4096, 8192] as $bytes) {
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
