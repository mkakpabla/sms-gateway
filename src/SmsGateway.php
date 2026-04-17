<?php

namespace SmsGateway;

use InvalidArgumentException;
use RuntimeException;
use SmsGateway\Contracts\SmsDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;

class SmsGateway
{
    /** @var array<string, SmsDriverInterface> */
    private array $drivers = [];

    /** @var string[] */
    private array $fallbackOrder = [];

    private ?string $defaultDriver = null;

    public function setDefaultDriver(string $name): self
    {
        $this->defaultDriver = $name;

        return $this;
    }

    public function getDefaultDriver(): ?string
    {
        return $this->defaultDriver;
    }

    /**
     * @param string[] $drivers
     */
    public function setFallbackOrder(array $drivers): self
    {
        $this->fallbackOrder = $drivers;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getFallbackOrder(): array
    {
        return $this->fallbackOrder;
    }

    public function registerDriver(string $name, SmsDriverInterface $driver): self
    {
        $this->drivers[$name] = $driver;

        return $this;
    }

    public function getDriver(string $name): SmsDriverInterface
    {
        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("SMS driver [{$name}] is not registered.");
        }

        return $this->drivers[$name];
    }

    /**
     * Send an SMS using the default driver.
     *
     * @throws CouldNotSendNotification
     */
    public function send(string $to, SmsMessage $message): void
    {
        $driverName = $this->defaultDriver ?? array_key_first($this->drivers);

        if ($driverName === null) {
            throw new RuntimeException('No SMS driver registered.');
        }

        $this->getDriver($driverName)->send($to, $message);
    }

    /**
     * Send an SMS with automatic fallback through configured drivers.
     *
     * @throws CouldNotSendNotification
     */
    public function sendWithFallback(string $to, SmsMessage $message): void
    {
        $order = $this->fallbackOrder;

        if (empty($order)) {
            $this->send($to, $message);

            return;
        }

        $lastException = null;

        foreach ($order as $driverName) {
            try {
                $this->getDriver($driverName)->send($to, $message);

                return;
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        throw CouldNotSendNotification::allDriversFailed($lastException);
    }
}
