<?php
require_once __DIR__ . '/includes/header.php';

$selectedCategory = $_GET['category'] ?? '';
$selectedStatus   = $_GET['status'] ?? '';
$selectedPriority = $_GET['priority'] ?? '';
$searchQuery      = $_GET['search'] ?? '';

$projects = getProjects([
    'category' => $selectedCategory,
    'status'   => $selectedStatus,
    'priority' => $selectedPriority,
    'search'   => $searchQuery
]);
?>

<!-- Control Bar: Filters & Search -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
      <div class="col-md-3">
        <select name="category" class="form-select" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= urlencode($cat['slug']) ?>" <?= $selectedCategory === $cat['slug'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="In Progress" <?= $selectedStatus === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="Needs Attention" <?= $selectedStatus === 'Needs Attention' ? 'selected' : '' ?>>Needs Attention</option>
          <option value="Completed" <?= $selectedStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
        </select>
      </div>
      <div class="col-md-3">
        <select name="priority" class="form-select" onchange="this.form.submit()">
          <option value="">All Priorities</option>
          <option value="Critical" <?= $selectedPriority === 'Critical' ? 'selected' : '' ?>>Critical</option>
          <option value="High" <?= $selectedPriority === 'High' ? 'selected' : '' ?>>High</option>
          <option value="Medium" <?= $selectedPriority === 'Medium' ? 'selected' : '' ?>>Medium</option>
          <option value="Low" <?= $selectedPriority === 'Low' ? 'selected' : '' ?>>Low</option>
        </select>
      </div>
      <div class="col-md-3">
        <div class="input-icon">
          <input type="text" name="search" class="form-control" placeholder="Search projects..." value="<?= htmlspecialchars($searchQuery) ?>">
          <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Projects Grid -->
<div class="row row-cards">
  <?php if (empty($projects)): ?>
    <div class="col-12">
      <div class="card card-body text-center py-5">
        <p class="text-secondary mb-0">No projects found matching the selected criteria.</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($projects as $project): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card <?= $project['needs_attention'] ? 'card-border-start border-danger' : '' ?>">
          <div class="card-header d-flex justify-content-between">
            <span class="badge bg-secondary-lt"><?= htmlspecialchars($project['category_name']) ?></span>
            <div>
              <span class="badge bg-<?= $project['priority'] === 'Critical' ? 'danger' : 'primary' ?>-lt me-1"><?= $project['priority'] ?></span>
              <span class="badge bg-<?= $project['status'] === 'Completed' ? 'success' : 'blue' ?>-lt"><?= $project['status'] ?></span>
            </div>
          </div>
          
          <div class="card-body">
            <h3 class="card-title">
              <a href="project_detail.php?id=<?= $project['id'] ?>" class="text-reset">
                <?= htmlspecialchars($project['title']) ?>
              </a>
            </h3>
            <p class="text-secondary small text-truncate-2"><?= htmlspecialchars($project['description'] ?: 'No description provided.') ?></p>

            <?php if ($project['needs_attention'] && !empty($project['attention_reason'])): ?>
              <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
                <i class="ti ti-alert-triangle me-1"></i> <?= htmlspecialchars($project['attention_reason']) ?>
              </div>
            <?php endif; ?>

            <div class="mb-2">
              <div class="d-flex justify-content-between mb-1 small">
                <span class="text-secondary">Progress</span>
                <span class="fw-bold"><?= $project['progress_percent'] ?>%</span>
              </div>
              <div class="progress progress-sm">
                <div class="progress-bar <?= $project['status'] === 'Completed' ? 'bg-success' : ($project['needs_attention'] ? 'bg-danger' : 'bg-primary') ?>" 
                     style="width: <?= $project['progress_percent'] ?>%"></div>
              </div>
            </div>
          </div>

          <div class="card-footer d-flex justify-content-between align-items-center text-secondary small">
            <div class="d-flex align-items-center gap-2">
              <span class="avatar avatar-xs rounded-circle bg-blue-lt"><?= strtoupper(substr($project['owner_name'], 0, 1)) ?></span>
              <span><?= htmlspecialchars($project['owner_name']) ?></span>
            </div>
            <div><i class="ti ti-calendar me-1"></i><?= date('M d', strtotime($project['target_completion_date'])) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>