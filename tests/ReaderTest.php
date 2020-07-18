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
     * Get a virtual file.
     *
     * @param string $name The name of the file to get.
     *
     * @return string
     */
    protected function getFile(string $name): string
    {
        $file = $this->vfs->url() . '/' . $name . '.csv';
        $content = [
            'callbacksWithHeader' => "Id,Name\n1,Foo\n2, Bar\n3, BAZ \n",
            'callbacksWithoutHeader' => "1000,Foo\n2000, Bar\n3000, BAZ \n",
            'header' => "Id , Name, Number\n1,Foo,37\n2,Bar,142\n3,Baz,71\n",
            'headerless' => "1,2,3,4,5\n6,7,8,9,10\n",
            'mismatch' => "Id,Name,Number\n1,Foo,37\n2,Bar\n3,Baz,71\n",
            'notUniqueHeader' => "Id,Id,Number\n1,Foo,37\n2,Bar,142\n3,Baz,71\n",
            'perms' => "a,b,c,d,e\n",
        ];
        if (array_key_exists($name, $content) === true) {
            file_put_contents($file, $content[$name]);
            if ($name === 'perms') {
                chmod($file, 0000);
            }
        }
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
        error_reporting(E_ALL & ~E_WARNING);
        $this->vfs = vfsStream::setup();
    }

    /**
     * Test that we can't read a file with restrictive perms.
     *
     * @return void
     */
    public function testBadPerms()
    {
        $this->expectException(\RuntimeException::class);
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('perms'),
        ]);
        $csv->toArray();
    }

    /**
     * Test that we detect a callback on a missing column
     *
     * @return void
     */
    public function testCallbackOnMissingColumn()
    {
        $this->expectException(\RuntimeException::class);
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('callbacksWithHeader'),
            'callbacks' => [
                'DoesNotExist' => ['strtolower'],
            ],
        ]);
        $csv->toArray('Id');
    }

    /**
     * Test that regex callbacks work.
     *
     * @return void
     */
    public function testCallbackRegex()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('callbacksWithHeader'),
            'callbacks' => [
                '/./' => ['strtolower', 'trim'],
            ],
        ]);
        $this->assertEquals(
            [
                '1' => ['Id' => 1, 'Name' => 'foo'],
                '2' => ['Id' => 2, 'Name' => 'bar'],
                '3' => ['Id' => 3, 'Name' => 'baz'],
            ],
            $csv->toArray('Id')
        );
    }

    /**
     * Test that callbacks with a header row work.
     *
     * @return void
     */
    public function testCallbacksWithHeader()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('callbacksWithHeader'),
            'callbacks' => [
                'Name' => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $this->assertEquals(
            [
                '1' => ['Id' => 1, 'Name' => 'oof'],
                '2' => ['Id' => 2, 'Name' => 'rab'],
                '3' => ['Id' => 3, 'Name' => 'zab'],
            ],
            $csv->toArray('Id')
        );
    }

    /**
     * Test that callbacks without a header row work.
     *
     * @return void
     */
    public function testCallbacksWithoutHeader()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('callbacksWithoutHeader'),
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
    public function testGetBadColumnViaInt()
    {
        $this->expectException(\RuntimeException::class);
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
            'header' => false,
        ]);
        iterator_to_array($csv->getColumn(100));
    }

    /**
     * Test that getting a bad column from a header file fails.
     *
     * @return void
     */
    public function testGetBadColumnViaString()
    {
        $this->expectException(\RuntimeException::class);
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
        ]);
        iterator_to_array($csv->getColumn('Foo'));
    }

    /**
     * Test that we can get the columns from a header file.
     *
     * @return void
     */
    public function testGetColumns()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
        ]);
        $this->assertEquals(['Id', 'Name', 'Number'], $csv->getColumns());
    }

    /**
     * Test that we get empty columns from a headerless file.
     *
     * @return void
     */
    public function testGetColumnsHeaderless()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
            'header' => false,
        ]);
        $this->assertEmpty($csv->getColumns());
    }

    /**
     * Test that we can get a column from a headerless file by integer.
     *
     * @return void
     */
    public function testGetColumnViaInt()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
            'header' => false,
        ]);
        $this->assertEquals([1, 6], iterator_to_array($csv->getColumn(0)));
        $this->assertEquals([5, 10], iterator_to_array($csv->getColumn(4)));
    }

    /**
     * Test that we can get a column from a header file via column name.
     *
     * @return void
     */
    public function testGetColumnViaString()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
        ]);
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn('Id')));
        $this->assertSame(['Foo', 'Bar', 'Baz'], iterator_to_array($csv->getColumn('Name')));
    }

    /**
     * Test that we can override the columns of a file with headers.
     *
     * @return void
     */
    public function testHeaderColumnOverride()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
            'columns' => ['one', 'two', 'three'],
        ]);
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn('one')));
        $this->assertSame(['Foo', 'Bar', 'Baz'], iterator_to_array($csv->getColumn('two')));
    }

    /**
     * Test that we properly read a header file.
     *
     * @return void
     */
    public function testHeaderFile()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
        ]);
        $this->assertEquals(
            [
                '1' => ['Id' => 1, 'Name' => 'Foo', 'Number' => 37],
                '2' => ['Id' => 2, 'Name' => 'Bar', 'Number' => 142],
                '3' => ['Id' => 3, 'Name' => 'Baz', 'Number' => 71],
            ],
            $csv->toArray('Id')
        );
    }

    /**
     * Test that we can specify the colunms of a file without headers.
     *
     * @return void
     */
    public function testHeaderlessColumnExplicit()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
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
    public function testHeaderlessFile()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
            'header' => false,
        ]);
        $this->assertEquals([[1, 2, 3, 4, 5], [6, 7, 8, 9, 10]], $csv->toArray());
    }

    /**
     * Test that the line count works with a header file.
     *
     * @return void
     */
    public function testLineCountHeader()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
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
    public function testLineCountHeaderless()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
            'header' => false,
        ]);
        foreach ($csv as $index => $row) {
            $this->assertSame($index + 1, $csv->getLineNumber());
        }
        // Make sure count gets reset for subsequent calls w/ same object.
        foreach ($csv as $index => $row) {
            $this->assertSame($index + 1, $csv->getLineNumber());
        }
    }

    /**
     * Test that the line number is zero before we start.
     *
     * @return void
     */
    public function testLineNumberZeroBeforeStart()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
        ]);
        $this->assertSame(0, $csv->getLineNumber());
    }

    /**
     * Test that we detect a column mismatch without also getting an E_WARNING.
     *
     * @return void
     */
    public function testMismatchFile()
    {
        set_error_handler(
            function () {
                $this->assertTrue(false);
            },
            E_WARNING
        );
        $this->expectException(\RuntimeException::class);
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('mismatch'),
        ]);
        $csv->toArray();
        restore_error_handler();
    }

    /**
     * Test that we can't reference files that don't exist.
     *
     * @return void
     */
    public function testMissingFile()
    {
        $this->expectException(\RuntimeException::class);
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('noneSuch'),
        ]);
        $csv->toArray();
    }

    /**
     * Test that using non-unique columns fails.
     *
     * @return void
     */
    public function testNonUniqueColumn()
    {
        $this->expectException(\RuntimeException::class);
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
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
    public function testNotUniqueHeaderFile()
    {
        $this->expectException(\RuntimeException::class);
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('notUniqueHeader'),
        ]);
        $csv->toArray();
    }

    /**
     * Test that we can read via STDIN.
     *
     * This is kinda wonky. It looks like "composer script" sets STDIN to non-blocking, which is required for this
     * test to pass. However, TravisCI doesn't invoke the tests via the composer wrapper, so this just hangs waiting
     * for input. To avoid that, we'll explicitly set STDIN to non-blocking here.
     *
     * @return void
     *
     * @throws \RuntimeException On error.
     */
    public function testStdin()
    {
        $fh = fopen('php://stdin', 'r');
        if ($fh === false) {
            throw new \RuntimeException('STDIN open failed');
        }
        stream_set_blocking($fh, false);
        fclose($fh);
        $csv = new \Ork\Csv\Reader(['file' => 'php://stdin']);
        $this->assertEmpty($csv->toArray());
    }

}
