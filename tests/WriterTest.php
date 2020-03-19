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
 * Test the Writer class.
 */
class WriterTest extends \PHPUnit\Framework\TestCase
{

    /**
     * VFS handle.
     *
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $vfs;

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
        error_reporting(E_ALL);
        $this->vfs = vfsStream::setup();
    }

    /**
     * Test that we detect a callback on a missing column
     *
     * @return void
     */
    public function testCallbackOnMissingColumn()
    {
        $this->expectException(\RuntimeException::class);
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'callbacks' => [
                'Foo' => ['trim'],
            ],
        ]);
        $csv->write(['Id' => 1, 'Name' => 'Foo']);
        unset($csv);
    }

    /**
     * Test that regex callbacks work.
     *
     * @return void
     */
    public function testCallbackRegex()
    {
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'callbacks' => [
                '/e/' => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $csv->write(['Id' => 1000, 'Name' => ' FOO ']);
        $csv->write(['Id' => 2000, 'Name' => ' Bar ']);
        $csv->write(['Id' => 3000, 'Name' => 'baz']);
        unset($csv);
        $this->assertSame('ac2235527f6b65b87b4ba26f2af0480a', md5_file($this->getTempFile()));
    }

    /**
     * Test that callbacks work on files with headers.
     *
     * @return void
     */
    public function testCallbacksWithHeader()
    {
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'callbacks' => [
                'Id' => 'number_format',
                'Name' => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $csv->write(['Id' => 1000, 'Name' => ' FOO ']);
        $csv->write(['Id' => 2000, 'Name' => ' Bar ']);
        $csv->write(['Id' => 3000, 'Name' => 'baz']);
        unset($csv);
        $this->assertSame('58c1fd79167879b4b096cf6b9390bed4', md5_file($this->getTempFile()));
    }

    /**
     * Test that callbacks work on files without headers.
     *
     * @return void
     */
    public function testCallbacksWithoutHeader()
    {
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'header' => false,
            'callbacks' => [
                0 => 'strtolower',
                1 => ['strtolower', 'trim', [$this, 'reverse']],
            ],
        ]);
        $csv->write([' FOO ', ' FOO ']);
        $csv->write([' Bar ', ' Bar ']);
        $csv->write(['baz', 'baz']);
        unset($csv);
        $this->assertSame('828b52089cff436139a64b135ef6ddc0', md5_file($this->getTempFile()));
    }

    /**
     * Test that we detect failure to create the output file.
     *
     * @return void
     */
    public function testCreateFail()
    {
        $this->expectException(\RuntimeException::class);
        error_reporting(E_ALL & ~E_WARNING);
        touch($this->getTempFile());
        chmod($this->getTempFile(), 0000);
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'header' => false,
        ]);
        $csv->write([1, 2, 3, 4, 5]);
    }

    /**
     * Test that we detect out of order columns.
     *
     * @return void
     */
    public function testOutOfOrderColumns()
    {
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'header' => true,
        ]);
        $csv->write([
            'Id' => 1,
            'Name' => 'foo',
        ]);
        $csv->write([
            'Name' => 'bar',
            'Id' => 2,
        ]);
        $csv->write([
            'Id' => 3,
            'Name' => 'baz',
        ]);
        unset($csv);
        $this->assertSame('2fc774926f1155e3f70065241680043e', md5_file($this->getTempFile()));
    }

    /**
     * Test that the write() return value is correct.
     *
     * @return void
     */
    public function testReturnValue()
    {
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'header' => false,
        ]);
        $this->assertSame(20, $csv->write([0, 1, 2, 3, 4, 5, 6, 7, 8, 9]));
    }

    /**
     * Test that we detect failure to write to the output file.
     *
     * @return void
     */
    public function testWriteFail()
    {
        $this->expectException(\RuntimeException::class);
        vfsStream::setQuota(1);
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'header' => false,
        ]);
        $csv->write([1, 2, 3, 4, 5]);
        vfsStream::setQuota(0);
    }

    /**
     * Test that we create the header correctly.
     *
     * @return void
     */
    public function testWriteHeader()
    {
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'header' => true,
        ]);
        $csv->write([
            'Id' => 1,
            'Name' => 'foo',
        ]);
        $csv->write([
            'Id' => 2,
            'Name' => 'bar',
        ]);
        $csv->write([
            'Id' => 3,
            'Name' => 'baz',
        ]);
        unset($csv);
        $this->assertSame('2fc774926f1155e3f70065241680043e', md5_file($this->getTempFile()));
    }

    /**
     * Test that we properly override column names.
     *
     * @return void
     */
    public function testWriteHeaderOverride()
    {
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'header' => true,
            'columns' => ['one', 'two', 'three'],
        ]);
        $csv->write([
            'one' => 1,
            'two' => 2,
            'three' => 3,
        ]);
        $csv->write([
            'two' => 2,
            'three' => 3,
        ]);
        $csv->write([
            'one' => 1,
            'three' => 3,
        ]);
        $csv->write([
            'one' => 1,
            'two' => 2,
        ]);
        $csv->write(['one' => 1]);
        $csv->write(['two' => 2]);
        $csv->write(['three' => 3]);
        unset($csv);
        $this->assertEquals('46b70113332cec6212541e14dc8f417c', md5_file($this->getTempFile()));
    }

    /**
     * Test that we don't create a header row when specified.
     *
     * @return void
     */
    public function testWriteNoHeader()
    {
        $csv = new \Ork\Csv\Writer([
            'file' => $this->getTempFile(),
            'header' => false,
        ]);
        $csv->write([1, 2, 3, 4, 5]);
        $csv->write([6, 7, 8, 9, 10]);
        unset($csv);
        $this->assertSame('66f1d63c002cde9257adc36a7ed58c31', md5_file($this->getTempFile()));
    }

}
