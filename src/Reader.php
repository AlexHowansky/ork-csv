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

use Generator;
use IteratorAggregate;
use RuntimeException;

/**
 * CSV reader.
 */
class Reader extends AbstractCsv implements IteratorAggregate
{

    /**
     * If `keyByColumn` is not null and `detectDuplicateKeys` is true, then
     * we'll need to manually track which keys we've already seen, because
     * Generators can output duplicates without issue.
     */
    protected array $detectedKeys = [];

    /**
     * Constructor.
     *
     * @param string|resource $file The file to process.
     * @param bool $hasHeader True if the file has a header row with column names.
     * @param array $columnNames An array of column names to use if the file does not include a header row.
     * @param array $callbacks Callbacks to apply to columns as they are read.
     * @param int|string|null $keyByColumn Key the generated array by this column.
     * @param bool $detectDuplicateKeys True to detect duplicate keys when using keyByColumn.
     * @param string $delimiterCharacter The CSV delimiter character to use.
     * @param string $quoteCharacter The CSV quote character to use.
     * @param string $escapeCharacter The CSV escape character to use.
     */
    public function __construct(
        protected mixed $file = 'php://stdin',
        protected bool $hasHeader = true,
        protected array $columnNames = [],
        protected array $callbacks = [],
        protected int|string|null $keyByColumn = null,
        protected bool $detectDuplicateKeys = true,
        protected string $delimiterCharacter = ',',
        protected string $quoteCharacter = '"',
        protected string $escapeCharacter = '\\',
    ) {
        $this->validateParameters();
    }

    /**
     * Get the values from only one specific column.
     *
     * @param int|string $column The column to get the values from.
     *
     * @return Generator An iterator over the values.
     *
     * @throws RuntimeException If an unknown column is referenced.
     */
    public function getColumn(int|string $column): Generator
    {
        foreach ($this as $row) {
            yield $row[$column] ?? throw new RuntimeException('No such column: ' . $column);
        }
    }

    /**
     * Get the column names.
     *
     * @return array The column names.
     */
    public function getColumnNames(): array
    {
        // If we have not yet processed any of the file, and we're expecting a
        // header row, then we'll have to iterate once to read the header row.
        if ($this->lineNumber === 0 && $this->hasHeader === true) {
            $this->getIterator()->current();
        }
        return $this->columnNames;
    }

    /**
     * Get an iterator for the data.
     *
     * @return Generator An iterator for the data.
     *
     * @throws RuntimeException If the referenced file can not be opened.
     */
    public function getIterator(): Generator
    {
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        $csv = is_resource($this->file) === true ? $this->file : @fopen($this->file, 'r');
        if ($csv === false) {
            throw new RuntimeException('Failed to open file: ' . $this->file);
        }
        $this->lineNumber = 0;
        while (true) {
            $fields = fgetcsv($csv, 0, $this->delimiterCharacter, $this->quoteCharacter, $this->escapeCharacter);
            if (is_array($fields) === false) {
                break;
            }
            if ($this->lineNumber++ === 0 && $this->hasHeader === true) {
                if (empty($this->columnNames) === true) {
                    $this->columnNames = $this->validateColumnNames($fields);
                }
            } else {
                $row = $this->applyCallbacks($this->map($fields));
                if ($this->keyByColumn === null) {
                    yield $row;
                } else {
                    yield $this->getKey($row) => $row;
                }
            }
        }
        fclose($csv);
    }

    /**
     * Get the key for a row.
     *
     * @param array $row The row to get the key for.
     *
     * @return string The key for this row.
     *
     * @throws RuntimeException If the requested key column is not present or a duplicate key is detected.
     */
    protected function getKey(array $row): string
    {
        $key = $row[$this->keyByColumn]
            ?? throw new RuntimeException('No such column: ' . $this->keyByColumn);
        if ($this->detectDuplicateKeys === true) {
            if (array_key_exists($key, $this->detectedKeys) === true) {
                throw new RuntimeException('Duplicate key detected: ' . $key);
            }
            $this->detectedKeys[$key] = true;
        }
        return $key;
    }

    /**
     * Map the indexed input row to an associative array with column names.
     *
     * @throws RuntimeException If the number of columns in a row doesn't match the expected number.
     */
    protected function map(array $row): array
    {
        if (empty($this->columnNames) === true) {
            return $row;
        }
        if (count($this->columnNames) !== count($row)) {
            throw new RuntimeException('Column mismatch on line: ' . $this->lineNumber);
        }
        return array_filter(
            array_combine($this->columnNames, $row),
            fn(string $key): bool => empty($key) === false,
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Return the entire file as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

}
