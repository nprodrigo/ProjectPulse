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

// 3. Fetch task sequence & dynamic estimated task completion date
$tasks = getProjectTasks($project['id']);
$db = getDBConnection();
$stmtLast = $db->prepare("SELECT MAX(due_date) FROM tasks WHERE project_id = :pid");
$stmtLast->execute(['pid' => $project['id']]);
$calculatedCompletionDate = $stmtLast->fetchColumn();

// Fallback to project start date if no tasks exist
$estimatedCompletion = $calculatedCompletionDate ?: $project['start_date'];

// 4. Calculate Timeline Variance (Task Estimated vs Expected Target Date)
$scheduleVar = getScheduleVariance($project['target_completion_date'], $estimatedCompletion);
?>

<!-- Project Overview Header Card -->
<div class="card mb-3">
  <div class="card-body">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="card-title h1 mb-2"><?= htmlspecialchars($project['title']) ?></h2>
        
        <div class="row g-3 my-1">
          <div class="col-auto">
            <div class="text-secondary small">Start Date</div>
            <strong class="fs-4 text-reset"><i class="ti ti-calendar me-1 text-primary"></i><?= date('M d, Y', strtotime($project['start_date'])) ?></strong>
          </div>
          <div class="col-auto border-start ps-3">
            <div class="text-secondary small">Expected Target Completion</div>
            <strong class="fs-4 text-reset"><i class="ti ti-calendar-check me-1 text-warning"></i><?= date('M d, Y', strtotime($project['target_completion_date'])) ?></strong>
          </div>
          <div class="col-auto border-start ps-3">
            <div class="text-secondary small">Task Estimated Completion (Calculated)</div>
            <strong class="fs-4 text-info"><i class="ti ti-clock-play me-1"></i><?= $calculatedCompletionDate ? date('M d, Y', strtotime($calculatedCompletionDate)) : 'Pending Tasks' ?></strong>
            <span class="badge <?= $scheduleVar['class'] ?> ms-2"><?= $scheduleVar['status'] ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Team Structure by Functional/Strategic Group -->
<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title"><i class="ti ti-users me-2"></i>Project Governance Teams</h3>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <?php 
        $teamCategories = ['Strategic', 'Functional', 'Technical', 'Project Management'];
        foreach ($teamCategories as $cat): 
      ?>
        <div class="col-md-3">
          <div class="card card-sm bg-dark-lt">
            <div class="card-body">
              <div class="text-secondary fw-bold small mb-2"><?= $cat ?> Team</div>
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
                  <span class="badge bg-blue-lt mb-1 d-inline-block"><?= htmlspecialchars($m['full_name']) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Task List with RACI Matrix, Day Planning & Order Swappers -->
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title"><i class="ti ti-list-check me-2"></i>Sequential Task Schedule & RACI Matrix</h3>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTaskRaciModal">
      <i class="ti ti-plus me-1"></i> Add Task
    </button>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th style="width: 70px;">Seq</th>
          <th>Task Details</th>
          <th>RACI Roles</th>
          <th>Original / Scope Delta / Current</th>
          <th>Schedule (Auto-Calculated)</th>
          <th>Status</th>
          <th class="w-1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php 
          $totalTasks = count($tasks);
          if (empty($tasks)):
        ?>
          <tr>
            <td colspan="7" class="text-center text-secondary py-4">No tasks planned for this project yet.</td>
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
                <div class="btn-group-vertical ms-1">
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
              <div class="d-flex gap-1 flex-wrap">
                <span class="badge bg-green text-green-fg" title="Responsible: <?= !empty($raci['R']) ? implode(', ', array_column($raci['R'], 'full_name')) : 'Unassigned' ?>">R</span>
                <span class="badge bg-blue text-blue-fg" title="Accountable: <?= !empty($raci['A']) ? implode(', ', array_column($raci['A'], 'full_name')) : 'Unassigned' ?>">A</span>
                <span class="badge bg-amber text-amber-fg" title="Consulted: <?= !empty($raci['C']) ? implode(', ', array_column($raci['C'], 'full_name')) : 'Unassigned' ?>">C</span>
                <span class="badge bg-purple text-purple-fg" title="Informed: <?= !empty($raci['I']) ? implode(', ', array_column($raci['I'], 'full_name')) : 'Unassigned' ?>">I</span>
              </div>
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
              <span class="badge bg-secondary-lt"><?= htmlspecialchars($t['status']) ?></span>
            </td>
            <td>
              <button class="btn btn-sm btn-icon btn-ghost-secondary" 
                      title="Edit Task & Scope"
                      onclick='openEditTaskModal(<?= json_encode($t) ?>, <?= json_encode($raci) ?>)'>
                <i class="ti ti-edit"></i>
              </button>
            </td>
          </tr>
        <?php 
            endforeach; 
          endif;
        ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: View Task Details & RACI -->
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
        <button type="button" class="btn btn-primary" id="btn_open_edit_from_view"><i class="ti ti-edit me-1"></i>Edit Task Details</button>
      </div>
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
          <p class="text-secondary small mb-3">Select team members for each matrix responsibility role.</p>

          <?php $allMembers = getTeamMembers(); ?>

          <div class="row g-2">
            <div class="col-md-6 mb-3">
              <label class="form-label text-success fw-bold"><i class="ti ti-user-check me-1"></i>Responsible (R) - Doers</label>
              <select name="raci[R][]" class="form-select" multiple size="3">
                <?php foreach ($allMembers as $tm): ?>
                  <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label text-blue fw-bold"><i class="ti ti-shield-check me-1"></i>Accountable (A) - Approver</label>
              <select name="raci[A][]" class="form-select" multiple size="3">
                <?php foreach ($allMembers as $tm): ?>
                  <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label text-warning fw-bold"><i class="ti ti-messages me-1"></i>Consulted (C) - Advisors</label>
              <select name="raci[C][]" class="form-select" multiple size="3">
                <?php foreach ($allMembers as $tm): ?>
                  <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label text-purple fw-bold"><i class="ti ti-bell me-1"></i>Informed (I) - Updates Only</label>
              <select name="raci[I][]" class="form-select" multiple size="3">
                <?php foreach ($allMembers as $tm): ?>
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
                <?php foreach ($allMembers as $tm): ?>
                  <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label text-blue fw-bold">Accountable (A)</label>
              <select name="raci[A][]" id="edit_raci_A" class="form-select" multiple size="3">
                <?php foreach ($allMembers as $tm): ?>
                  <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label text-warning fw-bold">Consulted (C)</label>
              <select name="raci[C][]" id="edit_raci_C" class="form-select" multiple size="3">
                <?php foreach ($allMembers as $tm): ?>
                  <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label text-purple fw-bold">Informed (I)</label>
              <select name="raci[I][]" id="edit_raci_I" class="form-select" multiple size="3">
                <?php foreach ($allMembers as $tm): ?>
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

    document.getElementById('btn_open_edit_from_view').onclick = function() {
        var viewModalEl = document.getElementById('viewTaskModal');
        var viewModal = bootstrap.Modal.getInstance(viewModalEl);
        if (viewModal) viewModal.hide();
        openEditTaskModal(currentViewingTask, currentViewingRaci);
    };

    var viewModal = new bootstrap.Modal(document.getElementById('viewTaskModal'));
    viewModal.show();
}

function openEditTaskModal(task, raci) {
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

    var editModal = new bootstrap.Modal(document.getElementById('editTaskRaciModal'));
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