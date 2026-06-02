<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Flag;

final readonly class Flag
{
    public function __construct(
        public string $name,
        public bool $enabled,
        public ?string $description = null,
    ) {}

    /**
     * @return array{name: string, enabled: bool, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'enabled' => $this->enabled,
            'description' => $this->description,
        ];
    }

    public function withEnabled(bool $enabled): self
    {
        return new self($this->name, $enabled, $this->description);
    }
}
