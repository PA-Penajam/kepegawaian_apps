# System Introduction

<cite>
**Referenced Files in This Document**
- [routes/web.php](file://routes/web.php)
- [routes/api.php](file://routes/api.php)
- [app/Http/Controllers/DashboardController.php](file://app/Http/Controllers/DashboardController.php)
- [app/Services/DashboardStatService.php](file://app/Services/DashboardStatService.php)
- [app/Http/Controllers/Kepegawaian/PegawaiController.php](file://app/Http/Controllers/Kepegawaian/PegawaiController.php)
- [app/Models/Pegawai.php](file://app/Models/Pegawai.php)
- [app/Http/Controllers/Kepegawaian/SelfServiceController.php](file://app/Http/Controllers/Kepegawaian/SelfServiceController.php)
- [app/Http/Controllers/Monitoring/MonitoringKgbController.php](file://app/Http/Controllers/Monitoring/MonitoringKgbController.php)
- [app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php](file://app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php)
- [app/Http/Controllers/Iam/AplikasiController.php](file://app/Http/Controllers/Iam/AplikasiController.php)
- [config/kepegawaian.php](file://config/kepegawaian.php)
- [config/iam.php](file://config/iam.php)
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

## Introduction
Kepegawaian Apps is an Indonesian government institution Human Resource Information System (HRIS) designed to streamline the management of civil servant records, career progression, and administrative workflows. It serves as a centralized platform for HR officers, administrators, and government employees to maintain accurate personnel data, track promotions and salary advancements, monitor key milestones such as Kenaikan Gaji Berkala (KGB) and Kenaikan Pangkat, and support self-service experiences for employees.

The system supports digital transformation of government HR processes by offering:
- Employee lifecycle management: recruitment, record maintenance, and exit procedures
- Career progression tracking: detailed history of education, training, positions, ranks, awards, and disciplinary actions
- Monitoring dashboards: real-time insights into upcoming KGB/KP events and workforce demographics
- Self-service portal: personal access to profile details and eligibility checks
- Identity and Access Management (IAM): secure application integration and single sign-on (SSO) capabilities
- API-first integration: secure APIs for external systems such as attendance platforms

Target audience:
- HR officers and administrators responsible for maintaining civil servant records and overseeing career progression
- Government employees who benefit from self-service access to personal HR data and milestone tracking
- System integrators working with external government systems requiring secure HR data exchange

Business value:
- Improved accuracy and transparency of HR data
- Reduced administrative burden through automation and standardized workflows
- Enhanced decision-making with actionable analytics and monitoring
- Strengthened compliance with national HR regulations and internal policies
- Seamless interoperability with existing government systems via secure APIs and SSO

Regulatory compliance:
- Built-in IAM controls and permission enforcement align with government security standards
- Secure API endpoints with multi-layer protection (HTTPS, tokens, HMAC signatures, rate limiting)
- Audit-ready data relationships and change tracking through dedicated history tables

Strategic importance:
- Supports national digital government initiatives by centralizing HR data and enabling cross-system collaboration
- Provides scalable infrastructure for future enhancements and integrations
- Ensures continuity and reliability of HR services across diverse government institutions

## Project Structure
The system follows a modular MVC architecture with clear separation of concerns:
- Web routes define UI-driven workflows for administration, monitoring, reference data, and self-service
- API routes expose secure endpoints for integration with external systems
- Controllers orchestrate requests, enforce permissions, and render Inertia-based views
- Services encapsulate business logic for statistics, monitoring, and data transformations
- Models represent domain entities with rich relationships and enumerations for standardized values
- Configuration files manage IAM settings, security keys, and runtime behavior

```mermaid
graph TB
subgraph "Routing Layer"
WEB["Web Routes<br/>UI Workflows"]
API["API Routes<br/>Integration Endpoints"]
end
subgraph "Presentation Layer"
CTRL_DASH["DashboardController"]
CTRL_PGW["PegawaiController"]
CTRL_SELF["SelfServiceController"]
CTRL_MON_KGB["MonitoringKgbController"]
CTRL_MON_KP["MonitoringKenaikanPangkatController"]
CTRL_IAM_APP["Iam\\AplikasiController"]
end
subgraph "Domain Layer"
MODEL_PGW["Pegawai Model"]
end
subgraph "Service Layer"
SVC_DASH["DashboardStatService"]
end
WEB --> CTRL_DASH
WEB --> CTRL_PGW
WEB --> CTRL_SELF
WEB --> CTRL_MON_KGB
WEB --> CTRL_MON_KP
WEB --> CTRL_IAM_APP
API --> MODEL_PGW
CTRL_DASH --> SVC_DASH
CTRL_PGW --> MODEL_PGW
CTRL_SELF --> MODEL_PGW
CTRL_MON_KGB --> MODEL_PGW
CTRL_MON_KP --> MODEL_PGW
CTRL_IAM_APP --> MODEL_PGW
```

**Diagram sources**
- [routes/web.php:31-136](file://routes/web.php#L31-L136)
- [routes/api.php:21-47](file://routes/api.php#L21-L47)
- [app/Http/Controllers/DashboardController.php:10-17](file://app/Http/Controllers/DashboardController.php#L10-L17)
- [app/Http/Controllers/Kepegawaian/PegawaiController.php:25-223](file://app/Http/Controllers/Kepegawaian/PegawaiController.php#L25-L223)
- [app/Http/Controllers/Kepegawaian/SelfServiceController.php:13-95](file://app/Http/Controllers/Kepegawaian/SelfServiceController.php#L13-L95)
- [app/Http/Controllers/Monitoring/MonitoringKgbController.php:10-31](file://app/Http/Controllers/Monitoring/MonitoringKgbController.php#L10-L31)
- [app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php:11-31](file://app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php#L11-L31)
- [app/Http/Controllers/Iam/AplikasiController.php:11-128](file://app/Http/Controllers/Iam/AplikasiController.php#L11-L128)
- [app/Services/DashboardStatService.php:14-29](file://app/Services/DashboardStatService.php#L14-L29)
- [app/Models/Pegawai.php:24-208](file://app/Models/Pegawai.php#L24-L208)

**Section sources**
- [routes/web.php:31-136](file://routes/web.php#L31-L136)
- [routes/api.php:21-47](file://routes/api.php#L21-L47)

## Core Components
- Employee Management: Centralized CRUD operations for civil servants with advanced filtering, sorting, and search capabilities
- Monitoring and Analytics: Real-time dashboards for KGB and Kenaikan Pangkat eligibility, workforce distribution, and milestone tracking
- Self-Service Portal: Personalized access to profile details, career history, and eligibility checks for employees
- Identity and Access Management (IAM): Application registration, role-permission management, and secure SSO integration
- API Integration: Secure endpoints for external system interoperability with robust authentication and integrity verification

**Section sources**
- [app/Http/Controllers/Kepegawaian/PegawaiController.php:25-223](file://app/Http/Controllers/Kepegawaian/PegawaiController.php#L25-L223)
- [app/Services/DashboardStatService.php:14-147](file://app/Services/DashboardStatService.php#L14-L147)
- [app/Http/Controllers/Kepegawaian/SelfServiceController.php:13-95](file://app/Http/Controllers/Kepegawaian/SelfServiceController.php#L13-L95)
- [app/Http/Controllers/Iam/AplikasiController.php:11-128](file://app/Http/Controllers/Iam/AplikasiController.php#L11-L128)
- [routes/api.php:21-47](file://routes/api.php#L21-L47)

## Architecture Overview
The system employs a layered architecture:
- Presentation: Inertia-based views for responsive UI and seamless server-side rendering
- Application: Controllers coordinate workflows, enforce authorization, and delegate to services
- Domain: Eloquent models encapsulate business entities, relationships, and enumerations
- Infrastructure: Configuration-driven security, caching, and external integrations

```mermaid
graph TB
Client["Browser / Mobile"]
Inertia["Inertia Renderer"]
WebCtrl["Web Controllers"]
ApiCtrl["API Controllers"]
Services["Business Services"]
Models["Eloquent Models"]
Config["Config Files"]
Client --> Inertia
Inertia --> WebCtrl
Inertia --> ApiCtrl
WebCtrl --> Services
ApiCtrl --> Services
Services --> Models
WebCtrl --> Config
ApiCtrl --> Config
Models --> Config
```

**Diagram sources**
- [app/Http/Controllers/DashboardController.php:10-17](file://app/Http/Controllers/DashboardController.php#L10-L17)
- [app/Services/DashboardStatService.php:14-29](file://app/Services/DashboardStatService.php#L14-L29)
- [app/Http/Controllers/Kepegawaian/PegawaiController.php:25-223](file://app/Http/Controllers/Kepegawaian/PegawaiController.php#L25-L223)
- [routes/web.php:31-136](file://routes/web.php#L31-L136)
- [routes/api.php:21-47](file://routes/api.php#L21-L47)
- [config/iam.php:4-8](file://config/iam.php#L4-L8)
- [config/kepegawaian.php:3-16](file://config/kepegawaian.php#L3-L16)

## Detailed Component Analysis

### Employee Lifecycle Management
Kepegawaian Apps provides comprehensive CRUD operations for civil servants with:
- Advanced search and filtering across NIP, name, unit, position, rank, and employment status
- Multi-step forms for detailed personal and professional information capture
- Rich relationship loading for displaying complete career histories
- Enumerations for standardized values ensuring data consistency

```mermaid
sequenceDiagram
participant User as "HR Officer"
participant Web as "Web Routes"
participant Ctrl as "PegawaiController"
participant Model as "Pegawai Model"
participant View as "Inertia View"
User->>Web : GET /kepegawaian/pegawai
Web->>Ctrl : index()
Ctrl->>Model : query() with filters and relations
Model-->>Ctrl : Paginated collection
Ctrl->>View : Render index with filters and options
View-->>User : Display searchable table
```

**Diagram sources**
- [routes/web.php:35-63](file://routes/web.php#L35-L63)
- [app/Http/Controllers/Kepegawaian/PegawaiController.php:25-113](file://app/Http/Controllers/Kepegawaian/PegawaiController.php#L25-L113)
- [app/Models/Pegawai.php:24-208](file://app/Models/Pegawai.php#L24-L208)

**Section sources**
- [app/Http/Controllers/Kepegawaian/PegawaiController.php:25-223](file://app/Http/Controllers/Kepegawaian/PegawaiController.php#L25-L223)
- [app/Models/Pegawai.php:24-208](file://app/Models/Pegawai.php#L24-L208)

### Monitoring and Self-Service
The system offers dual perspectives:
- Administrative monitoring dashboards for KGB and Kenaikan Pangkat eligibility
- Personal self-service portal for employees to review their profiles and milestones

```mermaid
sequenceDiagram
participant Admin as "Administrator"
participant MonKGB as "MonitoringKgbController"
participant MonKP as "MonitoringKenaikanPangkatController"
participant Dash as "DashboardController"
participant Stats as "DashboardStatService"
Admin->>Dash : GET /dashboard
Dash->>Stats : getStats()
Stats-->>Dash : Metrics and distributions
Dash-->>Admin : Dashboard with charts and counts
Admin->>MonKGB : GET /kepegawaian/monitoring/kgb
MonKGB-->>Admin : Upcoming KGB list and stats
Admin->>MonKP : GET /kepegawaian/monitoring/kenaikan-pangkat
MonKP-->>Admin : KP eligibility list and stats
```

**Diagram sources**
- [app/Http/Controllers/DashboardController.php:10-17](file://app/Http/Controllers/DashboardController.php#L10-L17)
- [app/Services/DashboardStatService.php:14-147](file://app/Services/DashboardStatService.php#L14-L147)
- [app/Http/Controllers/Monitoring/MonitoringKgbController.php:10-31](file://app/Http/Controllers/Monitoring/MonitoringKgbController.php#L10-L31)
- [app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php:11-31](file://app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php#L11-L31)

**Section sources**
- [app/Http/Controllers/DashboardController.php:10-17](file://app/Http/Controllers/DashboardController.php#L10-L17)
- [app/Services/DashboardStatService.php:14-147](file://app/Services/DashboardStatService.php#L14-L147)
- [app/Http/Controllers/Monitoring/MonitoringKgbController.php:10-31](file://app/Http/Controllers/Monitoring/MonitoringKgbController.php#L10-L31)
- [app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php:11-31](file://app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php#L11-L31)

### Self-Service Experience
Employees can access personalized information through the self-service portal:
- Personal profile with complete career history
- Eligibility checks for KGB and Kenaikan Pangkat
- Relationship-loaded details for comprehensive visibility

```mermaid
sequenceDiagram
participant Emp as "Government Employee"
participant Self as "SelfServiceController"
participant Model as "Pegawai Model"
participant KP as "KenaikanPangkatMonitoringService"
participant KGB as "KgbMonitoringService"
Emp->>Self : GET /self-service
Self->>Model : Load current user with relations
Self->>KP : Resolve KP info
Self->>KGB : Resolve KGB info
Self-->>Emp : Render dashboard with personal stats
```

**Diagram sources**
- [routes/web.php:106-112](file://routes/web.php#L106-L112)
- [app/Http/Controllers/Kepegawaian/SelfServiceController.php:13-95](file://app/Http/Controllers/Kepegawaian/SelfServiceController.php#L13-L95)
- [app/Models/Pegawai.php:24-208](file://app/Models/Pegawai.php#L24-L208)

**Section sources**
- [app/Http/Controllers/Kepegawaian/SelfServiceController.php:13-95](file://app/Http/Controllers/Kepegawaian/SelfServiceController.php#L13-L95)

### Identity and Access Management (IAM)
The IAM module enables secure application integration:
- Application registration with generated API credentials
- Role and permission management per application
- SSO integration with configurable token lifetimes and code TTLs
- API key masking for safe presentation

```mermaid
flowchart TD
Start(["Admin Action"]) --> CreateApp["Create Application"]
CreateApp --> GenCreds["Generate API Credentials"]
GenCreds --> Store["Persist Application Record"]
Store --> ManageRoles["Manage Roles & Permissions"]
ManageRoles --> ConfigureSSO["Configure SSO Settings"]
ConfigureSSO --> Integrate["External Systems Integrate"]
Integrate --> End(["Secure Access"])
```

**Diagram sources**
- [app/Http/Controllers/Iam/AplikasiController.php:41-107](file://app/Http/Controllers/Iam/AplikasiController.php#L41-L107)
- [config/iam.php:4-8](file://config/iam.php#L4-L8)

**Section sources**
- [app/Http/Controllers/Iam/AplikasiController.php:11-128](file://app/Http/Controllers/Iam/AplikasiController.php#L11-L128)
- [config/iam.php:4-8](file://config/iam.php#L4-L8)

### API Integration and Security
The system exposes secure APIs for external integrations:
- Multi-layer security: HTTPS, Sanctum tokens, HMAC-SHA256 signatures, and rate limiting
- Dedicated endpoints for employee lookups and IAM operations
- Configurable HMAC secret key for attendance system integration

```mermaid
sequenceDiagram
participant Ext as "External System"
participant API as "API Routes"
participant Auth as "Sanctum + HMAC"
participant Ctrl as "Controllers"
participant Model as "Models"
Ext->>API : GET /api/v1/pegawai/{nip}
API->>Auth : Verify token + signature + throttle
Auth->>Ctrl : Authorized request
Ctrl->>Model : Fetch employee data
Model-->>Ctrl : Employee resource
Ctrl-->>Ext : JSON response
```

**Diagram sources**
- [routes/api.php:21-47](file://routes/api.php#L21-L47)
- [config/kepegawaian.php:3-16](file://config/kepegawaian.php#L3-L16)

**Section sources**
- [routes/api.php:21-47](file://routes/api.php#L21-L47)
- [config/kepegawaian.php:3-16](file://config/kepegawaian.php#L3-L16)

## Dependency Analysis
The system exhibits strong cohesion within functional domains and controlled coupling through controllers and services:
- Controllers depend on services for business logic and on models for persistence
- Services encapsulate complex queries and calculations, promoting testability
- Models define clear relationships and constraints, reducing duplication
- Configuration files centralize environment-specific settings

```mermaid
graph LR
C_Dash["DashboardController"] --> S_Dash["DashboardStatService"]
C_Pgw["PegawaiController"] --> M_Pgw["Pegawai Model"]
C_Self["SelfServiceController"] --> M_Pgw
C_MonKGB["MonitoringKgbController"] --> M_Pgw
C_MonKP["MonitoringKenaikanPangkatController"] --> M_Pgw
S_Dash --> M_Pgw
S_Dash --> Config["Config Files"]
```

**Diagram sources**
- [app/Http/Controllers/DashboardController.php:10-17](file://app/Http/Controllers/DashboardController.php#L10-L17)
- [app/Services/DashboardStatService.php:14-147](file://app/Services/DashboardStatService.php#L14-L147)
- [app/Http/Controllers/Kepegawaian/PegawaiController.php:25-223](file://app/Http/Controllers/Kepegawaian/PegawaiController.php#L25-L223)
- [app/Http/Controllers/Kepegawaian/SelfServiceController.php:13-95](file://app/Http/Controllers/Kepegawaian/SelfServiceController.php#L13-L95)
- [app/Http/Controllers/Monitoring/MonitoringKgbController.php:10-31](file://app/Http/Controllers/Monitoring/MonitoringKgbController.php#L10-L31)
- [app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php:11-31](file://app/Http/Controllers/Monitoring/MonitoringKenaikanPangkatController.php#L11-L31)
- [app/Models/Pegawai.php:24-208](file://app/Models/Pegawai.php#L24-L208)
- [config/iam.php:4-8](file://config/iam.php#L4-L8)
- [config/kepegawaian.php:3-16](file://config/kepegawaian.php#L3-L16)

**Section sources**
- [app/Services/DashboardStatService.php:14-147](file://app/Services/DashboardStatService.php#L14-L147)
- [app/Models/Pegawai.php:24-208](file://app/Models/Pegawai.php#L24-L208)

## Performance Considerations
- Use eager loading to minimize N+1 queries in controllers and services
- Leverage database indexing on frequently filtered columns (NIP, unit, rank)
- Apply pagination for large datasets in listing views
- Cache frequently accessed reference data (positions, ranks, units)
- Monitor API response times and adjust rate limits as needed

## Troubleshooting Guide
Common issues and resolutions:
- Authentication failures: Verify Sanctum tokens and HMAC signatures for API endpoints
- Authorization errors: Confirm IAM permissions and role assignments for users
- Slow page loads: Review controller queries and consider adding indexes or caching
- Self-service access problems: Ensure employee accounts are properly linked to user records

**Section sources**
- [routes/api.php:21-47](file://routes/api.php#L21-L47)
- [app/Http/Controllers/Iam/AplikasiController.php:63-79](file://app/Http/Controllers/Iam/AplikasiController.php#L63-L79)

## Conclusion
Kepegawaian Apps delivers a comprehensive HRIS tailored for Indonesian government institutions, combining robust employee management, insightful monitoring, and secure self-service capabilities. Its modular architecture, strong IAM foundation, and API-first design enable seamless integration with existing systems while supporting ongoing digital transformation goals. By centralizing HR data and automating administrative workflows, the system enhances operational efficiency, ensures regulatory compliance, and improves service delivery to government employees and HR stakeholders.