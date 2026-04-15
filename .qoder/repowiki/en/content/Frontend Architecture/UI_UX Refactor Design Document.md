# UI/UX Refactor Design Document

<cite>
**Referenced Files in This Document**
- [2026-04-15-ui-refactor-design.md](file://docs/plans/2026-04-15-ui-refactor-design.md)
- [app.css](file://resources/css/app.css)
- [welcome.tsx](file://resources/js/pages/welcome.tsx)
- [app.tsx](file://resources/js/app.tsx)
- [button.tsx](file://resources/js/components/ui/button.tsx)
- [card.tsx](file://resources/js/components/ui/card.tsx)
- [table.tsx](file://resources/js/components/ui/table.tsx)
- [dialog.tsx](file://resources/js/components/ui/dialog.tsx)
- [input.tsx](file://resources/js/components/ui/input.tsx)
- [select.tsx](file://resources/js/components/ui/select.tsx)
- [app-sidebar.tsx](file://resources/js/components/app-sidebar.tsx)
- [use-appearance.tsx](file://resources/js/hooks/use-appearance.tsx)
- [auth-simple-layout.tsx](file://resources/js/layouts/auth/auth-simple-layout.tsx)
- [auth-split-layout.tsx](file://resources/js/layouts/auth/auth-split-layout.tsx)
- [package.json](file://package.json)
- [vite.config.ts](file://vite.config.ts)
</cite>

## Update Summary
**Changes Made**
- Updated color palette implementation with new oklch color tokens
- Added comprehensive Magic UI components integration
- Enhanced component architecture with token-driven theming
- Expanded layout system with themed authentication experiences
- Updated implementation plan with new scope and dependencies

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Current State Analysis](#current-state-analysis)
4. [Refactor Design Principles](#refactor-design-principles)
5. [Color Palette Implementation](#color-palette-implementation)
6. [Animation System](#animation-system)
7. [Component Architecture](#component-architecture)
8. [Layout System](#layout-system)
9. [Implementation Plan](#implementation-plan)
10. [Technical Specifications](#technical-specifications)
11. [Quality Assurance](#quality-assurance)
12. [Conclusion](#conclusion)

## Introduction

This document outlines a comprehensive UI/UX refactoring initiative for the Kepegawaian (Government Employee Management) application. The project aims to transform the current monochromatic, neutral design into a professional, government-appropriate interface featuring a sophisticated color palette centered around Forest Green (#1A5D1A), Gold (#D4AF37), and Orange (#FF8C00) accents. The refactoring follows a token-first approach using CSS variables for consistent theming across all components.

The application serves government institutions (ASN/PNS) and requires a formal yet modern aesthetic that maintains accessibility standards while incorporating subtle animations from the Magic UI library. This design system emphasizes performance, accessibility compliance, and maintainability through standardized component architecture.

## Project Structure

The Kepegawaian application follows a modern Laravel + React architecture with the following key structural components:

```mermaid
graph TB
subgraph "Frontend Layer"
A[React Application] --> B[Pages]
A --> C[Components]
A --> D[Hooks]
A --> E[Layouts]
A --> F[Types]
end
subgraph "Styling System"
G[CSS Variables] --> H[Tailwind CSS v4]
G --> I[Custom Styles]
G --> J[Animation Tokens]
end
subgraph "Backend Integration"
K[Laravel Backend] --> L[API Routes]
K --> M[IAM System]
K --> N[Database Models]
end
A --> G
C --> G
B --> K
E --> K
```

**Diagram sources**
- [app.tsx:1-36](file://resources/js/app.tsx#L1-L36)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)

The frontend architecture consists of:
- **Pages**: Route-specific React components organized by feature domains
- **Components**: Reusable UI primitives following the shadcn/ui design system
- **Hooks**: Custom React hooks for state management and browser APIs
- **Layouts**: Container components for different page types (auth, app, settings)
- **Types**: TypeScript definitions for type safety across the application

**Section sources**
- [app.tsx:1-36](file://resources/js/app.tsx#L1-L36)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)

## Current State Analysis

### Existing Design Issues

The current application exhibits several design limitations that necessitate the refactoring:

1. **Monochromatic Palette**: The existing design uses neutral tones with hardcoded blue-600 elements, lacking proper brand identity
2. **Missing Animations**: No motion design or interactive feedback mechanisms
3. **Dark Mode Complexity**: Overly complex dark mode implementation with inconsistent variable usage
4. **Hardcoded Colors**: Extensive use of hardcoded color values throughout components
5. **Limited Accessibility**: Insufficient contrast ratios and accessibility considerations

### Component Architecture Assessment

The current component system demonstrates good architectural foundations but requires modernization:

```mermaid
classDiagram
class Button {
+variant : string
+size : string
+asChild : boolean
+buttonVariants() string
}
class Card {
+CardHeader()
+CardContent()
+CardFooter()
+CardTitle()
+CardDescription()
}
class Table {
+TableHeader()
+TableBody()
+TableRow()
+TableHead()
+TableCell()
}
class Dialog {
+DialogTrigger()
+DialogContent()
+DialogOverlay()
+DialogTitle()
+DialogDescription()
}
Button --> "uses" CSSVariables
Card --> "uses" CSSVariables
Table --> "uses" CSSVariables
Dialog --> "uses" CSSVariables
```

**Diagram sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [table.tsx:1-115](file://resources/js/components/ui/table.tsx#L1-L115)
- [dialog.tsx:1-134](file://resources/js/components/ui/dialog.tsx#L1-L134)

**Section sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [table.tsx:1-115](file://resources/js/components/ui/table.tsx#L1-L115)
- [dialog.tsx:1-134](file://resources/js/components/ui/dialog.tsx#L1-L134)

## Refactor Design Principles

### Core Design Philosophy

The refactoring adheres to five fundamental principles:

1. **Formal but Modern**: Government applications require professionalism without sacrificing contemporary user experience
2. **Consistency via Tokens**: All colors derived from CSS variables for uniform theming
3. **Accessibility First**: WCAG AA compliance with minimum 4.5:1 contrast ratios
4. **Performance Conscious**: Lazy-loaded animations with reduced-motion support
5. **YAGNI Principle**: No feature additions, pure visual enhancement

### Color System Architecture

The new color system implements a hierarchical approach with clear semantic meaning:

```mermaid
flowchart TD
A[Base Color Tokens] --> B[Primary System]
A --> C[Accent System]
A --> D[Semantic System]
B --> E[Forest Green - Primary]
B --> F[Secondary - Background]
B --> G[Muted - Cards]
C --> H[Gold - Accent]
C --> I[Orange - Warning]
D --> J[Destructive - Error]
D --> K[Border - Separators]
D --> L[Ring - Focus States]
E --> M[Brand Identity]
H --> N[Important Highlights]
I --> O[Critical Alerts]
J --> P[Error States]
```

**Diagram sources**
- [2026-04-15-ui-refactor-design.md:20-64](file://docs/plans/2026-04-15-ui-refactor-design.md#L20-L64)

### Animation Strategy

The Magic UI integration provides a curated set of micro-interactions:

| Component | Purpose | Animation Type | Trigger |
|-----------|---------|----------------|---------|
| Shimmer Button | Primary CTAs | Shimmer effect | Hover/Active |
| Text Shimmer | Hero titles | Subtle shimmer | Page load |
| Blur In | Content reveal | Smooth fade | Viewport entry |
| Fade In | Stat cards | Staggered appear | Scroll trigger |
| Animated Number | Counters | Numeric tween | Value change |
| Border Beam | Urgent cards | Moving beam | Persistent |
| Shine Border | Important elements | Gentle glow | Focus |

**Section sources**
- [2026-04-15-ui-refactor-design.md:65-100](file://docs/plans/2026-04-15-ui-refactor-design.md#L65-L100)

## Color Palette Implementation

### CSS Variable System

The refactored color system replaces the existing monochrome palette with a comprehensive token-based approach:

| Token Category | CSS Variable | OKLCH Values | Usage Context |
|---------------|--------------|--------------|---------------|
| Primary System | `--primary` | `oklch(0.32 0.10 155)` | Main buttons, links, active states |
| Primary Foreground | `--primary-fg` | `oklch(0.98 0 0)` | Text on primary backgrounds |
| Secondary System | `--secondary` | `oklch(0.95 0.02 155)` | Alternative backgrounds |
| Accent System | `--accent` | `oklch(0.72 0.16 75)` | Gold highlights, secondary CTAs |
| Accent Foreground | `--accent-fg` | `oklch(0.25 0.05 60)` | Text on accent backgrounds |
| Semantic Destructive | `--destructive` | `oklch(0.577 0.245 27)` | Error states (unchanged) |
| Background System | `--background` | `oklch(0.99 0 0)` | Main page backgrounds |
| Foreground System | `--foreground` | `oklch(0.15 0.02 155)` | Primary text |

### Chart Color Tokens

Specialized chart colors designed for data visualization:

| Chart Token | OKLCH Values | Usage |
|-------------|--------------|-------|
| `--chart-1` | `oklch(0.45 0.12 155)` | Forest Green |
| `--chart-2` | `oklch(0.72 0.16 75)` | Gold |
| `--chart-3` | `oklch(0.65 0.18 55)` | Orange |
| `--chart-4` | `oklch(0.50 0.10 195)` | Teal |
| `--chart-5` | `oklch(0.55 0.10 40)` | Brown |

### Custom Color Variables

Additional convenience variables for direct usage:

```css
--gold: oklch(0.72 0.16 75);
--orange: oklch(0.65 0.18 55);
--green-dark: oklch(0.18 0.06 155);
```

**Section sources**
- [app.css:64-98](file://resources/css/app.css#L64-L98)
- [2026-04-15-ui-refactor-design.md:20-56](file://docs/plans/2026-04-15-ui-refactor-design.md#L20-L56)

## Animation System

### Magic UI Integration

The refactoring introduces a carefully selected subset of Magic UI components optimized for government use cases:

```mermaid
sequenceDiagram
participant User as User Interaction
participant Component as UI Component
participant Animation as Magic UI
participant Theme as CSS Variables
User->>Component : Hover/Click/Scroll
Component->>Animation : Trigger animation
Animation->>Theme : Access color tokens
Theme-->>Animation : oklch values
Animation-->>Component : Render smooth transition
Component-->>User : Enhanced visual feedback
```

**Diagram sources**
- [2026-04-15-ui-refactor-design.md:65-80](file://docs/plans/2026-04-15-ui-refactor-design.md#L65-L80)

### Animation Placement Strategy

| Page Type | Key Animations | Purpose |
|-----------|----------------|---------|
| Welcome Page | Particles, Shimmer, Blur In | Brand introduction, engagement |
| Dashboard | Animated Numbers, Border Beam | Data emphasis, urgency |
| Auth Pages | Blur In Staggered | Form focus, loading states |
| Navigation | Smooth Transitions | User orientation |

### Performance Optimizations

- **Viewport-based triggering**: Animations only activate when elements are visible
- **Reduced Motion Support**: Automatic adaptation for users with motion sensitivity
- **Hardware Acceleration**: CSS transforms and opacity for smooth performance
- **Lazy Loading**: Animation components loaded on demand

**Section sources**
- [2026-04-15-ui-refactor-design.md:81-100](file://docs/plans/2026-04-15-ui-refactor-design.md#L81-L100)

## Component Architecture

### Token-Driven Theming

All UI components receive their styling from CSS variables, ensuring consistency across the entire application:

```mermaid
graph LR
A[CSS Variables] --> B[Component Styles]
A --> C[Animation Effects]
A --> D[Chart Visualizations]
B --> E[Button Variants]
B --> F[Card States]
B --> G[Table Interactions]
C --> H[Shimmer Effects]
C --> I[Blur Transitions]
C --> J[Fade Animations]
D --> K[Data Visualization]
D --> L[Progress Indicators]
```

**Diagram sources**
- [button.tsx:7-39](file://resources/js/components/ui/button.tsx#L7-L39)
- [card.tsx:5-16](file://resources/js/components/ui/card.tsx#L5-L16)
- [table.tsx:5-18](file://resources/js/components/ui/table.tsx#L5-L18)

### Component Enhancement Strategy

Each component category receives targeted improvements:

#### Interactive Elements
- Buttons: Enhanced hover states with shimmer effects
- Inputs: Improved focus states with accent highlighting
- Select components: Refined dropdown animations

#### Content Containers
- Cards: Subtle shadows and accent borders
- Tables: Hover states with accent backgrounds
- Dialogs: Smooth transitions with backdrop effects

#### Navigation Components
- Sidebar: Dark green theme with gold accents
- Breadcrumbs: Subtle separators with accent indicators
- Pagination: Enhanced visual hierarchy

**Section sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [input.tsx:1-22](file://resources/js/components/ui/input.tsx#L1-L22)
- [select.tsx:1-194](file://resources/js/components/ui/select.tsx#L1-L194)

## Layout System

### Authentication Layouts

The refactoring enhances authentication experiences with themed backgrounds and improved visual hierarchy:

```mermaid
flowchart TD
A[Auth Layout Base] --> B[Simple Layout]
A --> C[Split Layout]
A --> D[Card Layout]
B --> E[Centered Form]
C --> F[Left Panel + Form]
D --> G[Modern Card Design]
E --> H[Background: Soft Green Gradient]
F --> G
G --> I[Gold Accent Borders]
H --> J[Responsive Design]
I --> J
J --> K[Accessibility Compliant]
```

**Diagram sources**
- [auth-simple-layout.tsx:1-39](file://resources/js/layouts/auth/auth-simple-layout.tsx#L1-L39)
- [auth-split-layout.tsx:1-45](file://resources/js/layouts/auth/auth-split-layout.tsx#L1-L45)

### Application Shell

The main application shell receives comprehensive styling updates:

- **Sidebar**: Dark green theme with gold accent for active states
- **Header**: Clean typography with accent branding
- **Content Areas**: Consistent spacing and visual hierarchy
- **Responsive Design**: Mobile-first approach with tablet/desktop adaptations

**Section sources**
- [auth-simple-layout.tsx:1-39](file://resources/js/layouts/auth/auth-simple-layout.tsx#L1-L39)
- [auth-split-layout.tsx:1-45](file://resources/js/layouts/auth/auth-split-layout.tsx#L1-L45)
- [app-sidebar.tsx:1-162](file://resources/js/components/app-sidebar.tsx#L1-L162)

## Implementation Plan

### Phase 1: Foundation Setup (Weeks 1-2)

**File Modifications:**
- Update `resources/css/app.css` with new color tokens
- Remove dark mode complexity while maintaining light mode
- Establish animation framework foundation

**Key Deliverables:**
- Complete CSS variable migration
- Animation system initialization
- Component token integration

### Phase 2: Component Enhancement (Weeks 3-4)

**File Modifications:**
- Update all UI components to use new tokens
- Implement Magic UI integration
- Enhance interactive states

**Key Deliverables:**
- Fully themed component library
- Animation-enabled interactive elements
- Consistent visual hierarchy

### Phase 3: Layout Transformation (Weeks 5-6)

**File Modifications:**
- Redesign authentication layouts
- Update application shell styling
- Implement responsive enhancements

**Key Deliverables:**
- Themed authentication experiences
- Professional application shell
- Mobile-responsive design system

### Phase 4: Testing & Optimization (Weeks 7-8)

**Testing Focus:**
- Accessibility compliance verification
- Cross-browser compatibility testing
- Performance optimization review
- User experience validation

**Section sources**
- [2026-04-15-ui-refactor-design.md:100-147](file://docs/plans/2026-04-15-ui-refactor-design.md#L100-L147)

## Technical Specifications

### Dependencies Management

The refactoring introduces strategic new dependencies while maintaining existing infrastructure:

```mermaid
graph TB
subgraph "Core Dependencies"
A[React 19.2.0] --> B[TypeScript 5.7.2]
C[@inertiajs/react 2.3.7] --> D[Radix UI Primitives]
end
subgraph "Styling System"
E[Tailwind CSS v4.0.0] --> F[CSS Variables]
G[Class Variance Authority] --> H[Component Variants]
end
subgraph "Animation System"
I[magicui ^latest] --> J[Shadcn Components]
K[framer-motion] --> L[Animation Engine]
end
subgraph "Build Tools"
M[Vite 7.0.4] --> N[Laravel Vite Plugin]
O[ESLint + Prettier] --> P[Code Quality]
end
```

**Diagram sources**
- [package.json:33-78](file://package.json#L33-L78)

### Build Configuration

The Vite configuration supports the new architecture with optimized build processes:

- **Development**: Fast HMR with React compiler optimization
- **Production**: Tree-shaking and minification for optimal performance
- **SSR**: Server-side rendering compatibility
- **Wayfinder**: Enhanced routing capabilities

### Browser Compatibility

The refactoring maintains broad browser support while leveraging modern CSS features:

- **Modern Browsers**: Full feature support with CSS custom properties
- **Legacy Support**: Graceful degradation for older browsers
- **Progressive Enhancement**: Feature detection for advanced animations

**Section sources**
- [package.json:1-78](file://package.json#L1-L78)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)

## Quality Assurance

### Accessibility Compliance

The refactored design prioritizes accessibility through:

- **WCAG AA Compliance**: Minimum 4.5:1 contrast ratios across all color combinations
- **Keyboard Navigation**: Full keyboard accessibility for all interactive elements
- **Screen Reader Support**: Proper ARIA attributes and semantic markup
- **Motion Sensitivity**: Reduced motion mode support for sensitive users

### Performance Metrics

Target performance benchmarks for the refactored application:

- **First Contentful Paint**: Under 2 seconds on 3G connections
- **Largest Contentful Paint**: Under 2.5 seconds
- **Cumulative Layout Shift**: Under 0.1
- **Bundle Size**: Optimized through tree-shaking and lazy loading

### Testing Strategy

Comprehensive testing approach covering:

- **Visual Regression**: Automated comparison of before/after states
- **Cross-browser Testing**: Validation across major browser versions
- **Performance Testing**: Load testing under various conditions
- **User Acceptance Testing**: Stakeholder validation of design changes

## Conclusion

The UI/UX refactoring represents a comprehensive modernization of the Kepegawaian application, transforming it from a functional but visually basic interface into a professional, government-appropriate digital platform. The token-first approach ensures maintainability and consistency, while the carefully selected animation system enhances user experience without compromising accessibility.

The implementation follows established design principles and technical standards, providing a solid foundation for future enhancements while meeting the specific needs of government institutions. The refactored system balances formal aesthetics with contemporary user expectations, creating an interface that is both trustworthy and engaging.

Through systematic implementation phases, rigorous quality assurance, and stakeholder collaboration, this refactoring initiative will deliver a modernized user experience that serves the needs of ASN/PNS professionals while maintaining the highest standards of accessibility and performance.