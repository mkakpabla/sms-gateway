<?php

namespace SmsGateway;

class WhatsAppMessage
{
    private string $body = '';

    private ?string $template = null;

    /** @var array<string, string> */
    private array $templateVariables = [];

    private ?string $language = null;

    /** @var string[] */
    private array $mediaUrls = [];

    private ?string $to = null;

    private ?string $from = null;

    private ?string $driver = null;

    public static function create(string $body = ''): self
    {
        return (new self())->body($body);
    }

    /**
     * Outside the 24-hour customer service window, Meta only accepts
     * pre-approved templates: free-form bodies are rejected.
     *
     * The identifier is provider specific: a Content SID for Twilio,
     * a template name for the Meta Cloud API, etc.
     *
     * @param array<int|string, string> $variables Positional values, keyed from 1 or as a list
     */
    public static function template(string $template, array $variables = [], ?string $language = null): self
    {
        $message = (new self())->templateId($template)->templateVariables($variables);

        return $language === null ? $message : $message->language($language);
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function templateId(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @param array<int|string, string> $variables
     */
    public function templateVariables(array $variables): self
    {
        // Template placeholders start at {{1}}, not {{0}}.
        if ($variables !== [] && array_is_list($variables)) {
            $variables = array_combine(range(1, count($variables)), $variables);
        }

        $this->templateVariables = [];

        foreach ($variables as $key => $value) {
            $this->templateVariables[(string) $key] = (string) $value;
        }

        return $this;
    }

    public function language(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function mediaUrl(string $url): self
    {
        $this->mediaUrls[] = $url;

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

    /**
     * Pin the message to a driver, bypassing the default driver and the fallback chain.
     */
    public function driver(string $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    /**
     * @return array<string, string>
     */
    public function getTemplateVariables(): array
    {
        return $this->templateVariables;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /**
     * @return string[]
     */
    public function getMediaUrls(): array
    {
        return $this->mediaUrls;
    }

    public function getTo(): ?string
    {
        return $this->to;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function getDriver(): ?string
    {
        return $this->driver;
    }

    public function isTemplate(): bool
    {
        return $this->template !== null && $this->template !== '';
    }
}
