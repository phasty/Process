<?php
namespace Phasty\Process\Child {
    class CallableClass extends \Phasty\Events\Eventable {
        static public function getClass() {
            return get_called_class();
        }
    }
}
