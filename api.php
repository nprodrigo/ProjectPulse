<?php
/**
 * Backend API & Form Request Handler
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Authentication Guard
if (!isset($_SESSION['user_id']) && !isset($_SESSION['member_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit;
    }
    header('Location: login.php');
    exit;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_POST['action']) && in_array($_POST['action'], ['update_progress', 'advance_task_stage', 'toggle_pending', 'toggle_task', 'reorder_tasks', 'swap_task_order']));

$db = getDBConnection();
if (!$db) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Check config/database.php']);
        exit;
    } else {
        die('Database Connection Failed.');
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper function to auto-sync task-weighted progress to the main project record
function syncProjectTaskProgress($projectId) {
    $db = getDBConnection();
    if (!$db || $projectId <= 0) return;
    
    $weightedProgress = getTaskWeightedProgress($projectId);
    $status = ($weightedProgress >= 100) ? 'Completed' : 'In Progress';

    $stmt = $db->prepare("UPDATE projects SET progress_percent = :progress, status = :status WHERE id = :pid");
    $stmt->execute(['progress' => $weightedProgress, 'status' => $status, 'pid' => $projectId]);
}

switch ($action) {

    // ----------------------------------------------------------------------
    // 1. Projects Management
    // ----------------------------------------------------------------------
    case 'create_project':
        $title           = trim($_POST['title'] ?? '');
        $categoryId      = (int)($_POST['category_id'] ?? 1);
        $buId            = !empty($_POST['bu_id']) ? (int)$_POST['bu_id'] : null;
        $managerId       = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        $teamMemberIds   = $_POST['team_member_ids'] ?? [];
        $priority        = $_POST['priority'] ?? 'Medium';
        $startDate       = $_POST['start_date'] ?? date('Y-m-d');
        $targetDate      = $_POST['target_completion_date'] ?? date('Y-m-d', strtotime('+30 days'));
        $description     = trim($_POST['description'] ?? '');
        $needsAttention  = isset($_POST['needs_attention']) ? 1 : 0;
        $attentionReason = trim($_POST['attention_reason'] ?? '');

        $ownerName = 'Unassigned';
        if ($managerId) {
            $stmtM = $db->prepare("SELECT full_name FROM team_members WHERE id = :mid");
            $stmtM->execute(['mid' => $managerId]);
            $ownerName = $stmtM->fetchColumn() ?: 'Unassigned';
        }

        if ($title) {
            $stmt = $db->prepare("INSERT INTO projects (title, category_id, bu_id, manager_id, priority, owner_name, start_date, original_start_date, target_completion_date, original_target_date, description, needs_attention, attention_reason, status) 
                                  VALUES (:title, :category_id, :bu_id, :manager_id, :priority, :owner_name, :start_date, :orig_start, :target_date, :orig_target, :description, :needs_attention, :attention_reason, :status)");
            $stmt->execute([
                'title'            => $title,
                'category_id'      => $categoryId,
                'bu_id'            => $buId,
                'manager_id'       => $managerId,
                'priority'         => $priority,
                'owner_name'       => $ownerName,
                'start_date'       => $startDate,
                'orig_start'       => $startDate,
                'target_date'      => $targetDate,
                'orig_target'      => $targetDate,
                'description'      => $description,
                'needs_attention'  => $needsAttention,
                'attention_reason' => $needsAttention ? $attentionReason : null,
                'status'           => $needsAttention ? 'Needs Attention' : 'In Progress'
            ]);
            $newId = $db->lastInsertId();

            if (!empty($teamMemberIds)) {
                updateProjectTeam($newId, $teamMemberIds);
            }

            recalculateProjectSchedule($newId);

            header("Location: project_detail.php?id=" . $newId);
            exit;
        }
        break;

    case 'update_project':
        $projectId       = (int)($_POST['project_id'] ?? 0);
        $progressPercent = (int)($_POST['progress_percent'] ?? 0);
        $status          = $_POST['status'] ?? 'In Progress';
        $priority        = $_POST['priority'] ?? 'Medium';
        $needsAttention  = isset($_POST['needs_attention']) ? 1 : 0;
        $attentionReason = trim($_POST['attention_reason'] ?? '');

        if ($progressPercent >= 100) {
            $status = 'Completed';
            $needsAttention = 0;
        }

        if ($projectId > 0) {
            $stmt = $db->prepare("UPDATE projects SET 
                                  progress_percent = :progress, 
                                  status = :status, 
                                  priority = :priority, 
                                  needs_attention = :needs_attention, 
                                  attention_reason = :attention_reason 
                                  WHERE id = :id");
            $stmt->execute([
                'progress'         => $progressPercent,
                'status'           => $status,
                'priority'         => $priority,
                'needs_attention'  => $needsAttention,
                'attention_reason' => $needsAttention ? $attentionReason : null,
                'id'               => $projectId
            ]);
            header("Location: project_detail.php?id=" . $projectId);
            exit;
        }
        break;

    case 'update_progress':
        header('Content-Type: application/json');
        $projectId       = (int)($_POST['project_id'] ?? 0);
        $progressPercent = (int)($_POST['progress_percent'] ?? 0);
        $status          = $_POST['status'] ?? null;

        if ($projectId > 0) {
            $sql = "UPDATE projects SET progress_percent = :progress";
            $params = ['progress' => $progressPercent, 'id' => $projectId];
            if ($status) {
                $sql .= ", status = :status";
                $params['status'] = $status;
            }
            if ($progressPercent >= 100) {
                $sql .= ", status = 'Completed', needs_attention = 0";
            }
            $sql .= " WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
        exit;

    case 'update_governance_teams':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $teams     = $_POST['teams'] ?? [];

        if ($projectId > 0) {
            $stmtDel = $db->prepare("DELETE FROM project_team_roles WHERE project_id = :pid");
            $stmtDel->execute(['pid' => $projectId]);

            $stmtIns = $db->prepare("INSERT INTO project_team_roles (project_id, member_id, team_type) VALUES (:pid, :mid, :type)");
            foreach ($teams as $type => $memberIds) {
                if (is_array($memberIds)) {
                    foreach ($memberIds as $mid) {
                        $stmtIns->execute(['pid' => $projectId, 'mid' => (int)$mid, 'type' => $type]);
                    }
                }
            }

            header("Location: project_detail.php?id=" . $projectId);
            exit;
        }
        break;

    // ----------------------------------------------------------------------
    // 2. Daily Logs & Issues Tracking
    // ----------------------------------------------------------------------
    case 'add_daily_log':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $logText   = trim($_POST['log_text'] ?? '');
        $isBlocked = isset($_POST['is_blocked']) ? 1 : 0;

        if ($projectId > 0 && !empty($logText)) {
            addDailyLog($projectId, $logText, $isBlocked);
            header("Location: project_detail.php?id=" . $projectId . "&msg=log_added");
            exit;
        }
        break;

    case 'create_pending':
        $projectId   = (int)($_POST['project_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $assignedTo  = trim($_POST['assigned_to'] ?? '');
        $priority    = $_POST['priority'] ?? 'High';
        $description = trim($_POST['description'] ?? '');

        if ($projectId > 0 && $title) {
            $stmt = $db->prepare("INSERT INTO pendings (project_id, title, assigned_to, priority, description) 
                                  VALUES (:project_id, :title, :assigned_to, :priority, :description)");
            $stmt->execute([
                'project_id'  => $projectId,
                'title'       => $title,
                'assigned_to' => $assignedTo,
                'priority'    => $priority,
                'description' => $description
            ]);
            header("Location: project_detail.php?id=" . $projectId);
            exit;
        }
        break;

    case 'toggle_pending':
        header('Content-Type: application/json');
        $pendingId = (int)($_POST['pending_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'Resolved';

        if ($pendingId > 0) {
            $stmt = $db->prepare("UPDATE pendings SET status = :status, resolved_at = NOW() WHERE id = :id");
            $stmt->execute(['status' => $newStatus, 'id' => $pendingId]);
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Invalid pending ID']);
        exit;

    case 'create_milestone':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $dueDate   = $_POST['due_date'] ?? date('Y-m-d');
        $status    = $_POST['status'] ?? 'Pending';

        if ($projectId > 0 && $title) {
            $stmt = $db->prepare("INSERT INTO milestones (project_id, title, due_date, status) VALUES (:project_id, :title, :due_date, :status)");
            $stmt->execute([
                'project_id' => $projectId,
                'title'      => $title,
                'due_date'   => $dueDate,
                'status'     => $status
            ]);
            header("Location: project_detail.php?id=" . $projectId);
            exit;
        }
        break;

    // ----------------------------------------------------------------------
    // 3. Task Management, RACI Matrix, Stage Progression & Sequence Swapping
    // ----------------------------------------------------------------------
    case 'create_task_raci':
        $projectId    = (int)($_POST['project_id'] ?? 0);
        $title        = trim($_POST['title'] ?? '');
        $originalDays = sanitizeDays($_POST['original_days'] ?? 0.5);
        $priority     = $_POST['priority'] ?? 'Medium';
        $description  = trim($_POST['description'] ?? '');
        $raci         = $_POST['raci'] ?? [];

        if ($projectId > 0 && $title) {
            $stmtOrder = $db->prepare("SELECT IFNULL(MAX(sort_order), 0) + 1 FROM tasks WHERE project_id = :pid");
            $stmtOrder->execute(['pid' => $projectId]);
            $nextOrder = $stmtOrder->fetchColumn();

            $stmt = $db->prepare("INSERT INTO tasks (project_id, title, priority, original_days, current_days, effort_changes, sort_order, description) 
                                  VALUES (:pid, :title, :priority, :orig, :curr, 0.0, :sorder, :desc)");
            $stmt->execute([
                'pid'      => $projectId,
                'title'    => $title,
                'priority' => $priority,
                'orig'     => $originalDays,
                'curr'     => $originalDays,
                'sorder'   => $nextOrder,
                'desc'     => $description
            ]);
            
            $taskId = $db->lastInsertId();

            if ($taskId && !empty($raci)) {
                saveRaciRoles('Task', $taskId, $raci);
            }

            recalculateProjectSchedule($projectId);
            syncProjectTaskProgress($projectId);

            header("Location: project_detail.php?id=" . $projectId);
            exit;
        }
        break;

    case 'update_task_raci':
        $taskId       = (int)($_POST['task_id'] ?? 0);
        $projectId    = (int)($_POST['project_id'] ?? 0);
        $title        = trim($_POST['title'] ?? '');
        $effortChange = (float)($_POST['effort_change'] ?? 0.0);
        $actualDays   = sanitizeDays($_POST['actual_days'] ?? 0.0);
        $status       = $_POST['status'] ?? 'To Do';
        $priority     = $_POST['priority'] ?? 'Medium';
        $description  = trim($_POST['description'] ?? '');
        $raci         = $_POST['raci'] ?? [];

        if ($taskId > 0 && $projectId > 0) {
            $stmtCur = $db->prepare("SELECT original_days, effort_changes FROM tasks WHERE id = :tid");
            $stmtCur->execute(['tid' => $taskId]);
            $curData = $stmtCur->fetch();

            $additionalScope  = round($effortChange * 2) / 2;
            $totalScopeChange = (float)$curData['effort_changes'] + $additionalScope;
            $newCurrentDays   = max(0.5, (float)$curData['original_days'] + $totalScopeChange);

            $stmtUpd = $db->prepare("UPDATE tasks 
                                     SET title = :title, priority = :priority, status = :status, 
                                         effort_changes = :echange, current_days = :cdays, actual_days = :actdays, description = :desc 
                                     WHERE id = :tid");
            $stmtUpd->execute([
                'title'   => $title,
                'priority'=> $priority,
                'status'  => $status,
                'echange' => $totalScopeChange,
                'cdays'   => $newCurrentDays,
                'actdays' => $actualDays,
                'desc'    => $description,
                'tid'     => $taskId
            ]);

            if (!empty($raci)) {
                saveRaciRoles('Task', $taskId, $raci);
            }

            recalculateProjectSchedule($projectId);
            syncProjectTaskProgress($projectId);

            header("Location: project_detail.php?id=" . $projectId);
            exit;
        }
        break;

    case 'advance_task_stage':
        header('Content-Type: application/json');
        $taskId     = (int)($_POST['task_id'] ?? 0);
        $nextStatus = $_POST['next_status'] ?? '';
        $projectId  = (int)($_POST['project_id'] ?? 0);

        $allowedStatuses = ['To Do', 'In Progress', 'Under Review', 'Completed'];

        if ($taskId > 0 && in_array($nextStatus, $allowedStatuses)) {
            $stmt = $db->prepare("UPDATE tasks SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $nextStatus, 'id' => $taskId]);

            if ($projectId > 0) {
                syncProjectTaskProgress($projectId);
            }

            echo json_encode(['success' => true]);
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid task or status progression.']);
        exit;

    case 'swap_task_order':
        header('Content-Type: application/json');
        $taskId    = (int)($_POST['task_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        $projectId = (int)($_POST['project_id'] ?? 0);

        if ($taskId > 0 && $projectId > 0 && in_array($direction, ['up', 'down'])) {
            $stmtCur = $db->prepare("SELECT id, sort_order FROM tasks WHERE id = :tid AND project_id = :pid");
            $stmtCur->execute(['tid' => $taskId, 'pid' => $projectId]);
            $currentTask = $stmtCur->fetch();

            if ($currentTask) {
                $currentOrder = (int)$currentTask['sort_order'];

                if ($direction === 'up') {
                    $stmtAdj = $db->prepare("SELECT id, sort_order FROM tasks WHERE project_id = :pid AND sort_order < :sorder ORDER BY sort_order DESC LIMIT 1");
                } else {
                    $stmtAdj = $db->prepare("SELECT id, sort_order FROM tasks WHERE project_id = :pid AND sort_order > :sorder ORDER BY sort_order ASC LIMIT 1");
                }
                $stmtAdj->execute(['pid' => $projectId, 'sorder' => $currentOrder]);
                $adjacentTask = $stmtAdj->fetch();

                if ($adjacentTask) {
                    $adjacentId    = (int)$adjacentTask['id'];
                    $adjacentOrder = (int)$adjacentTask['sort_order'];

                    $stmtUpd = $db->prepare("UPDATE tasks SET sort_order = :sorder WHERE id = :tid");
                    $stmtUpd->execute(['sorder' => $adjacentOrder, 'tid' => $taskId]);
                    $stmtUpd->execute(['sorder' => $currentOrder, 'tid' => $adjacentId]);

                    recalculateProjectSchedule($projectId);

                    echo json_encode(['success' => true]);
                    exit;
                }
            }
        }
        echo json_encode(['success' => false, 'message' => 'Unable to reorder task position.']);
        exit;

    case 'toggle_task':
        header('Content-Type: application/json');
        $taskId    = (int)($_POST['task_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'Completed';

        if ($taskId > 0) {
            $stmtT = $db->prepare("SELECT project_id FROM tasks WHERE id = :id");
            $stmtT->execute(['id' => $taskId]);
            $pid = $stmtT->fetchColumn();

            $stmt = $db->prepare("UPDATE tasks SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $newStatus, 'id' => $taskId]);

            if ($pid) {
                syncProjectTaskProgress($pid);
            }

            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
        exit;

    case 'reorder_tasks':
        header('Content-Type: application/json');
        $projectId = (int)($_POST['project_id'] ?? 0);
        $taskIds   = $_POST['task_ids'] ?? [];

        if ($projectId > 0 && !empty($taskIds)) {
            $stmt = $db->prepare("UPDATE tasks SET sort_order = :order WHERE id = :tid AND project_id = :pid");
            foreach ($taskIds as $index => $tid) {
                $stmt->execute(['order' => $index + 1, 'tid' => (int)$tid, 'pid' => $projectId]);
            }

            recalculateProjectSchedule($projectId);
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Invalid task list or project ID']);
        exit;
}

header("Location: index.php");
exit;