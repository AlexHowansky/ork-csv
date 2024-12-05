<?php

/**
 * Ork CSV
 *
 * @package   Ork\Csv
 * @copyright 2015-2024 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace Ork\Csv;

use RuntimeException;
use UnexpectedValueException;

/**
 * CSV abstract.
 */
abstract class AbstractCsv
{

    protected array $callbacks;

    protected array $columnNames;

    protected string $delimiterCharacter;

    protected string $escapeCharacter;

    protected mixed $file;

    protected int $lineNumber = 0;

    protected string $quoteCharacter;

    /**
     * Apply callbacks to a row.
     *
     * @param array $row The callbacks to apply.
     *
     * @return array The updated row.
     */
    protected function applyCallbacks(array $row): array
    {
        foreach ($this->callbacks as $column => $callbacks) {

            // If the column name of the callback starts with a slash, we'll
            // interpret it as a regex and apply the callback to all the row
            // columns that match the pattern.
            if (str_starts_with($column, '/') === true) {
                foreach (array_keys($row) as $key) {
                    if (preg_match($column, $key) === 1) {
                        foreach ((array) $callbacks as $callback) {
                            $row[$key] = call_user_func($callback, $row[$key]);
                        }
                    }
                }
            }

            // Apply this callback to one explicitly named (or indexed) column.
            if (array_key_exists($column, $row) === true) {
                foreach ((array) $callbacks as $callback) {
                    $row[$column] = call_user_func($callback, $row[$column]);
                }
            }

        }
        return $row;
    }

    /**
     * Get the line number that has most recently been processed.
     *
     * @return int The line number that has most recently been processed.
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * Validate that column names are unique.
     *
     * @throws RuntimeException If column names are not unique.
     */
    protected function validateColumnNames(array $columnNames): array
    {
        $columnNames = array_map(fn(string $columnName): string => trim($columnName), $columnNames);
        if (count($columnNames) !== count(array_unique($columnNames))) {
            throw new RuntimeException('Column names are not unique: ' . join(', ', $columnNames));
        }
        return $columnNames;
    }

    /**
     * Validate the constructor parameters.
     *
     * @throws UnexpectedValueException If an invalid parameters are provided.
     */
    protected function validateParameters(): void
    {
        if (is_string($this->file) === false && is_resource($this->file) === false) {
            throw new UnexpectedValueException('file must be a string or file handle');
        }
        foreach ($this->callbacks as $callbacks) {
            foreach ((array) $callbacks as $callback) {
                if (is_callable($callback) === false) {
                    throw new UnexpectedValueException('Callback is not callable: ' . $callback);
                }
            }
        }
        $this->columnNames = $this->validateColumnNames($this->columnNames);
        if (strlen($this->delimiterCharacter) > 1) {
            throw new UnexpectedValueException('delimiterCharacter must be a single character');
        }
        if (strlen($this->escapeCharacter) > 1) {
            throw new UnexpectedValueException('escapeCharacter must be a single character');
        }
        if (strlen($this->quoteCharacter) > 1) {
            throw new UnexpectedValueException('quoteCharacter must be a single character');
        }
    }

}
