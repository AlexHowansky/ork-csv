<?php

/**
 * Ork CSV
 *
 * @package   Ork\Csv\Tests
 * @copyright 2015-2024 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace Ork\Csv\Tests;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Ork\Csv\Reader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

/**
 * CSV reader test.
 */
class ReaderTest extends TestCase
{

    protected vfsStreamDirectory $vfs;

    protected function makeFile(string $content = "Id,Name,Number\n1,foo,123\n2,bar,456\n3,baz,789\n"): string
    {
        $file = $this->vfs->url() . '/' . debug_backtrace()[1]['function'];
        file_put_contents($file, $content);
        return $file;
    }

    /**
     * Wrapper to test callbacks using the `$this->reverse(...)` callable style.
     */
    public function reverse(string $value): string
    {
        return strrev($value);
    }

    public function setUp(): void
    {
        $this->vfs = vfsStream::setup();
    }

    /**
     * Test that we can specify callbacks for columns that might not exist.
     */
    public function testCallbacksOnMissingColumn(): void
    {
        $file = $this->makeFile();
        $csv = new Reader(
            file: $file,
            callbacks: ['DoesNotExist' => 'strtolower'],
        );
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'foo', 'Number' => 123],
                ['Id' => 2, 'Name' => 'bar', 'Number' => 456],
                ['Id' => 3, 'Name' => 'baz', 'Number' => 789],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that we can specify callbacks for column names by regex.
     */
    public function testCallbacksRegex(): void
    {
        $file = $this->makeFile();
        $csv = new Reader(
            file: $file,
            callbacks: ['/./' => ['strtoupper', 'strrev']],
        );
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'OOF', 'Number' => 321],
                ['Id' => 2, 'Name' => 'RAB', 'Number' => 654],
                ['Id' => 3, 'Name' => 'ZAB', 'Number' => 987],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that multiple regex callbacks on the same field work.
     */
    public function testCallbacksRegexMultiple(): void
    {
        $file = $this->makeFile();
        $csv = new Reader(
            file: $file,
            callbacks: [
                '/./' => 'strtoupper',
                '/^nu/i' => 'strrev',
            ],
        );
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'FOO', 'Number' => 321],
                ['Id' => 2, 'Name' => 'BAR', 'Number' => 654],
                ['Id' => 3, 'Name' => 'BAZ', 'Number' => 987],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that we can specify a single callback, not in an array.
     */
    public function testCallbacksSingleNotAsArray(): void
    {
        $file = $this->makeFile();
        $csv = new Reader(
            file: $file,
            callbacks: ['Name' => 'strtoupper'],
        );
        $this->assertSame(['FOO', 'BAR', 'BAZ'], iterator_to_array($csv->getColumn('Name')));
    }

