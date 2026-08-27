# 🚀 ProjectPulse Executive Tracker

**ProjectPulse** is an executive-grade Project Management and Delivery Tracking platform built using PHP 8.x, MySQL, and the Tabler (Bootstrap 5) UI framework. It features automated sequential business-day scheduling, RACI matrix role mapping, dual-progress KPI calculations, decision-focused management dashboards, executive PDF/print reporting, and multi-tier role-based access control (RBAC).

---

## 🌟 Key Features

* **Decision-Driven Executive Dashboard**: Features portfolio health tracking, operational blocker alerts, schedule variance indicators, and active project executive summaries.
* **Sequential Task Scheduling & Auto-Cascade**: Automatically calculates task `start_date` and `due_date` values sequentially based on 0.5-day effort increments, skipping weekends and configured public holidays.
* **Dual-Progress KPI Engine**:
* **Schedule Time Elapsed (%)**: Business-day time pace relative to project start and committed target deadlines.
* **Actual Work Completed (%)**: Effort-weighted progress derived from completed tasks ($\frac{\text{Completed Days}}{\text{Total Days}} \times 100$) with `progress_percent` manual override capabilities.


* **Executive Daily & Weekly Status Reports**:
* **Daily Report Workspace**: Log daily progress updates and blocker flags, outputting a 3-column executive operational table.
* **Executive Weekly Report**: Auto-aggregates logs from the past 7 days, categorizing items into *Work Completed (Against Plan)*, *Planned / In-Progress Work*, and *Planned But Not Completed (Carried Over / Blocked)*.
* **Print-Optimized Engine**: Built-in CSS `@media print` rules force dark body text, hide navigation/buttons, and enforce clean page breaks (`page-break-inside: avoid`).


* **RACI Matrix Assignment**: Assign team members as **R**esponsible, **A**ccountable, **C**onsulted, or **I**nformed per task.
* **Project Governance Teams**: Categorize team members into *Strategic*, *Functional*, *Technical*, *Project Management*, or *Viewer* roles per project.
* **Role-Based Access Control (RBAC)**: Unified authentication directly via team profiles with project-level access boundaries.
* **Sequence Order Swapping**: Reorder tasks up and down with instant schedule recalculation across the entire delivery timeline.
* **Dynamic Environment Database Switching**: Auto-detects local development vs. cloud production server configurations via HTTP Host inspection.


* **Sequential Task Scheduling & Auto-Cascade**: Automatically calculates task `start_date` and `due_date` values sequentially based on 0.5-day effort increments, skipping weekends and configured public holidays.
* **Dual-Progress KPI Engine**:
  * **Schedule Time Elapsed (%)**: Business-day time pace relative to project start and committed deadline.
  * **Actual Work Completed (%)**: Effort-weighted progress derived from completed tasks ($\frac{\text{Completed Days}}{\text{Total Days}} \times 100$) with manual override capability.
* **RACI Matrix Assignment**: Assign team members as **R**esponsible, **A**ccountable, **C**onsulted, or **I**nformed per task.
* **Project Governance Teams**: Categorize team members into *Strategic*, *Functional*, *Technical*, *Project Management*, or *Viewer* roles per project.
* **Role-Based Access Control (RBAC)**: Unified authentication directly via team profiles with project-level access boundaries.
* **Print & Executive PDF Export**: High-resolution print-optimized view for board reports and client updates.

---

## 🛠 Tech Stack & Architecture

* **Backend**: PHP 8.x (PDO MySQL)
* **Frontend**: HTML5, Tabler UI (Bootstrap 5), FontAwesome / Tabler Webfont Icons, JavaScript (Fetch API)
* **Database**: MySQL / MariaDB (`utf8mb4`)
* **Authentication**: BCRYPT Hashed Passwords (`password_hash` / `password_verify`)
* **CI/CD Pipeline**: GitHub Actions (Automated FTP/SFTP deployment)

---

## 📂 Directory Structure

```text
├── .github/
│   └── workflows/
│       └── deploy.yml        # GitHub Actions automated FTP deployment script
├── config/
│   └── database.php         # Dynamic host-based DB connection builder
├── includes/
│   ├── functions.php        # System calculation engine, RACI logic, DB helpers, & RBAC
│   ├── header.php           # 2-Tier dark executive navigation & user profile
│   └── footer.php           # Page wrapper, script bindings, & project modal
├── api.php                  # Centralized POST/GET AJAX & form action handler
├── index.php                # Executive metrics, decision hub, & active portfolio summary
├── projects.php             # Role-scoped project directory (Active vs. Completed tab filter)
├── project_detail.php       # Detailed project view, task list, & RACI matrix
├── daily_report.php         # Daily progress logging workspace & executive table summary
├── weekly_report.php        # 7-Day planned vs. completed vs. delayed report generator
├── team.php                 # Global team directory & user credential management
├── login.php                # Authentication page
└── logout.php               # Session termination script
```

