# Release Notes

---

## v1.0.0 — Portfolio Release

**Multi-tenant remote workforce management platform.**
PHP 8.2 · MySQL / MariaDB · vanilla JavaScript · Firebase Realtime Database · Agora RTC

Built without a framework, a package manager, or a build step. Clone it, point a web server
at it, and it runs.

---

### Roles & capabilities

**Company / Admin**
- Self-service company registration with sector and tax-number metadata
- Employee onboarding with role assignment
- Employee directory with inline role reassignment and removal
- Company-wide task overview
- Company-wide analytics and an alerts view for at-risk work

**Project Manager**
- Task statistics: total, not started, in progress, completed, high priority
- Create tasks with title, description, due date, and priority
- Delegate tasks to Team Leads
- Adjust priority and status, or remove tasks
- Per-task chat with file sharing and video calling
- Manager reporting, including delivery split by Team Lead

**Team Lead**
- Workload dashboard
- Decompose a task into named checkpoints
- Assign up to three Team Members per task
- Checkpoint progress and member workload reporting

**Team Member**
- Personal and assigned task views
- Tick off checkpoints to report progress
- Automatic task completion when the final checkpoint closes
- Per-task chat with file sharing and video calling
- Personal progress reporting

---

### Platform features

**Task delegation chain.** A Project Manager creates a task and delegates it to a Team Lead.
The Team Lead breaks it into checkpoints and assigns Team Members. Members tick checkpoints
off as they work, and task status is derived from checkpoint state on every update — so a
task can never disagree with its own checkpoints. When the last checkpoint closes, the task
transitions to `Completed` and stamps its completion date automatically.

**Per-task collaboration.** Each task gets a dedicated chat room, provisioned automatically
at assignment time, backed by Firebase Realtime Database for messaging and Agora RTC for
peer-to-peer video. Discussion stays attached to the work item rather than living in a
separate tool.

**File sharing.** Uploads are validated against an extension allowlist with a 10 MB cap,
filenames are sanitised and given a unique prefix, and a failed database write rolls the
stored file back. Listing and upload both require the caller to be a participant of the chat.

**Multi-tenancy.** Every task query and mutation is scoped to the requesting company, and
mutations carry an ownership predicate in the statement itself rather than relying on an
earlier check.

**Security posture**
- Passwords hashed with `password_hash()` / bcrypt
- CSRF tokens on every authenticated state-changing request, compared with `hash_equals()`
- Session ID regenerated at login; `HttpOnly`, `SameSite=Lax`, and `Secure`-over-TLS cookies
- Prepared statements throughout; output escaped at the point of rendering
- Server-side validation of dates, enums, lengths, and cross-company references
- Role guards enforced before any side effect on every page

**Reporting.** Company-wide analytics (status and priority distribution, completion rate,
overdue count, workforce composition, delivery by Team Lead), an alerts view (overdue, due
within an adjustable horizon, unstarted high-priority, unstaffed work), and a report for each
role scoped to what that role can see.

**Pagination.** All four task lists page 25 rows at a time through a shared helper. Page
links re-fetch the panel inside the dashboard shell and fall back to normal navigation when a
list is opened on its own.

---

### Verification

Validated against a running instance with a two-tenant fixture: HTTP and database assertions
covering authentication, authorization, the full task lifecycle, data integrity, file upload,
and edge cases; plus real-browser assertions in Chrome and Edge driven over the DevTools
Protocol covering navigation, panel loading, responsive layout at phone, tablet, and desktop
widths, and console cleanliness.

All queries were additionally confirmed to run under MySQL 8's default `sql_mode`
(`ONLY_FULL_GROUP_BY` with `STRICT_TRANS_TABLES`), so the application is not dependent on a
permissive server configuration.

---

### Requirements

- PHP 8.0+ with `pdo_mysql` and `mysqli`
- MySQL 5.7+ or MariaDB 10.4+
- A Firebase project with Realtime Database enabled, for chat
- An Agora project, for video calling

Chat and video require your own Firebase and Agora credentials in `includes/config.php`. The rest of
the application — authentication, task management, reporting, file sharing — runs with only a
database.

---

### What's next

Planned work and the reasoning behind the current design are in
[docs/ROADMAP.md](docs/ROADMAP.md).
