# Gracious Feature Flag Bundle

Lightweight feature flags for Symfony. Define flags in config and check them from
services, controllers, Twig, routes, and controller attributes. Supports one default
manager plus multiple named managers, and a read-only REST endpoint.

- Symfony `^7.0 || ^8.0`, PHP `>=8.3`.

## Installation

```bash
composer require gracious/feature-flag-bundle
```

If you do not use Symfony Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Gracious\FeatureFlagBundle\GraciousFeatureFlagBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/gracious_feature_flag.yaml
gracious_feature_flag:
    flags:
        new_checkout:
            enabled: true
            description: 'New checkout flow'
        beta_search:
            enabled: false

    # optional: separate groups of flags
    managers:
        billing:
            flags:
                invoices_v2: { enabled: true }

    # optional: customise the exception thrown by guards
    exception:
        class: Gracious\FeatureFlagBundle\Exception\FeatureNotAvailableException
        status_code: 404
        factory: ~   # service id implementing ExceptionFactoryInterface (takes precedence)
```

## Service usage

The default manager is autowired via `FeatureFlagManagerInterface`:

```php
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManagerInterface;

final class CheckoutService
{
    public function __construct(private FeatureFlagManagerInterface $flags) {}

    public function run(): void
    {
        if ($this->flags->isEnabled('new_checkout')) {
            // ...
        }
    }
}
```

Named managers autowire by variable name (`<name>Manager`):

```php
public function __construct(FeatureFlagManagerInterface $billingManager) {}
```

Manager API:

```php
$flags->isEnabled('new_checkout'); // bool
$flags->has('new_checkout');       // bool
$flags->get('new_checkout');       // Flag value object
$flags->all();                     // array<string, Flag>

// runtime overrides (per PHP process; not shared across workers)
$flags->enable('beta_search');
$flags->disable('new_checkout');
$flags->reset('beta_search');      // back to configured value
```

## Twig usage

```twig
{% if feature('new_checkout') %}
    <a href="/checkout/new">Try the new checkout</a>
{% endif %}

{# named manager #}
{% if feature('invoices_v2', 'billing') %} ... {% endif %}

{# as a test #}
{% if 'beta_search' is feature_enabled %} ... {% endif %}
```

## Routing restrictions

Guard a route with the `_feature_flag` default. String form requires the flag enabled:

```yaml
beta_page:
    path: /beta
    controller: App\Controller\BetaController
    defaults:
        _feature_flag: beta_search
```

Array form supports the required state and a named manager:

```yaml
legacy_page:
    path: /legacy
    controller: App\Controller\LegacyController
    defaults:
        _feature_flag: { name: legacy, enabled: false, manager: default }
```

When the requirement is not met, the configured exception is thrown (404 by default).

## Attributes

Use `#[RequireFeature]` on a controller class or method (repeatable):

```php
use Gracious\FeatureFlagBundle\Attribute\RequireFeature;

#[RequireFeature('new_checkout')]                 // require enabled
final class CheckoutController
{
    #[RequireFeature('legacy', enabled: false)]   // require disabled
    public function index(): Response { /* ... */ }

    #[RequireFeature('invoices_v2', manager: 'billing')]
    public function invoices(): Response { /* ... */ }
}
```

## Custom exception

Provide a factory service for full control:

```php
use Gracious\FeatureFlagBundle\Exception\ExceptionFactoryInterface;
use Gracious\FeatureFlagBundle\Flag\Flag;

final class RedirectExceptionFactory implements ExceptionFactoryInterface
{
    public function create(Flag $flag, bool $required): \Throwable
    {
        return new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
            sprintf('Feature "%s" gate failed.', $flag->name),
        );
    }
}
```

```yaml
gracious_feature_flag:
    exception:
        factory: App\FeatureFlag\RedirectExceptionFactory
```

Or set `exception.class` to any class with a `(string $name, int $statusCode)` constructor.

## API endpoint

The REST endpoint is **opt-in**. Import the routes in your app:

```yaml
# config/routes/gracious_feature_flag.yaml
feature_flags:
    resource: '@GraciousFeatureFlagBundle/config/routes.php'
    prefix: /_feature-flags
    trailing_slash_on_root: false
```

`trailing_slash_on_root: false` keeps the list route at `/_feature-flags` (no trailing
slash), so `GET /_feature-flags` returns `200` directly instead of a `301` redirect to
`/_feature-flags/`. Omit it only if you prefer the trailing-slash form.

| Method | Path                       | Description                       |
|--------|----------------------------|-----------------------------------|
| GET    | `/_feature-flags`          | list all flags (default manager)  |
| GET    | `/_feature-flags/{name}`   | read a single flag                |

Both accept `?manager=<name>`. The endpoint is **read-only** — it never toggles flags.

### Disabling the API

The endpoints can be switched off entirely. When disabled, both routes return `404`
even if you still import them — a hard kill switch independent of routing:

```yaml
gracious_feature_flag:
    api:
        enabled: false   # default: true
```

```bash
curl http://localhost/_feature-flags
# [{"name":"new_checkout","enabled":true,"description":"New checkout flow"}]
```

> **Security:** these endpoints expose flag names and states. The bundle does not protect
> them. Restrict the prefix to a dev/internal firewall, or guard the import with access
> control, before exposing it in production.

## Limitations

Runtime overrides (`enable`/`disable`/`reset`) live in memory for the current PHP process
only. They are not persisted or shared across workers/requests. For permanent changes,
edit the configuration.

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

### Running with Docker

If you do not have PHP/Composer locally, build the bundled image and run all commands inside it:

```bash
docker build -t feature-flag-bundle:php83 -f docker/Dockerfile .
docker run --rm -v "$PWD":/app -w /app feature-flag-bundle:php83 composer install
docker run --rm -v "$PWD":/app -w /app feature-flag-bundle:php83 vendor/bin/phpunit
docker run --rm -v "$PWD":/app -w /app feature-flag-bundle:php83 vendor/bin/phpstan analyse
docker run --rm -v "$PWD":/app -w /app feature-flag-bundle:php83 vendor/bin/php-cs-fixer fix
```
