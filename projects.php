<?php
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();

// Fetch role-scoped accessible projects
$sql   = getAccessibleProjectsQuery();
$stmt  = $db->query($sql);
$projects = $stmt->fetchAll();

$userRole = strtolower($_SESSION['role'] ?? 'viewer');
?>

<!-- Header Bar -->
<div class="card mb-3 d-print-none">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h2 class="card-title h1 mb-1"><i class="ti ti-folders me-2 text-primary"></i>All Projects Directory</h2>
        <div class="text-secondary small">
          Showing projects accessible to your role: 
          <span class="badge bg-indigo-lt text-indigo uppercase font-weight-bold ms-1"><?= htmlspecialchars($userRole) ?></span>
        </div>
      </div>
      
      <!-- Strictly Hide New Project button from Viewers -->
      <?php if ($userRole !== 'viewer'): ?>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
          <i class="ti ti-plus me-1"></i> New Project
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Projects Grid View -->
<div class="row row-cards">
  <?php if (empty($projects)): ?>
    <div class="col-12">
      <div class="card card-body text-center py-5">
        <i class="ti ti-folder-off fs-1 text-secondary mb-2"></i>
        <h3 class="text-secondary">No Projects Available</h3>
        <p class="text-muted small mb-0">You do not have permission to access any active projects, or no projects match your user assignment.</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($projects as $p): ?>
      <?php 
        $timeElapsed  = getScheduleElapsedPercent($p['start_date'], $p['target_completion_date']);
        $progress     = (int)($p['progress_percent'] ?? 0);
        $taskWeighted = getTaskWeightedProgress($p['id']);
        
        $variance = $progress - $timeElapsed;
        $statusBadge = 'bg-success-lt text-success';
        $statusText  = 'On Track';

        if ($variance < -15) {
            $statusBadge = 'bg-danger-lt text-danger';
            $statusText  = 'Behind Schedule';
        } elseif ($variance < 0) {
            $statusBadge = 'bg-warning-lt text-warning';
            $statusText  = 'Slight Delay';
        }
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="card card-sm">
          <div class="card-status-top bg-primary"></div>
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="badge bg-secondary-lt"><?= htmlspecialchars($p['category_name'] ?: 'General') ?></span>
              <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
            </div>

            <h3 class="card-title mb-2">
              <a href="project_detail.php?id=<?= $p['id'] ?>" class="text-reset text-decoration-none">
                <i class="ti ti-box me-1 text-primary"></i><?= htmlspecialchars($p['title']) ?>
              </a>
            </h3>

            <p class="text-secondary small text-truncate mb-3" style="max-width: 100%;">
              <?= htmlspecialchars($p['description'] ?: 'No description provided.') ?>
            </p>

            <!-- Dual Progress Metrics -->
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center small mb-1">
                <span class="text-muted"><i class="ti ti-clock me-1"></i>Time Elapsed:</span>
                <strong><?= $timeElapsed ?>%</strong>
              </div>
              <div class="progress progress-sm mb-2">
                <div class="progress-bar bg-primary" style="width: <?= $timeElapsed ?>%"></div>
              </div>

              <div class="d-flex justify-content-between align-items-center small mb-1">
                <span class="text-muted"><i class="ti ti-chart-pie me-1"></i>Work Progress:</span>
                <strong class="text-success"><?= $progress ?>% <small class="text-muted">(Task: <?= $taskWeighted ?>%)</small></strong>
              </div>
              <div class="progress progress-sm">
                <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
              </div>
            </div>

            <!-- Dates & Owner Footer -->
            <div class="d-flex justify-content-between align-items-center border-top pt-2 text-muted small">
              <div>
                <i class="ti ti-user me-1"></i><?= htmlspecialchars($p['owner_name'] ?: 'Unassigned') ?>
              </div>
              <div>
                <i class="ti ti-calendar me-1"></i><?= date('M d, Y', strtotime($p['target_completion_date'])) ?>
              </div>
            </div>
          </div>

          <div class="card-footer bg-transparent border-0 pt-0 text-end">
            <a href="project_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
              View Project Details <i class="ti ti-arrow-right ms-1"></i>
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>