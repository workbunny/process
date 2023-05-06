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
        $this->removeAllCaches();
        $this->_runtime = new Runtime();
        parent::setUp();
    }

    /**
     * @param bool $reset
     * @return Runtime|null
     */
    public function runtime(bool $reset = false): ?Runtime
    {
        if($reset){
            $this->_runtime = new Runtime();
        }
        return $this->_runtime;
    }

    /**
     * @param string $file
     * @param string $content
     * @return void
     */
    public function write(string $file, string $content): void
    {
        file_put_contents(__DIR__ . "/$file.cache", $content, FILE_APPEND|LOCK_EX);
    }

    /**
     * @param string $file
     * @return string
     */
    public function read(string $file): string
    {
        if(file_exists($file = __DIR__ . "/$file.cache")){
            return trim(file_get_contents($file));
        }
        throw new \RuntimeException('Cache Not Found : ' . $file);
    }

    /**
     * @return void
     */
    public function removeAllCaches()
    {
        array_map('unlink', glob( __DIR__ . '/*.cache'));
    }

    /**
     * @return void
     */
    public function removeCache(string $file)
    {
        if(file_exists($file = __DIR__ . "/$file.cache")){
            @unlink($file);
        }
    }

    /**
     * @param $expected
     * @param $actual
     * @param string $file
     * @return void
     */
    public function assertEqualsAndRmCache($expected, $actual, string $file = ''): void
    {
        $this->removeCache($file);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @param array $expected
     * @param array $actual
     * @param string $file
     * @return void
     */
    public function assertContainsHasAndRmCache(array $expected, array $actual, string $file = ''){
        $this->removeCache($file);
        $this->assertCount(count($expected), $actual);
        foreach ($expected as $value){
            $this->assertContains($value, $actual);
        }
    }

}