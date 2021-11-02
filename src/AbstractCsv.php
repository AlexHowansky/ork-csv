<?php

/**
 * Ork CSV
 *
 * @package   Ork\Csv
 * @copyright 2015-2021 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace Ork\Csv;

use Ork\Core\ConfigurableTrait;

/**
 * CSV abstract.
 */
abstract class AbstractCsv
{

    use ConfigurableTrait;

    /**
     * Contains the line count.
     *
     * @var int
     */
    protected int $line = 0;

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

            // Interpret as a regex and apply to all matching columns.
            if (strpos($column, '/') === 0) {
                foreach (array_keys($row) as $name) {
                    if (preg_match($column, (string) $name) === 1) {
                        foreach ((array) $callbacks as $callback) {
                            $row[$name] = call_user_func($callback, $row[$name]);
                        }
                    }
                }
                continue;
            }

            // Apply to one explicitly named column.
            if (array_key_exists($column, $row) === true) {
                foreach ((array) $callbacks as $callback) {
                    $row[$column] = call_user_func($callback, $row[$column]);
                }
            }

        }
        return $row;
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

}
