
<p align='center'><img width='260px' src='https://chaz6chez.cn/images/workbunny-logo.png' alt='workbunny'></p>

**<p align='center'>workbunny/process</p>**

**<p align='center'>🐇 A lightweight multi-process helper base on PHP. 🐇</p>**

# 简介

这是一个基于ext-pcntl和ext-posix拓展的PHP多进程助手，用于更方便的调用使用。

# 示例

- 在当前上下文中创建一个子进程，如下：

```php
// 使用对象方式
$p = new \WorkBunny\Process\Runtime();
$p->fork(function(){
    var_dump('child');
});
```

- 父进程执行

```php
$p = new \WorkBunny\Process\Runtime();

$p->parent(function(){
    var_dump('parent'); # 仅输出一次
});

```

- 快速创建运行多个进程

```php
$p = new \WorkBunny\Process\Runtime();

$p->run(function(){
    var_dump('child');
},function(){
    var_dump('parent');
}, 4); # 1 + 4 进程

```

- 父进程等待子进程

```php
$p = new \WorkBunny\Process\Runtime();

$p->wait(function(\WorkBunny\Process\Runtime $parent, int $status){
    # 子进程正常退出则会调用该方法，被调用次数是正常退出的子进程数量
},function(\WorkBunny\Process\Runtime $parent, $status){
    # 子进程异常退出则会调用该方法，被调用次数是异常的子进程数量
});
```

# 说明

- 在 **fork** 行为发生后，Runtime对象会产生两个分支
  - id=0 的父Runtime
  - id=N 的子Runtime

- **fork()** 和 **run()** 之后的代码域会被父子进程同时执行，但相互隔离：

```php
$p = new \WorkBunny\Process\Runtime();
$p->fork(function(\WorkBunny\Process\Runtime $runtime){
    var_dump($runtime->getId()); # id !== 0
});

var_dump('parent'); # 打印两次

```

```php
$p = new \WorkBunny\Process\Runtime();
$p->run(function (\WorkBunny\Process\Runtime $runtime){
    
},function(\WorkBunny\Process\Runtime $runtime){

}, 4);

var_dump('parent'); # 打印5次
```

- 所有注册的回调函数都可以接收当前的Runtime分支对象：

```php
$p = new \WorkBunny\Process\Runtime();
$p->fork(function(\WorkBunny\Process\Runtime $runtime){
    var_dump($runtime->getId()); # id !== 0
});
$p->parent(function (\WorkBunny\Process\Runtime $runtime){
    var_dump($runtime->getId()); # id === 0
});

$p->run(function (\WorkBunny\Process\Runtime $runtime){
    var_dump($runtime->getId()); # id !== 0
},function(\WorkBunny\Process\Runtime $runtime){
    var_dump($runtime->getId()); # id === 0
}, 4);
```

- Runtime中的所有方法仅对父Runtime生效:

```php
$p = new \WorkBunny\Process\Runtime();
$p->fork(function(\WorkBunny\Process\Runtime $runtime){
    var_dump('child'); # 生效
    
    $runtime->fork(function(){
        var_dump('child-child'); # 不生效
    });
});

$p->parent(function (\WorkBunny\Process\Runtime $runtime){
    var_dump('parent'); # 生效

    $runtime->fork(function(){
        var_dump('parent-child'); # 生效
    });
});

# run 方法同理
```

- 如需在子Runtime中进行 **fork** 操作，请创建新的Runtime；**不建议过多调用，因为进程的开销远比线程大**

```php
$p = new \WorkBunny\Process\Runtime();
$p->fork(function(\WorkBunny\Process\Runtime $runtime){
    var_dump($runtime->getId()); # id !== 0
    var_dump('old-child');
    
    $newP = new \WorkBunny\Process\Runtime();
    $newP->fork(function(\WorkBunny\Process\Runtime $newP){
        var_dump($newP->getId()); # id === 0
        var_dump('new-parent');
    });
});
# run 方法同理
```

# 方法

|      方法名      |   作用范围   | 是否产生分叉 |               描述               |
|:-------------:|:--------:|:------:|:------------------------------:|
|    fork()     | 父Runtime |   √    |          分叉一个子Runtime          |
|     run()     | 父Runtime |   √    |         快速分支N个子Runtime         |
|    wait()     | 父Runtime |   ×    |         监听所有子Runtime状态         |
|   parent()    | 父Runtime |   ×    |        为父Runtime增加回调响应         |
|   isChild()   |    所有    |   ×    |         判断是否是子Runtime          |
|    getId()    |    所有    |   ×    |         获取当前Runtime序号          |
|  getPidMap()  | 父Runtime |   ×    |        获取所有子RuntimePID         |
|   number()    | 父Runtime |   ×    | 获取子Runtime数量 or 产生子Runtime自增序号 |
| setPriority() |    所有    |   ×    |        为当前Runtime设置优先级         |
| getPriority() |    所有    |   ×    |         获取当前Runtime优先级         |