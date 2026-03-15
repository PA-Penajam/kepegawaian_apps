# Project Overview: kepegawaian-apps

## Purpose
A **kepegawaian (HR/personnel management) application** built on the Laravel React starter kit. Currently in early stages with authentication scaffolding in place — the "kepegawaian" (employee management) domain features are yet to be built on top of this foundation.

## Tech Stack

### Backend
- **PHP 8.4** with **Laravel 12** (streamlined structure — no `app/Http/Kernel.php`)
- **Laravel Fortify** v1 — headless authentication (login, register, password reset, email verification, 2FA/TOTP)
- **Laravel Wayfinder** v0 — TypeScript route generation for frontend
- **Inertia.js v2** — Laravel adapter for server-driven SPA
- **Laravel Boost** v2 — MCP server for development tooling
- **SQLite** database (dev), in-memory SQLite for testing
- **Pest 4** — testing framework with Laravel plugin

### Frontend
- **React 19** with **TypeScript 5.7+** (strict mode, ESNext target)
- **Inertia.js React adapter** v2 — SPA without API layer
- **Tailwind CSS 4** via Vite plugin
- **shadcn/ui** (new-york style, Radix UI primitives, Lucide icons)
- **class-variance-authority (CVA)** + **clsx** + **tailwind-merge** for styling utilities
- **React Compiler** via babel plugin
- **Vite 7** bundler with Laravel plugin, SSR support available

### Key Architectural Patterns
- **Inertia SPA**: Server returns Inertia responses, React renders pages. No separate API.
- **Fortify headless auth**: All auth routes/controllers via Fortify; custom React pages.
- **Wayfinder**: TypeScript route functions generated from Laravel routes (`@/actions/`, `@/routes/`).
- **Middleware in `bootstrap/app.php`**: HandleAppearance, HandleInertiaRequests, AddLinkHeadersForPreloadedAssets.

## Database
- SQLite (`database/database.sqlite`)
- Migrations: users, cache, jobs tables + two-factor columns

## Project Structure

```
app/
├── Actions/Fortify/         # Fortify action classes (CreateNewUser, ResetUserPassword)
├── Concerns/                # Traits (PasswordValidationRules, ProfileValidationRules)
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php   # Base controller
│   │   └── Settings/        # ProfileController, SecurityController
│   ├── Middleware/           # HandleInertiaRequests, HandleAppearance
│   └── Requests/Settings/   # Form request classes
├── Models/                  # User model
└── Providers/               # AppServiceProvider, FortifyServiceProvider

resources/js/
├── app.tsx                  # Main entry point
├── ssr.tsx                  # SSR entry point
├── actions/                 # Wayfinder-generated controller actions
├── routes/                  # Wayfinder-generated named routes
├── wayfinder/               # Wayfinder internal files
├── pages/                   # Inertia pages
│   ├── auth/                # login, register, forgot-password, etc.
│   ├── settings/            # profile, security, appearance
│   ├── dashboard.tsx
│   └── welcome.tsx
├── components/              # Shared components
│   └── ui/                  # shadcn/ui primitives
├── layouts/                 # App, Auth, Settings layouts
├── hooks/                   # Custom React hooks
├── lib/                     # Utility functions (cn helper, etc.)
└── types/                   # TypeScript type definitions (auth, navigation, ui)

routes/
├── web.php                  # Main routes (home, dashboard)
├── settings.php             # Settings routes
└── console.php              # Console/artisan routes

tests/
├── Feature/                 # Feature tests (Auth, Settings, Dashboard)
├── Unit/                    # Unit tests
├── Pest.php                 # Pest config (RefreshDatabase in Feature)
└── TestCase.php             # Base test case

config/                      # Laravel config files
bootstrap/
├── app.php                  # Application + middleware + routing config
└── providers.php            # Service providers
database/
├── migrations/              # Schema migrations
├── factories/               # Model factories
└── seeders/                 # Database seeders
```
