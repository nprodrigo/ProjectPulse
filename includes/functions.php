<?php
/**
 * Core Helper Functions & Database Access Logic
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Fetch projects with optional filters (category, status, priority, search)
 */
function getProjects($filters = []) {
    $db = getDBConnection();
    if (!$db) return [];

    // SQL query pointing to your exact database column: progress_percent
    $sql = "SELECT p.*, 
            c.name as category_name, 
            c.color_code as category_color, 
            c.slug as category_slug,
            (SELECT COUNT(*) FROM pendings WHERE project_id = p.id AND status != 'Resolved') as open_pendings_count,
            (SELECT COUNT(*) FROM milestones WHERE project_id = p.id) as total_milestones,
            (SELECT COUNT(*) FROM milestones WHERE project_id = p.id AND status = 'Completed') as completed_milestones,
            COALESCE(
                NULLIF(p.progress_percent, 0),
                (SELECT ROUND((COUNT(CASE WHEN status = 'Completed' THEN 1 END) * 100.0) / NULLIF(COUNT(*), 0)) 
                 FROM tasks WHERE project_id = p.id),
                (SELECT ROUND((COUNT(CASE WHEN status = 'Completed' THEN 1 END) * 100.0) / NULLIF(COUNT(*), 0)) 
                 FROM milestones WHERE project_id = p.id),
                0
            ) as calculated_progress
            FROM projects p
            JOIN categories c ON p.category_id = c.id
            WHERE 1=1";
    
    $params = [];

    if (!empty($filters['category'])) {
        $sql .= " AND c.slug = :category";
        $params['category'] = $filters['category'];
    }

    if (!empty($filters['status'])) {
        if ($filters['status'] === 'Needs Attention') {
            $sql .= " AND (p.status = 'Needs Attention' OR p.needs_attention = 1)";
        } else {
            $sql .= " AND p.status = :status";
            $params['status'] = $filters['status'];
        }
    }

    if (!empty($filters['priority'])) {
        $sql .= " AND p.priority = :priority";
        $params['priority'] = $filters['priority'];
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (p.title LIKE :search OR p.owner_name LIKE :search OR p.description LIKE :search)";
        $params['search'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['only_attention'])) {
        $sql .= " AND (p.needs_attention = 1 OR p.status = 'Needs Attention')";
    }

    $sql .= " ORDER BY p.needs_attention DESC, FIELD(p.priority, 'Critical', 'High', 'Medium', 'Low'), p.target_completion_date ASC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Map both array keys so $proj['progress_percent'] and $proj['progress'] return the correct value
        foreach ($projects as &$proj) {
            $val = (int)($proj['calculated_progress'] ?? 0);
            $proj['progress_percent'] = $val;
            $proj['progress']         = $val;
        }

        return $projects;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch milestones for a project
 */
function getMilestones($projectId) {
    $db = getDBConnection();
    if (!$db) return [];
    try {
        $stmt = $db->prepare("SELECT * FROM milestones WHERE project_id = :project_id ORDER BY sort_order ASC, due_date ASC");
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch pendings / action items for a project
 */
function getPendings($projectId = null, $onlyOpen = false) {
    $db = getDBConnection();
    if (!$db) return [];
    
    $sql = "SELECT pend.*, proj.title as project_title, cat.name as category_name, cat.color_code as category_color
            FROM pendings pend
            JOIN projects proj ON pend.project_id = proj.id
            JOIN categories cat ON proj.category_id = cat.id
            WHERE 1=1";
    $params = [];

    if ($projectId !== null) {
        $sql .= " AND pend.project_id = :project_id";
        $params['project_id'] = $projectId;
    }

    if ($onlyOpen) {
        $sql .= " AND pend.status != 'Resolved'";
    }

    $sql .= " ORDER BY FIELD(pend.priority, 'Urgent', 'High', 'Medium', 'Low'), pend.due_date ASC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Helper: Calculate remaining days or overdue status
 */
function getRemainingDaysInfo($targetDate, $status = '') {
    if ($status === 'Completed') {
        return ['text' => 'Completed', 'class' => 'badge-success', 'is_overdue' => false];
    }

    $now = new DateTime(date('Y-m-d'));
    $target = new DateTime($targetDate);
    $diff = $now->diff($target);

    if ($now > $target) {
        $days = $diff->days;
        return [
            'text' => ($days === 0 ? 'Due Today' : "{$days} Days Overdue"),
            'class' => 'badge-danger',
            'is_overdue' => true,
            'days' => -$days
        ];
    } else {
        $days = $diff->days;
        if ($days <= 7) {
            $class = 'badge-warning';
        } else {
            $class = 'badge-info';
        }
        return [
            'text' => ($days === 0 ? 'Due Today' : "{$days} Days Left"),
            'class' => $class,
            'is_overdue' => false,
            'days' => $days
        ];
    }
}

/**
 * Helper: Get CSS class for project status
 */
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Completed':       return 'status-completed';
        case 'In Progress':     return 'status-in-progress';
        case 'Needs Attention': return 'status-attention';
        case 'Under Review':    return 'status-review';
        case 'On Hold':         return 'status-on-hold';
        case 'Planning':        return 'status-planning';
        default:                return 'status-default';
    }
}

/**
 * Helper: Get CSS class for priority badge
 */
function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'Critical':
        case 'Urgent':   return 'priority-critical';
        case 'High':     return 'priority-high';
        case 'Medium':   return 'priority-medium';
        case 'Low':      return 'priority-low';
        default:         return 'priority-default';
    }
}

