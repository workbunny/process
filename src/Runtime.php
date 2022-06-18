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

    /** @var array 子进程PID字典 在子进程内始终为[] */
    protected array $_pidMap = [];

    /**
     * @return int
     */
    public function number(): int
    {
        if(!$this->isChild()){
            return $this->_number ++;
        }
        return 0;
    }
    /**
     * 获取process序号
     * @return int
     */
    public function getId(): int
    {
        return $this->_id;
    }

    /**
     * 设置process序号
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->_id = $id;
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
     * 获取子进程pid字典
     * @return array
     */
    public function getPidMap(): array
    {
        return $this->_pidMap;
    }

    /**
     * 是否是子进程
     * @return bool
     */
    public function isChild(): bool
    {
        return $this->getId() !== 0;
    }

    /**
     * 快速运行
     * @param Closure $child
     * @param Closure|null $parent
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
     * @param Closure|null $success = function($this, $status){}
     * @param Closure|null $error = function($this, $status){}
     * @return void
     */
    public function wait(?Closure $success = null, ?Closure $error = null)
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
     * 主进程执行
     * @param Closure|null $handler
     * @return void
     */
    public function parent(?Closure $handler = null)
    {
        if(!$this->isChild() and $handler){
            $handler($this);
        }
    }

    /**
     * 创建一个子进程
     * @param Closure $handler
     * @return void
     */
    public function fork(Closure $handler)
    {
        if($id = $this->number()){
            $pid = pcntl_fork(); # 此代码往后就是父子进程公用代码块
            try {
                switch (true){
                    case $pid > 0:
                        $this->setId(0);
                        $this->_pidMap[$id] = $pid;
                        break;
                    case $pid === 0:
                        $this->setId($id);
                        $this->_pidMap[$id] = posix_getpid();
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
}