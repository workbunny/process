<?php
declare(strict_types=1);

namespace WorkBunny\Process;

use Closure;
use InvalidArgumentException;
use RuntimeException;

class Runtime
{
    /** @var int 序号 */
    protected int $_id = 0;

    /** @var int 数目 */
    protected int $_number = 1;

    /** @var array 子Runtime PID字典 在子Runtime内始终为[] */
    protected array $_pidMap = [];

    /**
     * 返回编号/数量
     * @param bool $increment 自增
     * @return int 子Runtime会固定返回0
     */
    public function number(bool $increment = true): int
    {
        if(!$this->isChild()){
            return $increment ? $this->_number ++ : $this->_number;
        }
        return 0;
    }

    /**
     * 获取当前Runtime序号
     * @return int
     */
    public function getId(): int
    {
        return $this->_id;
    }

    /**
     * @param array $pidMap
     * @return void
     */
    public function setPidMap(array $pidMap): void
    {
        $this->_pidMap = $pidMap;
    }

    /**
     * 获取所有子Runtime的PID
     * @return array
     */
    public function getPidMap(): array
    {
        return $this->_pidMap;
    }

    /**
     * 是否是子Runtime
     * @return bool
     */
    public function isChild(): bool
    {
        return $this->getId() !== 0;
    }

    /**
     * 快速执行
     *
     * 该方法下父子Runtime没有优先级区分，除非自行设置，否则始终为0
     * @param Closure $child = function(Runtime){}
     * @param Closure|null $parent = function(Runtime){}
     * @param int $forkCount
     * @return void
     */
    public function run(Closure $child, ?Closure $parent = null, int $forkCount = 1): void
    {
        if($forkCount < 1){
            throw new InvalidArgumentException('Fork count cannot be less than 1. ');
        }

        for($i = 1; $i <= $forkCount; $i ++){
            $this->fork($child);
        }

        $this->parent($parent);
    }

    /**
     * 父Runtime 监听
     * @param Closure|null $success = function(Runtime, status){}
     * @param Closure|null $error = function(Runtime, status){}
     * @return void
     */
    public function wait(?Closure $success = null, ?Closure $error = null): void
    {
        if(!$this->isChild()){
            foreach ($this->_pidMap as $pid){
                $pid = pcntl_waitpid($pid, $status, WUNTRACED);
                if($pid > 0){
                    if ($status !== 0) {
                        if ($error){
                            $error($this, $status);
                        }
                    }else{
                        if($success){
                            $success($this, $status);
                        }
                    }
                }
            }
        }
    }

    /**
     * 父Runtime执行
     * @param Closure|null $handler = function(Runtime){}
     * @return void
     */
    public function parent(?Closure $handler = null): void
    {
        if(!$this->isChild() and $handler){
            $handler($this);
        }
    }

    /**
     * 创建一个子Runtime
     * @param Closure $handler = function(Runtime){}
     * @param int $priority 默认 父子Runtime同为0，但父Runtime始终为0
     * @return void
     */
    public function fork(Closure $handler, int $priority = 0): void
    {
        if($id = $this->number()){
            $pid = pcntl_fork(); # 此代码往后就是父子进程公用代码块
            try {
                switch (true){
                    case $pid > 0:
                        $this->_id = 0;
                        $this->setPriority(0, 0);
                        $this->_pidMap[$id] = $pid;
                        break;
                    case $pid === 0:
                        $this->_id = $id;
                        $this->setPriority($id, $priority);
                        $this->_pidMap = [];
                        $handler($this);
                        break;
                    default:
                        throw new RuntimeException('Fork process fail. ');
                }
            }catch (\Throwable $throwable){
                echo $throwable->getMessage();
                exit(250);
            }
        }
    }

    /**
     * 为Runtime设置优先级
     * @param int $id Runtime序号 0为主进程
     * @param int $priority 优先级 -20至20 越小优先级越高
     * @return void
     */
    public function setPriority(int $id, int $priority): void
    {
        if($this->getId() === $id){
            pcntl_setpriority($priority);
        }
    }

    /**
     * 获取Runtime优先级
     * @param int $id Runtime序号 0为主进程
     * @return int|null -20至20 越小优先级越高
     */
    public function getPriority(int $id): ?int
    {
        if($this->getId() === $id){
            return ($priority = pcntl_getpriority()) === false ? null : $priority;
        }
        return null;
    }
}