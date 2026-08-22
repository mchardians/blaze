<?php

use Livewire\Blaze\BladeService;
use Livewire\Blaze\BlazeManager;
use Livewire\Blaze\Compiler\Compiler;
use Livewire\Blaze\Config;
use Livewire\Blaze\Debugger\Debugger;
use Livewire\Blaze\Parser\Attribute;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Parser\Nodes\TextNode;
use Livewire\Blaze\Parser\Tokenizer;
use Livewire\Blaze\Parser\Tokens\SlotOpenToken;
use Livewire\Blaze\Parser\Tokens\TagCloseToken;
use Livewire\Blaze\Parser\Tokens\TagOpenToken;

beforeEach(function () {
    config()->set('blaze.prefixes', [
        'flux:' => ['namespace' => 'flux::', 'slot' => 'x-slot'],
        'x:' => ['namespace' => '', 'slot' => 'x-slot'],
        'x-' => ['namespace' => '', 'slot' => 'x-slot'],
        'test:' => ['namespace' => 'test::', 'slot' => 'test-slot'],
    ]);

    $reflection = new ReflectionClass(ComponentNode::class);
    if ($reflection->hasProperty('prefixesConfig')) {
        $property = $reflection->getProperty('prefixesConfig');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
});

it('tokenizes custom component prefix correctly', function () {
    $tokenizer = new Tokenizer(app(BladeService::class));
    $tokens = $tokenizer->tokenize('<test:button>Click</test:button>');

    expect($tokens[0])->toBeInstanceOf(TagOpenToken::class)
        ->and($tokens[0]->name)->toBe('button')
        ->and($tokens[0]->prefix)->toBe('test:')
        ->and($tokens[0]->namespace)->toBe('test::');

    expect($tokens[2])->toBeInstanceOf(TagCloseToken::class)
        ->and($tokens[2]->name)->toBe('button')
        ->and($tokens[2]->prefix)->toBe('test:');
});

it('tokenizes custom slot prefix correctly', function () {
    $tokenizer = new Tokenizer(app(BladeService::class));
    $tokens = $tokenizer->tokenize('<test-slot:header></test-slot>');

    expect($tokens[0])->toBeInstanceOf(SlotOpenToken::class)
        ->and($tokens[0]->prefix)->toBe('test-slot');
});

it('renders component node without hardcoded namespace', function () {
    $node = new ComponentNode(
        name: 'test::button',
        prefix: 'test:',
        attributeString: '',
        children: [],
        selfClosing: true
    );

    expect($node->render())->toBe('<test:button />');
});

it('compiles dynamic delegate component for any namespace', function () {
    $compiler = new Compiler(app(Config::class), app(BladeService::class), app(BlazeManager::class));

    $node = new ComponentNode(
        name: 'test::delegate-component',
        prefix: 'test:',
        attributeString: 'component="\'alert\'"',
        selfClosing: true,
        attributes: [
            'component' => new Attribute('component', '\'alert\'', 'component', false)
        ]
    );

    $result = $compiler->compile($node);

    expect($result)->toBeInstanceOf(TextNode::class)
        ->and($result->render())->toContain('$__blaze->resolve(\'test::\' . \'alert\')');
});

it('extracts custom component name in debugger based on configuration', function () {
    $debugger = new Debugger(app(BladeService::class));
    
    $path = resource_path('views/test/button.blade.php');
    
    $name = $debugger->extractComponentName($path);
    
    expect($name)->toBe('test:button');
});