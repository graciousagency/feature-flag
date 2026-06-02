# Gracious Feature Flag Bundle

Lightweight feature flags for Symfony. Define flags in config, then check them from
services, controllers, Twig, routes, and controller attributes.

- PHP `>=8.3`, Symfony `^7.0 || ^8.0`, Twig 3 (optional).

**Features**

- Config-driven flags with optional descriptions.
- One default manager plus any number of named managers (separate flag groups).
- Check flags in PHP, Twig, route defaults, or `#[RequireFeature]` attributes.
- Per-process runtime overrides (`enable` / `disable` / `reset`).
- Optional read-only REST endpoint with a kill switch.

## Installation

```bash
composer require gracious/feature-flag-bundle
```

With Symfony Flex this is all you need. Without Flex, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Gracious\FeatureFlagBundle\GraciousFeatureFlagBundle::class => ['all' => true],
];
```

## Quick start

```yaml
# config/packages/gracious_feature_flag.yaml
gracious_feature_flag:
    flags:
        new_checkout:
            enabled: true
            description: 'New checkout flow'
        beta_search:
            enabled: false   # 'enabled' defaults to false
```

```php
use Gracious\FeatureFlagBundle\Flag\FeatureFlagManagerInterface;

final class CheckoutService
{
    public function __construct(private FeatureFlagManagerInterface $flags) {}

    public function run(): void
    {
        if ($this->flags->isEnabled('new_checkout')) {
            // new flow
        }
    }
}
```

## Configuration

```yaml
gracious_feature_flag:
    # default manager flags
    flags:
        new_checkout: { enabled: true, description: 'New checkout flow' }
        beta_search:  { enabled: false }

    # optional: extra named managers, each its own flag group
    managers:
        billing:
            flags:
                invoices_v2: { enabled: true }

    # optional: exception thrown when a guard fails (route / attribute)
    exception:
        class: Gracious\FeatureFlagBundle\Exception\FeatureNotAvailableException
        status_code: 404
        factory: ~       # service id implementing ExceptionFactoryInterface; wins over 'class'

    # optional: REST endpoint kill switch
    api:
        enabled: true    # false => endpoints return 404
```

## Manager API

```php
$flags->isEnabled('new_checkout'); // bool
$flags->has('new_checkout');       // bool: flag is defined
$flags->get('new_checkout');       // Flag VO (name, enabled, description)
$flags->all();                     // array<string, Flag>

// runtime overrides (this PHP process only; see Limitations)
$flags->enable('beta_search');
$flags->disable('new_checkout');
$flags->reset('beta_search');      // back to the configured value
```

Unknown flag names throw `UnknownFeatureException`.

### Named managers

Each named manager is its own service. Autowire it by the variable name `<name>Manager`:

```php
public function __construct(FeatureFlagManagerInterface $billingManager) {}
```

To resolve a manager by name at runtime, inject the `ManagerRegistry`:

```php
use Gracious\FeatureFlagBundle\Flag\ManagerRegistry;

public function __construct(private ManagerRegistry $registry) {}

$this->registry->get('billing')->isEnabled('invoices_v2');
$this->registry->getDefault()->isEnabled('new_checkout');
```

Unknown manager names throw `UnknownManagerException`.

## Twig

```twig
{% if feature('new_checkout') %}
    <a href="/checkout/new">Try the new checkout</a>
{% endif %}

{# named manager (second argument) #}
{% if feature('invoices_v2', 'billing') %} ... {% endif %}

{# as a test #}
{% if 'beta_search' is feature_enabled %} ... {% endif %}
```

The Twig extension registers only when Twig is installed.

## Route guards

Guard a route with the `_feature_flag` default. The string form requires the flag enabled:

```yaml
beta_page:
    path: /beta
    controller: App\Controller\BetaController
    defaults:
        _feature_flag: beta_search
```

The array form sets the required state and an optional manager:

```yaml
legacy_page:
    path: /legacy
    controller: App\Controller\LegacyController
    defaults:
        _feature_flag: { name: legacy, enabled: false, manager: default }
```

When the requirement is not met, the configured exception is thrown (404 by default).

## Attribute guards

`#[RequireFeature]` works on a controller class or method and is repeatable:

```php
use Gracious\FeatureFlagBundle\Attribute\RequireFeature;

#[RequireFeature('new_checkout')]                 // class: require enabled
final class CheckoutController
{
    #[RequireFeature('legacy', enabled: false)]   // require disabled
    public function index(): Response { /* ... */ }

    #[RequireFeature('invoices_v2', manager: 'billing')]
    public function invoices(): Response { /* ... */ }
}
```

## Custom exception

For full control over the failure response, provide a factory service:

```php
use Gracious\FeatureFlagBundle\Exception\ExceptionFactoryInterface;
use Gracious\FeatureFlagBundle\Flag\Flag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AccessDeniedExceptionFactory implements ExceptionFactoryInterface
{
    public function create(Flag $flag, bool $required): \Throwable
    {
        return new AccessDeniedHttpException(
            sprintf('Feature "%s" gate failed.', $flag->name),
        );
    }
}
```

```yaml
gracious_feature_flag:
    exception:
        factory: App\FeatureFlag\AccessDeniedExceptionFactory
```

Alternatively set `exception.class` to any class with a `(string $name, int $statusCode)`
constructor. A `factory` always wins over `class`.

## REST endpoint

The endpoint is read-only and opt-in. Import the routes to enable it:

```yaml
# config/routes/gracious_feature_flag.yaml
feature_flags:
    resource: '@GraciousFeatureFlagBundle/config/routes.php'
    prefix: /_feature-flags
    trailing_slash_on_root: false
```

| Method | Path                     | Description                      |
|--------|--------------------------|---------------------------------|
| GET    | `/_feature-flags`        | list all flags (default manager)|
| GET    | `/_feature-flags/{name}` | read a single flag              |

Both accept `?manager=<name>`. Unknown flag or manager returns 404.

```bash
curl http://localhost/_feature-flags
# [{"name":"new_checkout","enabled":true,"description":"New checkout flow"}]
```

`trailing_slash_on_root: false` keeps the list route at `/_feature-flags` so the request
returns 200 directly instead of a 301 redirect to `/_feature-flags/`.

**Disabling the endpoint.** Set `api.enabled: false`. Both routes then return 404 even if
still imported, a hard kill switch independent of routing.

> **Security:** these endpoints expose flag names and states and are not protected by the
> bundle. Restrict the prefix to a dev/internal firewall or guard the import with access
> control before exposing it in production.
