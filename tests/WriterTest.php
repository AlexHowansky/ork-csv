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

use ArrayIterator;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Ork\Csv\Writer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * CSV writer test.
 */
class WriterTest extends TestCase
{

    protected const DEFAULT_DATA = [
        ['Id' => 1, 'Name' => 'foo', 'Number' => 123],
        ['Id' => 2, 'Name' => 'bar', 'Number' => 456],
        ['Id' => 3, 'Name' => 'baz', 'Number' => 789],
    ];

    protected vfsStreamDirectory $vfs;

    protected function getFile(): string
    {
        $bt = debug_backtrace();
        return sprintf(
            '%s/%s',
            $this->vfs->url(),
            $bt[1]['function'] === 'getFileContents' ? $bt[2]['function'] : $bt[1]['function']
        );
    }

    protected function getFileContents(): string
    {
        return file_get_contents($this->getFile());
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

    public function testCallbacksOnMissingColumn(): void
    {
        $csv = new Writer(
            file: $this->getFile(),
            callbacks: ['DoesNotExist' => 'strtolower'],
        );
        $csv->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("Id,Name,Number\n1,foo,123\n2,bar,456\n3,baz,789\n", $this->getFileContents());
    }

    public function testCallbacksRegex(): void
    {
        $csv = new Writer(
            file: $this->getFile(),
            callbacks: ['/./' => ['strtoupper', 'strrev']],
        );
        $csv->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("Id,Name,Number\n1,OOF,321\n2,RAB,654\n3,ZAB,987\n", $this->getFileContents());
    }

    public function testCallbacksRegexMultiple(): void
    {
        $csv = new Writer(
            file: $this->getFile(),
            callbacks: [
                '/./' => 'strtoupper',
                '/^nu/i' => 'strrev',
            ],
        );
        $csv->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("Id,Name,Number\n1,FOO,321\n2,BAR,654\n3,BAZ,987\n", $this->getFileContents());
    }

    public function testCallbacksViaColumnIndex(): void
    {
        $csv = new Writer(
            file: $this->getFile(),
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
            hasHeader: false,
        );
        $csv->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("1,oOF,642\n2,rAB,1308\n3,zAB,1974\n", $this->getFileContents());
    }

    public function testCallbacksViaColumnName(): void
    {
        $csv = new Writer(
            file: $this->getFile(),
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
            ]
        );
        $csv->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("Id,Name,Number\n1,oOF,642\n2,rAB,1308\n3,zAB,1974\n", $this->getFileContents());
    }

