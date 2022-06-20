
<p align='center'><img width='260px' src='https://chaz6chez.cn/images/workbunny-logo.png' alt='workbunny'></p>

**<p align='center'>workbunny/process</p>**

**<p align='center'>🐇 A lightweight multi-process helper base on PHP. 🐇</p>**

[![Latest Stable Version](http://poser.pugx.org/workbunny/process/v)](https://packagist.org/packages/workbunny/process) [![Total Downloads](http://poser.pugx.org/workbunny/process/downloads)](https://packagist.org/packages/workbunny/process) [![Latest Unstable Version](http://poser.pugx.org/workbunny/process/v/unstable)](https://packagist.org/packages/workbunny/process) [![License](http://poser.pugx.org/workbunny/process/license)](https://packagist.org/packages/workbunny/process) [![PHP Version Require](http://poser.pugx.org/workbunny/process/require/php)](https://packagist.org/packages/workbunny/process)

# 简介

这是一个基于ext-pcntl和ext-posix拓展的PHP多进程助手，用于更方便的调用使用。

# 快速开始

- 创建一个子Runtime

```php
// 使用对象方式
$p = new \WorkBunny\Process\Runtime();
$p->fork(function(){
    var_dump('child');
});
```

- 父Runtime执行

```php
$p = new \WorkBunny\Process\Runtime();

$p->parent(function(){
    var_dump('parent'); # 仅输出一次
});

```

- 快速创建运行多个子Runtime

```php
$p = new \WorkBunny\Process\Runtime();

$p->run(function(){
    var_dump('child');
},function(){
    var_dump('parent');
}, 4); # 1 + 4 进程

```

- 监听子Runtime

```php
$p = new \WorkBunny\Process\Runtime();

$p->wait(function(\WorkBunny\Process\Runtime $parent, int $status){
    # 子进程正常退出则会调用该方法，被调用次数是正常退出的子进程数量
},function(\WorkBunny\Process\Runtime $parent, $status){
    # 子进程异常退出则会调用该方法，被调用次数是异常的子进程数量
});
```

# 方法

**注：作用范围为父Runtime的方法仅在父Runtime内有有效响应**

|      方法名      |    作用范围    | 是否产生分叉 |                   描述                    |
|:-------------:|:----------:|:------:|:---------------------------------------:|
|    fork()     |  父Runtime  |   √    |              分叉一个子Runtime               |
|     run()     |  父Runtime  |   √    |             快速分叉N个子Runtime              |
|    wait()     |  父Runtime  |   ×    |             监听所有子Runtime状态              |
|   parent()    |  父Runtime  |   ×    |             为父Runtime增加回调响应             |
|   isChild()   |     所有     |   ×    |              判断是否是子Runtime              |
|    getId()    |     所有     |   ×    |              获取当前Runtime序号              |
|   getPid()    |     所有     |   ×    |             获取当前RuntimePID              |
|  getPidMap()  |  父Runtime  |   ×    |             获取所有子RuntimePID             |
|   number()    |  父Runtime  |   ×    |      获取Runtime数量 or 产生子Runtime自增序号      |
|  setConfig()  | 所有 且 分叉发生前 |   ×    |                设置config                 |
|  getConfig()  |     所有     |   ×    |                获取config                 |
|  getPidMap()  |  父Runtime  |   ×    |             获取所有子RuntimePID             |
| setPriority() |     所有     |   ×    | 为当前Runtime设置优先级 **需要当前执行用户为super user** |
| getPriority() |     所有     |   ×    |             获取当前Runtime优先级              |

# 说明

## 1. 初始化

- Runtime对象初始化支持配置
  - pre_gc ：接受bool值，控制Runtime在fork行为发生前是否执行PHP GC；**注：Runtime默认不进行gc**
  - priority：接受索引数组，为所有Runtime设置优先级，索引下标对应Runtime序号；
如实际产生的Runtime数量大于该索引数组数量，则默认为0；
**注：fork()的priority参数会改变该默认值**

```php
$p = new \WorkBunny\Process\Runtime([
    'pre_gc' => true,
    'priority' => [
        0,  // 主Runtime优先级为0
        -1, // id=1的子Runtime优先级为-1
        -2, // id=2的子Runtime优先级为-2
        -3  // id=3的子Runtime优先级为-3
    ]
]);
```

## 2. fork行为

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

## 3. 指定执行

- 指定某个id的Runtime执行

```php
$p = new \WorkBunny\Process\Runtime();
$p->run(function (){},function(){}, 4);

if($p->getId() === 3){
    var_dump('im No. 3'); # 仅id为3的Runtime会生效
}

# fork同理
```

- 指定所有子Runtime执行

```php
$p = new \WorkBunny\Process\Runtime();
$p->run(function (){},function(){}, 4);

if($p->isChild()){
    var_dump('im child'); # 所有子Runtime都生效
}

# fork同理
```

- 指定父Runtime执行

```php
$p = new \WorkBunny\Process\Runtime();
$p->run(function (){},function(){}, 4);

if(!$p->isChild()){
    var_dump('im parent'); # 父Runtime都生效
}

# 或以注册回调函数来执行
$p->parent(function(\WorkBunny\Process\Runtime $parent){
    var_dump('im parent');
});

# fork同理
```

## 4. 回调函数相关

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

- **注：注册的父Runtime回调函数内传入的是父Runtime对象，注册的子Runtime回调函数内传入的参数是子Runtime对象**

```php
$p = new \WorkBunny\Process\Runtime();
$p->fork(function(\WorkBunny\Process\Runtime $runtime){
    var_dump('child'); # 生效
    
    $runtime->fork(function(){
        var_dump('child-child'); # 由于fork作用范围为父Runtime，所以不生效
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

## 5. 其他

- 获取当前Runtime数量

**注：该方法仅父Runtime生效**

```php
$p = new \WorkBunny\Process\Runtime();
var_dump($p->number(false)); # 仅父Runtime会输出
```

- 获取当前RuntimePID

**注：该方法可结合指定执行区别获取**

```php
$p = new \WorkBunny\Process\Runtime();
var_dump($p->getPid()); # 所有Runtime会输出
```

- 阻塞监听

**注：该方法仅父Runtime生效**

**注：该方法在会阻塞至所有子Runtime退出**

```php
$p = new \WorkBunny\Process\Runtime();
$p->wait(function(\WorkBunny\Process\Runtime $runtime, $status){
    # 子Runtime正常退出时
}, function(\WorkBunny\Process\Runtime $runtime, $status){
    # 子Runtime异常退出时
});
```
