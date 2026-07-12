<?php

namespace DeptOfScrapyardRobotics\Actuators\GenericFans;

use GPIO\Digital\Output\DigitalOutput;

class GenericDigitalFan extends GenericFan
{
    public function __construct(
        protected DigitalOutput $output,
        protected bool $active_high = true,
    ) {}

    public function on(): void
    {
        $this->active_high ? $this->output->high() : $this->output->low();
    }

    public function off(): void
    {
        $this->active_high ? $this->output->low() : $this->output->high();
    }
}
