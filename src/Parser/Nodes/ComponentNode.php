<?php

namespace Livewire\Blaze\Parser\Nodes;

/**
 * Represents an <x-component> or <flux:component> tag in the AST.
 */
class ComponentNode extends Node
{
    /** Pre-computed by the Walker before children are compiled to TextNodes. */
    public bool $hasAwareDescendants = false;
    protected static ?array $prefixesConfig = null;

    public function __construct(
        public string $name,
        public string $prefix,
        public string $attributeString = '',
        public array $children = [],
        public bool $selfClosing = false,
        public array $parentsAttributes = [],
        /** @var Attribute[] */
        public array $attributes = [],
    ) {
    }

    /**
     * Resolve the slot name, handling both short (<x-slot:name>) and standard syntax.
     */
    protected function resolveSlotName(SlotNode $slot): string
    {
        if (! empty($slot->name)) {
            return $slot->name;
        }

        if (preg_match('/(?:^|\s)name\s*=\s*["\']([^"\']+)["\']/', $slot->attributeString, $matches)) {
            return $matches[1];
        }

        return 'slot';
    }

    /**
     * Set the accumulated parent component attributes for @aware resolution.
     */
    public function setParentsAttributes(array $parentsAttributes): void
    {
        $this->parentsAttributes = $parentsAttributes;
    }

    /** {@inheritdoc} */
    public function render(): string
    {
        $name = $this->stripNamespaceFromName($this->name, $this->prefix);

        $output = "<{$this->prefix}{$name}";

        foreach ($this->attributes as $attribute) {
            $output .= ' ' . $attribute->render();
        }

        if ($this->selfClosing) {
            return $output.' />';
        }

        $output .= '>';

        // Iterate over original children to preserve structure
        foreach ($this->children as $child) {
            $output .= $child->render();
        }

        $output .= "</{$this->prefix}{$name}>";

        return $output;
    }


    /**
     * Reset the cached prefix config. Call this whenever config('blaze.prefixes')
     * may have changed at runtime (e.g. between Octane requests or tests).
     */
    public static function flushPrefixCache(): void
    {
        self::$prefixesConfig = null;
    }

    /**
     * Strip the namespace prefix from a component name for tag rendering.
     */
    protected function stripNamespaceFromName(string $name, string $prefix): string
    {
        if (self::$prefixesConfig === null) {
            self::$prefixesConfig = config('blaze.prefixes', [
                'flux:' => ['namespace' => 'flux::'],
                'x:' => ['namespace' => ''],
                'x-' => ['namespace' => ''],
            ]);
        }

        if (isset(self::$prefixesConfig[$prefix])) {
            $namespace = self::$prefixesConfig[$prefix]['namespace'];
            if (! empty($namespace) && str_starts_with($name, $namespace)) {
                return substr($name, strlen($namespace));
            }
        }

        return $name;
    }
}
