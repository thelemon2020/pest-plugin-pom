<?php

declare(strict_types=1);

namespace Thelemon2020\PestPom;

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

/**
 * Base class for reusable UI component objects.
 *
 * A Component models a named region of the page (nav bar, data table, modal, etc.)
 * that appears across multiple pages. It holds a reference to the shared browser
 * session and delegates method calls to it, exactly like Page does.
 *
 * When selector() returns a non-empty string, the scoped assertion methods
 * (assertSee, assertVisible, etc.) automatically scope their checks to within
 * that selector. Sub-components composed via component() inherit and extend
 * the parent's resolved selector.
 */
abstract class Component
{
    protected PendingAwaitablePage|AwaitableWebpage $browser;

    private string $resolvedSelector;

    private string $parentSelector;

    final public function __construct(PendingAwaitablePage|AwaitableWebpage $browser, string $parentSelector = '', string $selectorSuffix = '')
    {
        $this->browser = $browser;
        $this->parentSelector = $parentSelector;

        $own = static::selector();

        if ($own === '') {
            $this->resolvedSelector = '';
        } elseif ($parentSelector === '') {
            $this->resolvedSelector = $own . $selectorSuffix;
        } else {
            $this->resolvedSelector = $parentSelector . ' ' . $own . $selectorSuffix;
        }
    }

    /**
     * The CSS selector that identifies this component's root element on the page.
     * Override in subclasses to enable scoped assertions and sub-component composition.
     */
    public static function selector(): string
    {
        return '';
    }

    /**
     * The fully-resolved CSS selector for this component, including any parent
     * component selector prefix. Empty string when no selector is defined.
     *
     * Intended for use inside component subclass methods, not in test code.
     */
    protected function resolvedSelector(): string
    {
        return $this->resolvedSelector;
    }

    /**
     * Create a typed sub-Component instance backed by this component's browser session.
     * The sub-component's selector is scoped within this component's resolved selector.
     *
     * @param  class-string<Component>  $componentClass
     */
    public function component(string $componentClass): Component
    {
        return new $componentClass($this->browser, $this->resolvedSelector);
    }

    /**
     * Create a sub-Component instance for use when multiple instances of the same
     * component exist on the page. Chain ->item(n) to target a specific occurrence.
     *
     * @param  class-string<Component>  $componentClass
     */
    public function components(string $componentClass): Component
    {
        return $this->component($componentClass);
    }

    /**
     * Return a new instance of this component scoped to its nth occurrence (1-based)
     * via :nth-of-type(). Intended to be chained from components().
     */
    public function item(int $index): static
    {
        return new static($this->browser, $this->parentSelector, ':nth-of-type(' . $index . ')');
    }

    /**
     * Assert that the number of elements matching this component's selector equals $expected.
     * Intended to be chained from components() to verify how many instances exist on the page.
     */
    public function assertTotal(int $expected): static
    {
        $this->requireSelector();

        return $this->callBrowser('assertCount', $this->toCssLocator($this->resolvedSelector), $expected);
    }

    /**
     * Assert that the given text appears somewhere within this component.
     */
    public function assertSee(string|int|float $text): static
    {
        $this->requireSelector();

        return $this->callBrowser('assertSeeIn', $this->toCssLocator($this->resolvedSelector), $text);
    }

    /**
     * Assert that the given text does not appear within this component.
     */
    public function assertDontSee(string|int|float $text): static
    {
        $this->requireSelector();

        return $this->callBrowser('assertDontSeeIn', $this->toCssLocator($this->resolvedSelector), $text);
    }

    /**
     * Assert that this component's root element is visible on the page.
     */
    public function assertVisible(): static
    {
        $this->requireSelector();

        return $this->callBrowser('assertVisible', $this->toCssLocator($this->resolvedSelector));
    }

    /**
     * Assert that this component's root element is present in the DOM.
     */
    public function assertPresent(): static
    {
        $this->requireSelector();

        return $this->callBrowser('assertPresent', $this->toCssLocator($this->resolvedSelector));
    }

    /**
     * Assert that this component's root element is absent from the DOM.
     */
    public function assertMissing(): static
    {
        $this->requireSelector();

        return $this->callBrowser('assertMissing', $this->toCssLocator($this->resolvedSelector));
    }

    /**
     * Assert that the given number of elements matching $childSelector exist within this component.
     */
    public function assertCount(string $childSelector, int $expected): static
    {
        $this->requireSelector();

        return $this->callBrowser('assertCount', $this->toCssLocator($this->resolvedSelector . ' ' . $childSelector), $expected);
    }