    public function testCreateFileFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed to create file/i');
        (new Writer('php://foo'))->write([]);
    }

    public function testDelimiterCharacter(): void
    {
        $csv = new Writer(file: $this->getFile(), delimiterCharacter: '|');
        $csv->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("Id|Name|Number\n1|foo|123\n2|bar|456\n3|baz|789\n", $this->getFileContents());
    }

    public function testExplicitColumnNames(): void
    {
        $csv = new Writer(file: $this->getFile(), columnNames: ['Number', 'Id']);
        $csv->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("Number,Id\n123,1\n456,2\n789,3\n", $this->getFileContents());
    }

    public function testGetLineNumberWithHeader(): void
    {
        $csv = new Writer(file: $this->getFile(), hasHeader: true);
        $this->assertSame(0, $csv->getLineNumber());
        $csv->writeFrom(static::DEFAULT_DATA);
        $this->assertSame(4, $csv->getLineNumber());
    }

    public function testGetLineNumberWithoutHeader(): void
    {
        $csv = new Writer(file: $this->getFile(), hasHeader: false);
        $this->assertSame(0, $csv->getLineNumber());
        $csv->writeFrom(static::DEFAULT_DATA);
        $this->assertSame(3, $csv->getLineNumber());
    }

    public function testOutOfOrderColumns(): void
    {
        $csv = new Writer($this->getFile());
        $csv->writeFrom([
            ['Id' => 1, 'Name' => 'foo', 'Number' => 123],
            ['Number' => 456, 'Name' => 'bar', 'Id' => 2],
            ['Id' => 3, 'Name' => 'baz', 'Number' => 789],
        ]);
        $this->assertSame("Id,Name,Number\n1,foo,123\n2,bar,456\n3,baz,789\n", $this->getFileContents());
    }

    public function testSkippedColumns(): void
    {
        $csv = new Writer($this->getFile());
        $csv->writeFrom([
            ['one' => 1, 'two' => 2,'three' => 3],
            ['two' => 2, 'three' => 3],
            ['one' => 1, 'three' => 3],
            ['one' => 1, 'two' => 2],
            ['one' => 1],
            ['two' => 2],
            ['three' => 3],
        ]);
        $this->assertSame("one,two,three\n1,2,3\n,2,3\n1,,3\n1,2,\n1,,\n,2,\n,,3\n", $this->getFileContents());
    }

    public function testUnknownColumnAllow(): void
    {
        $csv = new Writer(file: $this->getFile(), allowUnknownColumns: true);
        $csv->writeFrom([
            ['Id' => 1, 'Name' => 'foo', 'Number' => 123],
            ['Id' => 2, 'Name' => 'bar', 'Number' => 456],
            ['Id' => 3, 'Name' => 'baz', 'Number' => 789, 'Extra' => 'foo'],
        ]);
        $this->assertSame("Id,Name,Number\n1,foo,123\n2,bar,456\n3,baz,789\n", $this->getFileContents());
    }

    public function testUnknownColumnDisallow(): void
    {
        $csv = new Writer(file: $this->getFile(), allowUnknownColumns: false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/unknown column detected/i');
        $csv->writeFrom([
            ['Id' => 1, 'Name' => 'foo', 'Number' => 123],
            ['Id' => 2, 'Name' => 'bar', 'Number' => 456],
            ['Id' => 3, 'Name' => 'baz', 'Number' => 789, 'Extra' => 'foo'],
        ]);
    }

    public function testWriteFromArray(): void
    {
        (new Writer($this->getFile()))->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("Id,Name,Number\n1,foo,123\n2,bar,456\n3,baz,789\n", $this->getFileContents());
    }

    public function testWriteFromIterator(): void
    {
        (new Writer($this->getFile()))->writeFrom(new ArrayIterator(static::DEFAULT_DATA));
        $this->assertSame("Id,Name,Number\n1,foo,123\n2,bar,456\n3,baz,789\n", $this->getFileContents());
    }

    public function testWriteRowFails(): void
    {
        $csv = new Writer($this->getFile());
        $csv->write([1, 2, 3]);
        chmod($this->getFile(), 0000);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed to write to file/i');
        $csv->write([1, 2, 3]);
    }

    public function testWriteToExistingFileWithAppend(): void
    {
        file_put_contents($this->getFile(), "Id,Name,Number\n1,foo,123\n2,bar,456\n");
        (new Writer(file: $this->getFile(), appendToExistingFile: true))
            ->write(['Id' => 3, 'Name' => 'baz', 'Number' => 789]);
        $this->assertSame("Id,Name,Number\n1,foo,123\n2,bar,456\n3,baz,789\n", $this->getFileContents());
    }

    public function testWriteToExistingFileWithoutAppend(): void
    {
        file_put_contents($this->getFile(), "Id,Name,Number\n1,foo,123\n2,bar,456\n");
        (new Writer(file: $this->getFile(), appendToExistingFile: false))
            ->write(['Id' => 1, 'Name' => 'foo', 'Number' => 123]);
        $this->assertSame("Id,Name,Number\n1,foo,123\n", $this->getFileContents());
    }

    public function testWriteToFileHandle(): void
    {
        (new Writer(fopen($this->getFile(), 'w')))->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("Id,Name,Number\n1,foo,123\n2,bar,456\n3,baz,789\n", $this->getFileContents());
    }

    public function testWriteWithHeader(): void
    {
        (new Writer(file: $this->getFile(), hasHeader: true))->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("Id,Name,Number\n1,foo,123\n2,bar,456\n3,baz,789\n", $this->getFileContents());
    }

    public function testWriteWithoutHeader(): void
    {
        (new Writer(file: $this->getFile(), hasHeader: false))->writeFrom(static::DEFAULT_DATA);
        $this->assertSame("1,foo,123\n2,bar,456\n3,baz,789\n", $this->getFileContents());
    }

}
