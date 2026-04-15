# React Component System

<cite>
**Referenced Files in This Document**
- [button.tsx](file://resources/js/components/ui/button.tsx)
- [input.tsx](file://resources/js/components/ui/input.tsx)
- [card.tsx](file://resources/js/components/ui/card.tsx)
- [table.tsx](file://resources/js/components/ui/table.tsx)
- [dialog.tsx](file://resources/js/components/ui/dialog.tsx)
- [crud-form-card.tsx](file://resources/js/components/kepegawaian/crud-form-card.tsx)
- [crud-table.tsx](file://resources/js/components/kepegawaian/crud-table.tsx)
- [data-table-toolbar.tsx](file://resources/js/components/kepegawaian/data-table-toolbar.tsx)
- [multi-step-form.tsx](file://resources/js/components/kepegawaian/multi-step-form.tsx)
- [enum-select.tsx](file://resources/js/components/kepegawaian/enum-select.tsx)
- [pegawai-create.tsx](file://resources/js/pages/kepegawaian/pegawai/create.tsx)
- [pegawai-index.tsx](file://resources/js/pages/kepegawaian/pegawai/index.tsx)
- [app-shell.tsx](file://resources/js/components/app-shell.tsx)
- [utils.ts](file://resources/js/lib/utils.ts)
- [ui-types.ts](file://resources/js/types/ui.ts)
</cite>

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
This document describes the React Component System used in the kepegawaian application. It focuses on the modular component architecture built with Radix UI primitives and custom kepegawaian-specific components. The system emphasizes composability, reusability, and accessibility through shared UI primitives (buttons, inputs, cards, tables, dialogs), and domain-specific building blocks (CRUD form cards, data tables, multi-step forms, and employee tabs). Practical usage patterns, prop interfaces, state management integration via Inertia, and accessibility features are covered to guide consistent extension and maintenance of the component library.

## Project Structure
The component system is organized by feature domains:
- UI primitives under resources/js/components/ui (Radix-based wrappers and variants)
- Kepegawaian-specific components under resources/js/components/kepegawaian
- Page-level usage under resources/js/pages
- Shared utilities under resources/js/lib/utils.ts
- Type definitions under resources/js/types

```mermaid
graph TB
subgraph "UI Primitives"
BTN["button.tsx"]
INP["input.tsx"]
CARD["card.tsx"]
TABLE["table.tsx"]
DLG["dialog.tsx"]
end
subgraph "Kepegawaian Components"
CFC["crud-form-card.tsx"]
CTBL["crud-table.tsx"]
DTT["data-table-toolbar.tsx"]
MSF["multi-step-form.tsx"]
ENUMSEL["enum-select.tsx"]
end
subgraph "Pages"
PCREATE["pegawai-create.tsx"]
PINDEX["pegawai-index.tsx"]
end
UTIL["utils.ts"]
TYPES["ui-types.ts"]
PCREATE --> MSF
PCREATE --> ENUMSEL
PCREATE --> INP
PCREATE --> BTN
PINDEX --> DTT
PINDEX --> TABLE
PINDEX --> BTN
CFC --> BTN
CFC --> CARD
CTBL --> TABLE
CTBL --> BTN
DTT --> INP
DTT --> ENUMSEL
DLG -. uses Radix .- BTN
UTIL -. shared helpers .- BTN
UTIL -. shared helpers .- INP
TYPES -. type defs .- PCREATE
```

**Diagram sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [input.tsx:1-22](file://resources/js/components/ui/input.tsx#L1-L22)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [table.tsx:1-115](file://resources/js/components/ui/table.tsx#L1-L115)
- [dialog.tsx:1-134](file://resources/js/components/ui/dialog.tsx#L1-L134)
- [crud-form-card.tsx:1-63](file://resources/js/components/kepegawaian/crud-form-card.tsx#L1-L63)
- [crud-table.tsx:1-96](file://resources/js/components/kepegawaian/crud-table.tsx#L1-L96)
- [data-table-toolbar.tsx:1-119](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L1-L119)
- [multi-step-form.tsx:1-129](file://resources/js/components/kepegawaian/multi-step-form.tsx#L1-L129)
- [enum-select.tsx:1-60](file://resources/js/components/kepegawaian/enum-select.tsx#L1-L60)
- [pegawai-create.tsx:1-603](file://resources/js/pages/kepegawaian/pegawai/create.tsx#L1-L603)
- [pegawai-index.tsx:1-487](file://resources/js/pages/kepegawaian/pegawai/index.tsx#L1-L487)
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)
- [ui-types.ts:1-17](file://resources/js/types/ui.ts#L1-L17)

**Section sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [input.tsx:1-22](file://resources/js/components/ui/input.tsx#L1-L22)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [table.tsx:1-115](file://resources/js/components/ui/table.tsx#L1-L115)
- [dialog.tsx:1-134](file://resources/js/components/ui/dialog.tsx#L1-L134)
- [crud-form-card.tsx:1-63](file://resources/js/components/kepegawaian/crud-form-card.tsx#L1-L63)
- [crud-table.tsx:1-96](file://resources/js/components/kepegawaian/crud-table.tsx#L1-L96)
- [data-table-toolbar.tsx:1-119](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L1-L119)
- [multi-step-form.tsx:1-129](file://resources/js/components/kepegawaian/multi-step-form.tsx#L1-L129)
- [enum-select.tsx:1-60](file://resources/js/components/kepegawaian/enum-select.tsx#L1-L60)
- [pegawai-create.tsx:1-603](file://resources/js/pages/kepegawaian/pegawai/create.tsx#L1-L603)
- [pegawai-index.tsx:1-487](file://resources/js/pages/kepegawaian/pegawai/index.tsx#L1-L487)
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)
- [ui-types.ts:1-17](file://resources/js/types/ui.ts#L1-L17)

## Core Components
This section documents the foundational UI primitives and kepegawaian-specific components that form the backbone of the system.

- Button
  - Purpose: Unified action control with variants and sizes.
  - Variants: default, destructive, outline, secondary, ghost, link.
  - Sizes: default, xs, sm, lg, icon, icon-xs, icon-sm, icon-lg.
  - Accessibility: Focus-visible ring, aria-invalid integration, SVG pointer events preserved.
  - Composition: Uses Slot for asChild pattern to wrap anchor-like elements.

- Input
  - Purpose: Text input with focus-visible ring, disabled states, and aria-invalid support.
  - Accessibility: Inherits focus-visible ring and destructive ring on invalid state.

- Card
  - Purpose: Container with header/title/description/content/footer segments.
  - Composition: Provides semantic slots for structured card layouts.

- Table
  - Purpose: Scrollable responsive table container with header/body/footer/row/cell/head/caption helpers.
  - Accessibility: Hover and selected states; checkbox-aware alignment.

- Dialog
  - Purpose: Modal overlay with portal, close button, and header/footer slots.
  - Accessibility: Overlay animation, close button with screen reader label.

- CRUD Form Card
  - Purpose: Encapsulates form UI with title, description, actions, and processing state.
  - Props: title, description, children, onSubmit, onCancel, submitLabel, cancelLabel, isEditing, processing.

- CRUD Table
  - Purpose: Generic table with edit/delete actions per row and customizable columns.
  - Props: columns (key, header, cell, className), data, onEdit, onDelete, emptyMessage, getItemId.

- Data Table Toolbar
  - Purpose: Search box and filter selectors with clear controls.
  - Props: searchValue, onSearchChange, searchPlaceholder, filters, showClear, onClear, className.

- Multi-Step Form
  - Purpose: Progress-indicated wizard with navigation controls and processing state.
  - Props: steps, currentStep, children, onNext, onPrev, onSubmit, isLastStep, isFirstStep, processing, title.

- Enum Select
  - Purpose: Select component specialized for enum-style options with label/error support.
  - Props: options, value, onChange, placeholder, error, disabled, label, id.

**Section sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [input.tsx:1-22](file://resources/js/components/ui/input.tsx#L1-L22)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [table.tsx:1-115](file://resources/js/components/ui/table.tsx#L1-L115)
- [dialog.tsx:1-134](file://resources/js/components/ui/dialog.tsx#L1-L134)
- [crud-form-card.tsx:1-63](file://resources/js/components/kepegawaian/crud-form-card.tsx#L1-L63)
- [crud-table.tsx:1-96](file://resources/js/components/kepegawaian/crud-table.tsx#L1-L96)
- [data-table-toolbar.tsx:1-119](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L1-L119)
- [multi-step-form.tsx:1-129](file://resources/js/components/kepegawaian/multi-step-form.tsx#L1-L129)
- [enum-select.tsx:1-60](file://resources/js/components/kepegawaian/enum-select.tsx#L1-L60)

## Architecture Overview
The component architecture follows a layered approach:
- Primitive layer: Radix UI-based wrappers with Tailwind-based variants and shared utilities.
- Domain layer: Kepegawaian-specific components that compose primitives for common workflows.
- Page layer: Pages orchestrate state, fetch data, and render domain components.
- Utility layer: Shared helpers for class merging and URL normalization.

```mermaid
graph TB
PRIM["Primitives<br/>button, input, card, table, dialog"]
DOMAIN["Domain Components<br/>crud-form-card, crud-table, data-table-toolbar,<br/>multi-step-form, enum-select"]
PAGE["Pages<br/>pegawai-create, pegawai-index"]
UTIL["Utilities<br/>utils.ts"]
TYPES["Types<br/>ui-types.ts"]
PRIM --> DOMAIN
UTIL --> PRIM
UTIL --> DOMAIN
TYPES --> PAGE
PAGE --> DOMAIN
```

**Diagram sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [input.tsx:1-22](file://resources/js/components/ui/input.tsx#L1-L22)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [table.tsx:1-115](file://resources/js/components/ui/table.tsx#L1-L115)
- [dialog.tsx:1-134](file://resources/js/components/ui/dialog.tsx#L1-L134)
- [crud-form-card.tsx:1-63](file://resources/js/components/kepegawaian/crud-form-card.tsx#L1-L63)
- [crud-table.tsx:1-96](file://resources/js/components/kepegawaian/crud-table.tsx#L1-L96)
- [data-table-toolbar.tsx:1-119](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L1-L119)
- [multi-step-form.tsx:1-129](file://resources/js/components/kepegawaian/multi-step-form.tsx#L1-L129)
- [enum-select.tsx:1-60](file://resources/js/components/kepegawaian/enum-select.tsx#L1-L60)
- [pegawai-create.tsx:1-603](file://resources/js/pages/kepegawaian/pegawai/create.tsx#L1-L603)
- [pegawai-index.tsx:1-487](file://resources/js/pages/kepegawaian/pegawai/index.tsx#L1-L487)
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)
- [ui-types.ts:1-17](file://resources/js/types/ui.ts#L1-L17)

## Detailed Component Analysis

### Button Component
- Implementation highlights
  - Uses class-variance-authority for variant and size variants.
  - Supports asChild via Radix Slot to render anchors while preserving semantics.
  - Focus-visible ring and destructive ring on invalid states.
  - SVG sizing and pointer-event handling for nested icons.
- Accessibility
  - Focus-visible ring and aria-invalid integration.
  - Proper role and labeling for icon-only buttons.

```mermaid
classDiagram
class Button {
+variant : "default|destructive|outline|secondary|ghost|link"
+size : "default|xs|sm|lg|icon|icon-xs|icon-sm|icon-lg"
+asChild : boolean
+className : string
}
```

**Diagram sources**
- [button.tsx:7-39](file://resources/js/components/ui/button.tsx#L7-L39)

**Section sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)

### Input Component
- Implementation highlights
  - Focus-visible ring and destructive ring on invalid state.
  - Disabled state handling and consistent typography.
- Accessibility
  - Inherits focus-visible ring and aria-invalid styling.

```mermaid
classDiagram
class Input {
+type : string
+className : string
}
```

**Diagram sources**
- [input.tsx:5-18](file://resources/js/components/ui/input.tsx#L5-L18)

**Section sources**
- [input.tsx:1-22](file://resources/js/components/ui/input.tsx#L1-L22)

### Card Component Family
- Implementation highlights
  - Structured segments: Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter.
  - Semantic data-slot attributes for testing and styling hooks.
- Composition patterns
  - Used extensively by CRUD Form Card to encapsulate forms.

```mermaid
classDiagram
class Card {
+className : string
}
class CardHeader {
+className : string
}
class CardTitle {
+className : string
}
class CardDescription {
+className : string
}
class CardContent {
+className : string
}
class CardFooter {
+className : string
}
Card --> CardHeader
Card --> CardTitle
Card --> CardDescription
Card --> CardContent
Card --> CardFooter
```

**Diagram sources**
- [card.tsx:5-66](file://resources/js/components/ui/card.tsx#L5-L66)

**Section sources**
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)

### Table Component Family
- Implementation highlights
  - Scrollable container with responsive behavior.
  - Row hover and selection states; checkbox-aware alignment.
- Composition patterns
  - Used by CRUD Table and page-level tables.

```mermaid
classDiagram
class Table {
+className : string
}
class TableHeader {
+className : string
}
class TableBody {
+className : string
}
class TableFooter {
+className : string
}
class TableRow {
+className : string
}
class TableHead {
+className : string
}
class TableCell {
+className : string
}
class TableCaption {
+className : string
}
Table --> TableHeader
Table --> TableBody
Table --> TableFooter
TableHeader --> TableRow
TableBody --> TableRow
TableFooter --> TableRow
TableRow --> TableHead
TableRow --> TableCell
```

**Diagram sources**
- [table.tsx:5-103](file://resources/js/components/ui/table.tsx#L5-L103)

**Section sources**
- [table.tsx:1-115](file://resources/js/components/ui/table.tsx#L1-L115)

### Dialog Component
- Implementation highlights
  - Portal-based overlay with animations.
  - Close button with screen reader label.
- Accessibility
  - Overlay animation, close button with aria-label.

```mermaid
classDiagram
class Dialog {
+open : boolean
}
class DialogTrigger {
+asChild : boolean
}
class DialogPortal
class DialogOverlay {
+className : string
}
class DialogContent {
+className : string
+children : ReactNode
}
class DialogHeader {
+className : string
}
class DialogFooter {
+className : string
}
class DialogTitle {
+className : string
}
class DialogDescription {
+className : string
}
class DialogClose
Dialog --> DialogTrigger
Dialog --> DialogPortal
DialogPortal --> DialogOverlay
DialogPortal --> DialogContent
DialogContent --> DialogHeader
DialogContent --> DialogFooter
DialogContent --> DialogTitle
DialogContent --> DialogDescription
DialogContent --> DialogClose
```

**Diagram sources**
- [dialog.tsx:7-132](file://resources/js/components/ui/dialog.tsx#L7-L132)

**Section sources**
- [dialog.tsx:1-134](file://resources/js/components/ui/dialog.tsx#L1-L134)

### CRUD Form Card
- Purpose: Encapsulate form UI with standardized actions and processing state.
- Props interface
  - title: string
  - description: string
  - children: ReactNode
  - onSubmit: (e: React.FormEvent) => void
  - onCancel?: () => void
  - submitLabel?: string
  - cancelLabel?: string
  - isEditing?: boolean
  - processing?: boolean

```mermaid
sequenceDiagram
participant Page as "Page Component"
participant FormCard as "CrudFormCard"
participant Btn as "Button"
Page->>FormCard : Render with props
FormCard->>Btn : Render Submit Button (disabled if processing)
alt Editing mode
FormCard->>Btn : Render Cancel Button (disabled if processing)
end
Page->>FormCard : Handle submit
FormCard-->>Page : onSubmit(e)
```

**Diagram sources**
- [crud-form-card.tsx:23-62](file://resources/js/components/kepegawaian/crud-form-card.tsx#L23-L62)
- [button.tsx:41-62](file://resources/js/components/ui/button.tsx#L41-L62)

**Section sources**
- [crud-form-card.tsx:1-63](file://resources/js/components/kepegawaian/crud-form-card.tsx#L1-L63)

### CRUD Table
- Purpose: Generic table with edit/delete actions and customizable columns.
- Props interface
  - columns: Array of { key, header, cell(item), className? }
  - data: Array of items
  - onEdit: (item) => void
  - onDelete: (item) => void
  - emptyMessage?: string
  - getItemId: (item) => string

```mermaid
flowchart TD
Start(["Render CRUD Table"]) --> CheckEmpty{"Has data?"}
CheckEmpty --> |No| EmptyRow["Display empty message cell spanning all columns + 1"]
CheckEmpty --> |Yes| MapRows["Map data to rows"]
MapRows --> Cells["Render cells via columns.cell(item)"]
Cells --> Actions["Render Edit/Delete Buttons"]
Actions --> End(["Done"])
EmptyRow --> End
```

**Diagram sources**
- [crud-table.tsx:28-95](file://resources/js/components/kepegawaian/crud-table.tsx#L28-L95)
- [table.tsx:53-90](file://resources/js/components/ui/table.tsx#L53-L90)
- [button.tsx:41-62](file://resources/js/components/ui/button.tsx#L41-L62)

**Section sources**
- [crud-table.tsx:1-96](file://resources/js/components/kepegawaian/crud-table.tsx#L1-L96)

### Data Table Toolbar
- Purpose: Unified search and filter controls with clear action.
- Props interface
  - searchValue: string
  - onSearchChange: (value: string) => void
  - searchPlaceholder?: string
  - filters: Array of { key, label, placeholder, value, options, onChange }
  - showClear: boolean
  - onClear: () => void
  - className?: string

```mermaid
sequenceDiagram
participant Page as "Page Component"
participant Toolbar as "DataTableToolbar"
participant Input as "Input"
participant Select as "Select"
Page->>Toolbar : Provide filters and callbacks
Toolbar->>Input : Bind searchValue and onSearchChange
Toolbar->>Select : Render filter selects with options
Page->>Toolbar : Clear filters
Toolbar-->>Page : onClear()
```

**Diagram sources**
- [data-table-toolbar.tsx:38-118](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L38-L118)
- [input.tsx:5-18](file://resources/js/components/ui/input.tsx#L5-L18)
- [enum-select.tsx:28-59](file://resources/js/components/kepegawaian/enum-select.tsx#L28-L59)

**Section sources**
- [data-table-toolbar.tsx:1-119](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L1-L119)

### Multi-Step Form
- Purpose: Wizard with progress bar and navigation controls.
- Props interface
  - steps: string[]
  - currentStep: number
  - children: React.ReactNode
  - onNext?: () => void
  - onPrev?: () => void
  - onSubmit?: () => void
  - isLastStep: boolean
  - isFirstStep: boolean
  - processing?: boolean
  - title?: string

```mermaid
sequenceDiagram
participant Page as "Page Component"
participant Wizard as "MultiStepForm"
participant Btn as "Button"
Page->>Wizard : Provide steps and callbacks
Wizard->>Btn : Next/Prev/Submit buttons
Page->>Wizard : Navigate steps
Wizard-->>Page : onNext/onPrev/onSubmit
```

**Diagram sources**
- [multi-step-form.tsx:26-128](file://resources/js/components/kepegawaian/multi-step-form.tsx#L26-L128)
- [button.tsx:41-62](file://resources/js/components/ui/button.tsx#L41-L62)

**Section sources**
- [multi-step-form.tsx:1-129](file://resources/js/components/kepegawaian/multi-step-form.tsx#L1-L129)

### Enum Select
- Purpose: Specialized select for enum-style options with label and error messaging.
- Props interface
  - options: Array of { value, label?, name? }
  - value?: string
  - onChange: (value: string) => void
  - placeholder?: string
  - error?: string
  - disabled?: boolean
  - label?: string
  - id?: string

```mermaid
flowchart TD
Start(["Render EnumSelect"]) --> HasLabel{"Has label?"}
HasLabel --> |Yes| Label["Render Label"]
HasLabel --> |No| Select
Label --> Select["Render Select with options"]
Select --> Error{"Has error?"}
Error --> |Yes| ShowError["Render error message"]
Error --> |No| Done(["Done"])
ShowError --> Done
```

**Diagram sources**
- [enum-select.tsx:28-59](file://resources/js/components/kepegawaian/enum-select.tsx#L28-L59)

**Section sources**
- [enum-select.tsx:1-60](file://resources/js/components/kepegawaian/enum-select.tsx#L1-L60)

### Page-Level Usage Examples

#### Employee Create Page
- Composes MultiStepForm, EnumSelect, Input, Textarea, and Select to build a multi-step creation form.
- Integrates with Inertia useForm for state management and submission.
- Demonstrates error display and conditional rendering per step.

```mermaid
sequenceDiagram
participant Page as "PegawaiCreate"
participant Wizard as "MultiStepForm"
participant Step1 as "Step 1 Fields"
participant Step2 as "Step 2 Fields"
participant Step3 as "Step 3 Fields"
participant EnumSel as "EnumSelect"
participant Inertia as "useForm"
Page->>Inertia : Initialize form state
Page->>Wizard : Render with steps and callbacks
Wizard->>Step1 : Render step 1 fields
Step1->>EnumSel : Render enum selects
Step1->>Inertia : Update field values
Page->>Wizard : Navigate steps
Wizard->>Step2 : Render step 2 fields
Wizard->>Step3 : Render step 3 fields
Page->>Inertia : Submit form
```

**Diagram sources**
- [pegawai-create.tsx:44-602](file://resources/js/pages/kepegawaian/pegawai/create.tsx#L44-L602)
- [multi-step-form.tsx:26-128](file://resources/js/components/kepegawaian/multi-step-form.tsx#L26-L128)
- [enum-select.tsx:28-59](file://resources/js/components/kepegawaian/enum-select.tsx#L28-L59)
- [input.tsx:5-18](file://resources/js/components/ui/input.tsx#L5-L18)

**Section sources**
- [pegawai-create.tsx:1-603](file://resources/js/pages/kepegawaian/pegawai/create.tsx#L1-L603)

#### Employee Index Page
- Renders a paginated table with DataTableToolbar for filtering and sorting.
- Uses Badge for status indicators and Button for actions.
- Implements debounced search and filter persistence via Inertia router.

```mermaid
sequenceDiagram
participant Page as "PegawaiIndex"
participant Toolbar as "DataTableToolbar"
participant Table as "Table"
participant Badge as "Badge"
participant Inertia as "router"
Page->>Toolbar : Provide filters and callbacks
Toolbar->>Inertia : Apply filters via router.get
Page->>Table : Render table rows
Table->>Badge : Render status badges
Page->>Inertia : Navigate to show/edit actions
```

**Diagram sources**
- [pegawai-index.tsx:91-486](file://resources/js/pages/kepegawaian/pegawai/index.tsx#L91-L486)
- [data-table-toolbar.tsx:38-118](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L38-L118)
- [table.tsx:5-103](file://resources/js/components/ui/table.tsx#L5-L103)

**Section sources**
- [pegawai-index.tsx:1-487](file://resources/js/pages/kepegawaian/pegawai/index.tsx#L1-L487)

## Dependency Analysis
- Internal dependencies
  - All components depend on shared utilities (cn) for class merging.
  - Pages depend on domain components; domain components depend on primitives.
- External dependencies
  - Radix UI for accessible base components (Dialog, Slot).
  - Lucide icons for UI affordances.
  - Inertia for client-side routing and form state.

```mermaid
graph LR
UTIL["utils.ts"] --> BTN["button.tsx"]
UTIL --> INP["input.tsx"]
UTIL --> CARD["card.tsx"]
UTIL --> TABLE["table.tsx"]
UTIL --> DLG["dialog.tsx"]
UTIL --> CFC["crud-form-card.tsx"]
UTIL --> CTBL["crud-table.tsx"]
UTIL --> DTT["data-table-toolbar.tsx"]
UTIL --> MSF["multi-step-form.tsx"]
UTIL --> ENUMSEL["enum-select.tsx"]
DLG -. uses Radix .- BTN
ENUMSEL -. uses Select .- INP
CFC -. uses Card/Button .- CARD
CTBL -. uses Table/Button .- TABLE
```

**Diagram sources**
- [utils.ts:6-8](file://resources/js/lib/utils.ts#L6-L8)
- [button.tsx:1-6](file://resources/js/components/ui/button.tsx#L1-L6)
- [input.tsx:3-5](file://resources/js/components/ui/input.tsx#L3-L5)
- [card.tsx:3-4](file://resources/js/components/ui/card.tsx#L3-L4)
- [table.tsx:3-4](file://resources/js/components/ui/table.tsx#L3-L4)
- [dialog.tsx:1-6](file://resources/js/components/ui/dialog.tsx#L1-L6)
- [crud-form-card.tsx:2-9](file://resources/js/components/kepegawaian/crud-form-card.tsx#L2-L9)
- [crud-table.tsx:2-10](file://resources/js/components/kepegawaian/crud-table.tsx#L2-L10)
- [data-table-toolbar.tsx:3-12](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L3-L12)
- [multi-step-form.tsx:3-11](file://resources/js/components/kepegawaian/multi-step-form.tsx#L3-L11)
- [enum-select.tsx:2-9](file://resources/js/components/kepegawaian/enum-select.tsx#L2-L9)

**Section sources**
- [utils.ts:1-13](file://resources/js/lib/utils.ts#L1-L13)
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [input.tsx:1-22](file://resources/js/components/ui/input.tsx#L1-L22)
- [card.tsx:1-69](file://resources/js/components/ui/card.tsx#L1-L69)
- [table.tsx:1-115](file://resources/js/components/ui/table.tsx#L1-L115)
- [dialog.tsx:1-134](file://resources/js/components/ui/dialog.tsx#L1-L134)
- [crud-form-card.tsx:1-63](file://resources/js/components/kepegawaian/crud-form-card.tsx#L1-L63)
- [crud-table.tsx:1-96](file://resources/js/components/kepegawaian/crud-table.tsx#L1-L96)
- [data-table-toolbar.tsx:1-119](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L1-L119)
- [multi-step-form.tsx:1-129](file://resources/js/components/kepegawaian/multi-step-form.tsx#L1-L129)
- [enum-select.tsx:1-60](file://resources/js/components/kepegawaian/enum-select.tsx#L1-L60)

## Performance Considerations
- Prefer memoization for derived data (e.g., computed options) to avoid unnecessary renders.
- Debounce search inputs to reduce server requests during typing.
- Use virtualization for very large tables if performance becomes an issue.
- Keep component trees shallow and avoid deep nesting of heavy wrappers.
- Reuse shared utilities (cn) to minimize class computation overhead.

## Troubleshooting Guide
- Button disabled states
  - Ensure processing flag disables submit/cancel buttons to prevent duplicate submissions.
- Input validation
  - Use aria-invalid and destructive ring styles to indicate invalid states.
- Dialog accessibility
  - Ensure close button has a screen reader label and focus trapping is handled by Radix.
- Table responsiveness
  - Wrap tables in scroll containers and ensure proper cell alignment for checkboxes.
- Form state
  - Use Inertia useForm to manage controlled inputs and clear errors after successful submission.

**Section sources**
- [button.tsx:1-65](file://resources/js/components/ui/button.tsx#L1-L65)
- [input.tsx:1-22](file://resources/js/components/ui/input.tsx#L1-L22)
- [dialog.tsx:1-134](file://resources/js/components/ui/dialog.tsx#L1-L134)
- [table.tsx:1-115](file://resources/js/components/ui/table.tsx#L1-L115)
- [pegawai-create.tsx:1-603](file://resources/js/pages/kepegawaian/pegawai/create.tsx#L1-L603)

## Conclusion
The React Component System leverages Radix UI primitives and Tailwind variants to deliver accessible, reusable UI components. Kepegawaian-specific components encapsulate common workflows like CRUD forms, multi-step wizards, and data tables. By composing primitives and domain components consistently, the system ensures maintainability, accessibility, and scalability across the application.

## Appendices

### Prop Interfaces Summary
- Button
  - variant: "default|destructive|outline|secondary|ghost|link"
  - size: "default|xs|sm|lg|icon|icon-xs|icon-sm|icon-lg"
  - asChild: boolean
- Input
  - type: string
- Card family
  - className: string
- Table family
  - className: string
- Dialog family
  - className: string, children: ReactNode
- CRUD Form Card
  - title: string, description: string, children: ReactNode, onSubmit: Function, onCancel?: Function, submitLabel?: string, cancelLabel?: string, isEditing?: boolean, processing?: boolean
- CRUD Table
  - columns: Array<{ key: string, header: string, cell: Function, className?: string }>, data: Array<any>, onEdit: Function, onDelete: Function, emptyMessage?: string, getItemId: Function
- Data Table Toolbar
  - searchValue: string, onSearchChange: Function, searchPlaceholder?: string, filters: Array<{ key: string, label: string, placeholder: string, value: string|null, options: Array<{value: string,label: string}>, onChange: Function }>, showClear: boolean, onClear: Function, className?: string
- Multi-Step Form
  - steps: string[], currentStep: number, children: React.ReactNode, onNext?: Function, onPrev?: Function, onSubmit?: Function, isLastStep: boolean, isFirstStep: boolean, processing?: boolean, title?: string
- Enum Select
  - options: Array<{ value: string, label?: string, name?: string }>, value?: string, onChange: Function, placeholder?: string, error?: string, disabled?: boolean, label?: string, id?: string

**Section sources**
- [button.tsx:41-62](file://resources/js/components/ui/button.tsx#L41-L62)
- [input.tsx:5-18](file://resources/js/components/ui/input.tsx#L5-L18)
- [card.tsx:5-66](file://resources/js/components/ui/card.tsx#L5-L66)
- [table.tsx:5-103](file://resources/js/components/ui/table.tsx#L5-L103)
- [dialog.tsx:7-132](file://resources/js/components/ui/dialog.tsx#L7-L132)
- [crud-form-card.tsx:11-21](file://resources/js/components/kepegawaian/crud-form-card.tsx#L11-L21)
- [crud-table.tsx:12-26](file://resources/js/components/kepegawaian/crud-table.tsx#L12-L26)
- [data-table-toolbar.tsx:16-36](file://resources/js/components/kepegawaian/data-table-toolbar.tsx#L16-L36)
- [multi-step-form.tsx:13-24](file://resources/js/components/kepegawaian/multi-step-form.tsx#L13-L24)
- [enum-select.tsx:17-26](file://resources/js/components/kepegawaian/enum-select.tsx#L17-L26)

### Integration Patterns
- State management
  - Use Inertia useForm for controlled form inputs and submission.
  - Manage local UI state (e.g., currentStep) with useState.
- Routing
  - Use Inertia router for navigation and filter persistence.
- Accessibility
  - Provide labels for selects and inputs; ensure focus management and keyboard navigation.
- Theming and utilities
  - Use cn for class merging and consistent spacing.

**Section sources**
- [pegawai-create.tsx:53-93](file://resources/js/pages/kepegawaian/pegawai/create.tsx#L53-L93)
- [pegawai-index.tsx:101-129](file://resources/js/pages/kepegawaian/pegawai/index.tsx#L101-L129)
- [utils.ts:6-8](file://resources/js/lib/utils.ts#L6-L8)
- [ui-types.ts:4-16](file://resources/js/types/ui.ts#L4-L16)