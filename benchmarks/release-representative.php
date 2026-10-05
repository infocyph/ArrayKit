<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use RuntimeException;
use Symfony\Component\Process\Process;

require dirname(__DIR__) . '/vendor/autoload.php';

const RELEASE_BASELINE_TAG = '5.2.0';
const RELEASE_REPETITIONS = 3;
const RELEASE_STABILITY_SPREAD_PERCENT = 10.0;

/** @var list<array{name:string,operations:int,warmup:int}> $workloadSpecs */
$workloadSpecs = [
    ['name' => 'array-query', 'operations' => 100, 'warmup' => 20],
    ['name' => 'dot-config', 'operations' => 100, 'warmup' => 20],
    ['name' => 'lazy-array', 'operations' => 100, 'warmup' => 20],
    ['name' => 'lazy-file-cold', 'operations' => 40, 'warmup' => 10],
    ['name' => 'lazy-file-generated', 'operations' => 60, 'warmup' => 10],
];

$root = dirname(__DIR__);
$worker = __DIR__ . '/release-workload.php';
$buildDirectory = $root . '/build';
$baselineDirectory = sys_get_temp_dir() . '/arraykit-release-baseline-' . getmypid();

if (!is_dir($buildDirectory) && !mkdir($buildDirectory, 0777, true)) {
    throw new RuntimeException('Unable to create release benchmark build directory.');
}

releasePrepareBaseline($root, $baselineDirectory);

try {
    $environment = releaseEnvironment();
    $baselineSha = trim(releaseProcess(['git', 'rev-parse', RELEASE_BASELINE_TAG . '^{commit}'], $root));
    $candidateSha = trim(releaseProcess(['git', 'rev-parse', 'HEAD'], $root));

    $baseline = releaseBenchmarkDocument(
        $baselineDirectory,
        $worker,
        RELEASE_BASELINE_TAG . '@' . $baselineSha,
        $environment,
        $workloadSpecs,
    );
    $candidate = releaseBenchmarkDocument(
        $root,
        $worker,
        'candidate@' . $candidateSha,
        $environment,
        $workloadSpecs,
    );

    releaseWriteJson($buildDirectory . '/release-baseline.json', $baseline);
    releaseWriteJson($buildDirectory . '/release-candidate.json', $candidate);

    fwrite(STDOUT, sprintf(
        "Representative release benchmark generated for %s and %s.\n",
        $baseline['environment']['release'],
        $candidate['environment']['release'],
    ));
} finally {
    releaseRemoveBaseline($root, $baselineDirectory);
}

/**
 * @param array<string, mixed> $environment
 * @param list<array{name:string,operations:int,warmup:int}> $specs
 * @return array<string, mixed>
 */
function releaseBenchmarkDocument(
    string $root,
    string $worker,
    string $release,
    array $environment,
    array $specs,
): array {
    $workloads = [];

    foreach ($specs as $spec) {
        foreach ([1, 2, 4] as $concurrency) {
            $workloads[] = releaseBenchmarkWorkload(
                $root,
                $worker,
                $spec,
                $concurrency,
            );
        }
    }

    return [
        'schema_version' => 1,
        'generated_at' => gmdate(DATE_ATOM),
        'environment' => [
            ...$environment,
            'release' => $release,
        ],
        'workloads' => $workloads,
    ];
}

/**
 * @param array{name:string,operations:int,warmup:int} $spec
 * @return array<string, mixed>
 */
