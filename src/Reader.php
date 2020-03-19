<?php

/**
 * Ork CSV
 *
 * @package   Ork\Csv
 * @copyright 2015-2020 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace Ork\Csv;

/**
 * CSV reader.
 */
class Reader extends CsvAbstract implements \IteratorAggregate
{

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
         * return one value. For example:
         *
         * [
         *     '/./' => 'trim',
         *     'name' => 'strtolower',
         *     'email' => ['strtolower', 'trim'],
         *     'phone' => [[$someObject, 'methodName']],
         * ]
         */
        'callbacks' => [],

        // The column names to assign. If `header` is true and this is empty, the values from the header row are used.
        // If `header` is true and this is not empty, these values are used instead.
        'columns' => [],

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
    protected $line = 0;

    /**
     * Make sure we have unique column names.
     *
     * @param array $columns The column names.
     *
     * @return array
     *
     * @throws \RuntimeException If column names are not unique.
     */
    protected function filterConfigColumns(array $columns): array
    {
        if (count($columns) !== count(array_unique($columns))) {
            throw new \RuntimeException('Column names must be unique.');
        }
        return $columns;
    }

    /**
     * Get one column.
     *
     * @param int|string $column The column to get.
     *
     * @return \Generator
     *
     * @throws \RuntimeException On missing column reference.
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
     * Get the column headers.
     *
     * @return array The column headers.
     */
    public function getColumns(): array
    {

        // We might have to iterate once to get the header row.
        if (
            $this->line === 0 &&
            $this->getConfig('header') === true &&
            empty($this->getConfig('columns')) === true
        ) {
            $this->getIterator()->current();
        }

        return $this->getConfig('columns');

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
        $csv = fopen($this->getConfig('file'), 'r');
        if ($csv === false) {
            throw new \RuntimeException('Failed to open file: ' . $this->getConfig('file'));
        }
        $this->line = 0;
        while (true) {
            $fields = fgetcsv(
                $csv,
                0,
                $this->getConfig('delimiter'),
                $this->getConfig('quote'),
                $this->getConfig('escape')
            );
            if ($fields === false || $fields === null) {
                break;
            }
            if (++$this->line === 1 && $this->getConfig('header') === true) {
                if (empty($this->getConfig('columns')) === true) {
                    $this->setConfig(
                        'columns',
                        array_map(
                            function ($field) {
                                return trim($field);
                            },
                            $fields
                        )
                    );
                }
            } else {
                yield $this->applyCallbacks($this->map($fields));
            }
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
        if (empty($this->getConfig('columns')) === true) {
            return $fields;
        }
        $result = array_combine($this->getConfig('columns'), $fields);
        if ($result === false) {
            throw new \RuntimeException('Column mismatch on line: ' . $this->line);
        }
        return $result;
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
