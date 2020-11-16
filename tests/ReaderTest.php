<?php

/**
 * Ork CSV
 *
 * @package   OrkTest\Csv
 * @copyright 2015-2020 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace OrkTest\Csv;

use org\bovigo\vfs\vfsStream;
use Ork\Csv\Reader;

/**
 * Test the Reader class.
 */
class ReaderTest extends \PHPUnit\Framework\TestCase
{

    /**
     * VFS handle.
     *
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $vfs;

    /**
     * Make a temporary file for reading.
     *
     * @param string $content The content to save in the file.
     *
     * @return string The file name.
     */
    protected function makeFile(string $content = null): string
    {
        $file = $this->vfs->url() . '/' . debug_backtrace()[1]['function'];
        file_put_contents($file, $content);
        return $file;
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
     * Test that we can't read a file with restrictive perms.
     *
     * @return void
     */
    public function testBadPerms(): void
    {
        $this->expectException(\RuntimeException::class);
        $file = $this->makeFile();
        chmod($file, 0000);
        $csv = new Reader(['file' => $file]);
        // @phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        @$csv->toArray();
    }

    /**
     * Test that we can specify callbacks for columns that might not exist.
     *
     * @return void
     */
    public function testCallbacksOnMissingColumn(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name
                1,Foo
                2,Bar
                3,Baz
                EOS
            ),
            'callbacks' => ['DoesNotExist' => 'strtolower'],
        ]);
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'Foo'],
                ['Id' => 2, 'Name' => 'Bar'],
                ['Id' => 3, 'Name' => 'Baz'],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that regex callbacks work.
     *
     * @return void
     */
    public function testCallbacksRegex(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id," Name"
                1," Foo "
                2,"Bar "
                3,Baz
                EOS
            ),
            'callbacks' => [
                '/./' => ['strtolower', 'trim'],
            ],
        ]);
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'foo'],
                ['Id' => 2, 'Name' => 'bar'],
                ['Id' => 3, 'Name' => 'baz'],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that multiple regex callbacks on the same field work.
     *
     * @return void
     */
    public function testCallbacksRegexMultiple(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name
                1," Foo "
                2,"Bar "
                3,BAZ
                EOS
            ),
            'callbacks' => [
                '/./' => 'strtolower',
                '/e/' => 'trim',
            ],
        ]);
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'foo'],
                ['Id' => 2, 'Name' => 'bar'],
                ['Id' => 3, 'Name' => 'baz'],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that callbacks with a header row work.
     *
     * @return void
     */
    public function testCallbacksWithHeader(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name
                1," Foo "
                2,"Bar "
                3,BAZ
                EOS
            ),
            'callbacks' => [
                'Name' => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'oof'],
                ['Id' => 2, 'Name' => 'rab'],
                ['Id' => 3, 'Name' => 'zab'],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that callbacks without a header row work.
     *
     * @return void
     */
    public function testCallbacksWithoutHeader(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                1000," Foo "
                2000,"Bar "
                3000,BAZ
                EOS
            ),
            'header' => false,
            'callbacks' => [
                0 => 'number_format',
                1 => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $this->assertEquals(
            [
                ['1,000', 'oof'],
                ['2,000', 'rab'],
                ['3,000', 'zab'],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that referencing a bad column in a headerless file fails.
     *
     * @return void
     */
    public function testGetBadColumnViaInt(): void
    {
        $this->expectException(\RuntimeException::class);
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                1,2,3
                4,5,6
                EOS
            ),
            'header' => false,
        ]);
        $null = iterator_to_array($csv->getColumn(100));
    }

    /**
     * Test that getting a bad column from a header file fails.
     *
     * @return void
     */
    public function testGetBadColumnViaString(): void
    {
        $this->expectException(\RuntimeException::class);
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name
                1,Foo
                2,Bar
                3,Baz
                EOS
            ),
        ]);
        $null = iterator_to_array($csv->getColumn('BadColumn'));
    }

    /**
     * Test that we can get the columns from a header file.
     *
     * @return void
     */
    public function testGetColumns(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name,Number
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
        ]);
        $this->assertEquals(['Id', 'Name', 'Number'], $csv->getColumns());
    }

    /**
     * Test that we get empty columns from a headerless file.
     *
     * @return void
     */
    public function testGetColumnsHeaderless(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
            'header' => false,
        ]);
        $this->assertEmpty($csv->getColumns());
    }

    /**
     * Test that we can get a column from a headerless file by integer.
     *
     * @return void
     */
    public function testGetColumnViaInt(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
            'header' => false,
        ]);
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn(0)));
        $this->assertEquals([4, 5, 6], iterator_to_array($csv->getColumn(2)));
    }

    /**
     * Test that we can get a column from a header file via column name.
     *
     * @return void
     */
    public function testGetColumnViaString(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name,Number
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
        ]);
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn('Id')));
        $this->assertEquals(['Foo', 'Bar', 'Baz'], iterator_to_array($csv->getColumn('Name')));
    }

    /**
     * Test that we can override the columns of a file with headers.
     *
     * @return void
     */
    public function testHeaderColumnOverride(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name,Number
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
            'columns' => ['one', 'two', 'three'],
        ]);
        $this->assertEquals(['one', 'two', 'three'], $csv->getColumns());
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn('one')));
        $this->assertEquals(['Foo', 'Bar', 'Baz'], iterator_to_array($csv->getColumn('two')));
    }

    /**
     * Test that we properly read a header file.
     *
     * @return void
     */
    public function testHeaderFile(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name,Number
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
        ]);
        $this->assertEquals(
            [
                ['Id' => 1, 'Name' => 'Foo', 'Number' => 4],
                ['Id' => 2, 'Name' => 'Bar', 'Number' => 5],
                ['Id' => 3, 'Name' => 'Baz', 'Number' => 6],
            ],
            $csv->toArray()
        );
    }

    /**
     * Test that we can specify the columns of a file without headers.
     *
     * @return void
     */
    public function testHeaderlessColumnExplicit(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                1,2,3,4,5
                6,7,8,9,10
                EOS
            ),
            'header' => false,
            'columns' => ['one', 'two', 'three', 'four', 'five'],
        ]);
        $this->assertEquals([1, 6], iterator_to_array($csv->getColumn('one')));
        $this->assertEquals([5, 10], iterator_to_array($csv->getColumn('five')));
    }

    /**
     * Test that we properly read a headerless file.
     *
     * @return void
     */
    public function testHeaderlessFile(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                1,2,3,4,5
                6,7,8,9,10
                EOS
            ),
            'header' => false,
        ]);
        $this->assertEquals([[1, 2, 3, 4, 5], [6, 7, 8, 9, 10]], $csv->toArray());
    }

    /**
     * Test that the line count works with a header file.
     *
     * @return void
     */
    public function testLineCountHeader(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name,Number
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
        ]);
        foreach ($csv as $index => $row) {
            $this->assertSame($index + 2, $csv->getLineNumber());
        }
        $this->assertSame(4, $csv->getLineNumber());
        // Make sure count gets reset for subsequent calls w/ same object.
        foreach ($csv as $index => $row) {
            $this->assertSame($index + 2, $csv->getLineNumber());
        }
        $this->assertSame(4, $csv->getLineNumber());
    }

    /**
     * Test that the line count works with a headerless file.
     *
     * @return void
     */
    public function testLineCountHeaderless(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
            'header' => false,
        ]);
        foreach ($csv as $index => $row) {
            $this->assertSame($index + 1, $csv->getLineNumber());
        }
        $this->assertSame(3, $csv->getLineNumber());
        // Make sure count gets reset for subsequent calls w/ same object.
        foreach ($csv as $index => $row) {
            $this->assertSame($index + 1, $csv->getLineNumber());
        }
        $this->assertSame(3, $csv->getLineNumber());
    }

    /**
     * Test that the line number is zero before we start.
     *
     * @return void
     */
    public function testLineNumberZeroBeforeStart(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name,Number
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
        ]);
        $this->assertSame(0, $csv->getLineNumber());
    }

    /**
     * Test that we detect a column mismatch.
     *
     * @return void
     */
    public function testMismatchFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name,Number
                1,Foo,4,extra
                2,Bar,5
                3,Baz,6
                EOS
            ),
        ]);
        $csv->toArray();
    }

    /**
     * Test that we can't reference files that don't exist.
     *
     * @return void
     */
    public function testMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $csv = new Reader([
            'file' => $this->vfs->url() . '/fileThatDoesNotExist',
        ]);
        // @phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        @$csv->toArray();
    }

    /**
     * Test that using non-unique columns fails.
     *
     * @return void
     */
    public function testNonUniqueColumn(): void
    {
        $this->expectException(\RuntimeException::class);
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                1,2,3,4,5
                6,7,8,9,10
                EOS
            ),
            'header' => false,
            'columns' => ['one', 'one', 'two', 'three', 'four'],
        ]);
        $csv->toArray();
    }

    /**
     * Test that we detect bad headers.
     *
     * @return void
     */
    public function testNotUniqueHeaderFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name,Id
                1,Foo,4
                2,Bar,5
                3,Baz,6
                EOS
            ),
        ]);
        $csv->toArray();
    }

    /**
     * Test that we can read via STDIN.
     *
     * This is kinda wonky. It looks like `composer <script>` sets STDIN to non-blocking, which is required for this
     * test to pass. However, TravisCI doesn't invoke the tests via the composer wrapper, so this just hangs waiting
     * for input. To avoid that, we'll explicitly set STDIN to non-blocking here.
     *
     * @return void
     *
     * @throws \RuntimeException On error.
     */
    public function testStdin(): void
    {
        $fh = fopen('php://stdin', 'r');
        if ($fh === false) {
            throw new \RuntimeException('STDIN open failed');
        }
        stream_set_blocking($fh, false);
        fclose($fh);
        $csv = new Reader(['file' => 'php://stdin']);
        $this->assertEmpty($csv->toArray());
    }

    /**
     * Test that reading to an associative array works.
     *
     * @return void
     */
    public function testToArrayAssociative(): void
    {
        $csv = new Reader([
            'file' => $this->makeFile(
                <<<'EOS'
                Id,Name
                1,Foo
                2,Bar
                3,Baz
                EOS
            ),
        ]);
        $this->assertEquals(
            [
                1 => ['Id' => 1, 'Name' => 'Foo'],
                2 => ['Id' => 2, 'Name' => 'Bar'],
                3 => ['Id' => 3, 'Name' => 'Baz'],
            ],
            $csv->toArray('Id')
        );
        $this->assertEquals(
            [
                'Foo' => ['Id' => 1, 'Name' => 'Foo'],
                'Bar' => ['Id' => 2, 'Name' => 'Bar'],
                'Baz' => ['Id' => 3, 'Name' => 'Baz'],
            ],
            $csv->toArray('Name')
        );
    }

}
