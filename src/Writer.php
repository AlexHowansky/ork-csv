<?php

/**
 * Ork CSV
 *
 * @package   Ork\Csv
 * @copyright 2015-2022 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace Ork\Csv;

use RuntimeException;

/**
 * CSV writer.
 */
class Writer extends AbstractCsv
{

    /**
     * Contains the expected column names.
     *
     * @var array
     */
    protected ?array $columns = null;

    /**
     * Configurable trait settings.
     *
     * @var array
     */
    protected array $config = [

        // Callback functions to be run on the values before they're output.
        'callbacks' => [],

        // Explicit list of columns if auto-detection is not desired.
        'columns' => null,

        // The field delimiter character.
        'delimiter' => ',',

        // The escape character.
        'escape' => '\\',

        // The file to write to.
        'file' => 'php://stdout',

        // Write a header row with column names?
        'header' => true,

        // The field quote character.
        'quote' => '"',

        // If true, abort when encountering columns not described in the header. If false, ignore them.
        'strict' => true,

    ];

    /**
     * File handle.
     *
     * @var resource
     */
    protected $csv = null;

    /**
     * Make sure the file handle is closed.
     */
    public function __destruct()
    {
        if (is_resource($this->csv) === true) {
            fclose($this->csv);
        }
    }

    /**
     * Write a row to the file.
     *
     * @param array $row The row to write.
     *
     * @return int The number of bytes written.
     *
     * @throws RuntimeException On error.
     */
    protected function put(array $row): int
    {
        $result = fputcsv(
            $this->csv,
            $row,
            $this->getConfig('delimiter'),
            $this->getConfig('quote'),
            $this->getConfig('escape')
        );

        // As long as we use the awfully convenient fputcsv() function, it's not trivial to measure how many bytes we
        // should have written, so we'll just ensure we have at least one per element.
        if ($result === false || $result < count($row)) {
            throw new RuntimeException('Failed writing to file: ' . $this->getConfig('file'));
        }

        return $result;
    }

    /**
     * Write a row to the file.
     *
     * @param array $row The row to write.
     *
     * @return int The number of bytes written.
     *
     * @throws RuntimeException On error.
     */
    public function write(array $row): int
    {

        ++$this->line;

        // Open the file if it's not already open.
        if ($this->csv === null) {
            $csv = fopen($this->getConfig('file'), 'w');
            if (is_resource($csv) === false) {
                throw new RuntimeException('Failed to create file: ' . $this->getConfig('file'));
            }
            $this->csv = $csv;
        }

        // Output the header row if we haven't already.
        if ($this->columns === null) {
            $this->columns = array_flip(
                $this->getConfig('columns') === null
                    ? array_keys($row)
                    : $this->getConfig('columns')
            );
            if ($this->getConfig('header') === true) {
                $this->put(array_keys($this->columns));
            }
        }

        // In strict mode, abort when we get columns we don't know about.
        if ($this->getConfig('strict') === true) {
            foreach (array_keys($row) as $column) {
                if (array_key_exists($column, $this->columns) === false) {
                    throw new RuntimeException('Unknown column "' . $column . '" on line ' . $this->line);
                }
            }
        }

        $row = $this->applyCallbacks($row);

        // If this data row doesn't contain values for all the fields in the header row, then insert empty values for
        // the missing fields, so that the CSV columns line up properly. Also, make sure that the columns are in the
        // order that was specified in the header row.
        $ordered = [];
        foreach (array_keys($this->columns) as $column) {
            $ordered[] = array_key_exists($column, $row) === true ? $row[$column] : '';
        }
        return $this->put($ordered);

    }

    /**
     * Write multiple rows from an array or iterator.
     *
     * @param iterable $rows The iterator to iterate over.
     *
     * @return int The number of rows written.
     */
    public function writeFromIterator(iterable $rows): int
    {
        foreach ($rows as $row) {
            $this->write($row);
        }
        return $this->line;
    }

}
