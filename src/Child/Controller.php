<?php
namespace Phasty\Process\Child {
    use \Phasty\Log\File as log;
    /*
     * Объекты этого класса контролируют запуск и выполнение внешнего процесса
     */
    class Controller extends \Phasty\Events\Eventable {
        /*
         * PID запущенного контролируемого процесса
         */
        protected $pid = null;

        /*
         * Флаг того, что процесс выполняется
         */
        protected $running = false;

        /*
         * Флаг произошедшей ошибки в процессе
         */
        protected $error = false;

        /*
         * Имя класса или объект, в контексте которого нужно выполнить метод
         */
        protected $job = null;

        /*
         * Входящий поток событий
         */
        protected $inStream = null;

        /*
         * Исходящи поток команд
         */
        protected $outStream = null;

        /*
         * Читает события из потока и воспроизводит их на объекте
         */
        protected $streamReader = null;

        /**
         *  Результат функции proc_open
         */
        protected $proc = null;

        /*
         *
         */
        public function __construct($job, \Phasty\Stream\StreamSet $streamSet = null) {
            if (!is_subclass_of($job, "\\Phasty\\Process\\Child\\CallableClass")) {
                throw new \Exception("Job class must implement \\Phasty\\Process\\Child");
            }
            if (!$streamSet) {
                $streamSet = \Phasty\Stream\StreamSet::instance();
            }
            $this->job  = $job;

            $this->inStream = new \Phasty\Stream\Stream();
            $this->outStream = new \Phasty\Stream\Stream();
            $this->inStream->on("close", function() {
                // If stream is closed, but no STOP event received
                if ($this->isProcOpen()) {
                    $this->trigger("error");
                    $this->trigger("stop");
                }
            });

            $this->streamReader = new \Phasty\Events\StreamReader($this->inStream);

            $this->streamReader->addListener($this);
$this->on(null, function($event) {
    log::info("Event on child process " . $this->getPID() . ": " . $event->getName());
});
            if ($job instanceof \Phasty\Events\Eventable) {
                $this->streamReader->addListener($job);
            }

            $streamSet->addReadStream($this->inStream);

            $this->on("start", function () {
                $this->running = true;
            });

            $this->on("error", function() {
                $this->error = true;
            });

            $this->on("stop", [ $this, "onStop" ]);
        }

        protected function onStop() {
            if (!$this->isProcOpen()) {
                return;
            }
            $this->close();
        }

        protected function isProcOpen() {
            return is_resource($this->proc);
        }

        protected function close() {
            if ($this->isProcOpen()) {
                $proc = $this->proc;
                $this->proc = null;
            }
            $this->running = false;
            $this->inStream->close();
            $this->outStream->close();
            if (isset($proc)) {
                proc_close($proc);
            }
        }

        public function __call($name, array $arguments = []) {
            $this->execute($name, $arguments);
        }

        public function execute($method, $arguments) {
            $job       = escapeshellarg(base64_encode(serialize($this->job)));
            $arguments = $arguments ? escapeshellarg(base64_encode(serialize($arguments))) : "";
            $method    = escapeshellarg(base64_encode(serialize($method)));
            $error     = null;
            set_error_handler(function($e) use (&$error) {
                $error = $e;
            });
            $this->proc =  proc_open(
                realpath(__DIR__ . "/../async.php") . " $job $method $arguments",
                [
                    0 => ["pipe", "r"],
                    1 => ["pipe", "w"],
                    2 => ["file", "/tmp/error.log", "w"]
                ],
                $pipes
            );
            restore_error_handler();
            if (!$this->proc) {
                throw new \Exception("Could not create process: $error");
            }
            $this->outStream->open($pipes[ 0 ]);
            $this->inStream->open($pipes[ 1 ]);
        }

        static public function execAsync($cmd) {
            $cmd = escapeshellarg($cmd);
            $output = null;
            $return = null;
            exec("bash -c $cmd > /dev/null 2>&1 & echo $!", $output, $return);
            return $output[ 0 ];

        }

        public function getPID() {
            return $this->pid;
        }

        public function isRunning() {
            return $this->running;
        }

        public function getOutputPipe() {
            return self::getOutputPipeByPid($this->pid);
        }

        public static function getOutputPipeByPid($pid) {
            return "/tmp/proc-events/$pid";
        }
    }
}
