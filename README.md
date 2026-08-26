# ProjectPulse Executive Tracker

**ProjectPulse** is an executive-grade Project Management and Delivery Tracking dashboard built using PHP, MySQL, and the Tabler (Bootstrap 5) UI framework. It features automated sequential business-day scheduling, RACI matrix role mapping, dual progress KPI calculations, and multi-tier role-based access control (RBAC).

---

## Key Features

* **Sequential Task Scheduling & Auto-Cascade**: Automatically calculates task `start_date` and `due_date` values sequentially based on 0.5-day effort increments, skipping weekends and configured public holidays.
* **Dual-Progress KPI Engine**:
  * **Schedule Time Elapsed (%)**: Business-day time pace relative to project start and committed deadline.
  * **Actual Work Completed (%)**: Effort-weighted progress derived from completed tasks ($\frac{\text{Completed Days}}{\text{Total Days}} \times 100$) with manual override capability.
* **RACI Matrix Assignment**: Assign team members as **R**esponsible, **A**ccountable, **C**onsulted, or **I**nformed per task.
* **Project Governance Teams**: Categorize team members into *Strategic*, *Functional*, *Technical*, *Project Management*, or *Viewer* roles per project.
* **Role-Based Access Control (RBAC)**: Unified authentication directly via team profiles with project-level access boundaries.
* **Sequence Order Swapping**: Reorder tasks up and down with instant schedule recalculation across the entire delivery timeline.
* **Print & Executive PDF Export**: High-resolution print-optimized view for board reports and client updates.
* **Dynamic Environment Database Switching**: Auto-detects local development vs. cloud production server configurations via HTTP Host inspection.

---

## Tech Stack & Architecture

* **Backend**: PHP 8.x (PDO MySQL)
* **Frontend**: HTML5, Tabler UI (Bootstrap 5), Tabler Webfont Icons, JavaScript (Fetch API)
* **Database**: MySQL / MariaDB (utf8mb4)
* **Authentication**: BCRYPT Hashed Passwords (`password_hash` / `password_verify`)

---

## Directory Structure

```text
├── config/
│   └── database.php       # Dynamic host-based DB connection builder
├── includes/
│   ├── functions.php      # System calculation engine, RACI logic, & RBAC helpers
│   ├── header.php         # 2-Tier dark executive navigation & user profile
│   └── footer.php         # Page wrapper, script bindings, & project modal
├── api.php                # Centralized POST/GET AJAX & form action handler
├── index.php              # Executive metrics & project overview dashboard
├── projects.php           # Role-scoped project directory
├── project_detail.php     # Comprehensive project detail view, task list, & RACI matrix
├── team.php               # Global team directory & user credential management
├── login.php              # Authentication page
└── logout.php             # Session termination script
```

---

## User Roles & Access Control

Access control is governed by a unified model where `team_members` double as system users:

1. **System Admin (`admin`)**:
* Complete visibility across all projects and metrics.
* Can create, update, reorder, or delete any project, task, or team member.

2. **Standard User / Project Manager (`user`)**:
* Granted **Edit Access** to projects where designated as the Project Manager or assigned to a Governance Team (*Strategic*, *Functional*, *Technical*, *PM*).
* Restricted from modifying projects outside their roster.

3. **Project Viewer (`user` + `Viewer` Governance Role)**:
* Granted **Read-Only Access** to assigned projects.
* All edit buttons, task creation modals, scope adjustment inputs, and reordering arrows are automatically hidden.

---

## Core System Processes

### 1. Sequential Business-Day Schedule Calculation

When a task is added, edited, or reordered, `recalculateProjectSchedule($projectId)` performs a forward-pass schedule calculation:

1. Fetches public holiday dates from the `holidays` table.
2. Evaluates working business days (omitting Saturdays, Sundays, and holidays).
3. Sequentially maps task start dates to the completion date of the preceding task.
4. Auto-updates the master project `target_completion_date` based on the final task's finish date.

