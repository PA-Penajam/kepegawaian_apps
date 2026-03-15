# Suggested Commands

## Development

### Start Development Server
```bash
composer run dev
# Starts: Laravel server + queue + Pail logs + Vite dev server (concurrently)
```

### Start with SSR
```bash
composer run dev:ssr
# Builds SSR first, then starts server + queue + logs + Inertia SSR
```

### Frontend Only
```bash
npm run dev      # Vite dev server only
npm run build    # Production build
npm run build:ssr  # Build with SSR
```

## Testing

### Run All Tests
```bash
php artisan test --compact
```

### Run Specific Test File
```bash
php artisan test --compact tests/Feature/Auth/AuthenticationTest.php
```

### Run with Filter
```bash
php artisan test --compact --filter=testName
```

### Pest Direct
```bash
vendor/bin/pest --compact
```

## Linting & Formatting

### PHP (Pint)
```bash
vendor/bin/pint --dirty --format agent    # Fix modified files (MUST run after PHP changes)
vendor/bin/pint --format agent            # Fix all files
vendor/bin/pint --parallel --test         # Check only (CI)
```

### TypeScript/React (ESLint)
```bash
npm run lint           # Fix issues
npm run lint:check     # Check only
```

### Prettier
```bash
npm run format         # Format resources/
npm run format:check   # Check formatting
```

### TypeScript Type Check
```bash
npm run types:check    # tsc --noEmit
```

## CI/Full Check
```bash
composer run ci:check   # lint:check + format:check + types:check + tests
composer run test       # config:clear + lint:check + run tests
```

## Artisan Commands

### Create Files
```bash
php artisan make:model ModelName --no-interaction     # New model
php artisan make:controller ControllerName --no-interaction
php artisan make:test TestName --pest --no-interaction  # Feature test
php artisan make:test TestName --pest --unit --no-interaction  # Unit test
php artisan make:request RequestName --no-interaction
php artisan make:class ClassName --no-interaction
php artisan list                                       # All available commands
php artisan make:model --help                          # Check options
```

### Database
```bash
php artisan migrate
php artisan migrate:rollback
php artisan migrate:fresh --seed
```

### Debug
```bash
php artisan route:list
php artisan config:show [key]
php artisan tinker --execute "your code here"
```

### Wayfinder (route generation)
```bash
# Automatically generated via Vite plugin — no manual command needed
# Generated files in: resources/js/actions/, resources/js/routes/, resources/js/wayfinder/
```

## Task Completion Checklist
After completing work, run:
1. `vendor/bin/pint --dirty --format agent` (if PHP files changed)
2. `npm run lint` (if TS/TSX files changed)
3. `npm run format` (if TS/TSX files changed)
4. `npm run types:check` (if TS/TSX files changed)
5. `php artisan test --compact` (always — or filter to affected tests)
