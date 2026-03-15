# Code Style & Conventions

## PHP Conventions

### General
- **Pint preset**: `laravel` (PSR-12 based with Laravel-specific rules)
- Always use curly braces for control structures, even single-line bodies
- Use PHP 8 constructor property promotion
- Explicit return type declarations on all methods/functions
- Type hints for all method parameters
- PHPDoc blocks preferred over inline comments; no in-code comments unless exceptionally complex
- Add array shape type definitions in PHPDoc when appropriate
- Enum keys in TitleCase

### Laravel Patterns
- Use `Model::query()` instead of `DB::` facade
- Eager load relationships to prevent N+1 queries
- Form Request classes for validation (not inline)
- Check sibling files to match array vs string rule format
- Use `config()` helper, NEVER `env()` outside config files
- Named routes with `route()` function for URL generation
- Queued jobs with `ShouldQueue` for time-consuming operations
- Factories for test model creation; check factory states before manual setup
- Always run `vendor/bin/pint --dirty --format agent` after modifying PHP files

### File Creation
- Use `php artisan make:*` commands with `--no-interaction`
- When creating models, also create factories and seeders
- Most tests should be feature tests; use `--unit` flag for unit tests

## TypeScript/React Conventions

### General
- Strict mode enabled (`strict: true`, `noImplicitAny: true`)
- ESNext target, bundler module resolution
- Path alias: `@/*` → `resources/js/*`
- `type` imports enforced: `import type { Foo } from '...'` (separate type imports)
- Import order enforced: builtin → external → internal → parent → sibling → index (alphabetized)

### React
- React 19 with JSX automatic runtime (no `import React`)
- React Compiler enabled via babel plugin
- Functional components only
- Props types defined inline or in `types/` directory

### Styling
- Tailwind CSS 4 (via Vite plugin, NOT PostCSS config)
- `cn()` utility from `@/lib/utils` for class merging (clsx + tailwind-merge)
- shadcn/ui components in `@/components/ui/` — new-york style, Radix UI
- CVA for component variants
- Prettier with tailwindcss plugin sorts classes

### ESLint Rules
- `@typescript-eslint/no-explicit-any`: OFF (any is allowed)
- Consistent type imports: separate type imports enforced
- Padding lines around control statements (if, return, for, etc.)
- 1TBS brace style, curly braces required
- Prettier integration for formatting rules

### Prettier Config
- Semicolons: yes
- Single quotes: yes
- Tab width: 4
- Print width: 80
- Single attribute per line: no
- Tailwind class sorting enabled

### File Naming
- Pages: kebab-case (e.g., `forgot-password.tsx`, `verify-email.tsx`)
- Components: kebab-case (e.g., `app-sidebar.tsx`, `nav-main.tsx`)
- Types: kebab-case (e.g., `auth.ts`, `navigation.ts`)
- Layouts: kebab-case (e.g., `auth-layout.tsx`, `app-layout.tsx`)

### Inertia Patterns
- Pages in `resources/js/pages/` — matched to `Inertia::render()` calls
- Layouts in `resources/js/layouts/` — with sub-layouts for auth/settings/app
- Wayfinder imports: `@/actions/` for controllers, `@/routes/` for named routes
- Use `.form()` with `<Form>` component or `form.submit(store())` with useForm

## Testing Conventions
- **Framework**: Pest 4 with Laravel plugin
- Feature tests use `RefreshDatabase` trait (configured in `tests/Pest.php`)
- SQLite in-memory for testing
- Use `fake()` or `$this->faker` — follow existing convention in test files
- Use model factories with states; check available states before manual setup
