# Styling and Theming

<cite>
**Referenced Files in This Document**
- [app.css](file://resources/css/app.css)
- [vite.config.ts](file://vite.config.ts)
- [package.json](file://package.json)
- [use-appearance.tsx](file://resources/js/hooks/use-appearance.tsx)
- [appearance-tabs.tsx](file://resources/js/components/appearance-tabs.tsx)
- [appearance.tsx](file://resources/js/pages/settings/appearance.tsx)
- [utils.ts](file://resources/js/lib/utils.ts)
- [button.tsx](file://resources/js/components/ui/button.tsx)
- [card.tsx](file://resources/js/components/ui/card.tsx)
</cite>

## Update Summary
**Changes Made**
- Updated design system architecture to reflect the new hijau-gold-orange color palette with comprehensive oklch color specifications
- Enhanced CSS variable system with specialized chart color tokens and sidebar color scales
- Documented the complete color scale definitions for backgrounds, foregrounds, cards, popovers, and sidebar elements
- Updated theming system documentation to reflect the unified color scheme approach
- Added comprehensive color token documentation with detailed oklch specifications

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
This document explains the styling and theming system built with Tailwind CSS v4 and integrated into the frontend stack. The system implements a comprehensive token-first design approach with a carefully curated hijau-gold-orange color palette featuring dark green primary tones, gold accents, and orange highlights. The architecture emphasizes design token consistency, Magic UI component integration, and performance optimization through modern CSS techniques and React-based component composition.

**Updated** The theming system now operates on a unified approach with a complete color scale definition, removing the previous light/dark mode complexity in favor of a consistent design language across all interfaces with specialized color tokens for charts and sidebar elements.

## Project Structure
The styling system centers around a token-driven architecture with centralized design tokens, Magic UI component integration, and optimized build pipeline for modern CSS generation and animation support.

```mermaid
graph TB
subgraph "CSS Layer"
A["resources/css/app.css<br/>Token-first design system, hijau-gold-orange palette,<br/>oklch color specifications, chart tokens"]
end
subgraph "Build Pipeline"
B["vite.config.ts<br/>Plugins: laravel-vite-plugin, @tailwindcss/vite,<br/>react, wayfinder, babel compiler"]
C["package.json<br/>Tailwind CSS v4, magicui, tw-animate-css,<br/>react ecosystem dependencies"]
end
subgraph "React Runtime"
D["resources/js/hooks/use-appearance.tsx<br/>Theme provider and persistence<br/>(light mode only)"]
E["resources/js/components/appearance-tabs.tsx<br/>Theme selector UI<br/>(light mode only)"]
F["resources/js/pages/settings/appearance.tsx<br/>Appearance settings page<br/>(light mode only)"]
G["resources/js/lib/utils.ts<br/>Utility: cn() merging, clsx integration"]
H["resources/js/components/ui/button.tsx<br/>CVA-based component with<br/>Magic UI enhancements"]
I["resources/js/components/ui/card.tsx<br/>Semantic layout component<br/>with token integration"]
end
A --> B
C --> B
D --> E
E --> F
G --> H
G --> I
D --> A
```

**Diagram sources**
- [app.css:1-146](file://resources/css/app.css#L1-L146)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)
- [package.json:1-79](file://package.json#L1-L79)
- [use-appearance.tsx:1-116](file://resources/js/hooks/use-appearance.tsx#L1-L116)
- [appearance-tabs.tsx:1-46](file://resources/js/components/appearance-tabs.tsx#L1-L46)
- [appearance.tsx:1-36](file://resources/js/pages/settings/appearance.tsx#L1-L36)
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)

**Section sources**
- [app.css:1-146](file://resources/css/app.css#L1-L146)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)
- [package.json:1-79](file://package.json#L1-L79)

## Core Components
- **Enhanced Token-First Design System**: Comprehensive CSS variable architecture with hijau-gold-orange oklch-based color tokens, complete sidebar color scales, and specialized chart color palettes
- **Magic UI Integration**: Advanced animation components including shimmer effects, blur transitions, animated numbers, and particle systems
- **Component Primitive Library**: CVA-based UI components with semantic structure, accessibility compliance, and design token integration
- **Animation Framework**: tw-animate-css integration with custom animation utilities for smooth micro-interactions
- **Utility Layer**: Enhanced class merging with clsx and tailwind-merge for conflict-free composition

**Updated** The system now operates with a unified color scheme eliminating the previous light/dark mode complexity in favor of consistent design token application with comprehensive color scale definitions.

**Section sources**
- [app.css:10-146](file://resources/css/app.css#L10-L146)
- [package.json:33-67](file://package.json#L33-L67)
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)

## Architecture Overview
The design system architecture implements a token-first approach with centralized design tokens, Magic UI component integration, and optimized animation delivery through tw-animate-css.

```mermaid
sequenceDiagram
participant User as "User Interface"
participant Tokens as "Design Tokens"
participant Components as "UI Components"
participant Animations as "Magic UI Effects"
User->>Tokens : Request styled component
Tokens->>Components : Apply CSS variables
Components->>Animations : Trigger micro-interactions
Animations-->>User : Smooth transitions and effects
```

**Diagram sources**
- [app.css:10-62](file://resources/css/app.css#L10-L62)
- [button.tsx:7-39](file://resources/js/components/ui/button.tsx#L7-L39)
- [utils.ts:6-8](file://resources/js/lib/utils.ts#L6-L8)

## Detailed Component Analysis

### Enhanced Token-First Design System Architecture
The system implements a comprehensive token architecture with hijau-gold-orange oklch-based color spaces, complete sidebar color scales, and specialized chart color palettes.

**Enhanced Design Token Categories**:
- **Primary Color Tokens**: Hijau (dark green), Gold, Orange, Background, Foreground, Muted, Destructive
- **Sidebar Color Tokens**: Complete sidebar color scale with primary, accent, and border variations
- **Chart Color Tokens**: 5-color categorical palette specifically designed for data visualization
- **Typography Tokens**: Instrument Sans font stack, consistent scale ratios, and semantic text roles
- **Spacing Tokens**: Consistent unit system with logical relationships (base 4px grid)
- **Border Radius Tokens**: Multi-scale radius system (base 0.625rem with variants)
- **Shadow Tokens**: Subtle elevation system with consistent blur radii

**Enhanced Color Palette Implementation**:
- **Hijau (Primary)**: oklch(0.32 0.10 155) - Dark green base with high contrast
- **Gold (Accent)**: oklch(0.72 0.16 75) - Warm gold for highlights and emphasis
- **Orange (Accent)**: oklch(0.65 0.18 55) - Rich orange for warnings and urgency
- **Background**: oklch(0.99 0.002 155) - Pure white in light theme
- **Foreground**: oklch(0.15 0.02 155) - Deep gray in light theme
- **Sidebar Colors**: Complete color scale from oklch(0.18 0.06 155) to oklch(0.92 0.02 155)

**Specialized Chart Color Tokens**:
- **Chart-1**: oklch(0.45 0.12 155) - Dark green for primary data series
- **Chart-2**: oklch(0.72 0.16 75) - Gold for secondary data series
- **Chart-3**: oklch(0.65 0.18 55) - Orange for tertiary data series
- **Chart-4**: oklch(0.50 0.10 195) - Teal for quaternary data series
- **Chart-5**: oklch(0.55 0.10 40) - Brown for quinary data series

**Section sources**
- [app.css:67-104](file://resources/css/app.css#L67-L104)

### Magic UI Component Integration
The system integrates advanced animation components through the magicui package, providing sophisticated micro-interactions and visual effects.

**Available Magic UI Components**:
- **Shimmer Button**: Animated gradient effect for prominent actions
- **Blur In**: Smooth fade-in with blur transition
- **Animated Number**: Digit counting animations for statistics
- **Border Beam**: Animated border highlighting effects
- **Particles**: Interactive particle systems for backgrounds
- **Text Shimmer**: Text-based shimmer animations
- **Fade In**: Simple opacity transitions

**Integration Approach**:
- Components are imported individually for tree-shaking benefits
- Integrated with the existing CVA component architecture
- Compatible with the token-first design system
- Optimized for performance with lazy loading

**Section sources**
- [package.json:133-135](file://package.json#L133-L135)

### Component Primitive Library
The UI component library follows CVA patterns with semantic structure, accessibility compliance, and design token integration.

**Button Component Features**:
- Variant system: default, destructive, outline, secondary, ghost, link
- Size variants: default, xs, sm, lg, icon variants
- As-child pattern support via Radix Slot
- Focus-visible ring with ring token integration
- SVG sizing semantics with consistent spacing
- Disabled state handling with opacity scaling

**Card Component Architecture**:
- Semantic slot structure: header, title, description, content, footer
- Consistent spacing with token-based padding and gaps
- Border and shadow system with card token integration
- Responsive design with flexible content areas

**Accessibility Features**:
- Proper ARIA attributes and roles
- Focus management and keyboard navigation
- Color contrast compliance with WCAG guidelines
- Reduced motion support through prefers-reduced-motion

**Section sources**
- [button.tsx:7-39](file://resources/js/components/ui/button.tsx#L7-L39)
- [card.tsx:5-65](file://resources/js/components/ui/card.tsx#L5-L65)

### Animation and Micro-Interaction Framework
The system leverages tw-animate-css for smooth, performant animations that complement the Magic UI components.

**Animation Categories**:
- **Entrance Animations**: Fade in, slide up/down, scale transitions
- **State Changes**: Hover effects, focus rings, active states
- **Data Updates**: Counting numbers, progress indicators
- **Interactive Feedback**: Button press, form validation, loading states

**Performance Optimizations**:
- Hardware acceleration through transform properties
- Efficient animation timing with CSS variables
- Reduced motion compatibility
- Tree shaking for unused animations

**Section sources**
- [app.css:3](file://resources/css/app.css#L3)
- [package.json:64](file://package.json#L64)

## Dependency Analysis
The styling pipeline integrates modern CSS processing, React component architecture, and Magic UI animation system with optimized build configurations.

```mermaid
graph LR
Pkg["package.json<br/>Tailwind CSS v4, magicui, tw-animate-css,<br/>react ecosystem, babel compiler"] --> ViteCfg["vite.config.ts<br/>Plugins: laravel-vite-plugin, @tailwindcss/vite,<br/>react with compiler, wayfinder"]
ViteCfg --> CSS["resources/css/app.css<br/>Enhanced token system, base styles,<br/>animation utilities"]
ViteCfg --> JSX["React components<br/>UI primitives, hooks, pages,<br/>Magic UI integration"]
JSX --> CSS
```

**Diagram sources**
- [package.json:1-79](file://package.json#L1-L79)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)
- [app.css:1-146](file://resources/css/app.css#L1-L146)

**Section sources**
- [package.json:1-79](file://package.json#L1-L79)
- [vite.config.ts:1-28](file://vite.config.ts#L1-L28)

## Performance Considerations
- **Token Efficiency**: CSS variables reduce duplication and enable runtime theme switching without rebuilds
- **Animation Optimization**: Hardware-accelerated transforms and efficient timing functions
- **Bundle Size**: Individual Magic UI component imports prevent unused code inclusion
- **Build Optimization**: Lightning CSS and modern bundling minimize payload size
- **Tree Shaking**: Strategic imports ensure only used animations and components are included
- **Critical Rendering**: Essential styles extracted separately for optimal first paint

**Updated** Performance considerations now emphasize the streamlined token system and Magic UI optimizations rather than theme switching overhead, with enhanced color token efficiency.

## Troubleshooting Guide
- **Token values not applying**:
  - Verify CSS variable declarations in :root and .dark selectors
  - Check for proper token naming conventions (--color-* pattern)
  - Ensure @theme block contains all required token definitions
- **Magic UI components not animating**:
  - Confirm component imports are individual rather than bulk imports
  - Verify tw-animate-css is properly configured in Tailwind
  - Check for animation duration and timing conflicts
- **Animation performance issues**:
  - Use transform-based animations instead of layout-affecting properties
  - Implement reduced motion compatibility checks
  - Optimize animation frequency and duration
- **Build errors with new components**:
  - Ensure proper TypeScript definitions are available
  - Verify component registration in appropriate directories
  - Check for circular dependency issues

**Section sources**
- [app.css:10-62](file://resources/css/app.css#L10-L62)
- [package.json:133-135](file://package.json#L133-L135)

## Conclusion
The styling and theming system represents a comprehensive token-first approach with enhanced hijau-gold-orange color palette integration, Magic UI animation system, delivering consistent design language, smooth animations, and optimal performance. The unified color scheme with complete sidebar and chart color scales eliminates theme complexity while maintaining design flexibility through the extensive token system. Following the established patterns ensures maintainable component development and consistent user experiences across all interfaces.

## Appendices

### Guidelines for Maintaining Design Consistency
- **Token Usage**: Always reference CSS variables for colors, spacing, and typography
- **Component Composition**: Use CVA patterns and the cn() utility for safe class merging
- **Animation Standards**: Leverage tw-animate-css for micro-interactions and Magic UI components
- **Accessibility First**: Ensure all components meet WCAG guidelines with proper contrast ratios
- **Performance Budget**: Monitor bundle size and animation performance regularly

**Section sources**
- [app.css:10-62](file://resources/css/app.css#L10-L62)
- [button.tsx:7-39](file://resources/js/components/ui/button.tsx#L7-L39)
- [utils.ts:6-8](file://resources/js/lib/utils.ts#L6-L8)

### Creating Custom Components with Tailwind
- **Design Token Integration**: Reference --color-* variables for consistent theming
- **CVA Pattern Implementation**: Define variant and size scales with clear defaults
- **Magic UI Compatibility**: Ensure components work with animation utilities
- **Accessibility Compliance**: Include proper ARIA attributes and keyboard navigation
- **Performance Optimization**: Minimize DOM complexity and use efficient CSS properties

**Section sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)

### Extending the Design System
- **Token Expansion**: Add new CSS variables to the @theme block and :root selectors
- **Component Variants**: Extend CVA definitions with new variant categories
- **Animation Libraries**: Integrate additional tw-animate-css utilities as needed
- **Magic UI Integration**: Import specific components for targeted functionality
- **Pattern Development**: Create reusable component compositions from primitives

**Section sources**
- [app.css:10-62](file://resources/css/app.css#L10-L62)
- [button.tsx:10-32](file://resources/js/components/ui/button.tsx#L10-L32)
- [package.json:133-135](file://package.json#L133-L135)

### Enhanced Color System Reference
**Primary Palette**:
- **Hijau (Primary)**: oklch(0.32 0.10 155) - Dark green (#528540)
- **Hijau Foreground**: oklch(0.98 0 0) - White (#FFFFFF)

**Accent Colors**:
- **Gold**: oklch(0.72 0.16 75) - Warm gold (#B8B880)
- **Gold Foreground**: oklch(0.25 0.05 60) - Dark green (#404020)
- **Orange**: oklch(0.65 0.18 55) - Rich orange (#A5A56A)
- **Orange Foreground**: oklch(0.25 0.05 60) - Dark green (#404020)

**Neutral System**:
- **Background**: oklch(0.99 0.002 155) - Pure white (#FCFCFC)
- **Foreground**: oklch(0.15 0.02 155) - Deep gray (#242424)
- **Muted**: oklch(0.96 0.01 155) - Light neutral (#F0F0F0)
- **Muted Foreground**: oklch(0.50 0.02 155) - Medium gray (#808080)

**Sidebar Color Scale**:
- **Sidebar**: oklch(0.18 0.06 155) - Dark green (#2D452D)
- **Sidebar Foreground**: oklch(0.92 0.02 155) - Light gray (#E8E8E8)
- **Sidebar Primary**: oklch(0.78 0.15 80) - Medium green (#C8C890)
- **Sidebar Primary Foreground**: oklch(0.18 0.06 155) - Dark green (#2D452D)
- **Sidebar Accent**: oklch(0.25 0.07 155) - Very dark green (#404020)
- **Sidebar Accent Foreground**: oklch(0.92 0.02 155) - Light gray (#E8E8E8)
- **Sidebar Border**: oklch(0.28 0.06 155) - Dark green (#48482D)
- **Sidebar Ring**: oklch(0.50 0.10 155) - Medium gray (#808080)

**Chart Color Tokens**:
- **Chart-1**: oklch(0.45 0.12 155) - Dark green (#739660)
- **Chart-2**: oklch(0.72 0.16 75) - Gold (#B8B880)
- **Chart-3**: oklch(0.65 0.18 55) - Orange (#A5A56A)
- **Chart-4**: oklch(0.50 0.10 195) - Teal (#80A0A0)
- **Chart-5**: oklch(0.55 0.10 40) - Brown (#8A7560)

**Custom Color Variables**:
- **Gold**: oklch(0.72 0.16 75) - Gold (#B8B880)
- **Orange**: oklch(0.65 0.18 55) - Orange (#A5A56A)
- **Green-Dark**: oklch(0.18 0.06 155) - Dark green (#2D452D)

**Section sources**
- [app.css:67-104](file://resources/css/app.css#L67-L104)