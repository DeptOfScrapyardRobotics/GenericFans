<?php

namespace DeptOfScrapyardRobotics\Actuators\GenericFans;

use BareMetal\Sensors\Sensor;
use GPIO\Digital\Input\DigitalInput;
use GPIO\Contracts\Digital\EdgeEvent;
use BareMetal\Contracts\Sensors\Speed\RPMReadings;

class GenericTachometer extends Sensor implements RPMReadings
{
    public function __construct(
        protected DigitalInput $input,
    ) {}

    /**
     * Sample tach edges for $sample_ms and return revolutions per minute.
     *
     * PC fans usually emit 2 pulses per revolution on an open-collector tach
     * line. Configure the DigitalInput with an internal pull-up
     * (LineBias::PULL_UP), rising-edge events, and a short listen timeout
     * (e.g. 1–10ms) so the sample loop can honor the window without blocking
     * past it. Without a pull-up the line floats and rpm() stays at 0.
     *
     * @throws GenericFanException
     */
    public function rpm(int $sample_ms = 500, int $pulses_per_revolution = 2): float
    {
        if ($sample_ms <= 0) {
            throw GenericFanException::invalidSampleWindow($sample_ms);
        }

        if ($pulses_per_revolution <= 0) {
            throw GenericFanException::invalidPulsesPerRevolution($pulses_per_revolution);
        }

        $this->input->flush();

        $deadline = hrtime(true) + ($sample_ms * 1_000_000);
        $pulses = 0;

        while (hrtime(true) < $deadline) {
            $edge = $this->input->listen();

            if (is_null($edge)) {
                continue;
            }

            if ($edge->event === EdgeEvent::RISING) {
                $pulses++;
            }
        }

        return ($pulses / $pulses_per_revolution) * (60_000.0 / $sample_ms);
    }
}