    /**
     * Assert that the given text appears within a child element inside this component.
     */
    public function assertSeeIn(string $childSelector, string|int|float $text): static
    {
        $this->requireSelector();

        return $this->callBrowser('assertSeeIn', $this->toCssLocator($this->resolvedSelector . ' ' . $childSelector), $text);
    }

    /**
     * Assert that the given text does not appear within a child element inside this component.
     */
    public function assertDontSeeIn(string $childSelector, string|int|float $text): static
    {
        $this->requireSelector();

        return $this->callBrowser('assertDontSeeIn', $this->toCssLocator($this->resolvedSelector . ' ' . $childSelector), $text);
    }

    public function click(string $selector): static
    {
        return $this->callBrowser('click', $this->scope($selector));
    }

    public function rightClick(string $selector): static
    {
        return $this->callBrowser('rightClick', $this->scope($selector));
    }

    public function type(string $field, string $value): static
    {
        return $this->callBrowser('type', $this->scope($field), $value);
    }

    public function typeSlowly(string $field, string $value, int $delay = 100): static
    {
        return $this->callBrowser('typeSlowly', $this->scope($field), $value, $delay);
    }

    public function fill(string $field, string $value): static
    {
        return $this->callBrowser('fill', $this->scope($field), $value);
    }

    public function append(string $field, string $value): static
    {
        return $this->callBrowser('append', $this->scope($field), $value);
    }

    public function clear(string $field): static
    {
        return $this->callBrowser('clear', $this->scope($field));
    }

    public function hover(string $selector): static
    {
        return $this->callBrowser('hover', $this->scope($selector));
    }

    public function select(string $field, array|string|int $option): static
    {
        return $this->callBrowser('select', $this->scope($field), $option);
    }

    public function radio(string $field, string $value): static
    {
        return $this->callBrowser('radio', $this->scope($field), $value);
    }

    public function check(string $field, ?string $value = null): static
    {
        return $this->callBrowser('check', $this->scope($field), $value);
    }

    public function uncheck(string $field, ?string $value = null): static
    {
        return $this->callBrowser('uncheck', $this->scope($field), $value);
    }

    public function attach(string $field, string $path): static
    {
        return $this->callBrowser('attach', $this->scope($field), $path);
    }

    public function keys(string $selector, array|string $keys): static
    {
        return $this->callBrowser('keys', $this->scope($selector), $keys);
    }

    public function drag(string $from, string $to): static
    {
        return $this->callBrowser('drag', $this->scope($from), $this->scope($to));
    }

    public function text(string $selector): ?string
    {
        return $this->browser->text($this->scope($selector));
    }

    public function attribute(string $selector, string $attribute): ?string
    {
        return $this->browser->attribute($this->scope($selector), $attribute);
    }

    private function scope(string $childSelector): string
    {
        return $this->resolvedSelector !== ''
            ? $this->resolvedSelector . ' ' . $childSelector
            : $childSelector;
    }

    private function requireSelector(): void
    {
        if ($this->resolvedSelector === '') {
            throw new \LogicException(
                'Cannot call a scoped assertion on ' . static::class . ' because selector() returns an empty string.'
            );
        }
    }

    /**
     * Ensures a CSS selector is treated as explicit by pest-plugin-browser's GuessLocator.
     * Plain element-type selectors (e.g. "nav", "nav a") are not recognized as CSS
     * and fall back to text-content matching. Appending ":is(*)" makes them explicit
     * (the colon triggers the CSS path) while preserving the same element match.
     */
    private function toCssLocator(string $selector): string
    {
        foreach (['#', '.', '[', 'internal:'] as $prefix) {
            if (str_starts_with($selector, $prefix)) {
                return $selector;
            }
        }

        foreach (['[', ']', '#', '>', '+', '~', ':', '*', '|', '^', ',', '=', '(', ')'] as $char) {
            if (str_contains($selector, $char)) {
                return $selector;
            }
        }

        return $selector . ':is(*)';
    }

    /**
     * Calls a method on the browser and keeps the internal AwaitableWebpage reference
     * up to date, since it is a readonly value object that is re-created on each operation.
     */
    protected function callBrowser(string $method, mixed ...$args): static
    {
        $result = $this->browser->$method(...$args);

        if ($result instanceof AwaitableWebpage) {
            $this->browser = $result;
        }

        return $this;
    }

    /**
     * Delegates all calls to the underlying Pest browser page.
     *
     * @param  array<int, mixed>  $args
     */
    public function __call(string $method, array $args): mixed
    {
        $result = $this->browser->$method(...$args);

        if ($result instanceof AwaitableWebpage) {
            $this->browser = $result;

            return $this;
        }

        return $result;
    }
}
