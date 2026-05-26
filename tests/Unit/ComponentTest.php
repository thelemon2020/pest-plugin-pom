<?php

declare(strict_types=1);

use Thelemon2020\PestPom\Component;
use Thelemon2020\PestPom\Tests\Fixtures\ExampleComponent;
use Thelemon2020\PestPom\Tests\Fixtures\ExamplePage;

it('returns an instance of the given component class', function () {
    $page = new ExamplePage(pendingBrowser());

    expect($page->component(ExampleComponent::class))->toBeInstanceOf(ExampleComponent::class);
});

it('returns a distinct instance on each call', function () {
    $page = new ExamplePage(pendingBrowser());

    expect($page->component(ExampleComponent::class))
        ->not->toBe($page->component(ExampleComponent::class));
});

it('ExampleComponent selector returns its configured value', function () {
    expect(ExampleComponent::selector())->toBe('#example');
});

it('a component subclass with no selector override returns an empty string', function () {
    $component = new class(pendingBrowser()) extends Component {};

    expect($component::selector())->toBe('');
});

it('assertSee on a component with a selector scopes to that selector', function () {
    $component = new class(pendingBrowser()) extends ExampleComponent {
        public array $calls = [];
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSee('text');

    expect($component->calls)->toBe([['assertSeeIn', '#example', 'text']]);
});

it('scoped assertions throw LogicException when selector is empty', function () {
    $component = new class(pendingBrowser()) extends Component {};

    $component->assertSee('text');
})->throws(\LogicException::class);

it('component() on a Component scopes the child selector under the parent', function () {
    $childInstance = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '#example'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $parent = new class(pendingBrowser()) extends Component {
        public static function selector(): string { return '#nav'; }
    };

    $child = $parent->component($childInstance::class);
    $child->assertSee('text');

    expect($child->calls)->toBe([['assertSeeIn', '#nav #example', 'text']]);
});

it('a child component with no selector throws even when created under a parent', function () {
    $noSelectorInstance = new class(pendingBrowser()) extends Component {};

    $child = (new ExampleComponent(pendingBrowser()))->component($noSelectorInstance::class);

    $child->assertSee('text');
})->throws(\LogicException::class);

it('assertSee delegates to assertSeeIn with the resolved selector', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '#nav'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSee('Hello');

    expect($component->calls)->toBe([['assertSeeIn', '#nav', 'Hello']]);
});

it('click scopes the selector to the component', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '#nav'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->click('.menu-item');

    expect($component->calls)->toBe([['click', '#nav .menu-item']]);
});

it('type scopes the field selector but not the value', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '#search'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->type('input', 'pest php');

    expect($component->calls)->toBe([['type', '#search input', 'pest php']]);
});

it('drag scopes both the from and to selectors', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '#board'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->drag('.card', '.column');

    expect($component->calls)->toBe([['drag', '#board .card', '#board .column']]);
});

it('interactions pass the selector through unchanged when no component selector is set', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->click('.menu-item');

    expect($component->calls)->toBe([['click', '.menu-item']]);
});

it('assertCount composes the child selector under the resolved selector', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '#nav'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertCount('.item', 3);

    expect($component->calls)->toBe([['assertCount', '#nav .item', 3]]);
});

// toCssLocator — plain element-type selectors get :is(*) appended

it('assertSee appends :is(*) to a plain element-type selector', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return 'nav'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSee('text');

    expect($component->calls)->toBe([['assertSeeIn', 'nav:is(*)', 'text']]);
});

it('assertVisible appends :is(*) to a plain element-type selector', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return 'main'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertVisible();

    expect($component->calls)->toBe([['assertVisible', 'main:is(*)']]);
});

it('assertCount appends :is(*) when composing plain element selectors', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return 'nav'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertCount('a', 3);

    expect($component->calls)->toBe([['assertCount', 'nav a:is(*)', 3]]);
});

it('assertSeeIn appends :is(*) when composing plain element selectors', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return 'section'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSeeIn('p', 'hello');

    expect($component->calls)->toBe([['assertSeeIn', 'section p:is(*)', 'hello']]);
});

// toCssLocator — selectors that already look like CSS pass through unchanged

it('assertSee passes an id selector through unchanged', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '#main'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSee('text');

    expect($component->calls)->toBe([['assertSeeIn', '#main', 'text']]);
});

it('assertSee passes a class selector through unchanged', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '.sidebar'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSee('text');

    expect($component->calls)->toBe([['assertSeeIn', '.sidebar', 'text']]);
});

it('assertSee passes an attribute selector through unchanged', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '[data-nav]'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSee('text');

    expect($component->calls)->toBe([['assertSeeIn', '[data-nav]', 'text']]);
});

it('assertSee passes an internal: selector through unchanged', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return 'internal:role=button'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSee('text');

    expect($component->calls)->toBe([['assertSeeIn', 'internal:role=button', 'text']]);
});

it('assertSee passes a selector with a child combinator through unchanged', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return 'nav > ul'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSee('text');

    expect($component->calls)->toBe([['assertSeeIn', 'nav > ul', 'text']]);
});

it('assertTotal asserts the count of elements matching the component selector', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '.card'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertTotal(3);

    expect($component->calls)->toBe([['assertCount', '.card', 3]]);
});

it('assertTotal scopes the count within a parent component selector', function () {
    $card = new class(pendingBrowser(), '#list') extends Component {
        public array $calls = [];
        public static function selector(): string { return '.card'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $card->assertTotal(3);

    expect($card->calls)->toBe([['assertCount', '#list .card', 3]]);
});

it('assertTotal throws LogicException when the component has no selector', function () {
    $component = new class(pendingBrowser()) extends Component {};

    $component->assertTotal(3);
})->throws(\LogicException::class);

it('components()->item() appends :nth-of-type to the selector from a page', function () {
    $page = new ExamplePage(pendingBrowser());

    $card = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '.card'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $instance = $page->components($card::class)->item(2);
    $instance->assertSee('title');

    expect($instance->calls)->toBe([['assertSeeIn', '.card:nth-of-type(2)', 'title']]);
});

it('components()->item() appends :nth-of-type scoped under the parent selector', function () {
    $card = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return '.card'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $parent = new class(pendingBrowser()) extends Component {
        public static function selector(): string { return '#list'; }
    };

    $instance = $parent->components($card::class)->item(3);
    $instance->assertSee('title');

    expect($instance->calls)->toBe([['assertSeeIn', '#list .card:nth-of-type(3)', 'title']]);
});

it('assertSee passes a selector with a pseudo-class through unchanged', function () {
    $component = new class(pendingBrowser()) extends Component {
        public array $calls = [];
        public static function selector(): string { return 'nav:first-child'; }
        protected function callBrowser(string $method, mixed ...$args): static
        {
            $this->calls[] = [$method, ...$args];
            return $this;
        }
    };

    $component->assertSee('text');

    expect($component->calls)->toBe([['assertSeeIn', 'nav:first-child', 'text']]);
});