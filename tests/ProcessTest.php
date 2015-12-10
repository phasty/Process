<?php
use Phasty\ProcessTests\ChildProc;
use Phasty\Stream\StreamSet;
use Phasty\Process\Child\Controller;
class ProcessTest extends \PHPUnit_Framework_TestCase {
    public function testProcEvents() {
        $started = 0;
        $errored = 0;
        $customCalled = null;
        $onceEvent = 0;
        $stopped = 0;

        $processes = [];
        $processCount = 100;
        $times = 2;
        for ($j = 0; $j < $times; $j++) {
            for ($i = 0; $i < $processCount; $i++) {
                $processes[$i] = new Controller(ChildProc::getClass());
                $processes[$i]->on("start", function() use(&$started) {
                    $started++;
                });
                $processes[$i]->on("error", function() use(&$errored) {
                    $errored++;
                });
                $processes[$i]->on("custom.event", function($eventObject) use(&$customCalled, &$customRepeatCalled) {
                    $customCalled = $eventObject->getData();
                    $customRepeatCalled++;
                });

                $processes[$i]->on("once.event", function() use($processes, $i, &$onceEvent) {
                    $processes[$i]->off("once.event");
                    $onceEvent++;
                });
                $processes[$i]->on("stop", function() use(&$stopped, $processCount, $times) {
                    $stopped++;
                    if ($stopped == $processCount*$times) {
                        StreamSet::instance()->stop();
                    }
                    return false;
                });
                $processes[$i]->exec();
            }
        }


        StreamSet::instance()->listen();

        $this->assertEquals($processCount*$times, $started, "Событие start процесса не было вызвано");
        $this->assertEquals($processCount*$times, $errored, "Событие error процесса не было вызвано");
        $this->assertEquals(ChildProc::$SEND_DATA, $customCalled, "Неверные данные пришли от процесса");
        $this->assertEquals(ChildProc::$SEND_COUNT*$processCount*$times, $customRepeatCalled, "Кастомное событие обработано неполностью");
        $this->assertEquals($processCount*$times, $onceEvent, "Событие должно быть вызывано " . ($processCount*$times) . " раза");
        $this->assertEquals($processCount*$times, $stopped, "Событие stop процесса не было вызывано");
    }

    public function testWholeObjectArgument() {
        $process = new Controller($obj = new ChildProc);
        $obj->a = 1;
        $actual = 0;
        $process->on("increase", function($event) use (&$actual) {
            $actual = $event->getData();
            StreamSet::instance()->stop();
        });
        $process->increaseA();
        StreamSet::instance()->listen();
        $this->assertEquals(2, $actual, "В поток не был передан экземпляр класса как аргумент");
    }
    public function testWhenUnexcpectedChildDeath() {
        $streamSet = \Phasty\Stream\StreamSet::instance();
        $streamSet->addTimer(new \Phasty\Stream\Timer(10, 0, function() use ($streamSet) {
            $streamSet->stop();
        }));
        $times = 300;
        $stopped = $errored = 0;
        $procs = [];
        for ($i = 0; $i < $times; $i++) {
            $procs []=  new Controller(new ChildProc);
            $procs[ $i ]->on("stop", function () use (&$stopped) {
                $stopped++;
            });
            $procs[ $i ]->on("error", function ($event) use (&$errored) {
                $errored++;
            });
            $procs[ $i ]->sleep();
            $streamSet->addTimer($timers[ $i ] = new \Phasty\Stream\Timer(0, 100, function($event, $timer) use ($procs, $i) {
                $procs[ $i ]->kill();
                $timer->cancel();
            }));
        }
        $streamSet->listen();
        $this->assertEquals($times, $stopped, "Process should be stopped, but it wasn't");
        $this->assertEquals($times, $errored, "Process should catch error, but it hasn't");
        for ($i = 1; $i <= $times; $i++) {
            $signaled = $procs[ $i - 1 ]->isSignaled();

            $this->assertTrue($signaled, "Process [$i] was signaled, but says he wasn't");
        }
    }
}
