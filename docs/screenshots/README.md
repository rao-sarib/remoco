# Screenshot Catalogue

Captures of a local development instance, plus the design diagrams produced while building
the system. Files are named `<area>-<view>.png`.

---

## Design Diagrams

| File | Description |
|---|---|
| `architecture-diagram.png` | High-level component diagram — browser, PHP server, MySQL, Firebase |
| `use-case-diagram.png` | Actor/use-case model covering all four roles and both data stores |
| `database-erd.png` | Entity-relationship diagram, seven tables |
| `database-erd-wide.png` | Same ERD, wide single-row layout |

---

## Landing & Authentication

| File | Description |
|---|---|
| `web-landing-page.png` | Public landing page (`main.php`) |
| `login-company.png` | Login screen, Company tab |
| `login-employee.png` | Login screen, Employee tab — email + password + company ID |
| `company-registration-step1.png` | Company registration — ID, name, registered flag, sector |
| `company-registration-step2.png` | Company registration — tax number, official email, password |

---

## Company / Admin

| File | Description |
|---|---|
| `admin-dashboard-home.png` | Workforce overview — employee, PM, and team-lead counts |
| `admin-employee-registration.png` | Employee onboarding form — name, identity number, email |
| `admin-employee-registration-details.png` | Same form — designation, company ID, password |
| `admin-tasks-management.png` | Company-wide task table with team-member chips |

---

## Project Manager

| File | Description |
|---|---|
| `pm-dashboard-home.png` | Task statistics — total, not started, in progress, completed, high priority |
| `pm-create-task.png` | Create-task form — title, description, due date, priority |
| `pm-create-task-assign-lead.png` | Create-task form — team-lead assignment and submit |
| `pm-tasks-list.png` | Own tasks, full detail table |
| `pm-tasks-actions.png` | Same table scrolled to the priority/status/delete controls |
| `pm-chats.png` | Task chat list with **Start Call** |

---

## Team Lead

| File | Description |
|---|---|
| `tl-dashboard-home.png` | In-progress, pending, and completed counts |
| `tl-dashboard-recent-tasks.png` | Recent-task panel |
| `tl-assigned-tasks.png` | Tasks delegated to this lead, with **Open** action |
| `tl-assign-task.png` | Assignment screen — task detail plus three member selectors |
| `tl-assign-checkpoints.png` | Checkpoint builder with add/remove and character counters |
| `tl-all-tasks.png` | Full task table for this lead |

---

## Team Member

| File | Description |
|---|---|
| `tm-dashboard-home.png` | Completed count and recent tasks with team-lead attribution |
| `tm-assigned-tasks.png` | Directly assigned tasks with **Open** action |
| `tm-update-task.png` | Task detail — ID, title, description, due date, priority, status |
| `tm-update-task-checkpoints.png` | Checkpoint checkboxes driving automatic task completion |
| `tm-tasks-list.png` | All tasks across projects |

---

## Collaboration

| File | Description |
|---|---|
| `chat-file-sharing.png` | Task chat with message box, upload control, and shared-file list |

---

## Scope

REMOCO is a responsive web application; there is no separate mobile client, so the captures
above cover the full surface. Every capture is from a local instance running test data.
