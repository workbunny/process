<?php
declare(strict_types=1);

namespace Tests;

use WorkBunny\Process\Runtime;

/**
 * @runTestsInSeparateProcesses
 */
class ChildTest extends BaseTestCase
{
    /**
     * 测试获取子Runtime ID【在公共上下文中Exit】
     * @covers \WorkBunny\Process\Runtime::getId
     * @covers \WorkBunny\Process\Runtime::exitChildren
     * @covers \WorkBunny\Process\Runtime::wait
     * @return void
     */
    public function testGetChildRuntimeIDPublicContextExit()
    {
        $file = __FUNCTION__;

        $this->runtime()->child(function (Runtime $runtime) use($file){
            $this->write($file, (string)$runtime->getId());
        });
        $this->runtime()->wait(null, null, true);
        $this->assertEqualsAndRmCache('1', $this->read($file), $file);

        $this->runtime(true)->child(function (Runtime $runtime) use($file){
            $this->write($file, (string)$runtime->getId());
        });
        $this->runtime()->exitChildren();
        $this->runtime()->wait();
        $this->assertEqualsAndRmCache('1', $this->read($file), $file);
    }

    /**
     * 测试获取子Runtime ID【在私有上下文中Exit】
     * @covers \WorkBunny\Process\Runtime::exit
     * @covers \WorkBunny\Process\Runtime::wait
     * @return void
     */
    public function testGetChildRuntimeIDPrivateContextExit()
    {
        $file = __FUNCTION__;

        $this->runtime()->child(function (Runtime $runtime) use($file){
            $this->write($file, 'child' . PHP_EOL);
            $runtime->exit();
        });

        $this->runtime()->wait();

        $this->assertEqualsAndRmCache(
            'child',
            $this->read($file),
            $file
        );
    }

    /**
     * 测试子Runtime使用入参Runtime进行fork不生效
     * @covers \WorkBunny\Process\Runtime::child
     * @covers \WorkBunny\Process\Runtime::wait
     * @return void
     */
    public function testChildUseSameRuntimeForkNotEffectByCallback()
    {
        $file = __FUNCTION__;

        $this->runtime()->child(function(Runtime $runtime) use ($file){
            $this->write($file, 'child' . PHP_EOL);

            $runtime->child(function () use ($file){
                $this->write($file, 'child-child' . PHP_EOL);
            });
        });

        $this->runtime()->wait(null, null, true);

        $this->assertEqualsAndRmCache('child', $this->read($file), $file);
    }

    /**
     * 测试使用isChild获取子Runtime进行Fork
     * @covers \WorkBunny\Process\Runtime::isChild
     * @covers \WorkBunny\Process\Runtime::wait
     * @return void
     */
    public function testChildUseSameRuntimeForkNotEffectByIsChild()
    {
        $file = __FUNCTION__;

        $this->runtime()->child();

        if($this->runtime()->isChild()){
            $this->write($file, 'child' . PHP_EOL);

            $this->runtime()->child(function () use ($file){
                $this->write($file, 'child-child' . PHP_EOL);
            });
        }

        $this->runtime()->wait(null, null, true);

        $this->assertEqualsAndRmCache('child', $this->read($file), $file);
    }

    /**
     * 测试child
     * @covers \WorkBunny\Process\Runtime::child
     * @covers \WorkBunny\Process\Runtime::exit
     * @return void
     */
    public function testChild()
    {
        $file = __FUNCTION__;

        $this->runtime()->child(function (Runtime $runtime) use($file){
            $this->write($file, 'test' . PHP_EOL);
        });

        $this->runtime()->wait(null, null, true);

        $this->assertEqualsAndRmCache('test',$this->read($file), $file);
    }

    /**
     * 每个child只输出一次
     * @return void
     */
    public function testMultiChildOutputOnceEach()
    {
        $file = __FUNCTION__;

        $this->runtime()->child(function (Runtime $runtime) use($file){
            $this->write($file, $runtime->getId() . PHP_EOL);
        });

        $this->runtime()->child(function (Runtime $runtime) use($file){
            $this->write($file, $runtime->getId() . PHP_EOL);
        });

        $this->runtime()->wait(null, null, true);

        $this->assertEqualsAndRmCache(
            ['1', '2'],
            explode(PHP_EOL, $this->read($file)),
            $file
        );
    }

    /**
     * 通过子上下文内创建新runtime，父子孙三代嵌套获取runtime id
     * @covers \WorkBunny\Process\Runtime::run
     * @return void
     */
    public function testParentChildGrandChildByChildCreateNewRuntime()
    {
        $file = __FUNCTION__;

        $this->runtime()->child(function(Runtime $runtime) use ($file){
            // 获取子runtime id
            $id = $runtime->getId();
            // 创建孙
            $r = new Runtime();
            $r->child(function (Runtime $r) use ($id, $file){
                // 孙写入孙runtime id
                $this->write($file, $id . $r->getId() . PHP_EOL);
            });
            // 子监听孙
            $r->wait(null, null, true);
            // 子写入子runtime id
            $this->write($file, $runtime->getId() . PHP_EOL);
        });
        // 父监听子
        $this->runtime()->wait(null,null,true);
        // 父写入父runtime id
        $this->write($file, $this->runtime()->getId() . PHP_EOL);

        $this->assertContainsHasAndRmCache(
            ['11', '1', '0'],
            explode(PHP_EOL, $this->read($file)),
            $file
        );
    }
}