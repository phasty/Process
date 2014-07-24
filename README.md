Process
=======

Allows you make any job in another php-process with ability to get feedback from child
process during all execution time:

    // Child process code:
    use Phasty\process\Child\CallableClass;
    class ChildProcess extends CallableClass {
        public $property = "A";
        public function doAsyncJob($arg1, $arg2) {
            $this->trigger("doneJob", (object)[ "result" => $arg1 . $arg2  . $this->property ]);
        }
    }

And parent process code:

    use Phasty\Stream\StreamSet;
    use Phasty\Process\Child\Controller;

    $process = new Controller(ChildProcess::getClass());
    /*
      or you can use instance of class ChildProcess with predefined state like this:
      $childJob = new ChildProcess;
      $childJob->property = "B";
      $process = new Controller($childJob);
     */

    $process->on("start", function () {
        echo "Child process started\n";
    });

    $process->on("stop", function () {
        echo "Child process stopped\n";
    });

    $process->on("doneJob", function ($event) {
        echo "Got result from child: " . $event->getData()->result . "\n";
    });

    $process->doAsyncJob("foo", "bar");

    StreamSet::instance()->listen();
    echo "stopped\n";
    
After execution you should see:

    Child process started
    Got result from child: foobarA
    Child process stopped
    stopped

You can receive event "error" in parent process on child object in case
of catchable error occured like division by zero and so on

Coming soon:
------------

1. Killing process with $process->kill();
2. Getting result code
3. Bi-directional link (sending commands to child)
4. Getting stop reason (normal, signals like segmentation fault and so on)
5. Catching error on child process signalled (now you just get "stop" event)
