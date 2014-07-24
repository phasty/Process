<?php
namespace Daem\Tests\Process {
    class ChildProc extends \Daem\Process\Child\CallableClass {
        static public $SEND_COUNT = 100;
        static public $SEND_DATA = [
            "a" => 1
        ];
        public function exec() {
            $data = str_repeat("A", 10000);
            for ($i = 0; $i < self::$SEND_COUNT;$i++) {
                $this->trigger("custom.event", self::$SEND_DATA);
                $this->trigger("once.event");
//                usleep(10);
            }
            return 1/0;
        }

        public $a = 0;

        public function increaseA() {
            $this->trigger("increase", $this->a + 1);
        }
        public function startUnexpectedDeath() {
            posix_kill(getmypid(), 9);
        }
    }
}
