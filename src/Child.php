<?php
namespace Phasty\Process {
    use \Phasty\Log\File as log;
    class Child extends \Phasty\Events\Eventable {
        protected $outStream = null;
        public function __construct(Child\CallableClass $object) {
            $this->outStream = new \Phasty\Stream\Stream(STDOUT);

            $object->on(null, function($event) {
                log::info("Event {$event->getName()} on object");
                $this->trigger($event);
            });

            $this->on(null, function($event) {
                log::info("Event {$event->getName()} on process");
                $this->outStream->write($event);
                return;
                \Phasty\Stream\NamedPipe\Mx1::send($this->eventPipe(), $event);
            });
            $this->trigger("start", getmypid());
            $this->setHandlers();
        }

        protected function setHandlers() {
            register_shutdown_function([$this, 'shutdownHandler']);
            set_error_handler([$this, 'errorHandler']);
        }

        public function shutdownHandler() {
            $error = error_get_last();
            if ($error) {
//                if ($error[ "type" ] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
                    $this->trigger("error", $error);
//                }
            }
            $this->trigger("stop");
        }

        public function errorHandler($errno, $errstr, $errfile, $errline, array $errcontext) {
//            $this->trigger("warning", $warning = compact("errno", "errstr", "errfile", "errline"));
$warning = compact("errno", "errstr", "errfile", "errline");
            log::info(var_export($warning, 1));
        }

        protected function eventPipe() {
            return Child\Controller::getOutputPipeByPid(getmypid());
        }

        static public function getClass() {
            return get_called_class();
        }
    }
}
