# Event Class Documentation

## Overview

The `event` class provides a lightweight event system for FusionPBX. It lets code create named events, attach payload data, and dispatch those events to any loaded class that implements the `event_listener` interface.

Core behavior:
- Normalizes event names with an `on_`
- Carries optional payload data
- Tracks event creation time
- Optionally stores `domain_uuid`, `user_uuid`, and a `settings` object
- Dispatches to listener methods matching the event name

## Event Naming

Event names are normalized to start with `on_` unless overridden in the constructor.

Examples:
- `new event('login')` becomes `on_login`
- `new event('on_logout')` remains `on_logout`
- `event::user_create([...])` becomes `on_user_create`

## Creating And Dispatching Events

### 1. Instance style

```php
$user_create_event = new event('user_create');
$user_create_event->set_payload([
    'user_uuid' => $user_uuid,
    'username' => $username,
]);

$user_create_event(); // dispatch the event
```

You can also pass payload at call time:

```php
$user_create_event = new event('user_create');
$user_create_event(['user_uuid' => $user_uuid]);
```

If both existing payload and invoke payload are arrays, they are merged.

### 2. Static style

```php
event::user_create([
    'user_uuid' => $user_uuid,
    'username' => $username,
]);
```

Static calls:
- Create a new event object
- Set payload from first argument
- Auto-populate `domain_uuid` and `user_uuid` from `$_SESSION` when available
- Auto-populate `settings` from global `$settings`, or create a new `settings` object
- Dispatch automatically

## Payload Access

Use `set()` / `get()` for payload key-value usage:

```php
$event = new event('something_happened');
$event->set('status', 'ok');

$status = $event->get('status'); // ok
```

`get($key, $default)` checks:
1. Event object properties first
2. Payload array key second
3. Returns default when not found

## API Reference

### Constructor

```php
public function __construct(string $name, string $prefix = 'on_')
```

Creates an event, normalizes name prefix, and records timestamp.

### Dispatch via invoke

```php
public function __invoke($payload = null): self
```

Updates payload (merging arrays when appropriate) and dispatches event.

### Static dispatch

```php
public static function __callStatic(string $event_name, $payload = null): self
```

Creates and dispatches an event via static method syntax.

### Manual dispatch

```php
public static function dispatch(event $event): void
```

Notifies listeners by calling methods named after the event (for example `on_login`).

### Payload and metadata methods

- `set_payload($payload): void`
- `get_payload(): ?array`
- `get_data(): ?array` (alias behavior)
- `set($key, $value): void`
- `get($key, $default = null)`

### Identity and context methods

- `get_name(): string`
- `get_timestamp(): int`
- `set_domain_uuid(?string $domain_uuid): void`
- `get_domain_uuid(): ?string`
- `set_user_uuid(?string $user_uuid): void`
- `get_user_uuid(): ?string`
- `set_settings(?settings $settings): void`
- `get_settings(): ?settings`

## Listener Requirements

Listeners must:
- Implement the `event_listener` interface
- Provide static methods matching event names they handle

Example listener:

```php
class my_listener implements event_listener {

    public static function on_user_create(event $event): void {
        $payload = $event->get_payload();
        // react to event
    }
}
```

During dispatch, the autoloader retrieves all classes implementing `event_listener`. If a class has a method with the exact event name, it is called.

## Validation Rules

- `set_domain_uuid()` accepts `null` or a valid UUID string
- `set_user_uuid()` accepts `null` or a valid UUID string
- `set_settings()` accepts `null` or a `settings` instance
- Invalid values throw `InvalidArgumentException`

## Notes And Caveats

- `get_payload()` and `get_data()` are typed as `?array`; passing non-array payloads can conflict with that expectation.
- Listener methods are invoked statically (`$listener::$event_name($event)`). Ensure listener handlers are declared `public static`.
- If event name resolves to an empty value, dispatch exits early.
- Using the static method replaces the payload with the given array. This means that if the domain_uuid, user_uuid, or settings is given in the array, the *payload* contains the domain_uuid given in the array and the domain_uuid is set in the object using the $_SESSION.
- Setting the event name to an [empty](https://php.net/empty) value will cause the event loop dispatcher to exit effectively short-circuiting the event for any further execution tasks.

## Typical Usage Pattern

```php
// emit event with contextual payload
$dialplan_update_event = event::dialplan_updated([
    'dialplan_uuid' => $dialplan_uuid,
    'domain_uuid' => 'target_domain_uuid_here',
]);

echo "Dialplan updated by: " . $dialplan_update_event->get_user_uuid(); // Shows logged in user
echo "Current domain: " . $dialplan_update_event->get_domain_uuid(); // Shows the currently active domain uuid
echo "Dialplan changed for domain: " . $dialplan_update_event->get('domain_uuid');
```

This pattern decouples base code from plugins and app code.
