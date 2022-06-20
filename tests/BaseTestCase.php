<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use WorkBunny\Process\Runtime;

class BaseTestCase extends TestCase
{
    protected ?Runtime $_runtime = null;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->_runtime = new Runtime();
        parent::setUp();
    }

    /**
     * @return Runtime|null
     */
    public function runtime(): ?Runtime
    {
        return $this->_runtime;
    }

    public function write(string $file, string $content)
    {
        file_put_contents(__DIR__ . '/cache/' . $file, $content, FILE_APPEND|LOCK_EX);
    }

    public function read(string $file)
    {
        return file_exists($file = __DIR__ . '/cache/' . $file) ? file_get_contents($file) : '';
    }

    /**
     * @return void
     */
    public function removeCache(string $file)
    {
        if(file_exists($file = __DIR__ . '/cache/' . $file)){
            @unlink($file);
        }
    }

    public function assertEqualsAndRmCache($expected, $actual, string $file = ''): void
    {
        $this->removeCache($file);
        $this->assertEquals($expected, $actual);
    }

    public function assertContainsHasAndRmCache(array $expected, array $actual, string $file = ''){
        $this->removeCache($file);

        $this->assertCount(count($expected), $actual);
        foreach ($expected as $value){
            $this->assertContains($value, $actual);
        }
    }

}