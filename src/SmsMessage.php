<?php

namespace SmsGateway;

class SmsMessage
{
    private string $content = '';

    private ?string $to = null;

    private ?string $from = null;

    public static function create(string $content = ''): self
    {
        return (new self)->content($content);
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getTo(): ?string
    {
        return $this->to;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }
}
