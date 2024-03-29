<?php declare(strict_types=1);
/**
 * This file is part of workbunny.
 *
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<chaz6chez1993@outlook.com>
 * @copyright chaz6chez<chaz6chez1993@outlook.com>
 * @link      https://github.com/workbunny/process
 * @license   https://github.com/workbunny/process/blob/main/LICENSE
 */
namespace WorkBunny\Process;

use Closure;
use InvalidArgumentException;
use RuntimeException;

class Runtime
{
    /** @var int 序号 */
    protected int $_id = 0;

    /** @var int PID */
    protected int $_pid = 0;

    /** @var int 数目 */
    protected int $_number = 1;

    /** @var array 子Runtime PID字典 在子Runtime内始终为[] */
    protected array $_pidMap = [];

    /**
     * @see Runtime::__construct
     * @var array 配置
     */
    protected array $_config = [];

    /**
     * @param array $config = [
     *  'pre_gc'   => true, // 是否在fork前执行php garbage cycle，默认为false
     *
     *  'priority' => [0,1,1,2,3], // 设置默认的Runtime优先级，如果创建Runtime数量大于该配置数量，默认为0
     * ]
     */
    public function __construct(array $config = [])
    {
        $this->_config = $config;
    }

    /**
     * @see Runtime::__construct
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->_config = $config;
    }

    /**
     * @see Runtime::__construct
     * @return array
     */
    public function getConfig(): array
    {
        return $this->_config;
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
     * 获取当前Runtime PID
     * @return int
     */
    public function getPid(): int
    {
        return $this->_pid;
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
     * @param int $status
     * @param string|null $msg
     * @return void
     */
    public function exit(int $status = 0, ?string $msg = null){
        if($msg){
            $this->echo($msg . PHP_EOL);
        }
        exit($status);
    }

    /**
     * @return void
     */
    public function exitChildren(): void
    {
        if($this->isChild()){
            $this->exit();
        }
    }

    /**
     * @param string $msg
     * @return void
     */
    public function echo(string $msg){
        echo $msg;
    }

    /**
     * 快速执行
     *
     * 该方法下父子Runtime没有优先级区分，除非自行设置，否则始终为0
     * @param Closure $childContext = function(Runtime){}
     * @param Closure|null $parentContext = function(Runtime){}
     * @param int $forkCount
     * @return void
     */
    public function run(Closure $childContext, ?Closure $parentContext = null, int $forkCount = 1): void
    {
        if($forkCount < 1){
            throw new InvalidArgumentException('Fork count cannot be less than 1. ');
        }

        for($i = 1; $i <= $forkCount; $i ++){
            $this->child($childContext);
        }

        $this->parent($parentContext);
    }

    /**
     * 父Runtime 阻塞监听
     * @param Closure|null $success = function(id, pid, status){}
     * @param Closure|null $error = function(id, pid, status){}
     * @param bool $exitChild 是否结束子runtime
     * @return void
     */
    public function wait(?Closure $success = null, ?Closure $error = null, bool $exitChild = false): void
    {
        if(!$this->isChild()){
            foreach ($this->_pidMap as $id => $pid){
                $pid = pcntl_waitpid($pid, $status, WUNTRACED);
                if($pid > 0){
                    if ($status !== 0) {
                        if ($error){
                            $error($id, $pid, $status);
                        }
                    }else{
                        if($success){
                            $success($id, $pid, $status);
                        }
                    }
                }
            }
        }
        if($exitChild){
            $this->exitChildren();
        }
    }

    /**
     * 父Runtime 非阻塞监听
     * @param Closure|null $success = function(id, pid, status){}
     * @param Closure|null $error = function(id, pid, status){}
     * @param bool $exitChild 是否结束子runtime
     * @return void
     */
    public function listen(?Closure $success = null, ?Closure $error = null, bool $exitChild = false): void
    {
        if(!$this->isChild()){
            foreach ($this->_pidMap as $id => $pid){
                $pid = pcntl_waitpid($pid, $status, WNOHANG);
                if($pid > 0){
                    if ($status !== 0) {
                        if ($error){
                            $error($id, $pid, $status);
                        }
                    }else{
                        if($success){
                            $success($id, $pid, $status);
                        }
                    }
                }
            }
        }
        if($exitChild){
            $this->exitChildren();
        }
    }

    /**
     * 父Runtime执行
     * @param Closure|null $context = function(Runtime){}
     * @return void
     */
    public function parent(?Closure $context = null): void
    {
        if(!$this->isChild() and $context){
            $context($this);
        }
    }

    /**
     * 创建一个子Runtime
     * @param Closure|null $context = function(Runtime){}
     * @param int $priority 默认 父子Runtime同为0，但父Runtime始终为0
     * @param int|null $id 该id的子Runtime如果存在则会创建新Runtime替换旧Runtime
     * @return int
     */
    public function child(?Closure $context = null, int $priority = 0, ?int $id = null): int
    {
        // 创建子Runtime
        if($id = ($id !== null) ? $id : $this->number()){
            // gc
            if(isset($this->getConfig()['pre_gc']) and boolval($this->getConfig()['pre_gc'])){
                gc_collect_cycles();
            }
            // fork
            $pid = pcntl_fork(); # 此代码往后就是父子进程公用代码块
            try {
                switch (true){
                    // 父Runtime
                    case $pid > 0:
                        $this->_id = 0;
                        $this->_pid = posix_getpid();
                        $this->setPriority(
                            0,
                            isset($this->getConfig()['priority'][0])
                                ? (int)($this->getConfig()['priority'][0])
                                : 0
                        );
                        // 杀死旧Runtime
                        if($oldPid = ($this->_pidMap[$id] ?? null)){
                            posix_kill($oldPid, SIGKILL);
                        }
                        $this->_pidMap[$id] = $pid;
                        break;
                    // 子Runtime
                    case $pid === 0:
                        $this->_id = $id;
                        $this->_pid = posix_getpid();
                        $this->setPriority(
                            $id,
                            isset($this->getConfig()['priority'][$id])
                                ? (int)($this->getConfig()['priority'][$id])
                                : $priority
                        );
                        $this->_pidMap = [];
                        if($context){
                            $context($this);
                        }
                        break;
                    // 异常
                    default:
                        throw new RuntimeException('Fork process fail. ');
                }
            }catch (\Throwable $throwable){
                $this->exit(250, $throwable->getMessage());
            }
        }
        return $id;
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
            @pcntl_setpriority($priority);
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