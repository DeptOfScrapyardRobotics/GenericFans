<?php

namespace DeptOfScrapyardRobotics\Actuators\GenericFans;

use BareMetal\Contracts\Actuators\ActuationException;

class GenericFanException extends ActuationException
{
    public static function invalidSpeed(int $percent): static
    {
        return new static("Fan speed percent must be 0-100, got [{$percent}].");
    }

    public static function invalidFrequency(int $hz): static
    {
        return new static("Fan PWM frequency must be > 0 Hz, got [{$hz}].");
    }

    public static function invalidPeriod(int $period_ns): static
    {
        return new static("Fan PWM period must be > 0 ns before reading frequency, got [{$period_ns}].");
    }

    public static function invalidSampleWindow(int $sample_ms): static
    {
        return new static("Tachometer sample window must be > 0 ms, got [{$sample_ms}].");
    }

    public static function invalidPulsesPerRevolution(int $pulses): static
    {
        return new static("Tachometer pulses-per-revolution must be > 0, got [{$pulses}].");
    }
}
