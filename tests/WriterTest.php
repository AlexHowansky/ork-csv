<?php

/**
 * Ork CSV
 *
 * @package   Ork\Csv\Tests
 * @copyright 2015-2021 Alex Howansky (https://github.com/AlexHowansky)
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
 * Test the Writer class.
 */
class WriterTest extends TestCase
{

    /**
     * VFS handle.
     *
     * @var vfsStreamDirectory
     */
    protected vfsStreamDirectory $vfs;

    /**
     * Get a virtual file named for the test we're currently running.
     *
     * @return string
     */
    protected function getTempFile(): string
    {
        return $this->vfs->url() . '/' . debug_backtrace()[1]['function'];
    }

    /**
     * Wrapper method for filter callable.
     *
     * @param string $value The string to reverse.
     *
     * @return string The reversed string.
     */
    public function reverse(string $value): string
    {
        return strrev($value);
    }

    /**
     * Set up each test.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->vfs = vfsStream::setup();
    }

    /**
     * Test that we can specify callbacks for columns that might not exist.
     *
     * @return void
     */
    public function testCallbacksOnMissingColumn(): void
    {
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'callbacks' => ['Email' => ['strtolower', 'trim']],
        ]);
        $csv->writeFromIterator([
            ['Id' => 1, 'Name' => 'Foo'],
            ['Id' => 2, 'Name' => 'Bar'],
        ]);
        $this->assertSame(
            <<<'EOS'
            Id,Name
            1,Foo
            2,Bar

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that regex callbacks work.
     *
     * @return void
     */
    public function testCallbacksRegex(): void
    {
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'callbacks' => [
                '/^I/' => fn($value) => $value / 10,
                '/e/' => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $csv->writeFromIterator([
            ['Id' => 10, 'Name' => ' FOO '],
            ['Id' => 20, 'Name' => ' Bar '],
            ['Id' => 30, 'Name' => 'baz'],
        ]);
        $this->assertEquals(
            <<<'EOS'
            Id,Name
            1,oof
            2,rab
            3,zab

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that multiple regex callbacks on the same field work.
     *
     * @return void
     */
    public function testCallbacksRegexMultiple(): void
    {
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'callbacks' => [
                '/./' => 'strtolower',
                '/e/' => 'trim',
            ],
        ]);
        $csv->writeFromIterator([
            ['Id' => 1, 'Name' => ' FOO '],
            ['Id' => 2, 'Name' => ' Bar '],
            ['Id' => 3, 'Name' => 'baz'],
        ]);
        $this->assertEquals(
            <<<'EOS'
            Id,Name
            1,foo
            2,bar
            3,baz

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that callbacks work on associative array input.
     *
     * @return void
     */
    public function testCallbacksWithAssociative(): void
    {
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'callbacks' => [
                'Id' => 'number_format',
                'Name' => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $csv->writeFromIterator([
            ['Id' => 1000, 'Name' => ' FOO '],
            ['Id' => 2000, 'Name' => ' Bar '],
            ['Id' => 3000, 'Name' => 'baz'],
        ]);
        $this->assertSame(
            <<<'EOS'
            Id,Name
            "1,000",oof
            "2,000",rab
            "3,000",zab

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that callbacks work on files with indexed array input.
     *
     * @return void
     */
    public function testCallbacksWithIndexed(): void
    {
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'header' => false,
            'callbacks' => [
                0 => 'strtolower',
                1 => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $csv->writeFromIterator([
            [' FOO ', ' FOO '],
            [' Bar ', ' Bar '],
            ['baz', 'baz'],
        ]);
        $this->assertSame(
            <<<'EOS'
            " foo ",oof
            " bar ",rab
            baz,zab

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that we detect failure to create the output file.
     *
     * @return void
     */
    public function testCreateWriteFail(): void
    {
        $this->expectException(RuntimeException::class);
        $csv = new Writer(['file' => 'php://foo']);
        // @phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        @$csv->write([1, 2, 3, 4, 5]);
    }

    /**
     * Test that we can explicitly specify column names.
     *
     * @return void
     */
    public function testExplicitColumns(): void
    {
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'columns' => ['Id', 'Name', 'Email'],
            'strict' => false,
        ]);
        $csv->writeFromIterator([
            ['Id' => 1, 'Name' => 'foo'],
            ['Name' => 'baz', 'Email' => 'foo@bar.com'],
        ]);
        $this->assertSame(
            <<<'EOS'
            Id,Name,Email
            1,foo,
            ,baz,foo@bar.com

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that we get a proper line count.
     *
     * @return void
     */
    public function testGetLineNumber(): void
    {
        $csv = new Writer(['file' => $this->getTempFile()]);
        $this->assertSame(0, $csv->getLineNumber());
        $csv->writeFromIterator([
            ['Id' => 1, 'Name' => 'foo'],
            ['Id' => 2, 'Name' => 'bar'],
            ['Id' => 3, 'Name' => 'baz'],
        ]);
        $this->assertSame(3, $csv->getLineNumber());
    }

    /**
     * Test that we correctly rearrange out-of-order columns.
     *
     * @return void
     */
    public function testOutOfOrderColumns(): void
    {
        $csv = new Writer(['file' => $this->getTempFile()]);
        $csv->writeFromIterator([
            ['Id' => 1, 'Name' => 'foo'],
            ['Name' => 'bar', 'Id' => 2],
            ['Id' => 3, 'Name' => 'baz'],
        ]);
        $this->assertSame(
            <<<'EOS'
            Id,Name
            1,foo
            2,bar
            3,baz

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that the write() return value is correct.
     *
     * @return void
     */
    public function testReturnValue(): void
    {
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'header' => false,
        ]);
        $this->assertSame(20, $csv->write([0, 1, 2, 3, 4, 5, 6, 7, 8, 9]));
    }

    /**
     * Test that we can skip columns.
     *
     * @return void
     */
    public function testSkippedColumns(): void
    {
        $csv = new Writer(['file' => $this->getTempFile()]);
        $csv->writeFromIterator([
            ['one' => 1, 'two' => 2,'three' => 3],
            ['two' => 2, 'three' => 3],
            ['one' => 1, 'three' => 3],
            ['one' => 1, 'two' => 2],
            ['one' => 1],
            ['two' => 2],
            ['three' => 3],
        ]);
        $this->assertSame(
            <<<'EOS'
            one,two,three
            1,2,3
            ,2,3
            1,,3
            1,2,
            1,,
            ,2,
            ,,3

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that strict mode off works.
     *
     * @return void
     */
    public function testUnknownColumnLenient(): void
    {
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'strict' => false,
        ]);
        $csv->writeFromIterator([
            ['foo' => 1, 'bar' => 2],
            ['foo' => 3, 'bar' => 4],
            ['foo' => 5, 'bar' => 6, 'baz' => 7],
        ]);
        $this->assertSame(
            <<<'EOS'
            foo,bar
            1,2
            3,4
            5,6

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that strict mode on works.
     *
     * @return void
     */
    public function testUnknownColumnStrict(): void
    {
        $this->expectException(RuntimeException::class);
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'strict' => true,
        ]);
        $csv->writeFromIterator([
            ['foo' => 1, 'bar' => 2],
            ['foo' => 3, 'bar' => 4],
            ['foo' => 5, 'bar' => 6, 'baz' => 7],
        ]);
    }

    /**
     * Test that we detect failure to write to the output file.
     *
     * @return void
     */
    public function testWriteFail(): void
    {
        $this->expectException(RuntimeException::class);
        vfsStream::setQuota(1);
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'header' => false,
        ]);
        $csv->write([1, 2, 3, 4, 5]);
        vfsStream::setQuota(0);
    }

    /**
     * Test that we can iterate over an array.
     *
     * @return void
     */
    public function testWriteFromIteratorWithArray(): void
    {
        $csv = new Writer(['file' => $this->getTempFile()]);
        $this->assertSame(
            3,
            $csv->writeFromIterator([
                ['foo' => 1, 'bar' => 2, 'baz' => 3],
                ['foo' => 4, 'bar' => 5, 'baz' => 6],
                ['foo' => 7, 'bar' => 8, 'baz' => 9],
            ])
        );
        $this->assertSame(
            <<<'EOS'
            foo,bar,baz
            1,2,3
            4,5,6
            7,8,9

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that we can iterate over an iterator.
     *
     * @return void
     */
    public function testWriteFromIteratorWithTraversable(): void
    {
        $csv = new Writer(['file' => $this->getTempFile()]);
        $this->assertSame(
            3,
            $csv->writeFromIterator(
                new ArrayIterator([
                    ['foo' => 1, 'bar' => 2, 'baz' => 3],
                    ['foo' => 4, 'bar' => 5, 'baz' => 6],
                    ['foo' => 7, 'bar' => 8, 'baz' => 9],
                ])
            )
        );
        $this->assertSame(
            <<<'EOS'
            foo,bar,baz
            1,2,3
            4,5,6
            7,8,9

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that we create the header correctly.
     *
     * @return void
     */
    public function testWriteHeader(): void
    {
        $csv = new Writer(['file' => $this->getTempFile()]);
        $csv->writeFromIterator([
            ['Id' => 1, 'Name' => 'foo'],
            ['Id' => 2, 'Name' => 'bar'],
            ['Id' => 3, 'Name' => 'baz'],
        ]);
        $this->assertSame(
            <<<'EOS'
            Id,Name
            1,foo
            2,bar
            3,baz

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

    /**
     * Test that we don't create a header row when specified.
     *
     * @return void
     */
    public function testWriteNoHeader(): void
    {
        $csv = new Writer([
            'file' => $this->getTempFile(),
            'header' => false,
        ]);
        $csv->write([1, 2, 3, 4, 5]);
        $csv->write([6, 7, 8, 9, 10]);
        $this->assertSame(
            <<<'EOS'
            1,2,3,4,5
            6,7,8,9,10

            EOS,
            file_get_contents($this->getTempFile())
        );
    }

}
