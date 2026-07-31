# Git Workflow (Solo Project)

This is a solo project. To keep history simple and avoid the overhead
of PR ceremonies for a single-developer codebase, all work lands
directly on `main`.

## Rules

- Commit and push directly to `main`. No feature branches, no pull
  requests, no merges.
- Each commit is a self-contained change with a clear message. Prefer
  small, focused commits over large mixed ones.
- Before pushing, verify tests pass locally (`php artisan test`) and
  the build is clean (`npm run build`).
- Push with `git push origin main` as soon as a commit is ready. Do
  not accumulate unpushed commits.

## Exceptions

- A branch is acceptable only when a change is exploratory and might
  be discarded. Once the direction is confirmed, rebase or fast-forward
  onto `main` and delete the branch immediately.
- History rewrites on already-pushed commits (`push --force`,
  `rebase -i` over pushed history) are not allowed.

## Commit message style

- Imperative subject, under 70 characters.
- Body wraps at ~72 columns.
- Reference architecture rules or packet identifiers when relevant
  (e.g. `Refs: AR-60`).
