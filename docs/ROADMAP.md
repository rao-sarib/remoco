# Architecture & Roadmap

REMOCO was built without a framework, deliberately, to work through the parts a framework
normally provides — routing, session handling, authorization, and data access written by
hand. This document describes how the application is put together and where it is going next.

---

## How it is built

**Self-contained pages.** Each page is a PHP script that starts a session, enforces its role
guard, loads configuration, runs its queries, and renders HTML. There is no compilation step,
no package manager, and no vendor directory — cloning the repository and pointing a web
server at it is the whole setup.

**Four shared includes** carry the cross-cutting concerns:

| Include | Responsibility |
|---|---|
| `includes/session_bootstrap.php` | Session cookie flags, session start, CSRF token and helpers |
| `includes/config.php` | Database, Firebase, Agora, and timezone configuration |
| `includes/pagination.php` | Offset pagination and the in-place panel reload behaviour |
| `includes/report_styles.php` | Shared presentation for the five report panels |

**Role-prefixed filenames.** Pages are named `pm_`, `tl_`, `tm_` so the authorization
boundary is legible from a directory listing, and each page enforces its own guard before any
side effect.

**Authorization in the statement.** Task mutations carry their ownership predicate in the SQL
itself — `assigned_by = ?` for manager actions, `team_lead_id = ?` for lead actions,
`? IN (tm1, tm2, tm3)` for member actions — so a query cannot be reached without also being
scoped to the caller.

**Tenancy through participation.** A task belongs to a company through the employees attached
to it, which keeps `tasks` free of a redundant foreign key and makes company membership the
single source of tenancy.

**Derived progress.** Checkpoint state is the source of truth for task status, recomputed on
every update in both directions, so a task and its checkpoints are always consistent with one
another.

**Dashboard shells.** Each role has one shell that loads panels over AJAX against an
allow-list derived from its own sidebar, giving a single-page feel without a client-side
framework.

**Two clients over one model.** Alongside the server-rendered web app, a Flutter mobile
client consumes a JSON REST API (`public/api/`). The API authenticates with a stateless,
HMAC-signed bearer token issued at login (no session cookies, no third-party JWT library);
every endpoint derives identity, company, and role from the token and scopes each query to
it, so both clients share the same tenant and role boundaries. The mobile app attaches the
token through a single HTTP wrapper (`mobile/lib/services/api_http.dart`).

---

## Roadmap

Planned capabilities, ordered by the value each would deliver.

### 1. Automated test suite in the repository
An authorization matrix covering every (role × page × target-owner) combination, plus the
task lifecycle and data-integrity assertions, committed alongside the code with a documented
way to run it against a throwaway database. This is the highest-value addition: it turns the
authorization model from something enforced by convention into something enforced by CI.

### 2. Shared layout and data-access layer
Extracting a common shell and a thin repository layer would let the four dashboards and the
three chat views be expressed once and parameterised by role, so a change lands in one place.

### 3. Asset pipeline with cacheable stylesheets
Moving the page styles into external stylesheets makes them cacheable across requests and
shared between dashboards, cutting the bytes on every navigation.

### 4. Unlimited team size per task
A `task_assignees(task_id, employee_id, role)` join table in place of the current fixed
assignment columns, supporting teams of any size and simplifying the chat participant model
alongside it.

### 5. Denormalised tenancy column
An indexed `company_id` on `tasks` would let tenant-scoped queries filter on a single
predicate instead of resolving membership through joins — a straight performance win as task
volume grows.

### 6. Authenticated file delivery
Streaming shared files through a PHP handler that checks session and chat membership, with the
storage directory moved outside the document root, so file access runs through the same
authorization path as everything else.

### 7. Server-side video token generation
Generating short-lived, per-user, per-channel Agora tokens from the project's App Certificate.
The channel-creation endpoint is already the natural home for this, since it authenticates the
caller and verifies chat membership.

### 8. Single data-access abstraction
Consolidating the JSON endpoints and the page scripts behind one PDO connection factory, so
there is a single error model and a single place to configure connection behaviour.

### 9. Versioned database migrations
A migration runner invoked once at deploy time, separating schema management from the request
path and from the public landing page.

### 10. Consistent fragment responses
Returning HTML fragments from every AJAX-loaded panel, so the shell composes documents and the
panels stay presentation-only.

### 11. Login throttling and audit logging
Per-account and per-IP attempt counters with progressive backoff, plus an audit trail of
authentication and authorization events. Both need a small store for counters and events.

### 12. Notification delivery
Turning the alerts view into a subscription feature — per-user rules with email and in-app
delivery — which would also complete the account-creation email path.

### 13. Configurable identity formats
Making the national identity and tax number formats, and the company ID pattern, configurable
so the platform can be used outside a single region.

---

## Deployment notes

- Chat requires a Firebase project with Realtime Database enabled; video calling requires an
  Agora project. Both are configured in `includes/config.php`.
- Two Realtime Database endpoints are configurable — the global and the regional URL — so the
  deployment can point at whichever its project uses.
- PHP's timezone is set in `includes/config.php` and should match the database server's, so dates
  computed in either place agree.
- Every query has been confirmed to run under MySQL 8's default `sql_mode`
  (`ONLY_FULL_GROUP_BY` with `STRICT_TRANS_TABLES`).
