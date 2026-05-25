<?php

declare(strict_types=1);

namespace Thelemon2020\PestPom;

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Support\ComputeUrl;

/**
 * Base class for all page objects.
 *
 * Wraps the Pest browser page returned by visit() and delegates method calls
 * to it, keeping fluent chaining typed to the concrete page subclass.
 */
abstract class Page
{
    /**
     * The underlying browser page. Starts as PendingAwaitablePage after visit(),
     * then transitions to AwaitableWebpage once the first method is called.
     */
    protected PendingAwaitablePage|AwaitableWebpage $browser;

    final public function __construct(PendingAwaitablePage|AwaitableWebpage $browser)
    {
        $this->browser = $browser;
    }

    /**
     * The URL path this page represents.
     */
    abstract public static function url(): string;

    /**
     * Substitute `{param}` placeholders in the URL template.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function resolveUrl(array $parameters = []): string
    {
        $url = static::url();

        foreach ($parameters as $key => $value) {
            $url = str_replace('{' . $key . '}', (string) $value, $url);
        }

        return $url;
    }

    /**
     * Navigate to this page and return a typed instance.
     *
     * Enforces that the page class lives in the configured pages directory.
     *
     * @param  array<string, mixed>  $parameters  Values for any `{param}` placeholders in `url()`.
     */
    public static function open(array $parameters = []): static
    {
        Config::assertPageIsInConfiguredDirectory(static::class);

        return new static(visit(static::resolveUrl($parameters)));
    }

    /**
     * Create a typed Component instance backed by this page's browser session.
     *
     * @param  class-string<Component>  $componentClass
     * @return Component
     */
    public function component(string $componentClass): Component
    {
        return new $componentClass($this->browser);
    }

    /**
     * Create a Component instance for use when multiple instances of the same
     * component exist on the page. Chain ->item(n) to target a specific occurrence.
     *
     * @param  class-string<Component>  $componentClass
     * @return Component
     */
    public function components(string $componentClass): Component
    {
        return $this->component($componentClass);
    }

    /**
     * Re-wrap the current browser session as a different page type without navigating.
     * Use after an action that causes a server-side redirect (e.g. form submit).
     * Throws if the browser's current URL path does not match the page's URL.
     **
     * @param  class-string<Page>  $pageClass
     * @return Page
     */
    public function nowOn(string $pageClass): Page
    {
        $currentUrl = $this->currentBrowserUrl();

        if ($currentUrl !== null) {
            $currentPath = rtrim((string) parse_url($currentUrl, PHP_URL_PATH), '/');
            $expectedPath = rtrim((string) parse_url($pageClass::url(), PHP_URL_PATH), '/');

            if (! $this->pathMatchesPattern($currentPath, $expectedPath)) {
                throw new \RuntimeException(
                    "Expected to be on [{$expectedPath}] but the browser is at [{$currentPath}]."
                );
            }
        }

        return new $pageClass($this->browser);
    }

    /**
     * Returns the browser's current URL, or null when the browser has not yet been resolved.
     * Extracted so tests can override it without needing a live Playwright connection.
     */
    protected function currentBrowserUrl(): ?string
    {
        if (! $this->browser instanceof AwaitableWebpage) {
            return null;
        }

        return $this->browser->page()->url();
    }

    /**
     * Explicitly navigate to a different page class.
     * Use when an action (e.g. submitting a form) takes you to a new screen.
     **
     * @param  class-string<Page>  $pageClass
     * @param  array<string, mixed>  $parameters  Values for any `{param}` placeholders in the target page's `url()`.
     * @return Page
     */
    public function navigateTo(string $pageClass, array $parameters = []): Page
    {
        $resolvedUrl = $pageClass::resolveUrl($parameters);

        // When the browser is already resolved, reuse the existing Playwright page
        // (and its context) so cookies/session/auth state are preserved.
        // Calling visit() would create a new browser context (fresh incognito), losing the session.
        if ($this->browser instanceof AwaitableWebpage) {
            $url = ComputeUrl::from($resolvedUrl);
            $this->browser->page()->goto($url);

            return new $pageClass(new AwaitableWebpage($this->browser->page(), $url));
        }

        return new $pageClass($this->createVisit($resolvedUrl));
    }

    /**
     * Returns true when $currentPath matches the URL pattern, treating
     * `{param}` segments as wildcards that match any single path segment.
     */
    private function pathMatchesPattern(string $currentPath, string $patternPath): bool
    {
        if (! str_contains($patternPath, '{')) {
            return $currentPath === $patternPath;
        }

        $segments = (array) preg_split('/(\{[^}]+\})/', $patternPath, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';

        foreach ($segments as $segment) {
            $regex .= preg_match('/^\{[^}]+\}$/', $segment)
                ? '[^/]+'
                : preg_quote($segment, '#');
        }

        return (bool) preg_match('#^' . $regex . '$#', $currentPath);
    }

    /**
     * Performs a fresh browser visit. Extracted so tests can override it
     * without starting a real Playwright connection.
     */
    protected function createVisit(string $url): PendingAwaitablePage
    {
        return visit($url);
    }

    /**
     * Delegates all calls to the underlying Pest browser page.
     *
     * When the browser returns itself (chainable methods like click, type,
     * assertSee, etc.), we update our internal reference and return $this so
     * the chain stays typed to the concrete Page subclass.
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