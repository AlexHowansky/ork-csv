<?php

/**
 * Ork CSV
 *
 * @package   OrkTest\Csv
 * @copyright 2015-2017 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-csv/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-csv
 */

namespace OrkTest\Csv;

use org\bovigo\vfs\vfsStream;

class ReaderTest extends \PHPUnit\Framework\TestCase
{

    protected $vfs;

    protected function getFile($name)
    {
        $file = $this->vfs->url() . '/' . $name . '.csv';
        $content = [
            'bad' => random_bytes(1024),
            'callbacks' => "Id,Name\n1,Foo\n2, Bar\n3, BAZ \n",
            'header' => "Id , Name, Number\n1,Foo,37\n2,Bar,142\n3,Baz,71\n",
            'headerless' => "1,2,3,4,5\n6,7,8,9,10\n",
            'mismatch' => "Id,Name,Number\n1,Foo,37\n2,Bar\n3,Baz,71\n",
            'notUniqueHeader' => "Id,Id,Number\n1,Foo,37\n2,Bar,142\n3,Baz,71\n",
            'perms' => "a,b,c,d,e\n",
        ];
        if (array_key_exists($name, $content)) {
            file_put_contents($file, $content[$name]);
            if ($name === 'perms') {
                chmod($file, 0000);
            }
        }
        return $file;
    }

    public function reverse($value)
    {
        return strrev($value);
    }

    public function setUp()
    {
        $this->vfs = vfsStream::setup();
    }

    public function testHeaderlessFile()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
            'header' => false,
        ]);
        $this->assertEquals([[1,2,3,4,5], [6,7,8,9,10]], $csv->toArray());
    }

    public function testHeaderFile()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
        ]);
        $this->assertEquals([
            '1' => ['Id' => 1, 'Name' => 'Foo', 'Number' => 37],
            '2' => ['Id' => 2, 'Name' => 'Bar', 'Number' => 142],
            '3' => ['Id' => 3, 'Name' => 'Baz', 'Number' => 71],
        ], $csv->toArray('Id'));
    }

    public function testLineCountHeader()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
        ]);
        foreach ($csv as $index => $row) {
            $this->assertEquals($index + 2, $csv->getLineNumber());
        }
        // make sure count gets reset for subsequent calls w/ same object
        foreach ($csv as $index => $row) {
            $this->assertEquals($index + 2, $csv->getLineNumber());
        }
    }

    public function testLineCountHeaderless()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
            'header' => false,
        ]);
        foreach ($csv as $index => $row) {
            $this->assertEquals($index + 1, $csv->getLineNumber());
        }
        // make sure count gets reset for subsequent calls w/ same object
        foreach ($csv as $index => $row) {
            $this->assertEquals($index + 1, $csv->getLineNumber());
        }
    }

    public function testCallbacks()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('callbacks'),
            'callbacks' => [
                'Name' => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $this->assertEquals([
            '1' => ['Id' => 1, 'Name' => 'oof'],
            '2' => ['Id' => 2, 'Name' => 'rab'],
            '3' => ['Id' => 3, 'Name' => 'zab'],
        ], $csv->toArray('Id'));
    }

    public function testCallbackRegex()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('callbacks'),
            'callbacks' => [
                '/./' => ['strtolower', 'trim'],
            ],
        ]);
        $this->assertEquals([
            '1' => ['Id' => 1, 'Name' => 'foo'],
            '2' => ['Id' => 2, 'Name' => 'bar'],
            '3' => ['Id' => 3, 'Name' => 'baz'],
        ], $csv->toArray('Id'));
    }

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
     * @expectedException \RuntimeException
     */
    public function testGetBadColumnViaInt()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('headerless'),
            'header' => false,
        ]);
        iterator_to_array($csv->getColumn(100));
    }

    public function testGetColumnViaString()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
        ]);
        $this->assertEquals([1, 2, 3], iterator_to_array($csv->getColumn('Id')));
        $this->assertSame(['Foo', 'Bar', 'Baz'], iterator_to_array($csv->getColumn('Name')));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetBadColumnViaString()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('header'),
        ]);
        iterator_to_array($csv->getColumn('Foo'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testMissingFile()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('noneSuch'),
        ]);
        $csv->toArray();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNotUniqueHeaderFile()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('notUniqueHeader'),
        ]);
        $csv->toArray();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testBadFile()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('bad'),
        ]);
        $csv->toArray();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testBadPerms()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('perms'),
        ]);
        $csv->toArray();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testMismatchFile()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('mismatch'),
        ]);
        $csv->toArray();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCallbackOnMissingColumn()
    {
        $csv = new \Ork\Csv\Reader([
            'file' => $this->getFile('callbacks'),
            'callbacks' => [
                'DoesNotExist' => ['strtolower'],
            ],
        ]);
        $csv->toArray('Id');
    }

}
