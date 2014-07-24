<?php
use Daem\Tests\Process\ChildProc;
use Daem\Stream\StreamSet;
use Daem\Process\Child\Controller;
class ProcessTest extends \PHPUnit_Framework_TestCase {
    public function testProcEvents() {
define("WEB_SERVICE_LOG_DIR", "WEB_SERVICE_LOG_DIR");
        $started = 0;
        $errored = 0;
        $customCalled = null;
        $onceEvent = 0;
        $stopped = 0;

        $processes = [];
        $processCount = 50;
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
        $this->assertEquals(0, $errored, "Событие error процесса не было вызвано");
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
        $process = new Controller($obj = new ChildProc);
        $streamSet = \Daem\Stream\StreamSet::instance();
        $streamSet->addTimer(new \Daem\Stream\Timer(1, 0, function() use ($streamSet) {
            $streamSet->stop();
        }));
        $stopped = $errored = false;
        $process->on("stop", function () use (&$stopped) {
            $stopped = true;
        });
        $process->on("error", function () use (&$errored) {
            $errored = true;
        });
        $process->startUnexpectedDeath();
        $streamSet->listen();
        $this->assertTrue($stopped, "Process should be stopped, but it wasn't");
        $this->assertTrue($errored, "Process should catch error, but it hasn't");
        $this->assertTrue($process->isSignaled(), "Process was signaled, but says he didn't");
    }
}
