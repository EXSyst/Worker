[![Build Status](https://travis-ci.org/EXSyst/Worker.svg?branch=master)](https://travis-ci.org/EXSyst/Worker)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/EXSyst/Worker/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/EXSyst/Worker/?branch=master)

# EXSyst Worker Component
Worker subprocess management

## Operating system compatibility
This library uses Unix-specific utilities, such as ```lsof``` (for finding the process ID of a shared worker) and ```getconf``` (for querying the processor count, if you use the automatic worker pool sizing). If you use these features, please ensure that these programs are installed and executable by PHP.

This library is **unsupported on Windows**.

## Dedicated workers
A dedicated worker is the simplest worker type. It has only one master, and its communication channel is closed (which should stop it as soon as it has finished processing its messages) when its master's reference to it goes out of scope. It allows to run code in isolation from the master.

Here is a minimal example of a dedicated worker. ```use```s and ```require```s are skipped for brevity.

### ```Master.php```
```php
<?php
$wf = new WorkerFactory();
$w = $wf->createWorker(MyWorkerImpl::class);
for ($i = 0; $i < 10; ++$i) {
  $w->sendMessage($i);
  var_dump($w->receiveMessage());
}
```

### ```MyWorkerImpl.php```, as a raw worker
```php
<?php
class MyWorkerImpl implements RawWorkerImplementationInterface
{
  public function run(ChannelInterface $channel)
  {
    for (; ; ) {
      try {
        $message = $channel->receiveMessage();
      } catch (\UnderflowException $e) {
        // The master closed the connection (its Worker object went out of scope)
        break;
      }
      $channel->sendMessage($message . " squared is " . ($message * $message));
    }
  }
}
```

### ```MyWorkerImpl.php```, as an evented worker
Evented workers require [```react/event-loop```](https://github.com/reactphp/event-loop). For simple use cases, they can be more complex than raw workers, but for complex use cases, their power will lead to a generally simpler worker implementation.
```php
<?php
class MyWorkerImpl implements EventedWorkerImplementationInterface
{
  public function setLoop(LoopInterface $loop) { }
  public function initialize() { }
  public function onConnect(ChannelInterface $channel, $peerName) { }
  public function onDisconnect(ChannelInterface $channel) { }
  public function terminate() { }

  public function onMessage($message, ChannelInterface $channel, $peerName)
  {
    $channel->sendMessage($message . " squared is " . ($message * $message));
  }
}
```

## Dedicated worker pools
A worker pool allows to ease management of multiple, parallel, dedicated workers.

Here is a minimal example of a dedicated worker pool. As in the previous example, ```use```s and ```require```s are skipped for brevity. ```MyWorkerImpl.php``` is one of the previously seen files.

### ```MasterWithPool.php```
```php
<?php
$wf = new WorkerFactory();
$wp = $wf->createWorkerPool(MyWorkerImpl::class, 4);
$i = 0;
$busy = 0;
foreach ($wp as $w) {
  if ($i >= 10) {
    break;
  }
  $w->sendMessage($i);
  ++$busy;
  ++$i;
}
while ($i < 10) {
  // WorkerPool->receiveMessage takes an output parameter, which it fills
  // with the worker which actually received the returned message
  var_dump($wp->receiveMessage($w));
  $w->sendMessage($i);
  ++$i;
}
while ($busy > 0) {
  var_dump($wp->receiveMessage($w));
  --$busy;
}
```

## Shared workers
A shared worker is an evented worker (see above) which can have multiple masters. Instead of communicating with a single master using its standard I/O streams, it listens on a socket, to which any master can connect and disconnect at any time.

A shared worker can't be stopped just by disconnecting it from all of its masters. The only way to gracefully stop it is to send it an appropriate message containing its "admin cookie", which was configured in its bootstrap profile (see below). It can also be terminated by standard POSIX signals, but, in this case, it may leave some garbage behind.

Here is a minimal example of a shared worker. As in the previous examples, ```use```s and ```require```s are skipped for brevity.

### ```MasterOfShared.php```
```php
<?php
$wbsp = new WorkerBootstrapProfile();
$wbsp->setAdminCookie('This value is not so secret, change it in your app !');
$wf = new WorkerFactory($wbsp);
if ($argc > 1 and $argv[1] == '--stop') {
  $wf->stopSharedWorker('unix://' . __DIR__ . '/Worker.sock');
  exit;
}
$w = $wf->connectToSharedWorker('unix://' . __DIR__ . '/Worker.sock', MySharedWorkerImpl::class);
$w->sendMessage(($argc > 1) ? $argv[1] : 'world');
var_dump($w->receiveMessage());
```

### ```MySharedWorkerImpl.php```
```php
<?php
class MySharedWorkerImpl implements SharedWorkerImplementationInterface
{
  private $i;

  public function __construct()
  {
    $this->i = 0;
  }

  public function setLoop(LoopInterface $loop) { }
  public function initialize() { }
  public function onConnect(ChannelInterface $channel, $peerName) { }
  public function onDisconnect(ChannelInterface $channel) { }
  public function onStop() { }
  public function terminate() { }

  public function onQuery($privileged)
  {
    return 'Current counter value : ' . $this->i;
  }

  public function onMessage($message, ChannelInterface $channel, $peerName)
  {
    $channel->sendMessage("Hello " . $message . " ! You're my " . self::ordinal(++$this->i) . " client.");
  }

  private static function ordinal($n)
  {
    $units = $n % 10;
    $tens = (($n % 100) - $units) / 10;
    if ($tens == 1) {
      return $n . 'th';
    } elseif ($units == 1) {
      return $n . 'st';
    } elseif ($units == 2) {
      return $n . 'nd';
    } elseif ($units == 3) {
      return $n . 'rd';
    } else {
      return $n . 'th';
    }
  }
}
```

## Querying a shared worker's status
You can send a query message to your worker using ```$worker->query()``` or ```$workerFactory->querySharedWorker($socketAddress)```. If you have configured an "admin cookie" (see below), your worker can decide to return full information to clients which have it, and only limited information to others.

Your worker's ```onQuery``` method can return null if your worker doesn't have any interesting status to report. Otherwise, it must return either a ```WorkerStatus``` (which encapsulates a human-readable string describing its status and/or counters with a name, a numerical value, and an optional unit, minimum and maximum), or just a human-readable string.

This model is designed to ease interfacing with a monitoring system, such as Nagios. If you install [```symfony/console```](https://github.com/symfony/console), you can even use the standalone ```check_shared_worker``` command provided in this package, as a Nagios plugin.

## Gracefully stopping a shared worker
The library provides two ways of gracefully stopping your shared workers :
- You can call ```SharedWorker::stopCurrent()``` from inside your worker, and then manually clean up all the other resources ;
- You can send an appropriate stop message to your worker using ```$worker->stop()``` or ```$workerFactory->stopSharedWorker($socketAddress)``` if you have configured an "admin cookie" (see below).

If you use an "admin cookie", and if your shared worker owns resources (such as, for example, sub-worker pools, or [Ratchet](https://github.com/ratchetphp/Ratchet) server sockets) and has registered them against the event loop, it must, in its ```onStop``` method, either unregister them, or stop the loop : the loop will not stop automatically as long as any resources remain registered against it, which will make your shared worker unable to stop if you forget to unregister resources.

## Remote shared workers
Shared workers support listening on Unix-domain sockets, as well as Internet-domain sockets. They can therefore be exposed to a network.

A master can connect to a network-exposed shared worker on another machine, as well as stop it if it knows its "admin cookie", but it can't remotely start the shared worker.

**Warning : for security reasons, please do not use a ```SerializedChannelFactory``` (which is the default) on a network-exposed shared worker (see [```unserialize```](http://php.net/unserialize#refsect1-function.unserialize-notes) for more info). Instead, consider using a channel factory which uses a safe format, such as a ```JsonChannelFactory```.**

## Disabling shared workers
You may want to disable shared workers, for example as part of the deployment of a newer release of your application.

To this end, the library provides a kill switch, in the form of a JSON file which contains :
- A flag, which indicates whether shared workers are globally disabled for the current bootstrap profile (and may be used as part of a deployment) ;
- A list of socket addresses which indicate which specific shared workers, if any, are disabled.

You can manipulate the kill switch from the worker factory, for example :
```php
<?php
$wbsp = new WorkerBootstrapProfile();
$wbsp->setKillSwitchPath(__DIR__ . '/WorkerKillSwitch.json');
$wf = new WorkerFactory($wbsp);
// Save state :
$global = $wf->areSharedWorkersDisabledGlobally();
$addresses = $wf->getDisabledSharedWorkers();
// Disable them globally :
$wf->disableSharedWorkersGlobally();
// Disable the one from our previous example :
$wf->disableSharedWorker('unix://' . __DIR__ . '/Worker.sock');
// "Revert" our changes :
$wf->reEnableSharedWorker('unix://' . __DIR__ . '/Worker.sock');
$wf->reEnableSharedWorkersGlobally();
// Re-enable every single worker :
$wf->reEnableAllSharedWorkers();
// Really revert our changes :
if ($global) {
  $wf->disableSharedWorkersGlobally();
}
// If it fits between foreach and as, disable/reEnableSharedWorker will accept it and will process all of its elements in a single transaction.
$wf->disableSharedWorker($addresses);
```

Please note that disabling shared workers will not automatically stop them (and disabling them globally can't, as the library doesn't keep track of a list of all running shared workers). If you want to disable running workers, you must stop them manually, **after** disabling them (to avoid a race condition, where they could be restarted after you stop them, but before you disable them).

## The bootstrap profile
This object contains all the parameters needed to initialize a worker. The library is designed to try and provide default values for mandatory parameters :
- The ```php``` or ```hhvm``` executable's path and arguments (by default, will be auto-detected using [```symfony/process```](https://github.com/symfony/Process)) ;
- The "stage 1" parts, which are to be executed before ```require```ing any scripts (by default, none) ;
- The scripts to ```require``` (by default, the component will try to find ```composer```'s autoloader, unless told not to) ;
- The "stage 2" parts, which are to be executed after ```require```ing the scripts, but before creating the worker implementation (by default, none) ;
- The name of the variable which will hold the worker implementation (by default, ```workerImpl```) ;
- The arguments to pass to the worker implementation's constructor, when it is actually created using its constructor (by default, none) ;
- The "stage 3" parts, which are to be executed after creating the worker implementation, and which can refer to it using the specified variable name (by default, none) ;
- The channel factory, which can create a channel, and must be serializable (by default, the ```SerializedChannelFactory``` from [```exsyst/io```](https://github.com/EXSyst/IO)) ;
- The event loop expression which will be evaluated by the worker, after "stage 3" (by default, none, which will make the subprocess automatically call [```Factory::create()```](https://github.com/reactphp/event-loop/blob/master/src/Factory.php)) ;
- The socket context expression which will be evaluated by the worker, after "stage 3" (by default, none, which will make the shared workers' server sockets be created without contexts) ;
- The "admin cookie", which is a pre-shared secret string that must be sent to a shared worker as part of an appropriate message to get extended status information from it, or make it gracefully stop (by default, none, which makes it impossible to gracefully stop a shared worker using this mechanism) ;
- The kill switch path : the kill switch is a JSON file which indicates if shared workers are globally disabled, and if not, which specific shared workers, if any, are disabled (by default, none, which makes it impossible to disable shared workers) ;
- The precompiled script map, which allows reusing the same script for every worker which uses the same implementation, instead of using a "generate in ```/tmp```, run once, then delete" approach (by default, none).

If you don't specify a bootstrap profile when creating your worker factory, it will automatically create one, with the default values of all parameters.
