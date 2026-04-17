# Frontend Architecture

<cite>
**Referenced Files in This Document**
- [app.tsx](file://resources/js/app.tsx)
- [ssr.tsx](file://resources/js/ssr.tsx)
- [vite.config.ts](file://vite.config.ts)
- [package.json](file://package.json)
- [tsconfig.json](file://tsconfig.json)
- [app-shell.tsx](file://resources/js/components/app-shell.tsx)
- [app-sidebar-layout.tsx](file://resources/js/layouts/app/app-sidebar-layout.tsx)
- [app-header-layout.tsx](file://resources/js/layouts/app/app-header-layout.tsx)
- [button.tsx](file://resources/js/components/ui/button.tsx)
- [card.tsx](file://resources/js/components/ui/card.tsx)
- [utils.ts](file://resources/js/lib/utils.ts)
- [use-appearance.tsx](file://resources/js/hooks/use-appearance.tsx)
- [index.ts](file://resources/js/types/index.ts)
</cite>

## Update Summary
**Changes Made**
- Updated UI Primitive Library section to clarify that magic UI components (shimmer-button, number-ticker, blur-fade, border-beam, particles) are no longer part of the current implementation despite still existing in the codebase
- Added disclaimers about deprecated components in relevant sections
- Updated troubleshooting guide to address magic UI component removal

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)
10. [Appendices](#appendices)

## Introduction
This document explains the modern frontend architecture built with React and TypeScript, styled via Tailwind CSS, and integrated with Laravel through Inertia.js. It covers the component hierarchy, layout system, state management patterns, UI primitive library, build system, styling approach, and server-side rendering integration. The goal is to provide both conceptual guidance for frontend developers and technical depth for React specialists.

**Important**: The current implementation has removed magic UI components (shimmer-button, number-ticker, blur-fade, border-beam, particles) that were previously part of the UI primitive library. These components still exist in the codebase but are no longer actively used in the current application.

## Project Structure
The frontend is organized around three pillars:
- Application bootstrap and SSR setup
- Layouts and shell abstractions
- UI primitives and shared utilities

```mermaid
graph TB
subgraph "Bootstrapping"
A["resources/js/app.tsx"]
B["resources/js/ssr.tsx"]
end
subgraph "Layouts"
L1["layouts/app/app-sidebar-layout.tsx"]
L2["layouts/app/app-header-layout.tsx"]
S["components/app-shell.tsx"]
end
subgraph "UI Primitives"
U1["components/ui/button.tsx"]
U2["components/ui/card.tsx"]
U3["lib/utils.ts"]
U4["Magic UI Components<br/>(Removed)<br/>shimmer-button.tsx<br/>number-ticker.tsx<br/>blur-fade.tsx<br/>border-beam.tsx<br/>particles.tsx"]
end
subgraph "Styling & Build"
V["vite.config.ts"]
P["package.json"]
T["tsconfig.json"]
end
A --> L1
A --> L2
B --> L1
B --> L2
L1 --> S
L2 --> S
U1 --> U3
U2 --> U3
U4 -.-> U3
V --> A
V --> B
P --> V
T --> A
```

**Diagram sources**
- [app.tsx:1-36](file://resources/js/app.tsx#L1-L36)
- [ssr.tsx:1-28](file://resources/js/ssr.tsx#L1-L28)
- [app-sidebar-layout.tsx:1-21](file://resources/js/layouts/app/app-sidebar-layout.tsx#L1-L21)
- [app-header-layout.tsx:1-17](file://resources/js/layouts/app/app-header-layout.tsx#L1-L17)
- [app-shell.tsx:1-22](file://resources/js/components/app-shell.tsx#L1-L22)
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)
- [package.json:1-80](file://package.json#L1-L80)
- [tsconfig.json:1-122](file://tsconfig.json#L1-L122)

**Section sources**
- [app.tsx:1-36](file://resources/js/app.tsx#L1-L36)
- [ssr.tsx:1-28](file://resources/js/ssr.tsx#L1-L28)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)
- [package.json:1-80](file://package.json#L1-L80)
- [tsconfig.json:1-122](file://tsconfig.json#L1-L122)

## Core Components
- Application bootstrap: Initializes Inertia, resolves page components, renders the root, and sets up global providers.
- Layout system: Provides reusable layouts for sidebar and header variants, backed by a shell abstraction.
- UI primitives: A cohesive set of headless UI components built with Radix UI and styled via Tailwind, using class merging utilities.
- Utilities: Shared helpers for class composition and URL normalization.
- Appearance hook: Centralized theme management supporting system preference, persistence, and SSR.

**Important**: Magic UI components (shimmer-button, number-ticker, blur-fade, border-beam, particles) were part of the original UI primitive library but have been removed from active use in the current implementation.

Key implementation references:
- Bootstrapping and rendering: [app.tsx:11-32](file://resources/js/app.tsx#L11-L32)
- SSR setup: [ssr.tsx:9-27](file://resources/js/ssr.tsx#L9-L27)
- Layout composition: [app-sidebar-layout.tsx:7-20](file://resources/js/layouts/app/app-sidebar-layout.tsx#L7-L20), [app-header-layout.tsx:6-16](file://resources/js/layouts/app/app-header-layout.tsx#L6-L16)
- Shell abstraction: [app-shell.tsx:11-21](file://resources/js/components/app-shell.tsx#L11-L21)
- UI primitives: [button.tsx:7-39](file://resources/js/components/ui/button.tsx#L7-L39), [card.tsx:5-66](file://resources/js/components/ui/card.tsx#L5-L66)
- Utilities: [utils.ts:6-12](file://resources/js/lib/utils.ts#L6-L12)
- Appearance management: [use-appearance.tsx:90-115](file://resources/js/hooks/use-appearance.tsx#L90-L115)

**Section sources**
- [app.tsx:1-36](file://resources/js/app.tsx#L1-L36)
- [ssr.tsx:1-28](file://resources/js/ssr.tsx#L1-L28)
- [app-sidebar-layout.tsx:1-21](file://resources/js/layouts/app/app-sidebar-layout.tsx#L1-L21)
- [app-header-layout.tsx:1-17](file://resources/js/layouts/app/app-header-layout.tsx#L1-L17)
- [app-shell.tsx:1-22](file://resources/js/components/app-shell.tsx#L1-L22)
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)
- [use-appearance.tsx:1-116](file://resources/js/hooks/use-appearance.tsx#L1-L116)

## Architecture Overview
The frontend architecture integrates React with Inertia.js to deliver a seamless client-side experience while leveraging Laravel for routing, middleware, and SSR. The build pipeline uses Vite with plugins for React, Tailwind CSS, and Laravel integration. UI primitives are designed for composability and customization, while the layout system ensures consistent navigation and content areas.

```mermaid
graph TB
Client["Browser"]
Inertia["@inertiajs/react"]
Root["resources/js/app.tsx"]
SSR["resources/js/ssr.tsx"]
Layouts["Layouts<br/>app-sidebar-layout.tsx<br/>app-header-layout.tsx"]
Shell["components/app-shell.tsx"]
UI["UI Primitives<br/>button.tsx<br/>card.tsx"]
Utils["lib/utils.ts"]
Build["vite.config.ts"]
Types["tsconfig.json"]
Client --> Inertia
Inertia --> Root
Inertia --> SSR
Root --> Layouts
SSR --> Layouts
Layouts --> Shell
Shell --> UI
UI --> Utils
Build --> Root
Build --> SSR
Types --> Root
```

**Diagram sources**
- [app.tsx:1-36](file://resources/js/app.tsx#L1-L36)
- [ssr.tsx:1-28](file://resources/js/ssr.tsx#L1-L28)
- [app-sidebar-layout.tsx:1-21](file://resources/js/layouts/app/app-sidebar-layout.tsx#L1-L21)
- [app-header-layout.tsx:1-17](file://resources/js/layouts/app/app-header-layout.tsx#L1-L17)
- [app-shell.tsx:1-22](file://resources/js/components/app-shell.tsx#L1-L22)
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)
- [tsconfig.json:1-122](file://tsconfig.json#L1-L122)

## Detailed Component Analysis

### Layout System
The layout system provides two primary variants:
- Sidebar layout: Uses a sidebar provider and content area with a header bar and breadcrumbs.
- Header layout: Uses a top header and content area without a sidebar.

```mermaid
classDiagram
class AppShell {
+variant : "sidebar" | "header"
+children : ReactNode
}
class AppSidebarLayout {
+children : ReactNode
+breadcrumbs : Breadcrumb[]
}
class AppHeaderLayout {
+children : ReactNode
+breadcrumbs : Breadcrumb[]
}
AppSidebarLayout --> AppShell : "wraps"
AppHeaderLayout --> AppShell : "wraps"
```

**Diagram sources**
- [app-shell.tsx:6-21](file://resources/js/components/app-shell.tsx#L6-L21)
- [app-sidebar-layout.tsx:7-20](file://resources/js/layouts/app/app-sidebar-layout.tsx#L7-L20)
- [app-header-layout.tsx:6-16](file://resources/js/layouts/app/app-header-layout.tsx#L6-L16)

Practical usage examples:
- Sidebar layout pattern: [app-sidebar-layout.tsx:7-20](file://resources/js/layouts/app/app-sidebar-layout.tsx#L7-L20)
- Header layout pattern: [app-header-layout.tsx:6-16](file://resources/js/layouts/app/app-header-layout.tsx#L6-L16)

**Section sources**
- [app-shell.tsx:1-22](file://resources/js/components/app-shell.tsx#L1-L22)
- [app-sidebar-layout.tsx:1-21](file://resources/js/layouts/app/app-sidebar-layout.tsx#L1-L21)
- [app-header-layout.tsx:1-17](file://resources/js/layouts/app/app-header-layout.tsx#L1-L17)

### UI Primitive Library
The UI primitives are built with Radix UI under the hood and styled with Tailwind CSS. They use a variant system and a shared class merging utility for consistent styling and composition.

**Important**: Magic UI components (shimmer-button, number-ticker, blur-fade, border-beam, particles) were part of the original UI primitive library but have been removed from active use. These components still exist in the codebase but are not currently utilized.

```mermaid
classDiagram
class Button {
+variant : "default" | "destructive" | "outline" | "secondary" | "ghost" | "link"
+size : "default" | "xs" | "sm" | "lg" | "icon" | "icon-xs" | "icon-sm" | "icon-lg"
+asChild : boolean
}
class Card {
+CardHeader
+CardTitle
+CardDescription
+CardContent
+CardFooter
}
class Utils {
+cn(...inputs) : string
+toUrl(url) : string
}
class MagicUIComponents {
<<Deprecated>>
+ShimmerButton : Removed
+NumberTicker : Removed
+BlurFade : Removed
+BorderBeam : Removed
+Particles : Removed
}
Button --> Utils : "uses cn()"
Card --> Utils : "uses cn()"
MagicUIComponents --> Utils : "would use cn() (deprecated)"
```

**Diagram sources**
- [button.tsx:7-39](file://resources/js/components/ui/button.tsx#L7-L39)
- [card.tsx:5-66](file://resources/js/components/ui/card.tsx#L5-L66)
- [utils.ts:6-12](file://resources/js/lib/utils.ts#L6-L12)
- [shimmer-button.tsx:1-96](file://resources/js/components/ui/shimmer-button.tsx#L1-L96)
- [number-ticker.tsx:1-73](file://resources/js/components/ui/number-ticker.tsx#L1-L73)
- [blur-fade.tsx:1-93](file://resources/js/components/ui/blur-fade.tsx#L1-L93)
- [border-beam.tsx:1-106](file://resources/js/components/ui/border-beam.tsx#L1-L106)
- [particles.tsx:1-320](file://resources/js/components/ui/particles.tsx#L1-L320)

Implementation details:
- Variants and sizes for buttons: [button.tsx:7-39](file://resources/js/components/ui/button.tsx#L7-L39)
- Card composition: [card.tsx:5-66](file://resources/js/components/ui/card.tsx#L5-L66)
- Class merging utility: [utils.ts:6-8](file://resources/js/lib/utils.ts#L6-L8)
- URL normalization utility: [utils.ts:10-12](file://resources/js/lib/utils.ts#L10-L12)

**Section sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)

### Hook-Based State Management
The appearance hook demonstrates a centralized, external-store-like pattern for managing theme preferences across client and SSR contexts.

```mermaid
sequenceDiagram
participant Browser as "Browser"
participant App as "AppShell/App"
participant Hook as "useAppearance()"
participant DOM as "documentElement"
Browser->>App : "Initialize theme on load"
App->>Hook : "initializeTheme()"
Hook->>DOM : "Set 'dark' class if resolvedAppearance is 'dark'"
Hook->>Hook : "Persist 'appearance' in localStorage and cookie"
Hook-->>App : "Theme applied and listeners notified"
```

**Diagram sources**
- [use-appearance.tsx:73-88](file://resources/js/hooks/use-appearance.tsx#L73-L88)
- [app-shell.tsx:11-21](file://resources/js/components/app-shell.tsx#L11-L21)

Key behaviors:
- System preference detection and persistence
- SSR cookie synchronization
- Real-time updates via subscribers

**Section sources**
- [use-appearance.tsx:1-116](file://resources/js/hooks/use-appearance.tsx#L1-L116)
- [app-shell.tsx:1-22](file://resources/js/components/app-shell.tsx#L1-L22)

### Build System and Styling Approach
The build system leverages Vite with plugins for React, Tailwind CSS, and Laravel integration. TypeScript configuration enforces strictness and path aliases. The styling approach combines Tailwind utilities with a class merging strategy for predictable overrides.

```mermaid
flowchart TD
Start(["Vite Build"]) --> Plugins["Plugins<br/>laravel-vite-plugin<br/>@vitejs/plugin-react<br/>@tailwindcss/vite<br/>@laravel/vite-plugin-wayfinder"]
Plugins --> Resolve["Resolve entrypoints<br/>resources/js/app.tsx<br/>resources/js/ssr.tsx"]
Resolve --> Compile["Compile TS/TSX with React JSX transform"]
Compile --> Bundle["Bundle assets"]
Bundle --> Output["Emit static assets"]
```

**Diagram sources**
- [vite.config.ts:7-27](file://vite.config.ts#L7-L27)
- [package.json:5-14](file://package.json#L5-L14)
- [tsconfig.json:110-115](file://tsconfig.json#L110-L115)

**Section sources**
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)
- [package.json:1-80](file://package.json#L1-L80)
- [tsconfig.json:1-122](file://tsconfig.json#L1-L122)

### Inertia.js Integration
Inertia bridges the Laravel backend with React frontends. The application resolves page components dynamically and renders them with React root and SSR support.

```mermaid
sequenceDiagram
participant Client as "Client"
participant Inertia as "createInertiaApp"
participant Resolver as "resolvePageComponent"
participant Root as "createRoot"
participant SSR as "SSR Server"
Client->>Inertia : "Navigate to route"
Inertia->>Resolver : "Resolve page component by name"
Resolver-->>Inertia : "Loaded component module"
Inertia->>Root : "Render App with props"
Note over Root : "TooltipProvider wraps App"
SSR->>Inertia : "Server-side render (SSR)"
Inertia-->>Client : "HTML + hydration payload"
```

**Diagram sources**
- [app.tsx:11-32](file://resources/js/app.tsx#L11-L32)
- [ssr.tsx:9-27](file://resources/js/ssr.tsx#L9-L27)

**Section sources**
- [app.tsx:1-36](file://resources/js/app.tsx#L1-L36)
- [ssr.tsx:1-28](file://resources/js/ssr.tsx#L1-L28)

## Dependency Analysis
The frontend stack relies on a small set of core libraries and a curated plugin ecosystem. Dependencies are declared in package.json, while Vite orchestrates the build and development experience.

**Important**: Magic UI components require the motion library dependency, which is still present in the current package.json but may not be actively used.

```mermaid
graph LR
React["react"]
Inertia["@inertiajs/react"]
Radix["@radix-ui/*"]
Tailwind["tailwindcss"]
Vite["vite"]
Plugins["laravel-vite-plugin<br/>@vitejs/plugin-react<br/>@tailwindcss/vite<br/>@laravel/vite-plugin-wayfinder"]
Motion["motion (dependency for magic UI)<br/>Currently unused in implementation"]
React --> Inertia
Inertia --> Radix
Tailwind --> Plugins
Vite --> Plugins
Plugins --> React
Motion -.-> Radix
```

**Diagram sources**
- [package.json:34-70](file://package.json#L34-L70)
- [vite.config.ts:8-23](file://vite.config.ts#L8-L23)

**Section sources**
- [package.json:1-80](file://package.json#L1-L80)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)

## Performance Considerations
- Prefer component-level lazy loading and code splitting via Inertia's dynamic page resolution.
- Use the variant system in UI primitives to minimize custom CSS and leverage shared styles.
- Keep class merging minimal to reduce bundle size; consolidate repeated utility classes.
- Utilize SSR for initial page loads to improve perceived performance and SEO.
- Monitor theme switching costs; the appearance hook is optimized to avoid unnecessary re-renders.
- **Important**: Magic UI components (shimmer-button, number-ticker, blur-fade, border-beam, particles) are no longer part of the current implementation and should not be used, as they may cause confusion during development.

## Troubleshooting Guide
Common issues and remedies:
- Theme not persisting across reloads: Verify localStorage and cookie handling in the appearance hook initialization and update flows.
  - Reference: [use-appearance.tsx:73-88](file://resources/js/hooks/use-appearance.tsx#L73-L88), [use-appearance.tsx:101-112](file://resources/js/hooks/use-appearance.tsx#L101-L112)
- SSR mismatch after hydration: Ensure SSR and client-side providers align (e.g., TooltipProvider wrapping App).
  - Reference: [app.tsx:21-27](file://resources/js/app.tsx#L21-L27), [ssr.tsx:19-25](file://resources/js/ssr.tsx#L19-L25)
- Tailwind utilities not applying: Confirm Tailwind CSS is enabled and configured in Vite; verify class merging utility usage.
  - Reference: [vite.config.ts:19-19](file://vite.config.ts#L19-L19), [utils.ts:6-8](file://resources/js/lib/utils.ts#L6-L8)
- TypeScript errors in JSX: Ensure tsconfig JSX settings and module resolution are correct.
  - Reference: [tsconfig.json:110-115](file://tsconfig.json#L110-L115), [tsconfig.json:30-30](file://tsconfig.json#L30-L30)
- **Important**: Magic UI components (shimmer-button, number-ticker, blur-fade, border-beam, particles) are deprecated and should not be used. If encountered in code searches, they are remnants of the previous implementation and are not part of the current architecture.

**Section sources**
- [use-appearance.tsx:73-112](file://resources/js/hooks/use-appearance.tsx#L73-L112)
- [app.tsx:18-27](file://resources/js/app.tsx#L18-L27)
- [ssr.tsx:19-25](file://resources/js/ssr.tsx#L19-L25)
- [vite.config.ts:19-19](file://vite.config.ts#L19-L19)
- [utils.ts:6-8](file://resources/js/lib/utils.ts#L6-L8)
- [tsconfig.json:110-115](file://tsconfig.json#L110-L115)

## Conclusion
This frontend architecture combines React and TypeScript with Tailwind CSS and Inertia.js to deliver a maintainable, scalable, and developer-friendly user interface. The layout system and UI primitives promote consistency and reuse, while the appearance hook and SSR setup ensure a smooth user experience across environments. The build system and strict TypeScript configuration provide reliability and predictability.

**Important Note**: Magic UI components (shimmer-button, number-ticker, blur-fade, border-beam, particles) are no longer part of the current implementation despite still existing in the codebase. Developers should rely on the core UI primitives documented here for consistent, maintainable component development.

## Appendices
- Practical examples:
  - Using the button primitive with variants and sizes: [button.tsx:41-62](file://resources/js/components/ui/button.tsx#L41-L62)
  - Composing a card with header, title, description, content, and footer: [card.tsx:5-66](file://resources/js/components/ui/card.tsx#L5-L66)
  - Applying a layout variant in a page:
    - Sidebar layout: [app-sidebar-layout.tsx:7-20](file://resources/js/layouts/app/app-sidebar-layout.tsx#L7-L20)
    - Header layout: [app-header-layout.tsx:6-16](file://resources/js/layouts/app/app-header-layout.tsx#L6-L16)
- Type system:
  - Centralized type re-exports: [index.ts:1-6](file://resources/js/types/index.ts#L1-L6)
- **Important**: Magic UI components are deprecated and should not be used in current development:
  - ShimmerButton: [shimmer-button.tsx:1-96](file://resources/js/components/ui/shimmer-button.tsx#L1-L96)
  - NumberTicker: [number-ticker.tsx:1-73](file://resources/js/components/ui/number-ticker.tsx#L1-L73)
  - BlurFade: [blur-fade.tsx:1-93](file://resources/js/components/ui/blur-fade.tsx#L1-L93)
  - BorderBeam: [border-beam.tsx:1-106](file://resources/js/components/ui/border-beam.tsx#L1-L106)
  - Particles: [particles.tsx:1-320](file://resources/js/components/ui/particles.tsx#L1-L320)