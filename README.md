
<p align='center'><img width='260px' src='https://chaz6chez.cn/images/workbunny-logo.png' alt='workbunny'></p>

**<p align='center'>workbunny/process</p>**

**<p align='center'>🐇 A lightweight multi-process helper base on PHP. 🐇</p>**

<div align="center">
    <a href="https://github.com/workbunny/process/actions">
        <img src="https://github.com/workbunny/process/actions/workflows/CI.yml/badge.svg" alt="Build Status">
    </a>
    <a href="https://github.com/workbunny/process/blob/main/composer.json">
        <img alt="PHP Version Require" src="http://poser.pugx.org/workbunny/process/require/php">
    </a>
    <a href="https://github.com/workbunny/process/blob/main/LICENSE">
        <img alt="GitHub license" src="http://poser.pugx.org/workbunny/process/license">
    </a>
    
</div>

# 简介

这是一个基于ext-pcntl和ext-posix拓展的PHP多进程助手，用于更方便的调用使用。

# 快速开始

```
composer require workbunny/process
```

- 创建一个子Runtime

```php
// 使用对象方式
$p = new \WorkBunny\Process\Runtime();
$p->child(function(){
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

|      方法名      |     作用范围      | 是否产生分叉 |                   描述                    |
|:-------------:|:-------------:|:------:|:---------------------------------------:|
|    child()    | parentContext |   √    |       分叉一个子Runtime / 替换一个子Runtime       |
|     run()     | parentContext |   √    |             快速分叉N个子Runtime              |
|    wait()     | parentContext |   ×    |             监听所有子Runtime状态              |
|   parent()    | parentContext |   ×    |             为父Runtime增加回调响应             |
|   isChild()   |    public     |   ×    |              判断是否是子Runtime              |
|    getId()    |    public     |   ×    |              获取当前Runtime序号              |
|   getPid()    |    public     |   ×    |             获取当前RuntimePID              |
|  getPidMap()  | parentContext |   ×    |             获取所有子RuntimePID             |
|   number()    | parentContext |   ×    |      获取Runtime数量 or 产生子Runtime自增序号      |
|  setConfig()  |    public     |   ×    |                设置config                 |
|  getConfig()  |    public     |   ×    |                获取config                 |
|  getPidMap()  | parentContext |   ×    |             获取所有子RuntimePID             |
| setPriority() |    public     |   ×    | 为当前Runtime设置优先级 **需要当前执行用户为super user** |
| getPriority() |    public     |   ×    |             获取当前Runtime优先级              |
|    exit()     |    public     |   ×    |                  进程退出                   |

# 说明

## 1. 初始化

- Runtime对象初始化支持配置
  - pre_gc ：接受bool值，控制Runtime在fork行为发生前是否执行PHP GC；**注：Runtime默认不进行gc**
  - priority：接受索引数组，为所有Runtime设置优先级，索引下标对应Runtime序号；
如实际产生的Runtime数量大于该索引数组数量，则默认为0；

**注：child()的priority参数会改变该默认值**

**注：priority需要当前用户为super user**

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

- **child()** 和 **run()** 之后的代码域会被父子进程同时执行，但相互隔离：

```php
$p = new \WorkBunny\Process\Runtime();
$p->child(function(\WorkBunny\Process\Runtime $runtime){
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

- **child()** 函数可以进行替换子Runtime行为

```php
$p = new \WorkBunny\Process\Runtime();

// 创建一个子Runtime
// 假设父RuntimeID === 0，子RuntimeID === 1
// 假设父RuntimePID === 99，子RuntimePID === 100
$id = $p->child(function(\WorkBunny\Process\Runtime $runtime){
    $runtime->getId(); // 假设 id === 1
    $runtime->getPid(); // 假设 pid === 100
});

if($p->isChild()){
    $id === 0; // $id 在子Runtime的上下文中始终为0
    posix_getpid() === 100;
}else{
    $id === 1;// $id 在当前父Runtime的上下文中为1
    posix_getpid() === 99;
}

// 对id === 1的子Runtime进行替换
// 该用法会杀死原id下的子Runtime并新建Runtime替换它
// 该方法并不会改变子Runtime的id，仅改变id对应的pid
$newId = $p->child(function(\WorkBunny\Process\Runtime $runtime){
    $runtime->getId(); # id === 1
}, 0, $id);

if($p->isChild()){
    $id === $newId === 0;
    posix_getpid() !== 100; // 子Runtime PID发生变化，不再是100
    // 原PID === 100的子Runtime被kill
}else{
    $id === $newId === 1; // $id 没有发生变化
    posix_getpid() === 99;
}
```

- 如需在子Runtime中进行 **fork** 操作，请创建新的Runtime；**不建议过多调用，因为进程的开销远比线程大**

```php
$p = new \WorkBunny\Process\Runtime();
$id = $p->child(function(\WorkBunny\Process\Runtime $runtime){
    var_dump($runtime->getId()); # id !== 0
    var_dump('old-child');
    
    $newP = new \WorkBunny\Process\Runtime();
    $newP->child(function(\WorkBunny\Process\Runtime $newP){
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
$p->child(function(\WorkBunny\Process\Runtime $runtime){
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
$p->child(function(\WorkBunny\Process\Runtime $runtime){
    var_dump('child'); # 生效
    
    $runtime->child(function(){
        var_dump('child-child'); # 由于fork作用范围为父Runtime，所以不生效
    });
});

$p->parent(function (\WorkBunny\Process\Runtime $runtime){
    var_dump('parent'); # 生效

    $runtime->child(function(){
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

// $id RuntimeID
// $pid 进程PID
// $status 进程退出状态
$p->wait(function($id, $pid, $status){
    # 子Runtime正常退出时
}, function($id, $pid, $status){
    # 子Runtime异常退出时
});
```

- 非阻塞监听

**注：该方法仅父Runtime生效**

**注：该方法应配合event-loop的timer或者future进行监听**

```php
$p = new \WorkBunny\Process\Runtime();

// $id RuntimeID
// $pid 进程PID
// $status 进程退出状态
$p->listen(function($id, $pid, $status){
    # 子Runtime正常退出时
}, function($id, $pid, $status){
    # 子Runtime异常退出时
});
```

- 进程退出

**注：该方法可结合指定执行区别获取**

```php
$p = new \WorkBunny\Process\Runtime();

$p->exit(0, 'success');
```