function releaseBenchmarkWorkload(
    string $root,
    string $worker,
    array $spec,
    int $concurrency,
): array {
    $trials = [];

    for ($trial = 0; $trial < RELEASE_REPETITIONS; $trial++) {
        $trials[] = releaseBenchmarkTrial(
            $root,
            $worker,
            $spec['name'],
            $spec['operations'],
            $spec['warmup'],
            $concurrency,
        );
    }

    $rpms = array_column($trials, 'successful_rpm');
    $latencies = [];
    $attempted = 0;
    $successful = 0;
    $failed = 0;
    $cpuAverages = [];
    $cpuPeaks = [];
    $memoryAverages = [];
    $memoryPeaks = [];
    $memoryGrowth = [];

    foreach ($trials as $trial) {
        $attempted += $trial['attempted'];
        $successful += $trial['successful'];
        $failed += $trial['failed'];
        $latencies = [...$latencies, ...$trial['latencies_ms']];
        $cpuAverages[] = $trial['cpu_average_percent'];
        $cpuPeaks[] = $trial['cpu_peak_percent'];
        $memoryAverages[] = $trial['memory_average_mb'];
        $memoryPeaks[] = $trial['memory_peak_mb'];
        $memoryGrowth[] = $trial['memory_growth_mb'];
    }

    $medianRpm = releasePercentile($rpms, 50);
    $spread = $medianRpm > 0
        ? ((max($rpms) - min($rpms)) / $medianRpm) * 100
        : 100.0;

    return [
        'name' => $spec['name'] . '-c' . $concurrency,
        'type' => 'component',
        'metadata' => [
            'suite' => 'arraykit-5.3-release',
            'dataset' => 'deterministic-v1',
            'operations_per_worker' => $spec['operations'],
            'valid_output_only' => true,
        ],
        'repetitions' => RELEASE_REPETITIONS,
        'warmup_operations' => $spec['warmup'],
        'duration_seconds' => 0.0,
        'concurrency' => $concurrency,
        'result' => [
            'attempted_operations' => $attempted,
            'successful_operations' => $successful,
            'failed_operations' => $failed,
            'timeouts' => 0,
            'successful_rpm' => $medianRpm,
            'error_rate' => $attempted > 0 ? $failed / $attempted : 0.0,
            'latency_ms' => [
                'minimum' => $latencies === [] ? null : min($latencies),
                'average' => $latencies === [] ? null : array_sum($latencies) / count($latencies),
                'p50' => releasePercentile($latencies, 50),
                'p95' => releasePercentile($latencies, 95),
                'p99' => releasePercentile($latencies, 99),
                'maximum' => $latencies === [] ? null : max($latencies),
            ],
            'cpu' => [
                'average_percent' => releaseAverage($cpuAverages),
                'peak_percent' => $cpuPeaks === [] ? null : max($cpuPeaks),
            ],
            'memory' => [
                'average_mb' => releaseAverage($memoryAverages),
                'peak_mb' => $memoryPeaks === [] ? null : max($memoryPeaks),
                'growth_mb' => $memoryGrowth === [] ? null : max($memoryGrowth),
            ],
            'stability' => [
                'status' => $failed === 0 && $spread <= RELEASE_STABILITY_SPREAD_PERCENT
                    ? 'stable'
                    : 'unstable',
                'spread_percent' => max(0.0, $spread),
            ],
        ],
    ];
}

/**
 * @return array{
 *     attempted:int,
 *     successful:int,
 *     failed:int,
 *     successful_rpm:float,
 *     latencies_ms:list<float>,
 *     cpu_average_percent:float,
 *     cpu_peak_percent:float,
 *     memory_average_mb:float,
 *     memory_peak_mb:float,
 *     memory_growth_mb:float
 * }
 */
function releaseBenchmarkTrial(
    string $root,
    string $worker,
    string $workload,
    int $operations,
    int $warmup,
    int $concurrency,
): array {
    $processes = [];
    $startedAt = hrtime(true);

    for ($workerIndex = 0; $workerIndex < $concurrency; $workerIndex++) {
        $process = new Process([
            PHP_BINARY,
            '-d',
            'opcache.enable_cli=1',
            '-d',
            'opcache.jit=0',
            $worker,
            $root,
            $workload,
            (string) $operations,
            (string) $warmup,
        ]);
        $process->setTimeout(120);
        $process->start();
        $processes[] = $process;
    }

    $results = [];

    foreach ($processes as $process) {
        $exitCode = $process->wait();
        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Release benchmark worker failed: ' . trim($process->getErrorOutput()),
            );
        }

        $decoded = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Release benchmark worker returned invalid JSON.');
        }

        $results[] = $decoded;
    }

    $elapsedSeconds = max((hrtime(true) - $startedAt) / 1_000_000_000, 0.000001);
    $attempted = 0;
    $successful = 0;
    $failed = 0;
    $latencies = [];
    $cpuSeconds = 0.0;
    $cpuPeaks = [];
    $memoryInitial = [];
    $memoryFinal = [];
    $memoryPeak = [];

    foreach ($results as $result) {
        $attempted += (int) ($result['attempted'] ?? 0);
        $successful += (int) ($result['successful'] ?? 0);
        $failed += (int) ($result['failed'] ?? 0);
        $latencies = [
            ...$latencies,
            ...array_map('floatval', is_array($result['latencies_ms'] ?? null) ? $result['latencies_ms'] : []),
        ];
        $cpuSeconds += (float) ($result['cpu_seconds'] ?? 0.0);
        $cpuPeaks[] = (float) ($result['cpu_percent'] ?? 0.0);
        $memoryInitial[] = (float) ($result['memory_initial_mb'] ?? 0.0);
        $memoryFinal[] = (float) ($result['memory_final_mb'] ?? 0.0);
        $memoryPeak[] = (float) ($result['memory_peak_mb'] ?? 0.0);
    }

    $averageInitial = releaseAverage($memoryInitial) ?? 0.0;
    $averageFinal = releaseAverage($memoryFinal) ?? 0.0;

    return [
        'attempted' => $attempted,
        'successful' => $successful,
        'failed' => $failed,
        'successful_rpm' => ($successful / $elapsedSeconds) * 60,
        'latencies_ms' => $latencies,
        'cpu_average_percent' => ($cpuSeconds / $elapsedSeconds) * 100,
        'cpu_peak_percent' => $cpuPeaks === [] ? 0.0 : max($cpuPeaks),
        'memory_average_mb' => $averageFinal,
        'memory_peak_mb' => $memoryPeak === [] ? 0.0 : max($memoryPeak),
        'memory_growth_mb' => max(0.0, $averageFinal - $averageInitial),
    ];
}

