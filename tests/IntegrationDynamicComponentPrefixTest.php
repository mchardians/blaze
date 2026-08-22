<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Livewire\Blaze\Parser\Nodes\ComponentNode;
use Livewire\Blaze\Memoizer\Memo;

beforeEach(function () {
    Artisan::call('view:clear');

    config()->set('blaze.prefixes', [
        'x:' => ['namespace' => '', 'slot' => 'x-slot'],
        'x-' => ['namespace' => '', 'slot' => 'x-slot'],
        'test:' => ['namespace' => 'test::', 'slot' => 'test-slot'],
    ]);

    config()->set('blaze.include', ['*']);

    app(\Livewire\Blaze\BlazeManager::class)->refreshTokenizer();
    
    $reflection = new ReflectionClass(ComponentNode::class);
    if ($reflection->hasProperty('prefixesConfig')) {
        $property = $reflection->getProperty('prefixesConfig');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    $fixturesDir = __DIR__ . '/fixtures/views/components/dynamic-test';
    File::ensureDirectoryExists($fixturesDir);

    File::put($fixturesDir . '/button.blade.php', <<<'BLADE'
    @blaze(fold: true)
    <button {{ $attributes }}>{{ $slot }}</button>
    BLADE);

    File::put($fixturesDir . '/card.blade.php', <<<'BLADE'
    @blaze(fold: true)
    <div {{ $attributes }}>
        @if(isset($header))
            <div class="header">{{ $header }}</div>
        @endif
        <div class="body">{{ $slot }}</div>
    </div>
    BLADE);

    File::put($fixturesDir . '/avatar.blade.php', <<<'BLADE'
    @blaze(memo: true)
    <img {{ $attributes }} alt="Avatar">
    BLADE);

    Blade::anonymousComponentPath($fixturesDir, 'test');
    app('view.finder')->flush();
});

afterEach(function () {
    File::deleteDirectory(__DIR__ . '/fixtures/views/components/dynamic-test');
    Memo::flushState();
});

test('renders self-closing custom components with static and bound attributes', function () {
    $html = Blade::render('<test:button type="submit" :disabled="true" data-id="123" />');
    
    expect($html)->toContain('<button type="submit" disabled="disabled" data-id="123"></button>');
});

test('renders custom components with default and named slots using custom slot prefix', function () {
    $html = Blade::render(<<<'BLADE'
        <test:card class="shadow-sm">
            <test-slot:header>
                <h2>Card Title</h2>
            </test-slot:header>
            <p>Main content.</p>
        </test:card>
        BLADE
    );

    expect($html)
        ->toContain('<h2>Card Title</h2>')
        ->toContain('<p>Main content.</p>')
        ->toContain('class="shadow-sm"');
});

test('memoizes custom components seamlessly', function () {
    $html = Blade::render(<<<'BLADE'
        <test:avatar src="/img/user1.jpg" />
        <test:avatar src="/img/user1.jpg" />
        BLADE
    );
    
    expect($html)->toContain('<img src="/img/user1.jpg" alt="Avatar">');

    $reflection = new ReflectionClass(\Livewire\Blaze\Memoizer\Memo::class);
    $property = $reflection->getProperty('memo');
    $property->setAccessible(true);
    $memoCache = $property->getValue();

    expect($memoCache)->not->toBeEmpty();
    
    $memoKey = array_keys($memoCache)[0];
    expect($memoKey)->toContain('blaze_memoized_test::avatar:');
});