<?php
declare(strict_types=1);

namespace Tests;

use WorkBunny\Process\Runtime;

/**
 * @runTestsInSeparateProcesses
 */
class ForkTest extends BaseTestCase
{
    /**
     * 测试获取父Runtime ID
     * @covers \WorkBunny\Process\Runtime::getId
     * @return void
     */
    public function testForkGetParentRuntimeID()
    {
        $file = __FUNCTION__;

        $this->runtime()->parent(function(Runtime $runtime) use ($file){
            $this->write($file, (string)$runtime->getId());
        });

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            '0',
            $this->read($file),
            $file
        );
    }

    /**
     * 测试获取子Runtime ID
     * @return void
     */
    public function testForkGetChildRuntimeID()
    {
        $file = __FUNCTION__;

        $this->runtime()->fork(function (Runtime $runtime) use($file){
            $this->write($file, (string)$runtime->getId());
            exit;
        });

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            '1',
            $this->read($file),
            $file
        );
    }

    /**
     * 测试父Runtime callback fork生效
     * @covers \WorkBunny\Process\Runtime::parent
     * @return void
     */
    public function testParentCallbackToFork()
    {
        $file = __FUNCTION__;

        $this->runtime()->fork(function(){});

        $this->runtime()->parent(function (Runtime $runtime) use ($file){
            $this->write($file, 'parent' . PHP_EOL);

            $runtime->fork(function () use ($file){
                $this->write($file, 'parent-child' . PHP_EOL);
            });
        });
        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            ['parent', 'parent-child'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试子Runtime callback fork
     * @covers \WorkBunny\Process\Runtime::parent
     * @return void
     */
    public function testChildCallbackToFork()
    {
        $file = __FUNCTION__;

        $this->runtime()->fork(function(Runtime $runtime) use ($file){
            $this->write($file, 'child' . PHP_EOL);

            $runtime->fork(function () use ($file){
                $this->write($file, 'child-child' . PHP_EOL);
                exit;
            });
            exit;
        });

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            'child',
            trim($this->read($file)),
            $file
        );
    }

    /**
     * 测试使用isChild获取父Runtime进行Fork
     * @covers \WorkBunny\Process\Runtime::isChild
     * @return void
     */
    public function testUseIsChildGetParentToFork()
    {
        $file = __FUNCTION__;

        $this->runtime()->fork(function(){});

        if(!$this->runtime()->isChild()){
            $this->write($file, $this->runtime()->getId() . PHP_EOL);

            $this->runtime()->fork(function () use ($file){
                $this->write($file, 'parent-child' . PHP_EOL);
            });
        }

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            ['0', 'parent-child'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试使用isChild获取子Runtime进行Fork
     * @covers \WorkBunny\Process\Runtime::isChild
     * @return void
     */
    public function testUseIsChildGetChildToFork()
    {
        $file = __FUNCTION__;

        $this->runtime()->fork(function(){});

        $this->runtime()->fork(function(){});

        if($this->runtime()->isChild()){
            $this->write($file, $this->runtime()->getId() . PHP_EOL);

            $this->runtime()->fork(function () use ($file){
                $this->write($file, 'child-child' . PHP_EOL);
            });
        }

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            ['1', '2'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试使用RuntimeID获取父Runtime进行Fork
     * @covers \WorkBunny\Process\Runtime::parent
     * @return void
     */
    public function testUseRuntimeIdGetParentToFork()
    {
        $file = __FUNCTION__;

        $this->runtime()->fork(function(){});

        if($this->runtime()->getId() === 0){
            $this->write($file, $this->runtime()->getId() . PHP_EOL);

            $this->runtime()->fork(function () use ($file){
                $this->write($file, 'parent-child' . PHP_EOL);
            });
        }

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            ['0', 'parent-child'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试多个Fork后获取子Runtime ID
     * @return void
     */
    public function testMultiForkGetChildRuntimeID()
    {
        $file = __FUNCTION__;

        $this->runtime()->fork(function (Runtime $runtime) use($file){
            $this->write($file, $runtime->getId() . PHP_EOL);
            exit;
        });

        $this->runtime()->fork(function (Runtime $runtime) use($file){
            $this->write($file, $runtime->getId() . PHP_EOL);
            exit;
        });

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            ['1', '2'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试fork会导致多重输出
     * @return void
     */
    public function testForkLeadToMultiOutputs()
    {
        $file = __FUNCTION__;

        $this->runtime()->fork(function (){});

        $this->write($file, 'test' . PHP_EOL);

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            ['test', 'test'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }

    /**
     * 测试fork的Runtime嵌套
     * @covers \WorkBunny\Process\Runtime::run
     * @return void
     */
    public function testForkToRuntimeNesting()
    {
        $file = __FUNCTION__;

        $this->runtime()->fork(function(Runtime $runtime) use ($file)
        {
            $this->write($file, 'child' . ($id = $runtime->getId()) . PHP_EOL);

            $r = new Runtime();
            $r->run(function(Runtime $runtime) use ($file, $id){
                $this->write($file, 'child-child' . $id . $runtime->getId() . PHP_EOL);
                exit;
            },function(Runtime $runtime) use ($file, $id){
                $this->write($file, 'child-parent' . $id . $runtime->getId() . PHP_EOL);
            },1);
            exit;

        });

        $this->runtime()->wait();

        $this->assertContainsHasAndRmCache(
            ['child1', 'child-child11', 'child-parent10'],
            explode(PHP_EOL, trim($this->read($file))),
            $file
        );
    }
}