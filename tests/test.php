<?php
declare(strict_types=1);

require_once './../vendor/autoload.php';
use WorkBunny\Process\Runtime;

$p = new Runtime();

//$p->fork(function(Process $process){
//    dump('child-' . $process->getId());
//});
//$p->fork(function(Process $process){
//    dump('child-' . $process->getId());
//});
//$p->fork(function(Process $process){
//    dump('child-' . $process->getId());
//});

//$p->run(function (Process $process){
//    dump('parent-' . $process->getId());
//},function(Process $process){
//    dump('child-' . $process->getId());
//},4);


$p->run(function(Runtime $process){
    $process->fork(function(Runtime $p){
        sleep(3);
        dump('child-' . $p->getId());
    });
//    $p = new Process();
//    $p->fork(function(Process $p) use($process){
//        sleep(3);
//        dump('child-' . $process->getId() . '-' . $p->getId());
//    });
//    $p->wait();
},function (Runtime $process){
    dump('parent-' . $process->getId());
    $process->fork(function(Runtime $p){
        sleep(3);
        dump('child-' . $p->getId());
    });

},2);

$p->wait();

$p->parent(function(){
    dump('parent');
});