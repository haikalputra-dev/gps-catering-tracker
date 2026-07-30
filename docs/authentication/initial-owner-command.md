# Initial Owner Command

The application ships without a default owner account. The very first
owner must be created explicitly by an authorised administrator using
the `app:create-owner` Artisan command.

## Command

```
php artisan app:create-owner \
    --name="Full Name" \
    --email="owner@example.test" \
    --phone="081234567890"
```

`--phone` is optional. If `--name` or `--email` is omitted, the command
prompts for it interactively.

## Password entry

The command never accepts a password as an argument or option. After
running, it prompts twice for the password using hidden terminal input:

```
Password (min 8 characters): ********
Confirm password:            ********
```

Both values must match. The minimum length is 8 characters.

## Validation and behaviour

- `name`: required, string, max 100 chars.
- `email`: required, valid email, max 255 chars, trimmed and
  lower-cased, must not already exist in `users`.
- `phone`: optional, digits, spaces, hyphens, parentheses and an
  optional leading `+`. Alphabetic characters are rejected.
- `password`: required, min 8, must be confirmed.
- On success the command creates an active `owner` user and prints:
  `Owner account created for <email>.`
- On failure the command prints each validation error and exits with a
  non-zero code. No partial user is created.

## Security guarantees

- The signature contains no password option or argument. This is
  verified in `tests/Feature/Console/CreateOwnerCommandTest.php`.
- The password is hashed with the application's default hasher (bcrypt
  today) before insertion.
- The command never prints the password back.
- Passwords never appear in `.env`, seeders or any tracked file.

## When to run

- Once, on the target host, right after `.env` is configured and
  migrations are applied.
- Any additional owner (should the project ever need another) must be
  created the same way. There is no web UI for creating owners.
- Do not run the command against the automated test database. Tests
  cover the command using `RefreshDatabase` and SQLite `:memory:`.

## Example of an unsafe usage (do NOT do this)

```
# Wrong: password on the command line is not supported and would end up
# in shell history and process listings.
php artisan app:create-owner --password="secret1234"
```

The command has no `--password` option; the invocation above fails.
