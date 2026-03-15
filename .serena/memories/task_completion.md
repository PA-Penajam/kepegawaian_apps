# Task Completion Protocol

## After Every Task, Run These Steps:

### 1. PHP Changes
```bash
vendor/bin/pint --dirty --format agent
```
**MANDATORY** after any PHP file modification. Formats only dirty (modified) files.

### 2. TypeScript/React Changes
```bash
npm run lint        # ESLint fix
npm run format      # Prettier format
npm run types:check # TypeScript type check (tsc --noEmit)
```
All three must pass. Run in order.

### 3. Run Affected Tests
```bash
php artisan test --compact --filter=TestClassName
# or for specific file:
php artisan test --compact tests/Feature/Path/TestFile.php
```
Run the **minimum number of tests** needed to verify your changes.

### 4. Full Suite (When Appropriate)
```bash
php artisan test --compact
```
Run the full suite when changes affect shared code (models, middleware, config, etc.).

### 5. CI Check (Before Commits/PRs)
```bash
composer run ci:check
```
Runs: npm lint:check → npm format:check → npm types:check → php test

## Notes
- **Never skip formatting** — Pint format is checked in CI
- **Never skip type checking** — strict mode is enabled
- Test changes should be **programmatic** — avoid manual verification scripts
- If frontend changes aren't reflected: suggest `npm run build` or `npm run dev`
