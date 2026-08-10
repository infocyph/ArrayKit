<?php

declare(strict_types=1);

use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\ArrayShape;
use Infocyph\ArrayKit\Array\ArraySharedOps;
use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\BaseArrayHelper;
use Infocyph\ArrayKit\Array\DotNotation;
use Infocyph\ArrayKit\ArrayKit;
use Infocyph\ArrayKit\Collection\Collection;
use Infocyph\ArrayKit\Collection\HookedCollection;
use Infocyph\ArrayKit\Collection\LazyCollection;
use Infocyph\ArrayKit\Collection\Pipeline;
use Infocyph\ArrayKit\Concerns\HookTrait;
use Infocyph\ArrayKit\Config\Config;
use Infocyph\ArrayKit\Config\EnvParser;
use Infocyph\ArrayKit\Config\LazyFileConfig;
use Infocyph\ArrayKit\Config\Support\Environment;
use Infocyph\ArrayKit\DTO\DTO;
use Infocyph\ArrayKit\DTO\Concerns\DTOTrait;
use Infocyph\ArrayKit\Facade\ModuleProxy;

it('keeps documented public signatures aligned with reflection', function () {
    $sections = [
        'ArrayKit Facade' => ArrayKit::class,
        'Facade ModuleProxy' => ModuleProxy::class,
        'BaseArrayHelper' => BaseArrayHelper::class,
        'ArraySharedOps (Internal)' => ArraySharedOps::class,
        'ArraySingle' => ArraySingle::class,
        'ArrayMulti' => ArrayMulti::class,
        'ArrayShape' => ArrayShape::class,
        'DotNotation' => DotNotation::class,
        'Collection' => Collection::class,
        'HookedCollection' => HookedCollection::class,
        'Pipeline' => Pipeline::class,
        'Config' => Config::class,
        'LazyFileConfig' => LazyFileConfig::class,
        'Config Hook-Aware Variants' => Config::class,
        'EnvParser' => EnvParser::class,
        'Environment' => Environment::class,
        'DTO' => DTO::class,
        'DTOTrait' => DTOTrait::class,
        'HookTrait' => HookTrait::class,
        'LazyCollection' => LazyCollection::class,
    ];

    $normalizeType = static function (?string $type, ?string $selfType = null): array {
        if ($type === null || $type === '') {
            return [];
        }

        $types = str_starts_with($type, '?')
            ? [substr($type, 1), 'null']
            : explode('|', $type);

        $normalized = array_map(static function (string $part) use ($selfType): string {
            $part = ltrim(trim($part), '\\');

            if ($part === 'self' && $selfType !== null) {
                return $selfType;
            }

            return str_contains($part, '\\') ? basename(str_replace('\\', '/', $part)) : $part;
        }, $types);
        sort($normalized);

        return $normalized;
    };

    $reflectionType = static function (?ReflectionType $type, ?string $selfType = null) use ($normalizeType): array {
        if ($type === null) {
            return [];
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = array_map(static fn(ReflectionNamedType $part): string => $part->getName(), $type->getTypes());

            return $normalizeType(implode('|', $parts), $selfType);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $parts = array_map(static fn(ReflectionNamedType $part): string => $part->getName(), $type->getTypes());
            sort($parts);

            return $parts;
        }

        $name = $type->getName();
        if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') {
            $name .= '|null';
        }

        return $normalizeType($name, $selfType);
    };

    $parseParameter = static function (string $parameter) use ($normalizeType): array {
        $parameter = preg_replace('/^(?:(?:public|protected|private|readonly)\s+)+/', '', trim($parameter));
        preg_match(
            '/^(?:(?<type>[?\\\\A-Za-z_][\\\\A-Za-z0-9_|?]*)\s+)?(?<reference>&)?(?<variadic>\.\.\.)?\$(?<name>[A-Za-z_][A-Za-z0-9_]*)(?:\s*=\s*(?<default>.+))?$/',
            (string) $parameter,
            $matches,
        );

        return [
            'name' => $matches['name'] ?? '',
            'type' => $normalizeType($matches['type'] ?? null),
            'reference' => ($matches['reference'] ?? '') === '&',
            'variadic' => ($matches['variadic'] ?? '') === '...',
            'hasDefault' => array_key_exists('default', $matches) && $matches['default'] !== '',
            'default' => isset($matches['default']) ? ltrim(trim($matches['default']), '\\') : null,
        ];
    };

    $document = file_get_contents(__DIR__ . '/../../docs/rule-reference.rst');
    expect($document)->not->toBeFalse();
    $lines = preg_split('/\R/', (string) $document);
    expect($lines)->toBeArray();

    $activeClass = null;
    $documentedMethods = [];
    $documentedFunctions = [];
    foreach ($lines as $index => $line) {
        $nextLine = $lines[$index + 1] ?? '';
        if (preg_match('/^-{3,}$/', $nextLine) === 1) {
            $activeClass = $sections[$line] ?? null;

            continue;
        }

        if (preg_match('/^\s+function Infocyph\\\\ArrayKit\\\\(?<name>[A-Za-z_][A-Za-z0-9_]*)\((?<parameters>.*)\)(?:: (?<return>[^\/]+))?/', $line, $functionMatches) === 1) {
            $functionName = 'Infocyph\\ArrayKit\\' . $functionMatches['name'];
            $function = new ReflectionFunction($functionName);
            $documentedFunctions[$functionName] = true;
            $documentedParameters = trim($functionMatches['parameters']) === ''
                ? []
                : array_map($parseParameter, preg_split('/,\s*/', $functionMatches['parameters']));

            expect($documentedParameters)->toHaveCount(count($function->getParameters()), $functionName)
                ->and($reflectionType($function->getReturnType()))->toBe(
                    $normalizeType(isset($functionMatches['return']) ? trim($functionMatches['return']) : null),
                    $functionName . ' return type',
                );

            foreach ($function->getParameters() as $parameterIndex => $actual) {
                $documented = $documentedParameters[$parameterIndex];
                expect($documented['name'])->toBe($actual->getName(), $functionName)
                    ->and($documented['type'])->toBe($reflectionType($actual->getType()), $functionName . ' $' . $actual->getName())
                    ->and($documented['reference'])->toBe($actual->isPassedByReference(), $functionName . ' $' . $actual->getName())
                    ->and($documented['hasDefault'])->toBe($actual->isDefaultValueAvailable(), $functionName . ' $' . $actual->getName());
            }

            continue;
        }

        if ($activeClass === null || preg_match('/^\s+public (?<static>static )?function (?<name>[A-Za-z_][A-Za-z0-9_]*)\((?<parameters>.*)\)(?:: (?<return>[^\/]+))?/', $line, $matches) !== 1) {
            continue;
        }

        $documentedMethods[$activeClass][$matches['name']] = true;

        $method = new ReflectionMethod($activeClass, $matches['name']);
        $declaringType = $method->getDeclaringClass()->getShortName();
        $documentedParameters = trim($matches['parameters']) === ''
            ? []
            : array_map($parseParameter, preg_split('/,\s*/', $matches['parameters']));
        $actualParameters = $method->getParameters();

        expect($method->isPublic())->toBeTrue($activeClass . '::' . $method->getName())
            ->and($method->isStatic())->toBe(($matches['static'] ?? '') !== '', $activeClass . '::' . $method->getName())
            ->and($documentedParameters)->toHaveCount(count($actualParameters), $activeClass . '::' . $method->getName())
            ->and($reflectionType($method->getReturnType(), $declaringType))->toBe(
                $normalizeType(isset($matches['return']) ? trim($matches['return']) : null, $declaringType),
                $activeClass . '::' . $method->getName() . ' return type',
            );

        foreach ($actualParameters as $parameterIndex => $actual) {
            $documented = $documentedParameters[$parameterIndex];
            $actualDefault = null;
            if ($actual->isDefaultValueAvailable()) {
                if ($actual->isDefaultValueConstant()) {
                    $constantName = ltrim((string) $actual->getDefaultValueConstantName(), '\\');
                    $actualDefault = basename(str_replace('\\', '/', $constantName));
                } elseif ($actual->getDefaultValue() === null) {
                    $actualDefault = 'null';
                } elseif ($actual->getDefaultValue() === []) {
                    $actualDefault = '[]';
                } else {
                    $actualDefault = var_export($actual->getDefaultValue(), true);
                }
            }

            expect($documented['name'])->toBe($actual->getName(), $activeClass . '::' . $method->getName())
                ->and($documented['type'])->toBe($reflectionType($actual->getType(), $declaringType), $activeClass . '::' . $method->getName() . ' $' . $actual->getName())
                ->and($documented['reference'])->toBe($actual->isPassedByReference(), $activeClass . '::' . $method->getName() . ' $' . $actual->getName())
                ->and($documented['variadic'])->toBe($actual->isVariadic(), $activeClass . '::' . $method->getName() . ' $' . $actual->getName())
                ->and($documented['hasDefault'])->toBe($actual->isDefaultValueAvailable(), $activeClass . '::' . $method->getName() . ' $' . $actual->getName())
                ->and($documented['default'])->toBe($actualDefault, $activeClass . '::' . $method->getName() . ' $' . $actual->getName());
        }
    }

    foreach (array_unique(array_values($sections)) as $class) {
        $reflection = new ReflectionClass($class);
        $actualMethods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
                $actualMethods[] = $method->getName();
            }
        }

        $documented = array_keys($documentedMethods[$class] ?? []);
        sort($actualMethods);
        sort($documented);
        expect($documented)->toBe($actualMethods, $class . ' documented method set');
    }

    $actualFunctions = [];
    $helperPath = realpath(__DIR__ . '/../../src/namespaced-functions.php');
    foreach (get_defined_functions()['user'] as $functionName) {
        $function = new ReflectionFunction($functionName);
        if ($function->getFileName() === $helperPath) {
            $actualFunctions[] = $function->getName();
        }
    }

    $documentedFunctionNames = array_keys($documentedFunctions);
    sort($actualFunctions);
    sort($documentedFunctionNames);
    expect($documentedFunctionNames)->toBe($actualFunctions, 'Namespaced helper function set');
});