### 2. Dual Progress KPI Calculation

* **Time Pace**: Calculated via `getScheduleElapsedPercent()`, measuring elapsed business days as of today against total planned business days.
* **Task-Weighted Work Progress**: Calculated via `getTaskWeightedProgress()`, taking the sum of `current_days` for tasks marked `Completed` divided by the total sum of `current_days` across all tasks.
* **Auto-Sync**: Toggling a task status automatically recalculates and syncs the weighted completion percentage to the project table.

---

## Key Backend Functions (`includes/functions.php`)

| Function Signature | Description |
| --- | --- |
| `getDBConnection()` | Establishes or returns the static PDO connection (auto-selects local vs. cloud). |
| `getProjectById($id)` | Retrieves detailed project info including category names. |
| `getProjectTasks($projectId)` | Returns task list ordered by `sort_order ASC`. |
| `getTeamMembers()` | Fetches all global team member records. |
| `getDashboardMetrics()` | Computes total accessible projects, average completion, and blocked counts. |
| `isWorkingDay(DateTime $date, array $holidays)` | Evaluates if a given date is a non-weekend and non-holiday working day. |
| `addBusinessDays(DateTime $start, $days, $holidays)` | Adds effort days to a date skipping non-working days. |
| `recalculateProjectSchedule($projectId)` | Cascades business-day start/due dates across all sequential tasks. |
| `getScheduleElapsedPercent($startDate, $targetDate)` | Computes time pace percentage as of today. |
| `getTaskWeightedProgress($projectId)` | Computes effort-weighted task completion percentage. |
| `getRaciAssignments($entityType, $entityId)` | Returns associative array (`R`, `A`, `C`, `I`) of assigned members. |
| `saveRaciRoles($entityType, $entityId, $raciData)` | Saves RACI role assignments for a task or milestone. |
| `getProjectGovernanceMembers($projectId)` | Retrieves team members assigned to any Governance Team in a project. |
| `getProjectMemberRole($projectId, $memberId)` | Resolves a member's effective permission level on a given project. |
| `canViewProject($projectId)` | Returns `true` if current user is permitted to view the project. |
| `canEditProject($projectId)` | Returns `true` if current user is permitted to edit the project. |
| `getAccessibleProjectsQuery()` | Generates role-scoped SQL query for project listings. |

---

## API Action Handlers (`api.php`)

| Endpoint Action (`POST`) | Description |
| --- | --- |
| `create_project` | Inserts a new project, sets initial schedule, and triggers initial recalculation. |
| `update_project` | Updates manual completion %, status, priority, and attention flags. |
| `update_governance_teams` | Saves Governance Team members (*Strategic*, *Functional*, *Technical*, *PM*, *Viewer*). |
| `create_task_raci` | Inserts a sequential task, assigns RACI roles, and recalculates timeline. |
| `update_task_raci` | Updates task scope/actual days/status, updates RACI, and syncs progress. |
| `swap_task_order` | Swaps `sort_order` between adjacent tasks (`up`/`down`) and recalculates schedule. |
| `toggle_task` | Quick-toggles task status to `Completed` and auto-syncs project progress. |
| `add_daily_log` | Logs daily progress entries and blockage flags. |

---

## Installation & Setup

1. **Clone/Upload Repository**: Place files in your web server root (e.g., `/var/www/html` or `htdocs`).

2. **Configure Database**:
* Edit `config/database.php` to adjust local and cloud database credentials.

3. **Initialize Database Schema**:
* Execute the database migration scripts (`update_unified_team.sql`) or run `reset_passwords.php` once in your browser.

4. **Default Credentials**:
* **Admin**: `admin` / `Password123!`
* **User / PM**: `chamila` / `Password123!`
* **Viewer**: `auditor` / `Password123!`

5. **Security Cleanup**: Remove setup scripts (`reset_passwords.php`, `migrate.php`) prior to production deployment.