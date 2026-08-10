<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Config\EnvParser;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

#[Revs(1)]
#[Iterations(3)]
#[BeforeMethods('setUp')]
#[ParamProviders('provideInputs')]
final class EnvParserBench
{
    private string $contents = '';

    /** @param array{lines:int, mode:string} $params */
    public function setUp(array $params): void
    {
        $lines = ['BASE=base'];
        for ($index = 1; $index < $params['lines']; $index++) {
            $lines[] = match ($params['mode']) {
                'comments' => 'KEY_' . $index . '=value # comment',
                'escape-heavy' => 'KEY_' . $index . '="line\\n\\t\\"' . $index . '"',
                'interpolation' => 'KEY_' . $index . '=${BASE}-' . $index,
                'quoted' => 'KEY_' . $index . '="quoted value ' . $index . '"',
                default => 'KEY_' . $index . '=value-' . $index,
            };
        }

        $this->contents = implode("\n", $lines);
    }

    public function benchParse(): void
    {
        EnvParser::parse($this->contents);
    }

    public function benchParseRaw(): void
    {
        EnvParser::parseRaw($this->contents);
    }

    /** @return array<string, array{lines:int, mode:string}> */
    public function provideInputs(): array
    {
        $workloads = [];
        foreach ([10, 100, 1000, 10000] as $lines) {
            foreach (['plain', 'quoted', 'interpolation', 'comments', 'escape-heavy'] as $mode) {
                $workloads[$lines . '-' . $mode] = ['lines' => $lines, 'mode' => $mode];
            }
        }

        return $workloads;
    }
}
