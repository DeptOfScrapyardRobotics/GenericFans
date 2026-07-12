# Generic Fans
### Drive fans over digital GPIO and PWM with PHP

PHP package for on/off case fans, PWM speed-controlled fans, and open-collector tachometers. It sits on the ScrapyardIO GPIO stack and plugs into the BareMetal Actuation fan components (`BasicFan`, `SpeedControllableFan`, `TachometerComponent`).

Compatible Digital Interfaces
===============

Simple on/off fans (relay, MOSFET, or Pironman-style GPIO fans) are driven from a digital output pin.

You can interface with them the following ways:

* A Linux single-board computer's exposed GPIO pins using the POSIX / libgpiod carrier (`posix`)
* An MPSSE-enabled USB-to-serial device such as an FT232H using the USB carrier (`usb`) for digital out

Compatible PWM Interfaces
===============

4-pin speed-controlled fans are driven from a hardware PWM channel (duty cycle = speed %, period = carrier frequency).

You can interface with them the following ways:

* A Linux single-board computer's native PWM sysfs chips (`/sys/class/pwm/pwmchipN`) using the native carrier (`native`)
* *(Planned)* An I²C PWM expander such as the PCA9685 — not wired in this package yet

Compatible Tach Interfaces
===============

Most PC / case fan tach lines are open-collector and need a pull-up. This package samples rising edges on a digital input and returns RPM (default: 2 pulses per revolution).

You can interface with them the following ways:

* A Linux single-board computer's exposed GPIO pins using the POSIX carrier (`posix`) with `LineBias::PULL_UP`
* An MPSSE-enabled USB digital input using the USB carrier (`usb`) with an appropriate pull-up

Dependencies
=============

This package makes use of modules within:

* [The ScrapyardIO Framework](https://github.com/ScrapyardIO/framework)

For digital on/off fans and tachometers you also need:

* [POSI Extension v^0.4.0 or newer](https://github.com/php-io-extensions/posi)
* [Microscrap POSIX Package](https://github.com/microscrap/posix)
* [Microscrap Native GPIO Package](https://github.com/microscrap/gpio)
* [Microscrap POSIX Drivers](https://github.com/microscrap/scrapyard-posix-drivers) (`posix` carrier)

For PWM speed-controlled fans you also need:

* [Microscrap Native Drivers](https://github.com/microscrap/scrapyard-native-drivers) (`native` PWM carrier)

Installing from Composer
====================

```bash
composer require dept-of-scrapyard-robotics/generic-fans
```

Basic Usage
============

### Digital on/off fan (POSIX)

```php
<?php

use GPIO\Common\GPIO;
use DeptOfScrapyardRobotics\Actuators\GenericFans\GenericDigitalFan;

$output = GPIO::digitalOut('posix')
    ->device(4)          // gpiochip4 on Raspberry Pi 5 (RP1)
    ->pin(6)             // e.g. Pironman5 Max GPIO fans
    ->name('case-fans')
    ->defaultState(false)
    ->create();

$fan = new GenericDigitalFan($output);          // active-high by default
// $fan = new GenericDigitalFan($output, active_high: false);

$fan->on();
$fan->off();

$output->close();
```

### PWM speed-controlled fan (native sysfs)

```php
<?php

use GPIO\Common\GPIO;
use DeptOfScrapyardRobotics\Actuators\GenericFans\GenericControllableFan;

$pwm = GPIO::pwm('native')
    ->device(0)           // pwmchip0
    ->channel(2)
    ->name('pwm-fan')
    ->create();

$fan = new GenericControllableFan($pwm);

$fan->frequency(25_000); // set carrier before speed (Hz → period ns)
$fan->speed(50);         // 0–100%; 0 disables the channel
$fan->on();              // resumes last non-zero speed (or 100%)
$fan->off();             // speed 0

$pwm->close();
```

### Tachometer (POSIX, open-collector)

Fan tach lines float without a pull-up. Always bias the input or `rpm()` stays at `0` while the fan still spins.

```php
<?php

use GPIO\Common\GPIO;
use GPIO\Contracts\Digital\LineBias;
use DeptOfScrapyardRobotics\Actuators\GenericFans\GenericTachometer;

$tach_in = GPIO::digitalIn('posix')
    ->device(4)
    ->pin(24)
    ->name('fan-tach')
    ->lineBias(LineBias::PULL_UP)
    ->withEvents(true, false)   // rising edges
    ->timeout(5)                // short listen timeout (ms)
    ->create();

$tach = new GenericTachometer($tach_in);

$rpm = $tach->rpm();                              // default 500ms sample, 2 PPR
$rpm = $tach->rpm(sample_ms: 250, pulses_per_revolution: 2);

$tach_in->close();
```

Alternative Usage
============

### Using Through the Actuation Library (as a BasicFan)

```php
<?php

use GPIO\Common\GPIO;
use BareMetal\Actuation\Fans\BasicFan;
use DeptOfScrapyardRobotics\Actuators\GenericFans\GenericDigitalFan;

$output = GPIO::digitalOut('posix')
    ->device(4)
    ->pin(6)
    ->name('case-fans')
    ->defaultState(false)
    ->create();

$fans = new BasicFan(new GenericDigitalFan($output));
$fans->on();
$fans->off();
```

### Using Through the Actuation Library (as a SpeedControllableFan + tach)

```php
<?php

use GPIO\Common\GPIO;
use GPIO\Contracts\Digital\LineBias;
use BareMetal\Actuation\Fans\SpeedControllableFan;
use DeptOfScrapyardRobotics\Actuators\GenericFans\GenericControllableFan;
use DeptOfScrapyardRobotics\Actuators\GenericFans\GenericTachometer;

$pwm = GPIO::pwm('native')
    ->device(0)
    ->channel(2)
    ->name('pwm-fan')
    ->create();

$tach_in = GPIO::digitalIn('posix')
    ->device(4)
    ->pin(24)
    ->name('fan-tach')
    ->lineBias(LineBias::PULL_UP)
    ->withEvents(true, false)
    ->timeout(5)
    ->create();

$fan = new SpeedControllableFan(
    new GenericControllableFan($pwm),
    new GenericTachometer($tach_in),
);

$fan->frequency(25_000);
$fan->speed(85);
usleep(400_000);                 // let the rotor come up
$rpm = $fan->rpm();              // requires the tach argument above

$fan->speed(0);
$pwm->close();
$tach_in->close();
```

### Tach-only through TachometerComponent

```php
<?php

use BareMetal\Actuation\Fans\TachometerComponent;
use DeptOfScrapyardRobotics\Actuators\GenericFans\GenericTachometer;

$tach = new TachometerComponent(new GenericTachometer($tach_in));
$rpm = $tach->rpm();
```

Notes
=====

* Call `frequency()` before the first `speed()` on a PWM fan — `speed()` needs a valid period on the channel.
* Open-collector tach lines **must** use `LineBias::PULL_UP` (or an external pull-up). Without it, edge sampling returns `0` RPM.
* Use a short digital-in `timeout()` (about 1–10 ms) so `rpm()` can honor its sample window without blocking past it.
* PCA9685 / I²C PWM expander support is reserved in the package suggests and transport todos — not implemented yet.