---

## 👤 User Roles & Access Control (RBAC)

Access control is governed by a unified model where `team_members` double as system users:

1. **System Admin (`admin`)**:
* Complete visibility across all projects, reports, and metrics.
* Can create, update, reorder, or delete any project, task, or team member.

2. **Standard User / Project Manager (`user`)**:
* Granted **Edit Access** to projects where designated as the Project Manager or assigned to a Governance Team (*Strategic*, *Functional*, *Technical*, *PM*).
* Restricted from modifying projects outside their assigned roster.

3. **Project Viewer (`user` + `Viewer` Governance Role)**:
* Granted **Read-Only Access** to assigned projects.
* Edit buttons, task creation modals, scope adjustment inputs, and reordering arrows are automatically hidden.

---

## 🗄 Database Structure & Entity Analysis

The application relies on a MySQL relational database. Below is the structural layout for system tables and foreign key relationships.
ProjectPulse relies on a relational schema supporting multi-tenant project governance, task sequencing, daily logging, and dynamic status progress calculation.

### ER Diagram & Schema Blueprint

```
+--------------------+       +--------------------+
|     categories     |       |      projects      |
+--------------------+       +--------------------+
| id (PK)            |<------| category_id (FK)   |
| name               |       | id (PK)            |
| slug               |       | title              |
| color_code         |       | description        |
+--------------------+       | owner_name         |
                             | priority           |
                             | status             |
                             | progress_percent   | <--- Key field for manual override
                             | needs_attention    |
                             | target_completion  |
                             +--------------------+
                                 |         |
         +-----------------------+         +-----------------------+
         |                                                         |
         v                                                         v
+--------------------+                                   +--------------------+
|       tasks        |                                   |     milestones     |
+--------------------+                                   +--------------------+
| id (PK)            |                                   | id (PK)            |
| project_id (FK)    |                                   | project_id (FK)    |
| task_name          |                                   | milestone_title    |
| status             | ('To Do','In Progress','Completed')| status             | ('Pending','Completed')
| raci_responsible   |                                   | due_date           |
+--------------------+                                   +--------------------+
         |
         v
+--------------------+
|     daily_logs     |
+--------------------+
| id (PK)            |
| project_id (FK)    |
| log_text           |
| is_blocked (BOOL)  |
| created_at (TIMESTAMP)
+--------------------+
```

### Database Schema DDL

```sql
-- 1. Categories Table
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `color_code` VARCHAR(10) DEFAULT '#2563eb'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Projects Table
CREATE TABLE `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `owner_name` VARCHAR(150) DEFAULT 'Unassigned',
  `priority` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
  `status` VARCHAR(50) DEFAULT 'In Progress',
  `progress_percent` INT DEFAULT 0,
  `needs_attention` TINYINT(1) DEFAULT 0,
  `blocker_reason` TEXT NULL,
  `target_completion_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tasks Table
CREATE TABLE `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `task_name` VARCHAR(255) NOT NULL,
  `raci_responsible` VARCHAR(150) NULL,
  `status` ENUM('To Do', 'Under Review', 'In Progress', 'Completed') DEFAULT 'To Do',
  `sort_order` INT DEFAULT 0,
  `current_days` DECIMAL(4,1) DEFAULT 1.0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Daily Logs Table
