<?php

/**
 * Ork CSV
 *
 * @package   Ork\Csv
 * @copyright 2015-2019 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace Ork\Csv;

/**
 * CSV writer.
 */
class Writer
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

        // The column names for the header row. If not provided, we'll use the keys from the first array passed to the
        // write() method.
        'columns' => null,

        // The field delimiter character.
        'delimiter' => ',',

        // The escape character.
        'escape' => '\\',

        // The file to process.
        'file' => 'php://stdout',

        // Write a header row with column names?
        'header' => true,

        // The field quote charater.
        'quote' => '"',

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
     * @throws \RuntimeException On error.
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
            throw new \RuntimeException('Failed writing to file: ' . $this->getConfig('file'));
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
     * @throws \RuntimeException On error.
     */
    public function write(array $row): int
    {

        // Open the file if it's not already open.
        if ($this->csv === null) {
            $csv = fopen($this->getConfig('file'), 'w');
            if (is_resource($csv) === false) {
                throw new \RuntimeException('Failed to create file: ' . $this->getConfig('file'));
            }
            $this->csv = $csv;
        }

        // If we're not using a header row, just output the values. The caller will have to ensure the proper column
        // order.
        if ($this->getConfig('header') === false) {
            return $this->put(array_values($row));
        }

        // Output the header row if we haven't already.
        if ($this->columns === null) {
            $this->columns = $this->getConfig('columns') === null ? array_keys($row) : $this->getConfig('columns');
            $this->put($this->columns);
        }

        // If this data row doesn't contain values for all the fields in the header row, then insert empty values for
        // the missing fields, so that the CSV columns line up properly. Also, make sure that the columns are in the
        // order that was specified in the header row.
        $ordered = [];
        foreach ($this->columns as $column) {
            $ordered[] = array_key_exists($column, $row) === true ? $row[$column] : '';
        }
        return $this->put($ordered);

    }

}
