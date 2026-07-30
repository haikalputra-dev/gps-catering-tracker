# Project Structure

This project uses a layered architecture layered on top of the standard Laravel
directory conventions. The directories introduced in this packet are empty
architectural placeholders and do not yet contain any implementation.

## Layers

- `app/Domain` will contain domain-specific business concepts, grouped by bounded
  context (Kitchen, Delivery, Tracking, Device). These represent core business
  rules and entities.
- `app/Application` will orchestrate use cases, coordinating domain concepts to
  fulfill application workflows.
- `app/Infrastructure` will contain external integrations (persistence adapters,
  third-party services, device communication, etc.).

## Standard Laravel Directories

Laravel HTTP controllers, middleware, requests, and presentation logic remain
under the standard Laravel directories (`app/Http`, `resources/views`, `routes`,
etc.). Blade templates will provide the future frontend.

## Placeholder Notice

The following directories currently contain only a `.gitkeep` file:

```text
app/Domain/Kitchen
app/Domain/Delivery
app/Domain/Tracking
app/Domain/Device
app/Application
app/Infrastructure
```

These are architectural placeholders, not completed modules. No models,
controllers, services, repositories, DTOs, enums, migrations, middleware, API
routes, or business logic have been created in these directories.