/**
 * @return array<string, mixed>
 */
function releaseEnvironment(): array
{
    $extensions = get_loaded_extensions();
    sort($extensions);

    $cpuModel = 'unknown';
    $cpuInfo = is_readable('/proc/cpuinfo') ? file_get_contents('/proc/cpuinfo') : false;
    if (is_string($cpuInfo) && preg_match('/^model name\s*:\s*(.+)$/m', $cpuInfo, $matches) === 1) {
        $cpuModel = trim($matches[1]);
    }

    $operatingSystem = php_uname('s') . ' ' . php_uname('r');
    $runner = getenv('RUNNER_NAME');
    $runner = is_string($runner) && $runner !== '' ? $runner : php_uname('n');
    $fingerprintPayload = implode('|', [
        PHP_VERSION,
        PHP_SAPI,
        $operatingSystem,
        $cpuModel,
        $runner,
        implode(',', $extensions),
    ]);

    return [
        'stable' => getenv('ARRAYKIT_BENCHMARK_STABLE') === '1',
        'fingerprint' => hash('sha256', $fingerprintPayload),
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'operating_system' => $operatingSystem,
        'cpu_model' => $cpuModel,
        'memory_limit' => (string) ini_get('memory_limit'),
        'opcache' => 'cli-enabled',
        'jit' => false,
        'xdebug' => extension_loaded('xdebug'),
        'extensions' => $extensions,
        'runner' => $runner,
    ];
}

function releaseAverage(array $values): ?float
{
    return $values === [] ? null : array_sum($values) / count($values);
}

function releasePercentile(array $values, int $percentile): ?float
{
    if ($values === []) {
        return null;
    }

    sort($values, SORT_NUMERIC);
    $index = (int) ceil(($percentile / 100) * count($values)) - 1;
    $index = max(0, min(count($values) - 1, $index));

    return (float) $values[$index];
}

function releasePrepareBaseline(string $root, string $baselineDirectory): void
{
    releaseRemoveBaseline($root, $baselineDirectory);

    $verify = new Process(['git', 'rev-parse', '--verify', RELEASE_BASELINE_TAG . '^{commit}'], $root);
    $verify->run();

    if (!$verify->isSuccessful()) {
        releaseProcess([
            'git',
            'fetch',
            '--depth=1',
            'origin',
            'refs/tags/' . RELEASE_BASELINE_TAG . ':refs/tags/' . RELEASE_BASELINE_TAG,
        ], $root);
    }

    releaseProcess(
        ['git', 'worktree', 'add', '--detach', '--force', $baselineDirectory, RELEASE_BASELINE_TAG],
        $root,
    );
    releaseProcess([
        'composer',
        'install',
        '--no-dev',
        '--no-interaction',
        '--prefer-dist',
        '--no-progress',
        '--classmap-authoritative',
    ], $baselineDirectory, 240);
}

function releaseProcess(array $command, string $workingDirectory, int $timeout = 120): string
{
    $process = new Process($command, $workingDirectory, ['XDEBUG_MODE' => 'off']);
    $process->setTimeout($timeout);
    $process->run();

    if (!$process->isSuccessful()) {
        throw new RuntimeException(sprintf(
            "Command failed: %s\n%s",
            implode(' ', $command),
            trim($process->getErrorOutput()),
        ));
    }

    return $process->getOutput();
}

function releaseRemoveBaseline(string $root, string $baselineDirectory): void
{
    if (!is_dir($baselineDirectory)) {
        return;
    }

    $remove = new Process(
        ['git', 'worktree', 'remove', '--force', $baselineDirectory],
        $root,
    );
    $remove->setTimeout(60);
    $remove->run();

    if (is_dir($baselineDirectory)) {
        releaseRemoveDirectory($baselineDirectory);
    }
}

function releaseRemoveDirectory(string $directory): void
{
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path)) {
            releaseRemoveDirectory($path);
        } elseif (is_file($path) || is_link($path)) {
            unlink($path);
        }
    }

    rmdir($directory);
}

/**
 * @param array<string, mixed> $document
 */
function releaseWriteJson(string $path, array $document): void
{
    $encoded = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if (file_put_contents($path, $encoded . PHP_EOL) === false) {
        throw new RuntimeException('Unable to write release benchmark result: ' . $path);
    }
}
