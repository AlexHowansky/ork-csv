<?php

/**
 * Ork CSV
 *
 * @package   Ork\Csv
 * @copyright 2015-2023 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace Ork\Csv;

use RuntimeException;
use UnexpectedValueException;

/**
 * CSV writer.
 */
class Writer extends AbstractCsv
{

    protected $fileHandle;

    /**
     * Constructor.
     *
     * @param string $file The file to process.
     * @param bool $hasHeader True if the file has a header row with column names.
     * @param array $columnNames An array of column names to use if the file does not include a header row.
     * @param array $callbacks Callbacks to apply to columns as they are read.
     * @param bool $appendToExistingFile True to append output to an existing file.
     * @param bool $allowUnknownColumns True to allow keys that are not described in the header row.
     * @param string $delimiterCharacter The CSV delimiter character to use.
     * @param string $quoteCharacter The CSV quote character to use.
     * @param string $escapeCharacter The CSV escape character to use.
     *
     * @throws UnexpectedValueException If an invalid parameters are provided.
     */
    public function __construct(
        protected string $file = 'php://stdout',
        protected bool $hasHeader = true,
        protected array $columnNames = [],
        protected array $callbacks = [],
        protected bool $appendToExistingFile = false,
        protected bool $allowUnknownColumns = true,
        protected string $delimiterCharacter = ',',
        protected string $quoteCharacter = '"',
        protected string $escapeCharacter = '\\',
    ) {
        $this->validateParameters();
    }

    /**
     * Make sure the file is closed.
     */
    public function __destruct()
    {
        if (is_resource($this->fileHandle) === true) {
            fclose($this->fileHandle);
        }
    }

    /**
     * Get the CSV file handle, creating the associated file if it doesn't already exist.
     *
     * @return resource The CSV file handle.
     *
     * @throws RuntimeException If the file cannot be created.
     */
    protected function getFileHandle()
    {
        if (isset($this->fileHandle) === false) {
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $this->fileHandle = @fopen($this->file, $this->appendToExistingFile === true ? 'a' : 'w');
            if (is_resource($this->fileHandle) === false) {
                throw new RuntimeException('Failed to create file: ' . $this->file);
            }
        }
        return $this->fileHandle;
    }

    /**
     * Map the input row to the expected output row.
     *
     * If we get an input row that has keys in an order that is different than
     * the established key order, we need to rearrange the array with the keys
     * in the expected order.
     *
     * If we get an input row that is missing data for established keys, we need
     * to output a blank column so that the remaining columns align properly.
     *
     * @param array $row The input row.
     *
     * @return array The output row.
     */
    protected function map(array $row): array
    {
        $ordered = [];
        foreach ($this->columnNames as $columnName) {
            $ordered[] = array_key_exists($columnName, $row) === true ? $row[$columnName] : '';
        }
        return $ordered;
    }

    /**
     * Write a row to the CSV file.
     *
     * @param array $row The row to write.
     *
     * @return int The number of bytes written.
     *
     * @throws RuntimeException If writing fails.
     */
    protected function put(array $row): int
    {
        ++$this->lineNumber;
        $result = fputcsv(
            $this->getFileHandle(),
            $row,
            $this->delimiterCharacter,
            $this->quoteCharacter,
            $this->escapeCharacter
        );
        if ($result === false || $result === 0) {
            throw new RuntimeException('Failed to write to file: ' . $this->file);
        }
        return $result;
    }

    /**
     * Write a row to the CSV file.
     *
     * @param array $row The row to write.
     *
     * @return int The number of bytes written.
     *
     * @throws RuntimeException If an unknown column is detected.
     */
    public function write(array $row): int
    {
        if ($this->lineNumber === 0 && $this->hasHeader === true) {
            if (empty($this->columnNames) === true) {
                $this->columnNames = array_keys($row);
            }
            if ($this->appendToExistingFile === false) {
                $this->put($this->columnNames);
            }
        }
        if ($this->allowUnknownColumns === false) {
            foreach (array_keys($row) as $columnName) {
                if (array_key_exists($columnName, $this->columnNames) === false) {
                    throw new RuntimeException('Unknown column detected: ' . $columnName);
                }
            }
        }
        $row = $this->applyCallbacks($row);
        return $this->put(empty($this->columnNames) === true ? $row : $this->map($row));
    }

    /**
     * Write multiple rows to the CSV file.
     *
     * @param iterable $iterator An iterable that yields rows to write.
     *
     * @return void
     */
    public function writeFrom(iterable $iterator): void
    {
        foreach ($iterator as $row) {
            $this->write($row);
        }
    }

}
