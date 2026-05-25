# Pest Plugin POM

A [Pest](https://pestphp.com) plugin for writing expressive browser tests using the **Page Object Model** pattern.

Page Objects keep your browser tests readable and maintainable by encapsulating page-specific selectors and interactions into dedicated classes. The plugin integrates with [pest-plugin-browser](https://github.com/pestphp/pest-plugin-browser) and automatically starts the Playwright server for any test that uses a Page Object — no manual setup required.

> **Early Release:** This plugin is in early development (pre-v1). Bugs are expected — if you encounter one, please [open an issue](https://github.com/thelemon2020/pest-plugin-pom/issues). Feedback and suggestions are very welcome.

---

## Requirements

- PHP ^8.3
- Pest ^4.0
- pest-plugin-browser ^4.0

## Installation

```bash
composer require thelemon2020/pest-plugin-pom --dev
```

### Publishing the config (Laravel only)

```bash
php artisan vendor:publish --tag=pest-plugin-pom-config
```

This creates `config/pest-plugin-pom.php`:

```php
return [
    'path' => 'tests/Browser/Pages',
];
```

`path` controls where the `pest:page` generator writes new files and where `Page::open()` expects page classes to live at runtime. Change it if your project uses a different directory.

---

## Core Concepts

### Page Objects

Each page in your application is represented by a class that extends `Page`. The class defines the URL for the page and exposes methods that describe meaningful user interactions — clicking a button, filling a form, asserting content — in the language of your application rather than raw browser commands.

```php
namespace Tests\Browser\Pages;

use Thelemon2020\PestPom\Page;
use Thelemon2020\PestPom\Concerns\InteractsWithForms;

class LoginPage extends Page
{
    use InteractsWithForms;

    public static function url(): string
    {
        return '/login';
    }

    public function loginAs(string $email, string $password): static
    {
        return $this
            ->fillForm([
                'Email' => $email,
                'Password' => $password,
            ])
            ->submitForm('Log in');
    }
}
```

### Navigating to a Page

Use the `page()` helper or the static `::open()` method to navigate to a page and receive a typed instance:

```php
$page = page(LoginPage::class);

// or equivalently
$page = LoginPage::open();
```

Both return an instance of `LoginPage`, giving you full IDE autocompletion for any methods you have defined on that class.

### Parameterized URLs

For pages whose URL includes a dynamic segment — a product ID, a user slug, a post number — use `{param}` placeholders in `url()` and pass the values when navigating:

```php
class ProductPage extends Page
{
    public static function url(): string
    {
        return '/products/{id}';
    }
}
```

```php
// Navigate directly
page(ProductPage::class, ['id' => 42]);

// or equivalently
ProductPage::open(['id' => 42]);

// Navigate from another page
$page->navigateTo(ProductPage::class, ['id' => 42]);
```

Multiple placeholders work the same way:

```php
class PostPage extends Page
{
    public static function url(): string
    {
        return '/users/{userId}/posts/{postId}';
    }
}

PostPage::open(['userId' => 3, 'postId' => 99]);
```

When using `nowOn()` after a server-side redirect to a parameterized page, you do not need to supply the values — the URL is matched as a pattern, so `/products/42` and `/products/99` both satisfy `nowOn(ProductPage::class)`:

```php
$page->submitPurchase()->nowOn(ProductPage::class);
```

### Fluent Chaining

Every method on a Page Object returns `static`, so you can chain interactions naturally:

```php
page(LoginPage::class)
    ->loginAs('jane@example.com', 'password')
    ->assertSee('Welcome back, Jane');
```

---

## Writing Tests

Tests that use Page Objects must live in `tests/Browser/`. Pest's browser plugin picks up tests from that directory and starts the Playwright server automatically — no additional annotation is needed.

```php
// tests/Browser/AuthTest.php
it('allows a user to log in', function () {
    page(LoginPage::class)
        ->loginAs('jane@example.com', 'password')
        ->assertSee('Dashboard');
});
```

---

## Authentication

Many pages require a logged-in user. Use Laravel's `actingAs()` helper before navigating to the page — Pest Browser carries the authenticated session into the browser context automatically.

```php
it('shows the dashboard for an authenticated user', function () {
    $user = User::factory()->create();

    actingAs($user);

    DashboardPage::open()
        ->assertSee("Welcome, {$user->name}");
});

it('shows the correct profile for a parameterized page', function () {
    $user = User::factory()->create();

    actingAs($user);

    ProfilePage::open(['id' => $user->id])
        ->assertSee($user->email);
});
```

---

## Creating Page Objects

### Artisan Generator (Laravel only)

The quickest way to create a page is with the included Artisan command:

```bash
php artisan pest:page Login
```

This creates `tests/Browser/Pages/LoginPage.php` with a basic scaffold. The `Page` suffix is optional — it won't be doubled if you include it.

Pass `--concerns` to include traits in the generated class:

```bash
php artisan pest:page Register --concerns=forms,alerts
php artisan pest:page UserSettings --concerns=forms,alerts,modals,navigation
```

Available concern names: `forms`, `alerts`, `modals`, `navigation`.

The generated file for `pest:page Register --concerns=forms,alerts` looks like:

```php
<?php

declare(strict_types=1);

namespace Tests\Browser\Pages;

use Thelemon2020\PestPom\Page;
use Thelemon2020\PestPom\Concerns\InteractsWithForms;
use Thelemon2020\PestPom\Concerns\InteractsWithAlerts;

class RegisterPage extends Page
{
    use InteractsWithForms;
    use InteractsWithAlerts;

    public static function url(): string
    {
        return '/';
    }
}
```

The namespace is inferred automatically from your project's `composer.json` `autoload-dev` PSR-4 map, falling back to `Tests\Browser\Pages`.

### Manually

Create a class for each page (or distinct section of a page) in your application, extending the abstract `Page` base class.

```php
namespace Tests\Browser\Pages;

use Thelemon2020\PestPom\Page;

class DashboardPage extends Page
{
    public static function url(): string
    {
        return '/dashboard';
    }

    public function assertWelcomeMessage(string $name): static
    {
        return $this->assertSee("Welcome, {$name}");
    }
}
```

### Navigating Between Pages

There are two ways to move from one page object to another, depending on whether you want the browser to navigate or whether it has already arrived.

#### `navigateTo()`

Explicitly navigates the browser to the destination page's URL and returns a typed instance. Use this when you want to send the browser somewhere directly — the session and authentication cookies are preserved across the navigation.

```php
it('redirects to the dashboard after login', function () {
    $dashboard = page(LoginPage::class)
        ->loginAs('jane@example.com', 'password')
        ->navigateTo(DashboardPage::class);

    $dashboard->assertWelcomeMessage('Jane');
});
```

Pass a parameters array as the second argument for pages with `{param}` placeholders in their URL:

```php
$page->navigateTo(ProductPage::class, ['id' => 42]);
```

#### `nowOn()`

Re-wraps the current browser session as a different page type **without reloading the page**. Use this after an action (like submitting a form) that causes a server-side redirect — the browser has already landed on the new page, so there's no need to navigate again.

`nowOn()` verifies that the browser's current URL matches the destination page's URL and throws an exception if it doesn't, catching unexpected redirects (e.g. an auth failure that sends the user back to `/login`) immediately. For pages with `{param}` placeholders in their URL, the check is pattern-based — any value in that segment is accepted, so you don't need to know the exact ID the server redirected to.

```php
it('redirects to the dashboard after login', function () {
    $dashboard = page(LoginPage::class)
        ->loginAs('jane@example.com', 'password')
        ->nowOn(DashboardPage::class);  // no reload — already here after the POST redirect

    $dashboard->assertWelcomeMessage('Jane');
});
```

| | `navigateTo()` | `nowOn()` |
|---|---|---|
| Navigates the browser | Yes | No |
| Preserves session | Yes | Yes |
| Verifies current URL | No | Yes (exact or pattern) |
| Accepts `{param}` values | Yes | Pattern-matched automatically |
| Use when | You want to send the browser somewhere | The browser already arrived via redirect |

---

## Components

Components let you encapsulate a reusable piece of UI — a navigation bar, a data table, a search widget — into its own class, separate from any particular page. A `Component` works exactly like a `Page`: it exposes methods that describe meaningful interactions and returns `static` for fluent chaining. The difference is that a component has no URL and is always obtained through a page, sharing the same browser session.

### Creating Components (Laravel only)

Use the Artisan generator to scaffold a component:

```bash
php artisan pest:component SearchBar
```

This creates `tests/Browser/Components/SearchBarComponent.php`. The `Component` suffix is optional and won't be doubled.

Pass `--concerns` to include traits, just like with pages:

```bash
php artisan pest:component SearchBar --concerns=forms
php artisan pest:component DataTable --concerns=navigation,modals
```

The generated file looks like:

```php
<?php

declare(strict_types=1);

namespace Tests\Browser\Components;

use Thelemon2020\PestPom\Component;

class SearchBarComponent extends Component
{
    public static function selector(): string
    {
        return '';
    }
}
```

Fill in `selector()` with the CSS selector that identifies the component's root element, then add your interaction methods.

### Using Components

Call `component()` on any page instance, passing the component class name:

```php
$search = page(DashboardPage::class)->component(SearchBarComponent::class);
```

This returns a `SearchBarComponent` instance backed by the same browser session as the page. From there, call any methods you have defined on the component:

```php
page(DashboardPage::class)
    ->component(SearchBarComponent::class)
    ->search('pest php')
    ->assertSee('pest-plugin-browser');
```

### Example Component

```php
namespace Tests\Browser\Components;

use Thelemon2020\PestPom\Component;

class SearchBarComponent extends Component
{
    public static function selector(): string
    {
        return '#search-bar';
    }

    public function search(string $query): static
    {
        // fill() and click() are scoped to #search-bar automatically
        return $this
            ->fill('input[name=query]', $query)
            ->click('button[type=submit]');
    }
}
```

```php
it('returns results for a valid search', function () {
    $page = DashboardPage::open();

    $page->component(SearchBarComponent::class)
        ->search('pest php')
        ->assertSee('pest-plugin-browser');  // scoped to #search-bar
});
```

Components support all four concern traits (`InteractsWithForms`, `InteractsWithAlerts`, `InteractsWithModals`, `InteractsWithNavigation`) in exactly the same way as pages.

### Scoped Assertions

When `selector()` is defined, the component provides assertion methods that automatically scope their check to within the component's root element:

| Method | Description |
|---|---|
| `assertSee(string $text)` | Assert the text appears within the component |
| `assertDontSee(string $text)` | Assert the text does not appear within the component |
| `assertVisible()` | Assert the component's root element is visible |
| `assertPresent()` | Assert the component's root element is in the DOM |
| `assertMissing()` | Assert the component's root element is absent from the DOM |
| `assertCount(string $childSelector, int $expected)` | Assert the count of elements matching `$childSelector` within the component |
| `assertSeeIn(string $childSelector, string $text)` | Assert text appears within a child element inside the component |
| `assertDontSeeIn(string $childSelector, string $text)` | Assert text does not appear within a child element inside the component |

All of these throw a `LogicException` if `selector()` returns an empty string.

### Scoped Interactions

The same scoping applies to interactions. The selectors you pass are treated as relative to the component's root element — the full composed selector is constructed for you:

| Method | Description |
|---|---|
| `click(string $selector)` | Click an element within the component |
| `rightClick(string $selector)` | Right-click an element within the component |
| `type(string $field, string $value)` | Type into a field within the component |
| `typeSlowly(string $field, string $value, int $delay = 100)` | Type slowly into a field within the component |
| `fill(string $field, string $value)` | Fill a field within the component |
| `append(string $field, string $value)` | Append text to a field within the component |
| `clear(string $field)` | Clear a field within the component |
| `hover(string $selector)` | Hover over an element within the component |
| `select(string $field, array\|string\|int $option)` | Select a dropdown option within the component |
| `radio(string $field, string $value)` | Select a radio button within the component |
| `check(string $field, ?string $value = null)` | Check a checkbox within the component |
| `uncheck(string $field, ?string $value = null)` | Uncheck a checkbox within the component |
| `attach(string $field, string $path)` | Attach a file to an input within the component |
| `keys(string $selector, array\|string $keys)` | Send keyboard input to an element within the component |
| `drag(string $from, string $to)` | Drag one element to another, both scoped within the component |
| `text(string $selector): ?string` | Return the text content of an element within the component |
| `attribute(string $selector, string $attribute): ?string` | Return an attribute value of an element within the component |

If `selector()` is empty, selectors are passed through unchanged — no scoping is applied and there is no error.

The methods `press()`, `pressAndWaitFor()`, and `withKeyDown()` do not take CSS selectors and are not scoped — they work as normal via `__call` delegation.

The four concern traits (`InteractsWithForms`, `InteractsWithAlerts`, `InteractsWithModals`, `InteractsWithNavigation`) call these interaction methods internally, so they also benefit from scoping automatically when used in a component with a defined selector.

### Typed Component Accessors

For cleaner test syntax and IDE autocompletion, define typed methods on your page class that return component instances:

```php
class DashboardPage extends Page
{
    public static function url(): string
    {
        return '/dashboard';
    }

    public function header(): NavBarComponent
    {
        return $this->component(NavBarComponent::class);
    }

    public function footer(): FooterComponent
    {
        return $this->component(FooterComponent::class);
    }
}
```

### Asserting Multiple Components

`component()` returns a new component instance each time without mutating the page. To assert on multiple components in a single test, assign the page to a variable first and call component accessors on it as many times as you need:

```php
it('shows the correct layout for a logged-in user', function () {
    $page = DashboardPage::open();

    $page->header()
        ->assertVisible()
        ->assertSee('Dashboard');

    $page->footer()
        ->assertSee('Privacy Policy')
        ->assertSee('Terms of Service');
});
```

`$page` is never mutated by calling `header()` or `footer()`, so it remains valid throughout the test.

### Sub-Component Composition

Components can contain other components. Call `component()` on a component instance to create a child component whose selector is automatically scoped within the parent's:

```php
class NavBarComponent extends Component
{
    public static function selector(): string
    {
        return '#nav';
    }

    public function userMenu(): UserMenuComponent
    {
        return $this->component(UserMenuComponent::class);
    }
}

class UserMenuComponent extends Component
{
    // resolved selector will be: #nav .user-menu
    public static function selector(): string
    {
        return '.user-menu';
    }
}
```

```php
it('displays the logged-in user in the nav', function () {
    $page = DashboardPage::open();

    $page->header()
        ->userMenu()
        ->assertSee('Jane Doe');
});
```

The resolved selector is composed automatically — `UserMenuComponent` inherits `#nav` from `NavBarComponent` and appends its own `.user-menu`, producing `#nav .user-menu`.

---

## Available Concerns (Traits)

The plugin ships with four traits that cover common browser interactions. Include only the ones each Page Object needs.

### `InteractsWithForms`

```php
use Thelemon2020\PestPom\Concerns\InteractsWithForms;

class RegistrationPage extends Page
{
    use InteractsWithForms;

    public static function url(): string
    {
        return '/register';
    }
}
```

| Method | Description |
|--------|-------------|
| `fillForm(array $fields)` | Fill multiple fields at once, keyed by label |
| `submitForm(string $button = 'Submit')` | Click a submit button by its label |
| `checkBox(string $label)` | Check a checkbox by label |
| `choose(string $field, array\|string\|int $option)` | Select a dropdown option by label |

```php
page(RegistrationPage::class)
    ->fillForm([
        'Name'     => 'Jane Doe',
        'Email'    => 'jane@example.com',
        'Password' => 'super-secret',
    ])
    ->checkBox('I agree to the terms and conditions')
    ->choose('Country', 'United States')
    ->submitForm('Create account');
```

---

### `InteractsWithAlerts`

```php
use Thelemon2020\PestPom\Concerns\InteractsWithAlerts;

class ProfilePage extends Page
{
    use InteractsWithAlerts;

    public static function url(): string
    {
        return '/profile';
    }
}
```

| Method | Description |
|--------|-------------|
| `assertSuccessMessage(string $message)` | Assert a success alert or flash message is visible |
| `assertErrorMessage(string $message)` | Assert an error alert or flash message is visible |
| `assertFieldError(string $field, string $message)` | Assert a validation error is visible for a specific field |

```php
page(ProfilePage::class)
    ->fillForm(['Name' => ''])
    ->submitForm('Save')
    ->assertFieldError('Name', 'The name field is required.')
    ->assertErrorMessage('Your profile could not be saved.');
```

---

### `InteractsWithModals`

```php
use Thelemon2020\PestPom\Concerns\InteractsWithModals;

class UserListPage extends Page
{
    use InteractsWithModals;

    public static function url(): string
    {
        return '/users';
    }
}
```

| Method | Description |
|--------|-------------|
| `openModal(string $trigger)` | Click the element that opens the modal |
| `confirmModal(string $confirmButton = 'Confirm')` | Click the confirm button inside the modal |
| `dismissModal(string $cancelButton = 'Cancel')` | Click the cancel button inside the modal |
| `closeModal(string $closeButton = 'Close')` | Close the modal without confirming |

```php
page(UserListPage::class)
    ->openModal('Delete Jane Doe')
    ->confirmModal('Yes, delete user')
    ->assertSuccessMessage('User deleted.');
```

---

### `InteractsWithNavigation`

```php
use Thelemon2020\PestPom\Concerns\InteractsWithNavigation;

class ArticlePage extends Page
{
    use InteractsWithNavigation;

    public static function url(): string
    {
        return '/articles';
    }
}
```

| Method | Description |
|--------|-------------|
| `clickLink(string $label)` | Click a navigation link by its visible text |
| `goBack()` | Navigate back in browser history |
| `goForward()` | Navigate forward in browser history |
| `refresh()` | Reload the current page |

```php
page(ArticlePage::class)
    ->clickLink('Getting Started')
    ->assertSee('Introduction')
    ->goBack()
    ->assertSee('All Articles');
```

---

## Custom Expectations

The plugin registers two Pest expectations for Page Objects:

```php
expect($page)->toBeOnPage(DashboardPage::class);
expect($page)->toSee('Welcome back');
```

| Expectation | Description |
|-------------|-------------|
| `toBeOnPage(string $pageClass)` | Asserts the current URL contains the page's defined URL path |
| `toSee(string $text)` | Asserts the given text is visible on the page |

---

## Full Example

Here is a complete end-to-end registration flow demonstrating Page Objects, traits, navigation, and expectations working together.

**Page Objects:**

```php
// tests/Browser/Pages/RegistrationPage.php
namespace Tests\Browser\Pages;

use Thelemon2020\PestPom\Page;
use Thelemon2020\PestPom\Concerns\InteractsWithAlerts;
use Thelemon2020\PestPom\Concerns\InteractsWithForms;

class RegistrationPage extends Page
{
    use InteractsWithForms;
    use InteractsWithAlerts;

    public static function url(): string
    {
        return '/register';
    }

    public function register(string $name, string $email, string $password): static
    {
        return $this
            ->fillForm([
                'Name'     => $name,
                'Email'    => $email,
                'Password' => $password,
            ])
            ->checkBox('I agree to the terms')
            ->submitForm('Create account');
    }
}
```

```php
// tests/Browser/Pages/DashboardPage.php
namespace Tests\Browser\Pages;

use Thelemon2020\PestPom\Page;
use Thelemon2020\PestPom\Concerns\InteractsWithNavigation;

class DashboardPage extends Page
{
    use InteractsWithNavigation;

    public static function url(): string
    {
        return '/dashboard';
    }

    public function assertUserIsLoggedIn(string $name): static
    {
        return $this->assertSee("Welcome, {$name}");
    }
}
```

**Tests:**

```php
// tests/Browser/RegistrationTest.php
use Tests\Browser\Pages\DashboardPage;
use Tests\Browser\Pages\RegistrationPage;

it('allows a new user to register', function () {
    $dashboard = page(RegistrationPage::class)
        ->register('Jane Doe', 'jane@example.com', 'password')
        ->nowOn(DashboardPage::class);  // server redirected here after successful registration

    expect($dashboard)
        ->toBeOnPage(DashboardPage::class)
        ->toSee('Welcome, Jane Doe');
});

it('shows a validation error when the email is taken', function () {
    page(RegistrationPage::class)
        ->register('Jane Doe', 'existing@example.com', 'password')
        ->assertFieldError('Email', 'This email is already in use.')
        ->assertErrorMessage('Registration failed. Please correct the errors below.');
});
```

---

## License

MIT