CREATE TABLE `daily_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `log_text` TEXT NOT NULL,
  `is_blocked` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## ⚙️ Key Backend Functions (`includes/functions.php`)

| Function Signature | Description |
| --- | --- |
| `getDBConnection()` | Establishes or returns static PDO connection (auto-detects local vs. cloud). |
| `getProjects($filters = [])` | Fetches project directory using dynamic fallback logic for `progress_percent` vs. calculated tasks/milestones. |
| `getProjectById($id)` | Retrieves detailed project info including category names. |
| `getProjectTasks($projectId)` | Returns task list ordered by `sort_order ASC`. |
| `getDailyReportData($date)` | Fetches daily progress logs grouped by Business Unit and Project for a specific date. |
| `getWeeklyReportData()` | Aggregates daily status updates from the past 7 days for weekly report generation. |
| `addDailyLog($projectId, $text, $blocked)` | Inserts a new progress entry or blocker flag for a given project. |
| `isWorkingDay(DateTime $date, array $holidays)` | Evaluates if a given date is a non-weekend and non-holiday working day. |
| `addBusinessDays(DateTime $start, $days, $holidays)` | Adds effort days to a date, skipping non-working business days. |
| `recalculateProjectSchedule($projectId)` | Cascades business-day start/due dates sequentially across all project tasks. |
| `getScheduleElapsedPercent($startDate, $targetDate)` | Computes schedule time pace percentage as of today. |
| `getTaskWeightedProgress($projectId)` | Computes effort-weighted task completion percentage. |
| `getRaciAssignments($entityType, $entityId)` | Returns associative array (`R`, `A`, `C`, `I`) of assigned members per task. |
| `getProjectGovernanceMembers($projectId)` | Retrieves team members assigned to any Governance Team in a project. |
| `canViewProject($projectId)` / `canEditProject($projectId)` | Verifies if current user context holds read or edit permissions on a project. |
| `getTeamMembers()` | Fetches all global team member records. |
| `getDashboardMetrics()` | Computes total accessible projects, average completion, and blocked counts. |
| `saveRaciRoles($entityType, $entityId, $raciData)` | Saves RACI role assignments for a task or milestone. |
| `getProjectMemberRole($projectId, $memberId)` | Resolves a member's effective permission level on a given project. |
| `canViewProject($projectId)` | Returns `true` if current user is permitted to view the project. |
| `canEditProject($projectId)` | Returns `true` if current user is permitted to edit the project. |
| `getAccessibleProjectsQuery()` | Generates role-scoped SQL query for project listings. |

---

## 🔌 API Action Handlers (`api.php`)

| Endpoint Action (`POST`) | Description |
| --- | --- |
| `create_project` | Inserts a new project, sets initial schedule, and triggers schedule calculation. |
| `update_project` | Updates manual `progress_percent`, status, priority, and attention flags. |
| `update_governance_teams` | Saves Governance Team members (*Strategic*, *Functional*, *Technical*, *PM*, *Viewer*). |
| `create_task_raci` | Inserts a sequential task, assigns RACI roles, and recalculates timeline. |
| `update_task_raci` | Updates task scope, actual days, or status, updates RACI, and syncs progress. |
| `swap_task_order` | Swaps `sort_order` between adjacent tasks (`up`/`down`) and recalculates schedule. |
| `toggle_task` | Quick-toggles task status to `Completed` and auto-syncs project progress. |
| `add_daily_log` | Logs daily progress entries and blockage flags. |

---

## 🚀 Deployment & CI/CD Automation

This project is configured for automated deployments via **GitHub Actions**. Every push to `main` triggers an automatic synchronization to your cPanel/web host.

### GitHub Actions Workflow (`.github/workflows/deploy.yml`)

```yaml
name: 🚀 Auto Deploy to Server

on:
  push:
    branches:
      - main
  workflow_dispatch: # Allows manual deployment trigger from GitHub interface

jobs:
  web-deploy:
    name: Sync Files to Server
    runs-on: ubuntu-latest
    steps:
      - name: 🚚 Get latest code
        uses: actions/checkout@v4

      - name: 📂 Sync files via FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USER }}
          password: ${{ secrets.FTP_PASS }}
          server-dir: ./
```

### Required Repository Secrets

Configure these in GitHub (**Settings $\rightarrow$ Secrets and variables $\rightarrow$ Actions**):

* `FTP_SERVER`: Target server IP or FTP domain.
* `FTP_USER`: FTP / cPanel account username.
* `FTP_PASS`: FTP / cPanel account password.

---

## 💻 Installation & Local Setup

1. **Clone Repository**:
```bash
git clone https://github.com/YOUR-USERNAME/ProjectPulse.git
cd ProjectPulse
```

2. **Configure Database**:
* Edit `config/database.php` with your local and production MySQL credentials.

3. **Initialize Database**:
* Import the MySQL schema script into your database tool (phpMyAdmin / MySQL Workbench).

4. **Default System Credentials**:
* **Admin**: `admin` / `Password123!`
* **User / PM**: `chamila` / `Password123!`
* **Viewer**: `auditor` / `Password123!`

5. **Security Cleanup**: Remove initial setup/migration scripts prior to deploying to live server environments.

---

## 👨‍💻 Project Ownership & Maintained By

**ProjectPulse Executive Tracker** is designed, developed, and maintained by **Niroshan Rodrigo**.

* **Website**: [www.niro.ovh](https://www.niro.ovh)
* **Portfolio & Inquiries**: [niro.ovh](https://www.niro.ovh)

*Designed for executive portfolio management, task sequencing, and organizational project governance.*