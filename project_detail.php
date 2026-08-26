<?php
require_once __DIR__ . '/includes/header.php';

// 1. Fetch Project ID from URL Query String
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project   = getProjectById($projectId);

// 2. Redirect or display error if project doesn't exist
if (!$project) {
    echo '<div class="alert alert-danger my-4">Project not found or invalid ID provided. <a href="index.php" class="alert-link">Return to Dashboard</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// 3. Unified Access Check: Ensure member has project access
if (!canViewProject($project['id'])) {
    echo '<div class="alert alert-danger my-4">Access Denied: You are not assigned to this project\'s team roster. <a href="index.php" class="alert-link">Return to Dashboard</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// 4. Define Read-Only Status (Viewers get read-only access)
$isReadOnly = !canEditProject($project['id']);

// 5. Fetch Task Sequence & Calculated Completion Date
$tasks = getProjectTasks($project['id']);
$db    = getDBConnection();

$stmtLast = $db->prepare("SELECT MAX(due_date) FROM tasks WHERE project_id = :pid");
$stmtLast->execute(['pid' => $project['id']]);
$calculatedCompletionDate = $stmtLast->fetchColumn();

// 6. Calculate Dual Progress KPIs & Health Variance
$timeElapsedPercent  = getScheduleElapsedPercent($project['start_date'], $project['target_completion_date']);
$manualProgress      = (int)($project['progress_percent'] ?? 0);
$taskWeightedPercent = getTaskWeightedProgress($project['id']);

// Use task weighted progress if manual progress is 0 but completed tasks exist
$displayProgress = ($manualProgress === 0 && $taskWeightedPercent > 0) ? $taskWeightedPercent : $manualProgress;

$paceVariance   = $displayProgress - $timeElapsedPercent;
$paceBadgeClass = 'bg-success-lt text-success';
$paceStatusText = 'On Track';

if ($paceVariance < -15) {
    $paceBadgeClass = 'bg-danger-lt text-danger';
    $paceStatusText = 'Behind Schedule';
} elseif ($paceVariance < 0) {
    $paceBadgeClass = 'bg-warning-lt text-warning';
    $paceStatusText = 'Slight Delay';
}

$scheduleVar = getScheduleVariance($project['target_completion_date'], $calculatedCompletionDate);

// 7. Fetch Members assigned to this project's Governance Teams for RACI assignment
$projectGovernanceMembers = getProjectGovernanceMembers($project['id']);
if (empty($projectGovernanceMembers)) {
    $projectGovernanceMembers = getTeamMembers();
}

$teamCategories = ['Strategic', 'Functional', 'Technical', 'Project Management', 'Viewer'];
?>

<!-- Print & PDF Stylesheet -->
<style>
@media print {
  .d-print-none, .navbar, .modal, .btn, .btn-group-vertical, header {
    display: none !important;
  }
  body {
    background: #fff !important;
    color: #000 !important;
  }
  .card {
    border: 1px solid #ccc !important;
    box-shadow: none !important;
    page-break-inside: avoid;
  }
  .table {
    width: 100% !important;
    border-collapse: collapse !important;
  }
  .table td, .table th {
    padding: 6px 10px !important;
  }
}
</style>

<!-- Top Actions Bar (Export & Print) -->
<div class="d-flex justify-content-end gap-2 mb-3 d-print-none">
  <button class="btn btn-outline-secondary" onclick="window.print()">
    <i class="ti ti-printer me-1"></i> Print / Save as PDF
  </button>
</div>

<!-- Project Overview Header Card with Dual Progress KPIs -->
<div class="card mb-3">
  <div class="card-body">
    <div class="row align-items-center mb-3">
      <div class="col">
        <h2 class="card-title h1 mb-1"><?= htmlspecialchars($project['title']) ?></h2>
        <div class="text-secondary small">
          Category: <span class="badge bg-secondary-lt"><?= htmlspecialchars($project['category_name']) ?></span> &bull;
          Pace Status: <span class="badge <?= $paceBadgeClass ?> ms-1"><?= $paceStatusText ?></span> &bull;
          Target Schedule: <span class="badge <?= $scheduleVar['class'] ?> ms-1"><?= $scheduleVar['status'] ?></span>
          <?php if ($isReadOnly): ?>
            &bull; Access Level: <span class="badge bg-warning-lt text-warning ms-1">Read-Only Viewer</span>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!$isReadOnly): ?>
        <div class="col-auto d-print-none">
          <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateProgressModal">
            <i class="ti ti-adjustments me-1"></i> Update Progress %
          </button>
        </div>
      <?php endif; ?>
    </div>

    <!-- Dual Progress KPI Cards -->
    <div class="row g-3">
      <!-- KPI 1: Time Pace (Schedule Elapsed) -->
      <div class="col-md-6">
        <div class="card card-sm bg-dark-lt p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary fw-bold small">
              <i class="ti ti-clock me-1 text-primary"></i>1. Schedule Time Elapsed (As of Today)
            </span>
            <strong class="text-primary fs-3"><?= $timeElapsedPercent ?>%</strong>
          </div>
          <div class="progress progress-sm">
            <div class="progress-bar bg-primary" style="width: <?= $timeElapsedPercent ?>%" role="progressbar" aria-valuenow="<?= $timeElapsedPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <div class="d-flex justify-content-between text-muted small mt-2">
            <span>Start Date: <strong><?= date('M d, Y', strtotime($project['start_date'])) ?></strong></span>
            <span>Target Deadline: <strong><?= date('M d, Y', strtotime($project['target_completion_date'])) ?></strong></span>
          </div>
        </div>
      </div>

      <!-- KPI 2: Actual Execution Progress -->
      <div class="col-md-6">
        <div class="card card-sm bg-dark-lt p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary fw-bold small">
              <i class="ti ti-chart-pie me-1 text-success"></i>2. Actual Work Completed (Earned / Manual)
            </span>
            <strong class="text-success fs-3"><?= $displayProgress ?>% <small class="text-muted fs-6">(Task Weighted: <?= $taskWeightedPercent ?>%)</small></strong>
          </div>
          <div class="progress progress-sm">
            <div class="progress-bar bg-success" style="width: <?= $displayProgress ?>%" role="progressbar" aria-valuenow="<?= $displayProgress ?>" aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <div class="d-flex justify-content-between text-muted small mt-2">
            <span>Pace Variance: <strong class="<?= $paceVariance < 0 ? 'text-danger' : 'text-success' ?>"><?= ($paceVariance >= 0 ? '+' : '') . $paceVariance ?>%</strong></span>
            <span>Task Estimated Finish: <strong><?= $calculatedCompletionDate ? date('M d, Y', strtotime($calculatedCompletionDate)) : 'Pending Tasks' ?></strong></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Governance Teams Roster Structure -->
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title"><i class="ti ti-users me-2"></i>Project Governance & Access Roster</h3>
    <?php if (!$isReadOnly): ?>
      <button class="btn btn-outline-primary btn-sm d-print-none" data-bs-toggle="modal" data-bs-target="#manageGovernanceModal">
        <i class="ti ti-user-plus me-1"></i> Manage Governance & Access
      </button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php foreach ($teamCategories as $cat): ?>
        <div class="col">
          <div class="card card-sm bg-dark-lt h-100">
            <div class="card-body">
              <div class="<?= $cat === 'Viewer' ? 'text-warning' : 'text-secondary' ?> fw-bold small mb-2">
                <?= $cat === 'Viewer' ? 'Viewers (Read-Only)' : $cat . ' Team' ?>
              </div>
              <?php
                $stmtTeam = $db->prepare("SELECT tm.full_name FROM team_members tm 
                                          JOIN project_team_roles ptr ON tm.id = ptr.member_id 
                                          WHERE ptr.project_id = :pid AND ptr.team_type = :type");
                $stmtTeam->execute(['pid' => $project['id'], 'type' => $cat]);
                $members = $stmtTeam->fetchAll();
              ?>
              <?php if (empty($members)): ?>
                <span class="text-secondary small">Unassigned</span>
              <?php else: ?>
                <?php foreach ($members as $m): ?>
                  <span class="badge <?= $cat === 'Viewer' ? 'bg-warning-lt' : 'bg-blue-lt' ?> mb-1 d-inline-block"><?= htmlspecialchars($m['full_name']) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Task Schedule Table (Easy Reference: Responsible Only) -->
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title"><i class="ti ti-list-check me-2"></i>Sequential Task Schedule & RACI Assignments</h3>
    <?php if (!$isReadOnly): ?>
      <button class="btn btn-primary btn-sm d-print-none" data-bs-toggle="modal" data-bs-target="#addTaskRaciModal">
        <i class="ti ti-plus me-1"></i> Add Task
      </button>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th style="width: 70px;">Seq</th>
          <th>Task Details</th>
          <th>Responsible (R) Person</th>
          <th>Original / Scope Delta / Current</th>
          <th>Schedule (Auto-Calculated)</th>
          <th>Status</th>
          <?php if (!$isReadOnly): ?>
            <th class="w-1 d-print-none">Actions</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php 
          $totalTasks = count($tasks);
          if (empty($tasks)):
        ?>
          <tr>
            <td colspan="<?= $isReadOnly ? '6' : '7' ?>" class="text-center text-secondary py-4">No tasks planned for this project yet.</td>
          </tr>
        <?php 
          else:
            foreach ($tasks as $idx => $t): 
              $raci = getRaciAssignments('Task', $t['id']);
        ?>
          <tr>
            <td class="text-secondary fw-bold">
              <div class="d-flex align-items-center gap-1">
                <span><?= $idx + 1 ?></span>
                <?php if (!$isReadOnly): ?>
                  <div class="btn-group-vertical ms-1 d-print-none">
                    <?php if ($idx > 0): ?>
                      <button class="btn btn-ghost-secondary btn-icon btn-xs py-0 px-1" 
                              title="Move Up" 
                              onclick="moveTask(<?= $t['id'] ?>, 'up')">
                        <i class="ti ti-chevron-up fs-4"></i>
                      </button>
                    <?php endif; ?>
                    <?php if ($idx < $totalTasks - 1): ?>
                      <button class="btn btn-ghost-secondary btn-icon btn-xs py-0 px-1" 
                              title="Move Down" 
                              onclick="moveTask(<?= $t['id'] ?>, 'down')">
                        <i class="ti ti-chevron-down fs-4"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <a href="javascript:void(0)" 
                 class="fw-bold text-decoration-none text-reset task-title-link" 
                 onclick='viewTaskDetails(<?= json_encode($t) ?>, <?= json_encode($raci) ?>)'
                 style="cursor: pointer;">
                <i class="ti ti-file-text me-1 text-primary"></i><?= htmlspecialchars($t['title']) ?>
              </a>
              <div class="text-secondary small text-truncate" style="max-width: 250px;">
                <?= htmlspecialchars($t['description'] ?: 'No additional notes') ?>
              </div>
            </td>
            <td>
              <?php if (!empty($raci['R'])): ?>
                <?php foreach ($raci['R'] as $rMember): ?>
                  <span class="badge bg-green-lt text-green border-green mb-1 d-inline-flex align-items-center">
                    <i class="ti ti-user-check me-1"></i><?= htmlspecialchars($rMember['full_name']) ?>
                  </span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="text-secondary small fst-italic">Unassigned</span>
              <?php endif; ?>
            </td>
            <td>
              <div>Orig: <strong><?= number_format($t['original_days'] ?? 0.5, 1) ?>d</strong></div>
              <div class="small text-muted">
                Delta: <span class="<?= ($t['effort_changes'] ?? 0) > 0 ? 'text-danger' : (($t['effort_changes'] ?? 0) < 0 ? 'text-success' : '') ?>"><?= (($t['effort_changes'] ?? 0) >= 0 ? '+' : '') . number_format($t['effort_changes'] ?? 0, 1) ?>d</span> 
                &bull; Total: <strong class="text-info"><?= number_format($t['current_days'] ?? 0.5, 1) ?>d</strong>
              </div>
            </td>
            <td>
              <div class="small fw-bold">
                <?= !empty($t['start_date']) ? date('M d', strtotime($t['start_date'])) : 'TBD' ?> 
                &rarr; 
                <?= !empty($t['due_date']) ? date('M d, Y', strtotime($t['due_date'])) : 'TBD' ?>
              </div>
            </td>
            <td>
              <span class="badge <?= $t['status'] === 'Completed' ? 'bg-success-lt text-success' : ($t['status'] === 'In Progress' ? 'bg-primary-lt text-primary' : 'bg-secondary-lt') ?>">
                <?= htmlspecialchars($t['status']) ?>
              </span>
            </td>
            <?php if (!$isReadOnly): ?>
              <td class="d-print-none">
                <button class="btn btn-sm btn-icon btn-ghost-secondary" 
                        title="Edit Task & Scope"
                        onclick='openEditTaskModal(<?= json_encode($t) ?>, <?= json_encode($raci) ?>)'>
                  <i class="ti ti-edit"></i>
                </button>
              </td>
            <?php endif; ?>
          </tr>
        <?php 
            endforeach; 
          endif;
        ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: View Task Details -->
<div class="modal modal-blur fade" id="viewTaskModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="v_task_title"><i class="ti ti-info-circle me-2 text-primary"></i>Task Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3 align-items-center">
          <div class="col-auto">
            <span class="badge bg-secondary-lt fs-6" id="v_task_status">Status</span>
          </div>
          <div class="col-auto">
            <span class="badge bg-primary-lt fs-6" id="v_task_priority">Priority</span>
          </div>
          <div class="col text-end text-secondary small">
            Schedule: <strong id="v_task_schedule" class="text-reset">--</strong>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-bold text-secondary">Description / Notes</label>
          <div class="card card-sm bg-dark-lt p-3 text-secondary" id="v_task_desc">No notes provided.</div>
        </div>
        <h4 class="mb-3 text-primary"><i class="ti ti-users me-2"></i>RACI Matrix Member Assignments</h4>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="card card-sm border-success">
              <div class="card-body">
                <div class="text-success fw-bold small mb-2"><i class="ti ti-user-check me-1"></i>Responsible (R) - Doers</div>
                <div id="v_raci_R" class="d-flex flex-wrap gap-1"><span class="text-secondary small">None</span></div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card card-sm border-blue">
              <div class="card-body">
                <div class="text-blue fw-bold small mb-2"><i class="ti ti-shield-check me-1"></i>Accountable (A) - Approvers</div>
                <div id="v_raci_A" class="d-flex flex-wrap gap-1"><span class="text-secondary small">None</span></div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card card-sm border-warning">
              <div class="card-body">
                <div class="text-warning fw-bold small mb-2"><i class="ti ti-messages me-1"></i>Consulted (C) - Advisors</div>
                <div id="v_raci_C" class="d-flex flex-wrap gap-1"><span class="text-secondary small">None</span></div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card card-sm border-purple">
              <div class="card-body">
                <div class="text-purple fw-bold small mb-2"><i class="ti ti-bell me-1"></i>Informed (I) - Updates Only</div>
                <div id="v_raci_I" class="d-flex flex-wrap gap-1"><span class="text-secondary small">None</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Close</button>
        <?php if (!$isReadOnly): ?>
          <button type="button" class="btn btn-primary" id="btn_open_edit_from_view"><i class="ti ti-edit me-1"></i>Edit Task Details</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if (!$isReadOnly): ?>
  <!-- Modal: Manual Progress Update -->
  <div class="modal modal-blur fade" id="updateProgressModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <form action="api.php" method="POST">
          <input type="hidden" name="action" value="update_project">
          <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
          <input type="hidden" name="status" value="<?= htmlspecialchars($project['status']) ?>">
          <input type="hidden" name="priority" value="<?= htmlspecialchars($project['priority']) ?>">

          <div class="modal-header">
            <h5 class="modal-title"><i class="ti ti-adjustments me-2"></i>Update Project Completion %</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Manual Progress Percentage</label>
              <div class="d-flex align-items-center gap-3">
                <input type="range" class="form-range flex-fill" min="0" max="100" step="5" name="progress_percent" id="progressRange" value="<?= $manualProgress ?>" oninput="document.getElementById('progressOutput').innerText = this.value + '%'">
                <span class="badge bg-primary fs-3" id="progressOutput"><?= $manualProgress ?>%</span>
              </div>
              <span class="form-hint mt-2">Current calculated task-weighted progress is <strong><?= $taskWeightedPercent ?>%</strong>.</span>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i>Save Progress</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Manage Governance Teams & Access Roster -->
  <div class="modal modal-blur fade" id="manageGovernanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <form action="api.php" method="POST">
          <input type="hidden" name="action" value="update_governance_teams">
          <input type="hidden" name="project_id" value="<?= $project['id'] ?>">

          <div class="modal-header">
            <h5 class="modal-title"><i class="ti ti-users me-2"></i>Manage Governance & Access Roster</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <p class="text-secondary small mb-3">Assign team members to governance categories. Members added here can access this project and populate RACI assignment dropdowns.</p>

            <?php 
              $allGlobalMembers = getTeamMembers();
              $assignedByType = [];
              foreach ($teamCategories as $cat) {
                  $stmtG = $db->prepare("SELECT member_id FROM project_team_roles WHERE project_id = :pid AND team_type = :type");
                  $stmtG->execute(['pid' => $project['id'], 'type' => $cat]);
                  $assignedByType[$cat] = $stmtG->fetchAll(PDO::FETCH_COLUMN);
              }
            ?>

            <div class="row g-3">
              <?php foreach ($teamCategories as $cat): ?>
                <div class="col-md-6 mb-2">
                  <label class="form-label fw-bold <?= $cat === 'Viewer' ? 'text-warning' : 'text-primary' ?>">
                    <?= $cat === 'Viewer' ? 'Viewer (Read-Only Access)' : $cat . ' Team' ?>
                  </label>
                  <select name="teams[<?= $cat ?>][]" class="form-select" multiple size="4">
                    <?php foreach ($allGlobalMembers as $tm): ?>
                      <option value="<?= $tm['id'] ?>" <?= in_array($tm['id'], $assignedByType[$cat]) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tm['full_name']) ?> (<?= htmlspecialchars($tm['role_title'] ?: 'Member') ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i>Save Governance Roster</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Add Task with RACI -->
  <div class="modal modal-blur fade" id="addTaskRaciModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <form action="api.php" method="POST">
          <input type="hidden" name="action" value="create_task_raci">
          <input type="hidden" name="project_id" value="<?= $project['id'] ?>">

          <div class="modal-header">
            <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Sequential Task</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Task Title</label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Conduct System Integration Testing" required>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">Original Effort Estimation (Days)</label>
                <input type="number" step="0.5" min="0.5" name="original_days" class="form-control" value="1.0" required>
                <span class="form-hint">Minimum 0.5 days; accepts 0.5 increments.</span>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                  <option value="Medium">Medium</option>
                  <option value="High">High</option>
                  <option value="Critical">Critical</option>
                  <option value="Low">Low</option>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="2" placeholder="Detail scope or key deliverables..."></textarea>
            </div>

            <hr class="my-3">
            <h4 class="mb-2 text-primary">RACI Matrix Assignment</h4>
            <div class="row g-2">
              <div class="col-md-6 mb-3">
                <label class="form-label text-success fw-bold"><i class="ti ti-user-check me-1"></i>Responsible (R)</label>
                <select name="raci[R][]" class="form-select" multiple size="3">
                  <?php foreach ($projectGovernanceMembers as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label text-blue fw-bold"><i class="ti ti-shield-check me-1"></i>Accountable (A)</label>
                <select name="raci[A][]" class="form-select" multiple size="3">
                  <?php foreach ($projectGovernanceMembers as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label text-warning fw-bold"><i class="ti ti-messages me-1"></i>Consulted (C)</label>
                <select name="raci[C][]" class="form-select" multiple size="3">
                  <?php foreach ($projectGovernanceMembers as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label text-purple fw-bold"><i class="ti ti-bell me-1"></i>Informed (I)</label>
                <select name="raci[I][]" class="form-select" multiple size="3">
                  <?php foreach ($projectGovernanceMembers as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Task & Auto-Schedule</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Edit Task & Scope Changes -->
  <div class="modal modal-blur fade" id="editTaskRaciModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <form action="api.php" method="POST">
          <input type="hidden" name="action" value="update_task_raci">
          <input type="hidden" name="task_id" id="edit_task_id">
          <input type="hidden" name="project_id" value="<?= $project['id'] ?>">

          <div class="modal-header">
            <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit Task & Log Effort Changes</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Task Title</label>
              <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Additional Scope Delta (Days)</label>
                <input type="number" step="0.5" name="effort_change" class="form-control" placeholder="e.g. +0.5 or -0.5" value="0.0">
                <span class="form-hint">Adjust current total scope (0.5 steps).</span>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Actual Spent Days</label>
                <input type="number" step="0.5" min="0.0" name="actual_days" id="edit_actual_days" class="form-control" value="0.0">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Status</label>
                <select name="status" id="edit_status" class="form-select">
                  <option value="To Do">To Do</option>
                  <option value="In Progress">In Progress</option>
                  <option value="Under Review">Under Review</option>
                  <option value="Completed">Completed</option>
                </select>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Description / Scope Change Reason</label>
              <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
            </div>

            <hr class="my-3">
            <h4 class="mb-2 text-primary">Update RACI Matrix Assignment</h4>
            <div class="row g-2">
              <div class="col-md-6 mb-3">
                <label class="form-label text-success fw-bold">Responsible (R)</label>
                <select name="raci[R][]" id="edit_raci_R" class="form-select" multiple size="3">
                  <?php foreach ($projectGovernanceMembers as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label text-blue fw-bold">Accountable (A)</label>
                <select name="raci[A][]" id="edit_raci_A" class="form-select" multiple size="3">
                  <?php foreach ($projectGovernanceMembers as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label text-warning fw-bold">Consulted (C)</label>
                <select name="raci[C][]" id="edit_raci_C" class="form-select" multiple size="3">
                  <?php foreach ($projectGovernanceMembers as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label text-purple fw-bold">Informed (I)</label>
                <select name="raci[I][]" id="edit_raci_I" class="form-select" multiple size="3">
                  <?php foreach ($projectGovernanceMembers as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Task & Recalculate</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
let currentViewingTask = null;
let currentViewingRaci = null;

function viewTaskDetails(task, raci) {
    currentViewingTask = task;
    currentViewingRaci = raci;

    document.getElementById('v_task_title').innerHTML = '<i class="ti ti-file-text me-2 text-primary"></i>' + escapeHtml(task.title);
    document.getElementById('v_task_status').innerText = task.status || 'To Do';
    document.getElementById('v_task_priority').innerText = (task.priority || 'Medium') + ' Priority';
    document.getElementById('v_task_schedule').innerText = (task.start_date || 'TBD') + ' to ' + (task.due_date || 'TBD');
    document.getElementById('v_task_desc').innerText = task.description || 'No description provided for this task.';

    ['R', 'A', 'C', 'I'].forEach(function(role) {
        let container = document.getElementById('v_raci_' + role);
        container.innerHTML = '';
        
        if (raci && raci[role] && raci[role].length > 0) {
            raci[role].forEach(function(member) {
                let badge = document.createElement('span');
                badge.className = 'badge bg-secondary-lt me-1 mb-1';
                badge.innerHTML = '<i class="ti ti-user me-1"></i>' + escapeHtml(member.full_name) + ' <small class="text-muted">(' + escapeHtml(member.role_title || 'Member') + ')</small>';
                container.appendChild(badge);
            });
        } else {
            container.innerHTML = '<span class="text-secondary small">Unassigned</span>';
        }
    });

    var editBtn = document.getElementById('btn_open_edit_from_view');
    if (editBtn) {
        editBtn.onclick = function() {
            var viewModalEl = document.getElementById('viewTaskModal');
            var viewModal = bootstrap.Modal.getInstance(viewModalEl);
            if (viewModal) viewModal.hide();
            openEditTaskModal(currentViewingTask, currentViewingRaci);
        };
    }

    var viewModal = new bootstrap.Modal(document.getElementById('viewTaskModal'));
    viewModal.show();
}

function openEditTaskModal(task, raci) {
    var editModalEl = document.getElementById('editTaskRaciModal');
    if (!editModalEl) return;

    document.getElementById('edit_task_id').value = task.id;
    document.getElementById('edit_title').value = task.title;
    document.getElementById('edit_actual_days').value = task.actual_days || 0.0;
    document.getElementById('edit_status').value = task.status;
    document.getElementById('edit_description').value = task.description || '';

    ['R', 'A', 'C', 'I'].forEach(function(role) {
        var select = document.getElementById('edit_raci_' + role);
        if (select) {
            Array.from(select.options).forEach(function(opt) {
                opt.selected = false;
            });
            if (raci && raci[role]) {
                var memberIds = raci[role].map(function(m) { return m.id; });
                Array.from(select.options).forEach(function(opt) {
                    if (memberIds.includes(parseInt(opt.value))) {
                        opt.selected = true;
                    }
                });
            }
        }
    });

    var editModal = new bootstrap.Modal(editModalEl);
    editModal.show();
}

function moveTask(taskId, direction) {
    let formData = new FormData();
    formData.append('action', 'swap_task_order');
    formData.append('task_id', taskId);
    formData.append('direction', direction);
    formData.append('project_id', '<?= $project['id'] ?>');

    fetch('api.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Error swapping task order.');
        }
    })
    .catch(error => console.error('Error swapping task position:', error));
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

