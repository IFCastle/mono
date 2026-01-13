<?php

declare(strict_types=1);

namespace IfCastle\Console;

use Attribute;
use IfCastle\TypeDefinitions\NativeSerialization\AttributeNameInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
readonly class AsConsole implements AttributeNameInterface
{
    /**
     * @param string[]    $aliases
     */
    public function __construct(
        public string   $commandName    = '',
        public ?string  $namespace      = null,
        public array    $aliases        = [],
        public bool     $hidden         = false,
        public string   $help           = '',
        public string   $description    = ''
    ) {}

    #[\Override]
    public function getAttributeName(): string
    {
        return static::class;
    }
}
