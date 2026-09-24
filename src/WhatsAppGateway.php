<?php

namespace SmsGateway;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use SmsGateway\Contracts\WhatsAppDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;

class WhatsAppGateway
{
    /** @var array<string, WhatsAppDriverInterface> */
    private array $drivers = [];

    /** @var array<string, Closure(): WhatsAppDriverInterface> */
    private array $factories = [];

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

    public function registerDriver(string $name, WhatsAppDriverInterface $driver): self
    {
        $this->drivers[$name] = $driver;
        unset($this->factories[$name]);

        return $this;
    }

    /**
     * Register a driver built on first use, so that unused providers
     * never require their credentials or SDK.
     *
     * @param Closure(): WhatsAppDriverInterface $factory
     */
    public function extend(string $name, Closure $factory): self
    {
        $this->factories[$name] = $factory;
        unset($this->drivers[$name]);

        return $this;
    }

    public function hasDriver(string $name): bool
    {
        return isset($this->drivers[$name]) || isset($this->factories[$name]);
    }

    public function getDriver(string $name): WhatsAppDriverInterface
    {
        if (isset($this->factories[$name])) {
            $this->drivers[$name] = ($this->factories[$name])();
            unset($this->factories[$name]);
        }

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("WhatsApp driver [{$name}] is not registered.");
        }

        return $this->drivers[$name];
    }

    /**
     * Send a WhatsApp message using the message driver, or the default one.
     *
     * @throws CouldNotSendNotification
     */
    public function send(string $to, WhatsAppMessage $message): void
    {
        $driverName = $message->getDriver()
            ?? $this->defaultDriver
            ?? array_key_first($this->drivers + $this->factories);

        if ($driverName === null) {
            throw new RuntimeException('No WhatsApp driver registered.');
        }

        $this->getDriver($driverName)->send($to, $message);
    }

    /**
     * Send a WhatsApp message with automatic fallback through configured drivers.
     *
     * A message pinned to a driver never falls back: its template
     * identifier only makes sense for that provider.
     *
     * @throws CouldNotSendNotification
     */
    public function sendWithFallback(string $to, WhatsAppMessage $message): void
    {
        if ($this->fallbackOrder === [] || $message->getDriver() !== null) {
            $this->send($to, $message);

            return;
        }

        $lastException = null;

        foreach ($this->fallbackOrder as $driverName) {
            try {
                $this->getDriver($driverName)->send($to, $message);

                return;
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        throw CouldNotSendNotification::allWhatsAppDriversFailed($lastException);
    }
}
