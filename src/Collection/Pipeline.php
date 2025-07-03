<?php

declare(strict_types=1);

namespace Infocyph\ArrayKit\Collection;

use Infocyph\ArrayKit\Array\ArraySingle;
use Infocyph\ArrayKit\Array\ArrayMulti;
use Infocyph\ArrayKit\Array\BaseArrayHelper;

class Pipeline
{
    /**
     * Construct with an initial array.
     */
    public function __construct(
        protected array &$working,
        private readonly Collection $collection,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | ArraySingle-based chainable methods (Single-Dimensional usage)
    |--------------------------------------------------------------------------
    */

    /**
     * Keep only certain keys in the array, using ArraySingle::only.
     * (Typically relevant if the array is associative 1D.)
     */
    public function only(array|string $keys): Collection
    {
        $this->working = ArraySingle::only($this->working, $keys);
        return $this->collection;
    }

    /**
     * Return every n-th element, using ArraySingle::nth.
     */
    public function nth(int $step, int $offset = 0): Collection
    {
        $this->working = ArraySingle::nth($this->working, $step, $offset);
        return $this->collection;
    }

    /**
     * Keep only duplicate values, using ArraySingle::duplicates.
     * (Typically this means setting $this->working to the *list of duplicates*.)
     */
    public function duplicates(): Collection
    {
        // If you want to *replace* the original array with only duplicates:
        $dupes = ArraySingle::duplicates($this->working);
        // This means our collection now becomes an array of those duplicated values.
        // Possibly you might want to keep them in a "counts" structure, but let's do direct.
        $this->working = $dupes;
        return $this->collection;
    }

    /**
     * Slice the array (like array_slice) using ArraySingle::slice.
     */
    public function slice(int $offset, ?int $length = null): Collection
    {
        $this->working = ArraySingle::slice($this->working, $offset, $length);
        return $this->collection;
    }

    /**
     * "Paginate" the array by slicing it into a smaller segment, using ArraySingle::paginate.
     */
    public function paginate(int $page, int $perPage): Collection
    {
        $this->working = ArraySingle::paginate($this->working, $page, $perPage);
        return $this->collection;
    }

    /**
     * Combine the current array with a second array of values, using ArraySingle::combine.
     * (We treat $this->working as the *keys*, user passes an array of values.)
     */
    public function combine(array $values): Collection
    {
        $combined = ArraySingle::combine($this->working, $values);
        // Replacing the entire array with the combined result
        $this->working = $combined;
        return $this->collection;
    }

    /**
     * Map each element, updating $this->working. (From prior example)
     */
    public function map(callable $callback): Collection
    {
        $this->working = ArraySingle::map($this->working, $callback);
        return $this->collection;
    }

    /**
     * Filter the array using a callback, same as your prior "filter" example.
     */
    public function filter(callable $callback): Collection
    {
        // We use "where(...)" or direct array_filter:
        $this->working = ArraySingle::where($this->working, $callback);
        return $this->collection;
    }

    /**
     * chunk the array (single-dim).
     */
    public function chunk(int $size, bool $preserveKeys = false): Collection
    {
        $this->working = ArraySingle::chunk($this->working, $size, $preserveKeys);
        return $this->collection;
    }

    /**
     * Return only unique values using ArraySingle::unique.
     */
    public function unique(bool $strict = false): Collection
    {
        $this->working = ArraySingle::unique($this->working, $strict);
        return $this->collection;
    }

    /**
     * Reject certain items (inverse of filter), using ArraySingle::reject
     */
    public function reject(mixed $callback = true): Collection
    {
        $this->working = ArraySingle::reject($this->working, $callback);
        return $this->collection;
    }

    /**
     * Skip the first N items.
     */
    public function skip(int $count): Collection
    {
        $this->working = ArraySingle::skip($this->working, $count);
        return $this->collection;
    }

    /**
     * Skip items while callback returns true; once false, keep remainder.
     */
    public function skipWhile(callable $callback): Collection
    {
        $this->working = ArraySingle::skipWhile($this->working, $callback);
        return $this->collection;
    }

    /**
     * Skip items until callback returns true, then keep remainder.
     */
    public function skipUntil(callable $callback): Collection
    {
        $this->working = ArraySingle::skipUntil($this->working, $callback);
        return $this->collection;
    }

    /**
     * Partition [passed, failed].
     */
    public function partition(callable $callback): Collection
    {
        $this->working = ArraySingle::partition($this->working, $callback);
        return $this->collection;
    }

    /*
    |--------------------------------------------------------------------------
    | ArrayMulti-based chainable methods (Multi-Dimensional usage)
    |--------------------------------------------------------------------------
    */

    /**
     * Flatten the array using ArrayMulti::flatten.
     */
    public function flatten(float|int $depth = \INF): Collection
    {
        $this->working = ArrayMulti::flatten($this->working, $depth);
        return $this->collection;
    }

    /**
     * Flatten the array into a single level but preserve keys, using flattenByKey.
     */
    public function flattenByKey(): Collection
    {
        $this->working = ArrayMulti::flattenByKey($this->working);
        return $this->collection;
    }

    /**
     * Recursively sort the array by keys/values, using ArrayMulti::sortRecursive.
     */
    public function sortRecursive(int $options = SORT_REGULAR, bool $descending = false): Collection
    {
        $this->working = ArrayMulti::sortRecursive($this->working, $options, $descending);
        return $this->collection;
    }

    /**
     * Collapses an array of arrays into a single (1D) array (2D -> 1D).
     */
    public function collapse(): Collection
    {
        $this->working = ArrayMulti::collapse($this->working);
        return $this->collection;
    }

    /**
     * Group a 2D array by a given column or callback, using ArrayMulti::groupBy.
     */
    public function groupBy(string|callable $groupBy, bool $preserveKeys = false): Collection
    {
        $this->working = ArrayMulti::groupBy($this->working, $groupBy, $preserveKeys);
        return $this->collection;
    }

    /**
     * Filter rows where a certain key is between two values, using ArrayMulti::between.
     */
    public function between(string $key, float|int $from, float|int $to): Collection
    {
        $this->working = ArrayMulti::between($this->working, $key, $from, $to);
        return $this->collection;
    }

    /**
     * Filter using a custom callback on each row, using ArrayMulti::whereCallback.
     */
    public function whereCallback(?callable $callback = null, mixed $default = null): Collection
    {
        $this->working = ArrayMulti::whereCallback($this->working, $callback, $default);
        return $this->collection;
    }

    /**
     * Filter rows by a single key's comparison (like ->where('age', '>', 18)).
     */
    public function where(string $key, mixed $operator = null, mixed $value = null): Collection
    {
        $this->working = ArrayMulti::where($this->working, $key, $operator, $value);
        return $this->collection;
    }

    /**
     * Filter rows where "column" matches one of the given values.
     */
    public function whereIn(string $key, array $values, bool $strict = false): Collection
    {
        $this->working = ArrayMulti::whereIn($this->working, $key, $values, $strict);
        return $this->collection;
    }

    /**
     * Filter rows where "column" is not in the given values.
     */
    public function whereNotIn(string $key, array $values, bool $strict = false): Collection
    {
        $this->working = ArrayMulti::whereNotIn($this->working, $key, $values, $strict);
        return $this->collection;
    }

    /**
     * Filter rows where a column is null, using ArrayMulti::whereNull.
     */
    public function whereNull(string $key): Collection
    {
        $this->working = ArrayMulti::whereNull($this->working, $key);
        return $this->collection;
    }

    /**
     * Filter rows where a column is NOT null, using ArrayMulti::whereNotNull.
     */
    public function whereNotNull(string $key): Collection
    {
        $this->working = ArrayMulti::whereNotNull($this->working, $key);
        return $this->collection;
    }

    /**
     * Sort by a column or callback in ascending/descending order.
     */
    public function sortBy(string|callable $by, bool $desc = false, int $options = SORT_REGULAR): Collection
    {
        $this->working = ArrayMulti::sortBy($this->working, $by, $desc, $options);
        return $this->collection;
    }

    /*
    |--------------------------------------------------------------------------
    | BaseArrayHelper-based chainable or one-time checks
    |--------------------------------------------------------------------------
    */

    /**
     * Check if the current array is multiDimensional, from BaseArrayHelper (not chainable).
     */
    public function isMultiDimensional(): bool
    {
        return BaseArrayHelper::isMultiDimensional($this->working);
    }

    /**
     * Wrap the entire array if it's not already an array, from BaseArrayHelper::wrap
     */
    public function wrap(): Collection
    {
        $this->working = BaseArrayHelper::wrap($this->working);
        return $this->collection;
    }

    /**
     * Example: Unwrap an array if it has exactly one element, from BaseArrayHelper::unWrap.
     */
    public function unWrap(): Collection
    {
        // Might produce a non-array, so up to you if you want to store that as $working...
        $unwrapped = BaseArrayHelper::unWrap($this->working);
        // If $unwrapped is not array, we store it as a single-element array to keep chain consistent
        $this->working = is_array($unwrapped) ? $unwrapped : [$unwrapped];
        return $this->collection;
    }

    /**
     * Shuffle the array in place, from ArraySingle::shuffle or BaseArrayHelper logic.
     */
    public function shuffle(?int $seed = null): Collection
    {
        $this->working = ArraySingle::shuffle($this->working, $seed);
        return $this->collection;
    }

    /*
    |--------------------------------------------------------------------------
    | Additional Non-Chain Methods That Return A Single Value
    |--------------------------------------------------------------------------
    */

    /**
     * Return the sum of the current array (for single-dim usage).
     * Not chainable, it ends the pipeline by returning a numeric.
     */
    public function sum(?callable $callback = null): float|int
    {
        return ArraySingle::sum($this->working, $callback);
    }

    /**
     * Return the first item in a 2D array, or single-dim array, depending on usage.
     * from ArrayMulti::first or direct approach.
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        return ArrayMulti::first($this->working, $callback, $default);
    }

    /**
     * Return the last item in a 2D array, or single-dim array, depending on usage.
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        return ArrayMulti::last($this->working, $callback, $default);
    }

    /**
     * Return a "reduced" single value from the array (like sum-of-squares), from ArraySingle::reduce.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return ArraySingle::reduce($this->working, $callback, $initial);
    }

    /**
     * Quick example: Check if at least one item passes a truth test, from ArraySingle::some or ArrayMulti::some
     * Not chainable, returns bool.
     */
    public function any(callable $callback): bool
    {
        return ArraySingle::some($this->working, $callback);
    }

    /** Remove keys (inverse of only) */
    public function except(array|string $keys): Collection
    {
        $this->working = ArraySingle::except($this->working, $keys);
        return $this->collection;
    }

    /** Return the statistical median – TERMINATES chain (scalar) */
    public function median(): float|int
    {
        return ArraySingle::median($this->working);
    }

    /** Return the statistical mode(s) – TERMINATES chain (array) */
    public function mode(): array
    {
        return ArraySingle::mode($this->working);
    }

    /** Extract a column (optionally re-index) */
    public function pluck(string $column, ?string $indexBy = null): Collection
    {
        $this->working = ArrayMulti::pluck($this->working, $column, $indexBy);
        return $this->collection;
    }

    /** Matrix transpose (rows ↔ columns) */
    public function transpose(): Collection
    {
        $this->working = ArrayMulti::transpose($this->working);
        return $this->collection;
    }

    /**
     * Tap into the current working array for side-effects (debug/log), then continue.
     *
     * @param callable $callback  fn(array $working): void
     * @return Collection
     */
    public function tap(callable $callback): Collection
    {
        $callback($this->working);
        return $this->collection;
    }

    /**
     * Pipe the working array through a callback, replacing it with whatever you return.
     *
     * @param callable $callback  fn(array $working): array
     * @return Collection
     */
    public function pipe(callable $callback): Collection
    {
        $this->working = $callback($this->working);
        return $this->collection;
    }

    /**
     * Conditionally apply one of two callbacks based on $condition.
     *
     * @param bool          $condition
     * @param callable      $callback  fn(array $working): array
     * @param callable|null $default   fn(array $working): array
     * @return Collection
     */
    public function when(bool $condition, callable $callback, ?callable $default = null): Collection
    {
        if ($condition) {
            $this->working = $callback($this->working);
        } elseif ($default) {
            $this->working = $default($this->working);
        }
        return $this->collection;
    }

    /**
     * Inverse of when(): only run if $condition is false.
     *
     * @param bool          $condition
     * @param callable      $callback
     * @param callable|null $default
     * @return Collection
     */
    public function unless(bool $condition, callable $callback, ?callable $default = null): Collection
    {
        return $this->when(! $condition, $callback, $default);
    }

}