    /**
     * Test that callbacks work on columns referenced by index.
     */
    public function testCallbacksViaColumnIndex(): void
    {
        $file = $this->makeFile("1,foo,123\n2,bar,456\n3,baz,789\n");
        $csv = new Reader(
            file: $file,
            callbacks: [
                1 => [
                    'strtoupper',
                    $this->reverse(...),
                    fn($value) => lcfirst((string) $value),
                ],
                2 => [
                    'strrev',
                    fn($value) => $value * 2,
                ],
            ],
            hasHeader: false,
        );
        $this->assertEquals(
            [
                [1, 'oOF', '642'],
                [2, 'rAB', '1308'],
                [3, 'zAB', '1974'],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that callbacks work on columns referenced by name.
     */
    public function testCallbacksViaColumnName(): void
    {
        $file = $this->makeFile();
        $csv = new Reader(
            file: $file,
            callbacks: [
                'Name' => [
                    'strtoupper',
                    $this->reverse(...),
                    fn($value) => lcfirst((string) $value),
                ],
                'Number' => [
                    'strrev',
                    fn($value) => $value * 2,
                ],
            ],
        );
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'oOF', 'Number' => '642'],
                ['Id' => 2, 'Name' => 'rAB', 'Number' => '1308'],
                ['Id' => 3, 'Name' => 'zAB', 'Number' => '1974'],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that providing a non-callable as a callback fails.
     */
    public function testCallbacksWithNotCallable(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/is not callable/i');
        new Reader(callbacks: ['Name' => 'functionThatDoesNotExist']);
    }

    /**
     * Test that we detect a column mismatch.
     */
    public function testColumnMismatch(): void
    {
        $file = $this->makeFile("Id,Name,Number\n1,foo,123\n2,bar,456,extra\n3,baz,789\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/column mismatch/i');
        (new Reader($file))->toArray();
    }

    /**
     * Test that custom delimiter characters work.
     */
    public function testDelimiterCharacter(): void
    {
        $file = $this->makeFile("Id|Name|Number\n1|foo|123\n2|bar|456\n3|baz|789\n");
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'foo', 'Number' => 123],
                ['Id' => 2, 'Name' => 'bar', 'Number' => 456],
                ['Id' => 3, 'Name' => 'baz', 'Number' => 789],
            ],
            (new Reader(file: $file, delimiterCharacter: '|'))->toArray()
        );
    }

    /**
     * Test that we can read from an open file handle.
     */
    public function testFileHandle(): void
    {
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'foo', 'Number' => 123],
                ['Id' => 2, 'Name' => 'bar', 'Number' => 456],
                ['Id' => 3, 'Name' => 'baz', 'Number' => 789],
            ],
            (new Reader(fopen($this->makeFile(), 'r')))->toArray()
        );
    }

    /**
     * Test that we can't read a file with restrictive permissions.
     */
    public function testFileWithBadPermissions(): void
    {
        $file = $this->makeFile();
        $this->assertNotEmpty((new Reader($file))->toArray());
        chmod($file, 0000);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed to open file/i');
        (new Reader($file))->toArray();
    }

    /**
     * Test that we can properly read a file with a header row.
     */
    public function testFileWithHeader(): void
    {
        $file = $this->makeFile();
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'foo', 'Number' => 123],
                ['Id' => 2, 'Name' => 'bar', 'Number' => 456],
                ['Id' => 3, 'Name' => 'baz', 'Number' => 789],
            ],
            (new Reader($file))->toArray()
        );
    }

    /**
     * Test that we can properly read a file without a header row.
     */
    public function testFileWithoutHeader(): void
    {
        $expect = [[1, 'foo', 123], [2, 'bar', 456], [3, 'baz', 789]];
        $file = $this->makeFile("1,foo,123\n2,bar,456\n3,baz,789\n");
        $this->assertEquals($expect, (new Reader(file: $file, hasHeader: false))->toArray());
    }

    /**
     * Test that referencing a bad column index in a file without a header fails.
     */
    public function testGetBadColumnViaIndex(): void
    {
        $file = $this->makeFile("1,foo,123\n2,bar,456\n3,baz,789\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no such column/i');
        (new Reader(file: $file, hasHeader: false))->getColumn(100)->current();
    }

    /**
     * Test that referencing a bad column index in a file with a header fails.
     */
    public function testGetBadColumnViaName(): void
    {
        $file = $this->makeFile();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no such column/i');
        (new Reader(file: $file, hasHeader: false))->getColumn('DoesNotExist')->current();
    }

    /**
     * Test that we can get the column names from a file with a header row.
     */
    public function testGetColumnNamesForFileWithHeader(): void
    {
        $expect = ['Id', 'Name', 'Number'];
        $file = $this->makeFile();
        $csv = new Reader($file);

        $this->assertSame(0, $csv->getLineNumber());
        $this->assertSame($expect, $csv->getColumnNames());

        // Make sure the line counter skipped over the header row.
        $this->assertSame(2, $csv->getLineNumber());

        // Invoke the method again and make sure we did not iterate again.
        $this->assertSame($expect, $csv->getColumnNames());
        $this->assertSame(2, $csv->getLineNumber());

        // Consume the whole file and make sure we still have columns available.
        $csv->toArray();
        $this->assertSame($expect, $csv->getColumnNames());
        $this->assertSame(4, $csv->getLineNumber());
    }

    /**
     * Test that we get empty column names from a file without a header row.
     */
    public function testGetColumnNamesForFileWithoutHeader(): void
    {
        $file = $this->makeFile("1,foo,123\n2,bar,456\n3,baz,789\n");
        $csv = new Reader(
            file: $file,
            hasHeader: false,
        );

        // Make sure we don't iterate when getting columns.
        $this->assertSame(0, $csv->getLineNumber());
        $this->assertEmpty($csv->getColumnNames());
        $this->assertSame(0, $csv->getLineNumber());

        // Make sure nothing changes after consuming the file.
        $csv->toArray();
        $this->assertEmpty($csv->getColumnNames());
        $this->assertSame(3, $csv->getLineNumber());
    }

    /**
     * Test that column names are trimmed.
     */
    public function testGetColumnNamesTrimmed(): void
    {
        $file = $this->makeFile('Id  ,  Name,  Number  ');
        $this->assertSame(['Id', 'Name', 'Number'], (new Reader($file))->getColumnNames());
    }

    /**
     * Test that we can get a column from a file without a header row via column index.
     */
    public function testGetColumnViaIndex(): void
    {
        $file = $this->makeFile("1,foo,123\n2,bar,456\n3,baz,789\n");
        $csv = new Reader(file: $file, hasHeader: false);
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn(0)));
        $this->assertEquals(['foo', 'bar', 'baz'], iterator_to_array($csv->getColumn(1)));
    }

    /**
     * Test that we can get a column from a file with a header row via column name.
     */
    public function testGetColumnViaName(): void
    {
        $file = $this->makeFile();
        $csv = new Reader($file);
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn('Id')));
        $this->assertEquals(['foo', 'bar', 'baz'], iterator_to_array($csv->getColumn('Name')));
    }

    /**
     * Test that we can filter rows via a callback.
     */
    public function testGetWhere(): void
    {
        $csv = new Reader(
            callbacks: ['Id' => 'intval'],
            file: $this->makeFile()
        );
        $this->assertEquals(
            [
                ['Id' => 2, 'Name' => 'bar', 'Number' => 456],
            ],
            iterator_to_array($csv->getWhere(fn(array $row): bool => $row['Id'] === 2))
        );
    }

    public function testInvalidFileType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/file must be a string or file handle/i');
        // @phpstan-ignore-next-line
        new Reader([]);
    }

    public function testKeyByColumn(): void
    {
        $file = $this->makeFile();
        $csv = new Reader(file: $file, keyByColumn: 'Name');
        $this->assertEquals(
            [
                'foo' => ['Id' => 1, 'Name' => 'foo', 'Number' => 123],
                'bar' => ['Id' => 2, 'Name' => 'bar', 'Number' => 456],
                'baz' => ['Id' => 3, 'Name' => 'baz', 'Number' => 789],
            ],
            $csv->toArray()
        );
    }

    public function testKeyByColumnWithBadColumn(): void
    {
        $file = $this->makeFile();
        $csv = new Reader(file: $file, keyByColumn: 'columnThatDoesNotExist');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no such column/i');
        $csv->toArray();
    }

    public function testKeyByColumnWithNotUniqueKeyAllowed(): void
    {
        $file = $this->makeFile("Id,Name,Number\n1,foo,123\n2,bar,456\n3,foo,789\n");
        $csv = new Reader(file: $file, keyByColumn: 'Name', detectDuplicateKeys: false);
        $this->assertCount(2, $csv->toArray());
    }

    public function testKeyByColumnWithNotUniqueKeyDisallowed(): void
    {
        $file = $this->makeFile("Id,Name,Number\n1,foo,123\n2,bar,456\n3,foo,789\n");
        $csv = new Reader(file: $file, keyByColumn: 'Name', detectDuplicateKeys: true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/duplicate key detected/i');
        $csv->toArray();
    }

    /**
     * Test that the line count works with a header file.
     */
    public function testLineCountHeader(): void
    {
        $file = $this->makeFile();
        $csv = new Reader($file);

        foreach ($csv as $index => $row) {
            $this->assertSame($index + 2, $csv->getLineNumber());
        }
        $this->assertSame(4, $csv->getLineNumber());

        // Make sure the count gets reset for subsequent iterations on the same object.
        foreach ($csv as $index => $row) {
            $this->assertSame($index + 2, $csv->getLineNumber());
        }
        $this->assertSame(4, $csv->getLineNumber());
    }

    /**
     * Test that the line count works with a headerless file.
     */
    public function testLineCountHeaderless(): void
    {
        $file = $this->makeFile("1,foo,123\n2,bar,456\n3,baz,789\n");
        $csv = new Reader(file: $file, hasHeader: false);

        foreach ($csv as $index => $row) {
            $this->assertSame($index + 1, $csv->getLineNumber());
        }
        $this->assertSame(3, $csv->getLineNumber());

        // Make sure the count gets reset for subsequent iterations on the same object.
        foreach ($csv as $index => $row) {
            $this->assertSame($index + 1, $csv->getLineNumber());
        }
        $this->assertSame(3, $csv->getLineNumber());
    }

    /**
     * Test that the line number is zero before we start.
     */
    public function testLineNumberZeroBeforeStart(): void
    {
        $file = $this->makeFile();
        $csv = new Reader($file);
        $this->assertSame(0, $csv->getLineNumber());
    }

    public function testLongStringDelimiter(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must be a single character');
        new Reader(delimiterCharacter: 'moreThanOneCharacter');
    }

    public function testLongStringEscape(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must be a single character');
        new Reader(escapeCharacter: 'moreThanOneCharacter');
    }

    public function testLongStringQuote(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('must be a single character');
        new Reader(quoteCharacter: 'moreThanOneCharacter');
    }

    /**
     * Test that we can't read a file that doesn't exist.
     */
    public function testMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed to open file/i');
        (new Reader($this->vfs->url() . '/fileThatDoesNotExist'))->toArray();
    }

    /**
     * Test that we successfully detect non-unique column names in a header row.
     */
    public function testNotUniqueHeaderRowColumnNames(): void
    {
        $file = $this->makeFile("Id,Name,Number, Id\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/are not unique/i');
        (new Reader($file))->toArray();
    }

    /**
     * Test that we successfully detect manually-specified non-unique column names.
     */
    public function testNotUniqueManuallySpecifiedColumnNames(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/are not unique/i');
        new Reader(columnNames: ['Id', 'Name', 'Number', ' Id']);
    }

    public function testNullColumnsHeader(): void
    {
        $csv = new Reader(
            file: $this->makeFile(),
            hasHeader: true,
            columnNames: ['Id', null, ''],
        );
        $this->assertEquals(
            [
                ['Id' => 1],
                ['Id' => 2],
                ['Id' => 3],
            ],
            $csv->toArray()
        );
    }

    public function testNullColumnsNoHeader(): void
    {
        $csv = new Reader(
            file: $this->makeFile("1,foo,123\n2,bar,456\n3,baz,789\n"),
            hasHeader: false,
            columnNames: ['Id', null, ''],
        );
        $this->assertEquals(
            [
                ['Id' => 1],
                ['Id' => 2],
                ['Id' => 3],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that we can override the column names of a file with a header row.
     */
    public function testProvideColumnNamesForFileWithHeader(): void
    {
        $file = $this->makeFile();
        $csv = new Reader(
            file: $file,
            hasHeader: true,
            columnNames: ['one', 'two', 'three'],
        );
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn('one')));
        $this->assertEquals(['foo', 'bar', 'baz'], iterator_to_array($csv->getColumn('two')));
        $this->assertEquals([123, 456, 789], iterator_to_array($csv->getColumn('three')));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no such column/i');
        $csv->getColumn('Id')->current();
    }

    /**
     * Test that we can specify the column names for a file without a header row.
     */
    public function testProvideColumnNamesForFileWithoutHeader(): void
    {
        $file = $this->makeFile("1,foo,123\n2,bar,456\n3,baz,789\n");
        $csv = new Reader(
            file: $file,
            hasHeader: false,
            columnNames: ['Id', 'Name', 'Number'],
        );
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn('Id')));
        $this->assertEquals(['foo', 'bar', 'baz'], iterator_to_array($csv->getColumn('Name')));
        $this->assertEquals([123, 456, 789], iterator_to_array($csv->getColumn('Number')));
    }

    /**
     * Test that we can read from STDIN.
     */
    public function testStdin(): void
    {
        $fh = fopen('php://stdin', 'r');
        if ($fh === false) {
            $this->fail('STDIN open failed');
        }
        stream_set_blocking($fh, false);
        fclose($fh);
        $this->assertEmpty((new Reader())->toArray());
    }

}