/**
 * Add a daily log entry for a project
 */
function addDailyLog($projectId, $logText, $isBlocked = 0) {
    $db = getDBConnection();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("INSERT INTO daily_logs (project_id, log_text, is_blocked) VALUES (:project_id, :log_text, :is_blocked)");
        $stmt->execute([
            'project_id' => $projectId,
            'log_text'   => $logText,
            'is_blocked' => $isBlocked
        ]);

        if ($isBlocked) {
            $stmtAtt = $db->prepare("UPDATE projects SET needs_attention = 1, attention_reason = :reason WHERE id = :id");
            $stmtAtt->execute(['reason' => $logText, 'id' => $projectId]);
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Fetch daily updates logged over the last 7 days grouped by Business Unit
 */
function getWeeklyReportData() {
    $db = getDBConnection();
    if (!$db) return [];

    $sql = "SELECT 
                bu.name AS bu_name,
                p.title AS project_title,
                p.owner_name,
                dl.log_text,
                dl.is_blocked,
                dl.created_at
            FROM daily_logs dl
            JOIN projects p ON dl.project_id = p.id
            LEFT JOIN business_units bu ON p.bu_id = bu.id
            WHERE dl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY bu.name ASC, p.title ASC, dl.created_at DESC";

    try {
        return $db->query($sql)->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch daily updates logged for a specific date (defaults to today)
 */
function getDailyReportData($targetDate = null) {
    $db = getDBConnection();
    if (!$db) return [];

    if (!$targetDate) {
        $targetDate = date('Y-m-d');
    }

    $sql = "SELECT 
                bu.name AS bu_name,
                p.id AS project_id,
                p.title AS project_title,
                p.owner_name,
                p.status AS project_status,
                dl.id AS log_id,
                dl.log_text,
                dl.is_blocked,
                dl.created_at
            FROM daily_logs dl
            JOIN projects p ON dl.project_id = p.id
            LEFT JOIN business_units bu ON p.bu_id = bu.id
            WHERE DATE(dl.created_at) = :targetDate
            ORDER BY bu.name ASC, p.title ASC, dl.created_at DESC";

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(['targetDate' => $targetDate]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch team members assigned to a specific project
 */
function getProjectTeam($projectId) {
    $db = getDBConnection();
    if (!$db) return [];
    try {
        $stmt = $db->prepare("SELECT tm.* FROM team_members tm
                              JOIN project_teams pt ON tm.id = pt.member_id
                              WHERE pt.project_id = :pid
                              ORDER BY tm.full_name ASC");
        $stmt->execute(['pid' => $projectId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Assign a list of team members to a project
 */
function updateProjectTeam($projectId, $memberIds = []) {
    $db = getDBConnection();
    if (!$db) return false;
    try {
        $stmt = $db->prepare("DELETE FROM project_teams WHERE project_id = :pid");
        $stmt->execute(['pid' => $projectId]);

        if (!empty($memberIds)) {
            $insertStmt = $db->prepare("INSERT INTO project_teams (project_id, member_id) VALUES (:pid, :mid)");
            foreach ($memberIds as $mId) {
                $insertStmt->execute(['pid' => $projectId, 'mid' => (int)$mId]);
            }
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Fetch Project details by ID
 */
function getProjectById($id) {
    $db = getDBConnection();
    if (!$db) return false;
    try {
        $stmt = $db->prepare("SELECT p.*, c.name as category_name 
                              FROM projects p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              WHERE p.id = :id");
        $stmt->execute(['id' => (int)$id]);
        return $stmt->fetch();
        } catch (PDOException $e) {
            return false;
    }
}

/**
 * Fetch all tasks for a project ordered by sort sequence
 */
function getProjectTasks($projectId) {
    $db = getDBConnection();
    if (!$db) return [];
    try {
        $stmt = $db->prepare("SELECT * FROM tasks WHERE project_id = :pid ORDER BY sort_order ASC, id ASC");
        $stmt->execute(['pid' => (int)$projectId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch all active team members
 */
function getTeamMembers() {
    $db = getDBConnection();
    if (!$db) return [];
    try {
        return $db->query("SELECT * FROM team_members ORDER BY full_name ASC")->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch all business units
 */
function getBusinessUnits() {
    $db = getDBConnection();
    if (!$db) return [];
    try {
        return $db->query("SELECT * FROM business_units ORDER BY name ASC")->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch all categories
 */
function getCategories() {
    $db = getDBConnection();
    if (!$db) return [];
    try {
        return $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch Dashboard Overview Metrics
 */
function getDashboardMetrics() {
    $db = getDBConnection();
    $default = ['total_projects' => 0, 'avg_progress' => 0, 'attention_needed' => 0, 'finishing_soon' => 0];
    if (!$db) return $default;

    try {
        $total = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
        $avg   = round($db->query("SELECT IFNULL(AVG(progress_percent), 0) FROM projects")->fetchColumn());
        $att   = $db->query("SELECT COUNT(*) FROM projects WHERE needs_attention = 1 OR status = 'Needs Attention'")->fetchColumn();
        $soon  = $db->query("SELECT COUNT(*) FROM projects WHERE target_completion_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)")->fetchColumn();

        return [
            'total_projects'   => (int)$total,
            'avg_progress'     => (int)$avg,
            'attention_needed' => (int)$att,
            'finishing_soon'   => (int)$soon
        ];
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Calculate Baseline Schedule Variance Status
 */
function getScheduleVariance($expectedDate, $calculatedDate) {
    if (!$expectedDate || !$calculatedDate) {
        return ['status' => 'On Time', 'class' => 'bg-success-lt text-success'];
    }
    
    $expected = new DateTime($expectedDate);
    $actual   = new DateTime($calculatedDate);
    $diff     = $expected->diff($actual);

    if ($actual > $expected) {
        return ['status' => '+' . $diff->days . ' Days Delayed', 'class' => 'bg-danger-lt text-danger'];
    } elseif ($actual < $expected) {
        return ['status' => $diff->days . ' Days Ahead', 'class' => 'bg-success-lt text-success'];
    }
    return ['status' => 'On Schedule', 'class' => 'bg-success-lt text-success'];
}

/**
 * Validate and round effort to nearest 0.5 increment (Min: 0.5)
 */
function sanitizeDays($value) {
    $val = (float)$value;
    if ($val < 0.5) return 0.5;
    return round($val * 2) / 2;
}

/**
 * Fetch configured holiday dates
 */
function getHolidaysList() {
    $db = getDBConnection();
    if (!$db) return [];
    try {
        return $db->query("SELECT holiday_date FROM holidays")->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Check if a date is a working business day (Non-Weekend & Non-Holiday)
 */
function isWorkingDay(DateTime $date, array $holidays = []) {
    $dayOfWeek = (int)$date->format('N');
    if ($dayOfWeek >= 6) return false;
    if (in_array($date->format('Y-m-d'), $holidays)) return false;
    return true;
}

/**
 * Add working business days to a starting date
 */
function addBusinessDays(DateTime $startDate, $daysToAdd, array $holidays = []) {
    $currentDate = clone $startDate;
    
    while (!isWorkingDay($currentDate, $holidays)) {
        $currentDate->modify('+1 day');
    }

    $remainingDays = max(1, ceil((float)$daysToAdd)) - 1;
    while ($remainingDays > 0) {
        $currentDate->modify('+1 day');
        if (isWorkingDay($currentDate, $holidays)) {
            $remainingDays -= 1;
        }
    }
    return $currentDate;
}

/**
 * Recalculate Project Task Schedules skipping Weekends & Holidays
 */
function recalculateProjectSchedule($projectId) {
    $db = getDBConnection();
    if (!$db) return;

    $holidays = getHolidaysList();

    $stmtP = $db->prepare("SELECT start_date FROM projects WHERE id = :pid");
    $stmtP->execute(['pid' => $projectId]);
    $projectStart = $stmtP->fetchColumn() ?: date('Y-m-d');

    $stmtT = $db->prepare("SELECT id, current_days FROM tasks WHERE project_id = :pid ORDER BY sort_order ASC, id ASC");
    $stmtT->execute(['pid' => $projectId]);
    $tasks = $stmtT->fetchAll();

    $cursorDate = new DateTime($projectStart);
    $stmtUpd = $db->prepare("UPDATE tasks SET start_date = :sdate, due_date = :ddate WHERE id = :tid");

    foreach ($tasks as $task) {
        while (!isWorkingDay($cursorDate, $holidays)) {
            $cursorDate->modify('+1 day');
        }
        $startDateStr = $cursorDate->format('Y-m-d');

        $effortDays = max(0.5, (float)$task['current_days']);
        $dueDateObj = addBusinessDays($cursorDate, $effortDays, $holidays);
        $dueDateStr = $dueDateObj->format('Y-m-d');

        $stmtUpd->execute([
            'sdate' => $startDateStr,
            'ddate' => $dueDateStr,
            'tid'   => $task['id']
        ]);

        $cursorDate = clone $dueDateObj;
        $cursorDate->modify('+1 day');
    }

    if (!empty($tasks)) {
        $stmtLast = $db->prepare("SELECT MAX(due_date) FROM tasks WHERE project_id = :pid");
        $stmtLast->execute(['pid' => $projectId]);
        $maxDueDate = $stmtLast->fetchColumn();

        if ($maxDueDate) {
            $stmtProjUpd = $db->prepare("UPDATE projects SET target_completion_date = :maxdate WHERE id = :pid");
            $stmtProjUpd->execute(['maxdate' => $maxDueDate, 'pid' => $projectId]);
        }
    }
}

/**
 * Calculate Schedule Time Elapsed % between Start Date and Target Date as of today (Working Days)
 */
function getScheduleElapsedPercent($startDateStr, $targetDateStr) {
    if (!$startDateStr || !$targetDateStr) return 0;

    $holidays = getHolidaysList();
    $start    = new DateTime($startDateStr);
    $target   = new DateTime($targetDateStr);
    $today    = new DateTime(date('Y-m-d'));

    if ($today <= $start)  return 0;
    if ($today >= $target) return 100;

    $totalDays = 0;
    $curr = clone $start;
    while ($curr <= $target) {
        if (isWorkingDay($curr, $holidays)) {
            $totalDays++;
        }
        $curr->modify('+1 day');
    }

    if ($totalDays <= 0) return 100;

    $elapsedDays = 0;
    $curr = clone $start;
    while ($curr < $today) {
        if (isWorkingDay($curr, $holidays)) {
            $elapsedDays++;
        }
        $curr->modify('+1 day');
    }

    return min(100, round(($elapsedDays / $totalDays) * 100));
}

/**
 * Calculate Work Completion Progress based on completed task effort vs total task effort
 */
function getTaskWeightedProgress($projectId) {
    $db = getDBConnection();
    if (!$db) return 0;

    try {
        $stmt = $db->prepare("SELECT 
                                SUM(current_days) as total_days,
                                SUM(CASE WHEN status = 'Completed' THEN current_days ELSE 0 END) as completed_days
                              FROM tasks WHERE project_id = :pid");
        $stmt->execute(['pid' => $projectId]);
        $res = $stmt->fetch();

        $total = (float)($res['total_days'] ?? 0);
        $done  = (float)($res['completed_days'] ?? 0);

        if ($total <= 0) return 0;

        return min(100, round(($done / $total) * 100));
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Fetch RACI Matrix for a Task or Module
 */
function getRaciAssignments($entityType, $entityId) {
    $db = getDBConnection();
    if (!$db) return ['R' => [], 'A' => [], 'C' => [], 'I' => []];

    $sql = "SELECT rm.raci_role, tm.id, tm.full_name, tm.role_title 
            FROM raci_matrix rm
            JOIN team_members tm ON rm.member_id = tm.id
            WHERE rm.entity_type = :type AND rm.entity_id = :id";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(['type' => $entityType, 'id' => $entityId]);
        $rows = $stmt->fetchAll();

        $raci = ['R' => [], 'A' => [], 'C' => [], 'I' => []];
        foreach ($rows as $row) {
            $raci[$row['raci_role']][] = $row;
        }
        return $raci;
    } catch (PDOException $e) {
        return ['R' => [], 'A' => [], 'C' => [], 'I' => []];
    }
}

/**
 * Save RACI Matrix Roles for an Entity
 */
function saveRaciRoles($entityType, $entityId, $raciData) {
    $db = getDBConnection();
    if (!$db) return;

    try {
        $stmtDelete = $db->prepare("DELETE FROM raci_matrix WHERE entity_type = :type AND entity_id = :id");
        $stmtDelete->execute(['type' => $entityType, 'id' => $entityId]);

        $stmtInsert = $db->prepare("INSERT INTO raci_matrix (entity_type, entity_id, member_id, raci_role) VALUES (:type, :id, :mid, :role)");
        
        foreach (['R', 'A', 'C', 'I'] as $role) {
            if (!empty($raciData[$role])) {
                foreach ($raciData[$role] as $memberId) {
                    $stmtInsert->execute(['type' => $entityType, 'id' => $entityId, 'mid' => (int)$memberId, 'role' => $role]);
                }
            }
        }
    } catch (PDOException $e) {
        // Silently handle exceptions
    }
}

/**
 * Fetch team members assigned to any Governance Team in a given project
 */
function getProjectGovernanceMembers($projectId) {
    $db = getDBConnection();
    if (!$db) return [];
    try {
        $stmt = $db->prepare("SELECT DISTINCT tm.id, tm.full_name, tm.role_title 
                              FROM team_members tm
                              JOIN project_team_roles ptr ON tm.id = ptr.member_id
                              WHERE ptr.project_id = :pid
                              ORDER BY tm.full_name ASC");
        $stmt->execute(['pid' => $projectId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Role Check Helpers (Case-Insensitive)
 */
function isAdmin() {
    return isset($_SESSION['system_role']) && strtolower($_SESSION['system_role']) === 'admin';
}

function isPM() {
    return isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'pm';
}

function isViewer() {
    return isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'viewer';
}

/**
 * Get logged-in Team Member ID
 */
function getLoggedInMemberId() {
    return $_SESSION['member_id'] ?? 0;
}

/**
 * Check View Permission
 */
function canViewProject($projectId) {
    if (isAdmin()) return true;
    $role = getProjectMemberRole($projectId);
    return $role !== false;
}

/**
 * Check Edit Permission (Admins, PMs, and assigned Team Members can edit; Viewers cannot)
 */
function canEditProject($projectId) {
    if (isAdmin()) return true;
    $role = getProjectMemberRole($projectId);
    return in_array($role, ['Project Manager', 'Team Member']);
}

/**
 * Get SQL query for accessible projects based on Governance Membership
 */
function getAccessibleProjectsQuery() {
    if (isAdmin()) {
        return "SELECT p.*, c.name as category_name FROM projects p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC";
    }

    $mid = (int)getLoggedInMemberId();

    return "SELECT DISTINCT p.*, c.name as category_name 
            FROM projects p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN project_team_roles ptr ON p.id = ptr.project_id 
            WHERE p.manager_id = {$mid} OR ptr.member_id = {$mid} 
            ORDER BY p.id DESC";
}

/**
 * Fetch member's assigned role on a specific project
 * Returns: 'Project Manager', 'Team Member', 'Viewer', or false if not assigned
 */
function getProjectMemberRole($projectId, $memberId = null) {
    if (isAdmin()) return 'Admin';

    $mid = $memberId ?: getLoggedInMemberId();
    if (!$mid || !$projectId) return false;

    $db = getDBConnection();
    if (!$db) return false;

    // Check if member is designated as the Project Manager on the project record
    $stmtM = $db->prepare("SELECT COUNT(*) FROM projects WHERE id = :pid AND manager_id = :mid");
    $stmtM->execute(['pid' => $projectId, 'mid' => $mid]);
    if ($stmtM->fetchColumn() > 0) return 'Project Manager';

    // Check Governance Team role assignment
    $stmtG = $db->prepare("SELECT team_type FROM project_team_roles WHERE project_id = :pid AND member_id = :mid LIMIT 1");
    $stmtG->execute(['pid' => $projectId, 'mid' => $mid]);
    $teamType = $stmtG->fetchColumn();

    if ($teamType === 'Viewer') return 'Viewer';
    if ($teamType) return 'Team Member';

    return false; // Not a member of this project
}