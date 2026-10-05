<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Benchmarks;

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\LazyFileConfig;
use RuntimeException;

[$script, $root, $workload, $operations, $warmup] = $argv + [null, null, null, null, null];
unset($script);

if (!is_string($root) || !is_string($workload) || !is_numeric($operations) || !is_numeric($warmup)) {
    fwrite(STDERR, "Usage: release-workload.php <root> <workload> <operations> <warmup>\n");
    exit(2);
}

require rtrim($root, DIRECTORY_SEPARATOR) . '/vendor/autoload.php';

$operations = max(1, (int) $operations);
$warmup = max(0, (int) $warmup);
$temporaryDirectories = [];

$operation = match ($workload) {
    'array-query' => releaseArrayQueryWorkload(),
    'dot-config' => releaseDotConfigWorkload(),
    'lazy-array' => releaseLazyArrayWorkload(),
    'lazy-file-cold' => releaseLazyFileColdWorkload($temporaryDirectories),
    'lazy-file-generated' => releaseLazyFileGeneratedWorkload($temporaryDirectories),
    default => throw new RuntimeException('Unknown release benchmark workload: ' . $workload),
};

for ($index = 0; $index < $warmup; $index++) {
    $operation();
}

$usageBefore = getrusage();
$memoryBefore = memory_get_usage(true);
$startedAt = hrtime(true);
$latencies = [];
$successes = 0;
$failures = 0;

for ($index = 0; $index < $operations; $index++) {
    $operationStartedAt = hrtime(true);

    try {
        $operation();
        $successes++;
    } catch (\Throwable) {
        $failures++;
    }

    $latencies[] = (hrtime(true) - $operationStartedAt) / 1_000_000;
}

$elapsedSeconds = max((hrtime(true) - $startedAt) / 1_000_000_000, 0.000001);
$usageAfter = getrusage();
$cpuSeconds = releaseCpuSeconds($usageAfter) - releaseCpuSeconds($usageBefore);
$memoryAfter = memory_get_usage(true);

foreach ($temporaryDirectories as $directory) {
    releaseRemoveDirectory($directory);
}

echo json_encode([
    'attempted' => $operations,
    'successful' => $successes,
    'failed' => $failures,
    'elapsed_seconds' => $elapsedSeconds,
    'latencies_ms' => $latencies,
    'cpu_seconds' => max(0.0, $cpuSeconds),
    'cpu_percent' => max(0.0, ($cpuSeconds / $elapsedSeconds) * 100),
    'memory_initial_mb' => $memoryBefore / 1_048_576,
    'memory_final_mb' => $memoryAfter / 1_048_576,
    'memory_peak_mb' => memory_get_peak_usage(true) / 1_048_576,
], JSON_THROW_ON_ERROR), PHP_EOL;

/**
 * @return \Closure(): void
 */
function releaseArrayQueryWorkload(): \Closure
{
    $rows = [];
    for ($index = 1; $index <= 320; $index++) {
        $rows[] = [
            'id' => $index,
            'name' => 'user-' . $index,
            'group' => $index % 7,
        ];
    }

    $needles = range(1, 256);

    return static function () use ($rows, $needles): void {
        $matched = ArrayMulti::whereIn($rows, 'id', $needles, true);
        $like = ArrayMulti::whereLike($rows, 'name', 'user-%', true);
        $page = ArraySingle::paginate($rows, 4, 50);

        if (count($matched) !== 256 || count($like) !== 320 || count($page) !== 50) {
            throw new RuntimeException('Array/query release workload returned invalid data.');
        }
    };
}

function releaseCpuSeconds(array $usage): float
{
    $user = ((int) ($usage['ru_utime.tv_sec'] ?? 0))
        + (((int) ($usage['ru_utime.tv_usec'] ?? 0)) / 1_000_000);
    $system = ((int) ($usage['ru_stime.tv_sec'] ?? 0))
        + (((int) ($usage['ru_stime.tv_usec'] ?? 0)) / 1_000_000);

    return $user + $system;
}

