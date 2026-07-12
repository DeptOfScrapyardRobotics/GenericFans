<?php

namespace DeptOfScrapyardRobotics\Actuators\GenericFans;

use BareMetal\Contracts\Actuators\Fans\FanSpeedControl;
use GPIO\PWM\PWMChannel;

class GenericControllableFan extends GenericFan implements FanSpeedControl
{
    protected int $speed_percent = 0;

    protected int $last_speed_percent = 100;

    public function __construct(
        protected PWMChannel $pwm,
    ) {}

    public function on(): void
    {
        $resume = $this->last_speed_percent > 0 ? $this->last_speed_percent : 100;
        $this->speed($resume);
    }

    public function off(): void
    {
        if ($this->speed_percent > 0) {
            $this->last_speed_percent = $this->speed_percent;
        }

        $this->speed(0);
    }

    /**
     * Get or set fan speed as a duty-cycle percentage (0-100).
     * Speed 0 disables the PWM channel; any positive speed enables it.
     *
     * @throws GenericFanException
     */
    public function speed(?int $percent = null): int
    {
        if (is_null($percent)) {
            return $this->speed_percent;
        }

        if ($percent < 0 || $percent > 100) {
            throw GenericFanException::invalidSpeed($percent);
        }

        $period = $this->pwm->getPeriod();
        if ($period <= 0) {
            throw GenericFanException::invalidPeriod($period);
        }

        $duty = (int) round($period * ($percent / 100));
        $this->pwm->setDutyCycle($duty);
        $this->pwm->setEnable($percent > 0);
        $this->speed_percent = $percent;

        if ($percent > 0) {
            $this->last_speed_percent = $percent;
        }

        return $this->speed_percent;
    }

    /**
     * Get or set the PWM carrier frequency in Hz (period = 1e9 / hz nanoseconds).
     * Preserves the current speed percentage across the period change.
     *
     * @throws GenericFanException
     */
    public function frequency(?int $hz = null): int
    {
        if (is_null($hz)) {
            $period = $this->pwm->getPeriod();
            if ($period <= 0) {
                throw GenericFanException::invalidPeriod($period);
            }

            return (int) round(1_000_000_000 / $period);
        }

        if ($hz <= 0) {
            throw GenericFanException::invalidFrequency($hz);
        }

        $period = (int) round(1_000_000_000 / $hz);
        $enabled = $this->pwm->getEnable();

        // Drop duty before shrinking period so sysfs never sees duty > period.
        if ($enabled) {
            $this->pwm->setEnable(false);
        }
        $this->pwm->setDutyCycle(0);
        $this->pwm->setPeriod($period);

        if ($this->speed_percent > 0) {
            $duty = (int) round($period * ($this->speed_percent / 100));
            $this->pwm->setDutyCycle($duty);
            $this->pwm->setEnable(true);
        }

        return $hz;
    }
}
