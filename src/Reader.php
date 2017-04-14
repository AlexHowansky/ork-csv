<?php

/**
 * Ork CSV
 *
 * @package   Ork\Csv
 * @copyright 2015-2017 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace Ork\Csv;

/**
 * CSV reader.
 */
class Reader implements \IteratorAggregate
{

    use \Ork\Core\ConfigurableTrait;

    /**
     * Contains the column names from the header row.
     *
     * @var array
     */
    protected $columns = null;

    /**
     * Configurable trait settings.
     *
     * @var array
     */
    protected $config = [

        /**
         * Callback functions to be run on the values after they're extracted. If using a header row, the array index
         * should be the name of the field to apply callbacks to. Alternatively, if the index string begins with a
         * slash, it will be treated as a regex and applied to all matching fields. If not using a header row, the
         * array index should be the numerical index of the column to apply the callback(s) to. The value for each
         * entry can be a single callable or an array of callables. Each callable should expect one parameter and
         * return one value. Example:
         *
         * ``php
         * [
         *     '/./' => 'trim',
         *     'name' => 'strtolower',
         *     'email' => ['strtolower', 'trim'],
         *     'phone' => [[$someObject, 'methodName']],
         * ]
         * ```
         */
        'callbacks' => [],

        // The field delimiter character.
        'delimiter' => ',',

        // The escape character.
        'escape' => '\\',

        // The file to process.
        'file' => 'php://stdin',

        // True if the first row contains column names.
        'header' => true,

        // The field quote charater.
        'quote' => '"',

    ];

    /**
     * Contains the line count.
     *
     * @var int
     */
    protected $line = null;

    /**
     * Apply callbacks.
     *
     * @param array $row The row to process.
     *
     * @return array The processed row.
     */
    protected function applyCallbacks(array $row): array
    {
        foreach ($this->getConfig('callbacks') as $column => $callbacks) {
            if (strpos($column, '/') === 0) {
                // Interpret as a regex and apply to all matching columns.
                foreach (array_keys($row) as $name) {
                    if (preg_match($column, $name) === 1) {
                        foreach ((array) $callbacks as $callback) {
                            $row[$name] = call_user_func($callback, $row[$name]);
                        }
                    }
                }
            } else {
                // Apply to one explicitly named column.
                if (array_key_exists($column, $row) === false) {
                    throw new \RuntimeException('Unable to apply callback to missing column: ' . $column);
                }
                foreach ((array) $callbacks as $callback) {
                    $row[$column] = call_user_func($callback, $row[$column]);
                }
            }
        }
        return $row;
    }

    /**
     * Get one column.
     *
     * @param int|string $column The column to get.
     *
     * @return \Generator
     */
    public function getColumn($column): \Generator
    {
        foreach ($this as $row) {
            if (array_key_exists($column, $row) === false) {
                throw new \RuntimeException('No such column: ' . $column);
            }
            yield $row[$column];
        }
    }

    /**
     * Required by \IteratorAggregate interface.
     *
     * @return \Generator
     *
     * @throws \RuntimeException On error reading file.
     */
    public function getIterator(): \Generator
    {
        $csv = @fopen($this->getConfig('file'), 'r');
        if ($csv === false) {
            throw new \RuntimeException('Failed to open file: ' . $this->getConfig('file'));
        }
        $this->line = 1;
        $this->columns = null;
        while (true) {
            $fields = fgetcsv(
                $csv,
                0,
                $this->getConfig('delimiter'),
                $this->getConfig('quote'),
                $this->getConfig('escape')
            );
            if ($fields === false) {
                break;
            }
            if ($this->line === 1 && $this->getConfig('header') === true) {
                $this->columns = array_map(
                    function ($field) {
                        return trim($field);
                    },
                    $fields
                );
                if (count($this->columns) !== count(array_unique($this->columns))) {
                    throw new \RuntimeException(
                        'File does not have unique column headers: ' . $this->getConfig('file')
                    );
                }
            } else {
                $row = $this->getConfig('header') === true ? $this->map($fields) : $fields;
                yield empty($this->getConfig('callbacks')) === true ? $row : $this->applyCallbacks($row);
            }
            ++$this->line;
        }
        fclose($csv);
    }

    /**
     * Get the current line number.
     *
     * @return int The current line number.
     */
    public function getLineNumber(): int
    {
        return $this->line;
    }

    /**
     * Map a line's fields to an associative array key according to the headers.
     *
     * @param array $fields The fields to map.
     *
     * @return array The mapped fields.
     *
     * @throws \RuntimeException On column mismatch.
     */
    protected function map(array $fields): array
    {
        if (count($this->columns) !== count($fields)) {
            throw new \RuntimeException('Column mismatch on line: ' . $this->line);
        }
        return array_combine($this->columns, $fields);
    }

    /**
     * Convert the entire CSV file to a big array.
     *
     * @param string $column If $column is provided, the resulting array will be associative and the value in the
     *                       field named by $column will be used as the array key. If $column is not provided, the
     *                       resulting array will indexed.
     *
     * @return array
     */
    public function toArray(string $column = null): array
    {
        $array = [];
        foreach ($this as $line) {
            if ($column === null) {
                $array[] = $line;
            } else {
                $array[$line[$column]] = $line;
            }
        }
        return $array;
    }

}
