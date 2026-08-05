# Pest-POM

A [Pest](https://pestphp.com) plugin for writing browser tests using the [Page Object Model pattern](https://playwright.dev/docs/pom). Integrates with [pest-plugin-browser](https://github.com/pestphp/pest-plugin-browser).

## Requirements

- PHP ^8.3 (^8.4 if using Pest 5)
- Pest ^4.0 or ^5.0
- pest-plugin-browser ^4.0 or ^5.0

## Installation

```bash
composer require thelemon2020/pest-plugin-pom --dev
```

### Config

```bash
php artisan vendor:publish --tag=pest-plugin-pom-config
```

Creates `config/pest-plugin-pom.php`:

```php
return [
    'path' => 'tests/Browser/Pages',
];
```

---

## Page Objects

Extend `Page`, define a `url()`, and add interaction methods:

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
            ->fillForm(['Email' => $email, 'Password' => $password])
            ->submitForm('Log in');
    }
}
```

Navigate to a page with `page()` or `::open()`:

```php
$page = page(LoginPage::class);
// or
$page = LoginPage::open();
```

### Parameterized URLs

```php
class ProductPage extends Page
{
    public static function url(): string
    {
        return '/products/{id}';
    }
}

ProductPage::open(['id' => 42]);
$page->navigateTo(ProductPage::class, ['id' => 42]);
```

### Navigating Between Pages

`navigateTo()` sends the browser to a page's URL. `nowOn()` re-wraps the current session as a different page type without reloading — useful after a server-side redirect. `nowOn()` also verifies the current URL matches the destination.

```php
// explicit navigation
$page->navigateTo(DashboardPage::class);

// after a redirect
$page->submitForm('Log in')->nowOn(DashboardPage::class);
```

| | `navigateTo()` | `nowOn()` |
|---|---|---|
| Navigates the browser | Yes | No |
| Verifies current URL | No | Yes |
| Accepts `{param}` values | Yes | Pattern-matched |

---

## Tests

Tests using Page Objects live in `tests/Browser/` — Pest's browser plugin handles the rest.

```php
it('allows a user to log in', function () {
    page(LoginPage::class)
        ->loginAs('jane@example.com', 'password')
        ->assertSee('Dashboard');
});
```

---

## Components

Components encapsulate a reusable piece of UI. They work like pages but have no URL and are always obtained through a page instance.

```bash
php artisan pest:component SearchBar
```

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
        return $this
            ->fill('input[name=query]', $query)
            ->click('button[type=submit]');
    }
}
```

```php
page(DashboardPage::class)
    ->component(SearchBarComponent::class)
    ->search('pest php')
    ->assertSee('pest-plugin-browser');
```

Selectors passed to interaction methods are scoped to the component's root element automatically.

#### Selector types

`selector()` supports all Pest selector forms:

| Form | Example | Matches |
|---|---|---|
| CSS | `'nav'`, `'.card'`, `'#main'`, `'my-element'` | CSS selector |
| `@` data-test | `'@search-panel'` | `data-test` or `data-testid` attribute |
| Text content | `'Featured Items'` | Element with that visible text |

> **Tip:** If a text content selector isn't behaving as expected, use Playwright's explicit `text=` prefix — e.g. `'text=submit'`.

### Typed Accessors

```php
class DashboardPage extends Page
{
    public function searchBar(): SearchBarComponent
    {
        return $this->component(SearchBarComponent::class);
    }
}
```

### Multiple Instances

Use `components()->item(n)` (1-based) to target a specific occurrence:

```php
$page->components(CardComponent::class)->assertTotal(3);
$page->components(CardComponent::class)->item(2)->assertTitle('Advanced Usage');
```

### Sub-Components

Call `component()` on a component to create a child scoped within the parent:

```php
// resolves to: #nav .user-menu
$page->header()->userMenu()->assertSee('Jane Doe');
```

### Scoped Assertions

| Method | Description |
|---|---|
| `assertSee(string $text)` | Text appears within the component |
| `assertDontSee(string $text)` | Text does not appear within the component |
| `assertVisible()` | Root element is visible |
| `assertPresent()` | Root element is in the DOM |
| `assertMissing()` | Root element is absent from the DOM |
| `assertCount(string $selector, int $expected)` | Count of child elements matching selector |
| `assertSeeIn(string $selector, string $text)` | Text appears within a child element |
| `assertDontSeeIn(string $selector, string $text)` | Text does not appear within a child element |
| `assertTotal(int $expected)` | Number of elements matching this component's selector |

### Scoped Interactions

| Method | Description |
|---|---|
| `click(string $selector)` | Click an element |
| `rightClick(string $selector)` | Right-click an element |
| `type(string $field, string $value)` | Type into a field |
| `typeSlowly(string $field, string $value, int $delay = 100)` | Type slowly into a field |
| `fill(string $field, string $value)` | Fill a field |
| `append(string $field, string $value)` | Append text to a field |
| `clear(string $field)` | Clear a field |
| `hover(string $selector)` | Hover over an element |
| `select(string $field, array\|string\|int $option)` | Select a dropdown option |
| `radio(string $field, string $value)` | Select a radio button |
| `check(string $field, ?string $value = null)` | Check a checkbox |
| `uncheck(string $field, ?string $value = null)` | Uncheck a checkbox |
| `attach(string $field, string $path)` | Attach a file |
| `keys(string $selector, array\|string $keys)` | Send keyboard input |
| `drag(string $from, string $to)` | Drag one element to another |
| `text(string $selector): ?string` | Return element text content |
| `attribute(string $selector, string $attribute): ?string` | Return element attribute value |

`press()`, `pressAndWaitFor()`, and `withKeyDown()` are not scoped — they pass through via `__call`.

---

## Generators

```bash
php artisan pest:page Login
php artisan pest:page Register --concerns=forms,alerts

php artisan pest:component SearchBar
php artisan pest:component DataTable --concerns=navigation,modals
```

Available concerns: `forms`, `alerts`, `modals`, `navigation`.

The `Page`/`Component` suffix is optional and won't be doubled.

---

## Concerns

### `InteractsWithForms`

| Method | Description |
|---|---|
| `fillForm(array $fields)` | Fill multiple fields, keyed by label |
| `submitForm(string $button = 'Submit')` | Click a submit button by label |
| `checkBox(string $label)` | Check a checkbox by label |
| `choose(string $field, array\|string\|int $option)` | Select a dropdown option by label |

### `InteractsWithAlerts`

| Method | Description |
|---|---|
| `assertSuccessMessage(string $message)` | Assert a success alert is visible |
| `assertErrorMessage(string $message)` | Assert an error alert is visible |
| `assertFieldError(string $field, string $message)` | Assert a validation error for a field |

### `InteractsWithModals`

| Method | Description |
|---|---|
| `openModal(string $trigger)` | Click the element that opens the modal |
| `confirmModal(string $button = 'Confirm')` | Click the confirm button |
| `dismissModal(string $button = 'Cancel')` | Click the cancel button |
| `closeModal(string $button = 'Close')` | Close without confirming |

### `InteractsWithNavigation`

| Method | Description |
|---|---|
| `clickLink(string $label)` | Click a link by visible text |
| `goBack()` | Navigate back |
| `goForward()` | Navigate forward |
| `refresh()` | Reload the page |

---

## Expectations

```php
expect($page)->toBeOnPage(DashboardPage::class);
expect($page)->toSee('Welcome back');
```

---

## License

MIT
