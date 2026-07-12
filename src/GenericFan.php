<?php

namespace DeptOfScrapyardRobotics\Actuators\GenericFans;

use BareMetal\Actuation\Actuator;
use BareMetal\Contracts\Actuators\Fans\BasicFanFunctionality;

abstract class GenericFan extends Actuator implements BasicFanFunctionality
{
    abstract public function on(): void;

    abstract public function off(): void;
}
