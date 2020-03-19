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
 * CSV abstract.
 */
abstract class CsvAbstract
{

    use \Ork\Core\ConfigurableTrait;

    /**
     * Apply callbacks.
     *
     * @param array $row The row to process.
     *
     * @return array The processed row.
     *
     * @throws \RuntimeException On missing column reference.
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
            if (array_key_exists($column, $row) === false) {
                throw new \RuntimeException('Unable to apply callback to missing column: ' . $column);
            }
            foreach ((array) $callbacks as $callback) {
                $row[$column] = call_user_func($callback, $row[$column]);
            }

        }
        return $row;
    }

}
