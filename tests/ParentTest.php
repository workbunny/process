<?php
declare(strict_types=1);

namespace Tests;

use WorkBunny\Process\Runtime;

/**
 * @runTestsInSeparateProcesses
 */
class ParentTest extends BaseTestCase
{
    /**
     * 测试exitChildren方法
     * @covers \WorkBunny\Process\Runtime::exitChildren
     * @covers \WorkBunny\Process\Runtime::child
     * @return void
     */
    public function testExitChildren()
    {
        $file = __FUNCTION__;

        // 对照
        $this->runtime()->child();
        $this->write($file, $this->runtime()->getId() . PHP_EOL);
        $this->runtime()->wait(null, null, true);
        $this->assertContainsHasAndRmCache(['0', '1'], explode(PHP_EOL, $this->read($file)), $file);

        // 测试
        $this->runtime(true)->child();
        $this->runtime()->exitChildren();
        $this->write($file, (string)$this->runtime()->getId());
        $this->assertEqualsAndRmCache('0', $this->read($file), $file);


    }

    /**
     * 测试exit方法
     * @covers \WorkBunny\Process\Runtime::exit
     * @return void
     */
    public function testExit()
    {
        $file = __FUNCTION__;
        // 对照组
        $this->runtime()->child();
        $this->write($file, $this->runtime()->getId() . PHP_EOL);
        $this->runtime()->wait(null, null, true);
        $this->assertContainsHasAndRmCache(['0', '1'], explode(PHP_EOL, $this->read($file)), $file);

        // 测试组-子上下文退出
        $this->runtime(true)->child(function(){
            $this->runtime()->exit();
        });
        $this->runtime()->wait(null, null, true);
        $this->write($file, (string)$this->runtime()->getId());
        $this->assertEqualsAndRmCache('0', $this->read($file), $file);

        // 测试组-子上下文退出后写入无效
        $this->runtime(true)->child(function() use ($file){
            $this->runtime()->exit();
            $this->write($file, $this->runtime()->getId() . PHP_EOL);
        });
        $this->runtime()->wait(null, null, true);
        $this->write($file, (string)$this->runtime()->getId());
        $this->assertEqualsAndRmCache('0', $this->read($file), $file);
    }

    /**
     * 测试使用isChild
     * @covers \WorkBunny\Process\Runtime::isChild
     * @covers \WorkBunny\Process\Runtime::getId
     * @return void
     */
    public function testIsChild()
    {
        $file = __FUNCTION__;
        // 对照组
        $this->runtime()->child();
        $this->write($file, $this->runtime()->getId() . PHP_EOL);
        $this->runtime()->wait(null, null, true);
        $this->assertContainsHasAndRmCache(['0', '1'], explode(PHP_EOL, $this->read($file)), $file);

        // 测试组
        $this->runtime(true)->child();
        if($this->runtime()->isChild()){
            $this->write($file, $this->runtime()->getId() . PHP_EOL);
        }
        $this->runtime()->wait(null, null, true);
        $this->assertEqualsAndRmCache('1', $this->read($file), $file);
    }

    /**
     * 测试获取父Runtime ID
     * @covers \WorkBunny\Process\Runtime::getId
     * @return void
     */
    public function testGetParentRuntimeID()
    {
        $file = __FUNCTION__;

        $this->runtime()->child();
        $this->runtime()->parent(function(Runtime $runtime) use ($file){
            $this->write($file, (string)$runtime->getId());
        });

        $this->runtime()->wait(null, null, true);
        $this->assertEqualsAndRmCache('0', $this->read($file), $file);
    }

    /**
     * 同父异母的子
     * @covers \WorkBunny\Process\Runtime::parent
     * @return void
     */
    public function testHalfBrother()
    {
        $file = __FUNCTION__;

        $this->runtime()->child(function (){
            sleep(2);
        });
        $this->runtime()->parent(function () use ($file){
            $r = new Runtime();
            $r->child(function () use ($file){
                $this->write($file, 'child-2' . PHP_EOL);
            });
            // 子监听孙
            $r->wait(null, null, true);
        });

        if($this->runtime()->isChild()){
            $this->write($file,'child-1' . PHP_EOL);
        }
        $this->runtime()->wait(null, null, true);
        $this->write($file,'parent' . PHP_EOL);

        $this->assertEqualsAndRmCache(['child-2', 'child-1', 'parent'], explode(PHP_EOL, $this->read($file)), $file);
    }
}