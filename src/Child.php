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
            $error = compact("errno", "errstr", "errfile", "errline");
            if ((int)$errno & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_WARNING)) {
                $this->trigger("error", $error);
            }
            log::error(var_export($error, 1));
        }

        static public function getClass() {
            return get_called_class();
        }
    }
}