/**
 * @return \Closure(): void
 */
function releaseDotConfigWorkload(): \Closure
{
    $rows = [];
    for ($index = 1; $index <= 64; $index++) {
        $rows[] = ['id' => $index, 'profile' => ['active' => true]];
    }

    $data = ['rows' => $rows];
    $config = new Config();
    $config->loadArray([
        'app' => [
            'name' => 'arraykit',
            'nested' => ['value' => 42],
        ],
    ]);

    return static function () use ($config, $data): void {
        $ids = DotNotation::getSafe(
            $data,
            'rows.*.id',
            maxDepth: 8,
            maxNodes: 1_000,
            throwOnTooDeep: true,
        );
        $flat = ArrayMulti::flattenGuarded(
            [['a' => 1], ['b' => 2], ['c' => 3]],
            maxDepth: 8,
            maxNodes: 32,
            throwOnTooDeep: true,
        );

        if (
            count($ids) !== 64
            || $flat !== [1, 2, 3]
            || $config->get('app.nested.value') !== 42
        ) {
            throw new RuntimeException('Dot/config release workload returned invalid data.');
        }
    };
}

/**
 * @return \Closure(): void
 */
function releaseLazyArrayWorkload(): \Closure
{
    $values = range(1, 256);

    return static function () use ($values): void {
        $result = LazyCollection::from($values)
            ->mapLazy(static fn(int $value): int => $value * 2)
            ->filterLazy(static fn(int $value): bool => ($value % 3) === 0)
            ->take(64)
            ->all();

        if (count($result) !== 64) {
            throw new RuntimeException('Lazy array release workload returned invalid data.');
        }
    };
}

/**
 * @param list<string> $temporaryDirectories
 * @return \Closure(): void
 */
function releaseLazyFileColdWorkload(array &$temporaryDirectories): \Closure
{
    [$source] = releaseLazyFileDirectories($temporaryDirectories);

    return static function () use ($source): void {
        $config = new LazyFileConfig($source);

        if (
            $config->get('app.name') !== 'arraykit'
            || $config->get('app.nested.value') !== 42
        ) {
            throw new RuntimeException('Cold lazy-file release workload returned invalid data.');
        }
    };
}

/**
 * @param list<string> $temporaryDirectories
 * @return \Closure(): void
 */
function releaseLazyFileGeneratedWorkload(array &$temporaryDirectories): \Closure
{
    [$source, $cache] = releaseLazyFileDirectories($temporaryDirectories);

    (new LazyFileConfig($source, namespaceCacheDirectory: $cache))
        ->warmNamespaceCache('app');

    return static function () use ($cache, $source): void {
        $config = new LazyFileConfig($source, namespaceCacheDirectory: $cache);

        if (
            $config->get('app.name') !== 'arraykit'
            || $config->get('app.nested.value') !== 42
        ) {
            throw new RuntimeException('Generated lazy-file release workload returned invalid data.');
        }
    };
}

/**
 * @param list<string> $temporaryDirectories
 * @return array{0:string,1:string}
 */
function releaseLazyFileDirectories(array &$temporaryDirectories): array
{
    $suffix = bin2hex(random_bytes(5));
    $source = sys_get_temp_dir() . '/arraykit-release-source-' . $suffix;
    $cache = sys_get_temp_dir() . '/arraykit-release-cache-' . $suffix;

    if (!mkdir($source, 0777, true) || !mkdir($cache, 0777, true)) {
        throw new RuntimeException('Unable to create release benchmark directories.');
    }

    $payload = "<?php\n\ndeclare(strict_types=1);\n\nreturn ['name' => 'arraykit', 'nested' => ['value' => 42]];\n";
    if (file_put_contents($source . '/app.php', $payload) === false) {
        throw new RuntimeException('Unable to write release benchmark config.');
    }

    $temporaryDirectories[] = $source;
    $temporaryDirectories[] = $cache;

    return [$source, $cache];
}

function releaseRemoveDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

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
