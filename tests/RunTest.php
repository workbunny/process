<?php
declare(strict_types=1);

namespace Tests;

use WorkBunny\Process\Runtime;

/**
 * @runTestsInSeparateProcesses
 */
class RunTest extends BaseTestCase
{
    /**
     * 测试run
     * @covers \WorkBunny\Process\Runtime::run
     * @return void
     */
    public function testRun()
    {
        $file = __FUNCTION__;

        $this->runtime()->run(function(Runtime $runtime) use ($file){
            $this->write($file, $runtime->getId() . PHP_EOL);
            exit;
        },function (Runtime $runtime) use ($file){
            $this->write($file, $runtime->getId() . PHP_EOL);
        }, 3);

        $this->runtime()->wait();

        $this->assertContainsHasAndRmCache(
            ['0', '1', '2', '3'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试run
     * @covers \WorkBunny\Process\Runtime::run
     * @return void
     */
    public function testRunUseGC()
    {
        $file = __FUNCTION__;

        $this->runtime()->setConfig([
            'pre_gc' => true
        ]);

        $this->runtime()->run(function(Runtime $runtime) use ($file){
            $this->write($file, $runtime->getId() . PHP_EOL);
            exit;
        },function (Runtime $runtime) use ($file){
            $this->write($file, $runtime->getId() . PHP_EOL);
        }, 3);

        $this->runtime()->wait();

        $this->assertContainsHasAndRmCache(
            ['0', '1', '2', '3'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试获取优先级
     * @covers \WorkBunny\Process\Runtime::run
     * @return void
     */
    public function testRunGetPriority()
    {
        $file = __FUNCTION__;

        $this->runtime()->run(function(Runtime $runtime) use ($file){
            $this->write($file, $runtime->getPriority($runtime->getId()) . PHP_EOL);
        },function (Runtime $runtime) use ($file){
            $this->write($file, $runtime->getPriority($runtime->getId()) . PHP_EOL);
        }, 2);

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            ['0', '0', '0'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试run
     * @covers \WorkBunny\Process\Runtime::run
     * @return void
     */
    public function testRunToFork()
    {
        $file = __FUNCTION__;

        $this->runtime()->run(function(Runtime $runtime) use ($file){
            $this->write($file, 'child' . $runtime->getId() . PHP_EOL);

            $runtime->fork(function(Runtime $r) use ($file, $runtime){
                $this->write($file, 'child-child' . $runtime->getId() . $r->getId() . PHP_EOL);
            });
            exit;

        },function (Runtime $runtime) use ($file){
            $this->write($file, 'parent' . ($id = $runtime->getId()) . PHP_EOL);

            $runtime->fork(function(Runtime $r) use ($file, $id){
                $this->write($file, 'parent-child' . $id . $r->getId() . PHP_EOL);
                exit;
            });

        }, 3);

        $this->runtime()->wait();

        $this->assertContainsHasAndRmCache(
            ['parent0','parent-child04', 'child1', 'child2', 'child3'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试run的Runtime嵌套
     * @covers \WorkBunny\Process\Runtime::run
     * @return void
     */
    public function testRunToRuntimeNesting()
    {
        $file = __FUNCTION__;

        $this->runtime()->run(function(Runtime $runtime) use ($file){
            $this->write($file, 'child' . ($id = $runtime->getId()) . PHP_EOL);

            $r = new Runtime();
            $r->run(function(Runtime $runtime) use ($file, $id){
                $this->write($file, 'child-child' . $id . $runtime->getId() . PHP_EOL);
                exit;
            },function(Runtime $runtime) use ($file, $id){
                $this->write($file, 'child-parent' . $id . $runtime->getId() . PHP_EOL);
            },1);
            exit;

        },function (Runtime $runtime) use ($file){
            $this->write($file, 'parent' . ($id = $runtime->getId()) . PHP_EOL);

            $r = new Runtime();
            $r->run(function(Runtime $runtime) use ($file, $id){
                $this->write($file, 'parent-child' . $id . $runtime->getId() . PHP_EOL);
                exit;
            },function(Runtime $runtime) use ($file, $id){
                $this->write($file, 'parent-parent' . $id . $runtime->getId() . PHP_EOL);
            },1);

        }, 1);

        $this->runtime()->wait();

        $this->assertContainsHasAndRmCache(
            ['parent0','parent-parent00', 'parent-child01', 'child1', 'child-child11', 'child-parent10'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }
}