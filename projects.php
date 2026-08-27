<?php
require_once __DIR__ . '/includes/header.php';

$selectedStatus = $_GET['status'] ?? 'active';
$allProjects = getProjects();

// Filter Projects based on status tab
$filteredProjects = array_filter($allProjects, function($proj) use ($selectedStatus) {
    if ($selectedStatus === 'completed') {
        return ($proj['status'] ?? '') === 'Completed';
    }
    return ($proj['status'] ?? '') !== 'Completed';
});
?>

<div class="container" style="padding: 1.5rem 0;">
  
  <!-- Directory Header & Status Filter Tabs -->
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
      <h2 style="font-size: 1.4rem; font-weight: 700; color: #ffffff;">
        <i class="fa-solid fa-folder-open"></i> Projects Directory
      </h2>
      <p style="color: #94a3b8; font-size: 0.85rem;">Detailed execution view and project controls</p>
    </div>

    <!-- Filter Buttons (Active vs Completed) -->
    <div style="display: flex; gap: 0.5rem; background: #1e293b; padding: 0.3rem; border-radius: 6px;">
      <a href="projects.php?status=active" class="btn" style="padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 4px; text-decoration: none; color: <?= $selectedStatus === 'active' ? '#fff' : '#94a3b8' ?>; background: <?= $selectedStatus === 'active' ? '#2563eb' : 'transparent' ?>;">
        Active Projects
      </a>
      <a href="projects.php?status=completed" class="btn" style="padding: 0.4rem 1rem; font-size: 0.85rem; border-radius: 4px; text-decoration: none; color: <?= $selectedStatus === 'completed' ? '#fff' : '#94a3b8' ?>; background: <?= $selectedStatus === 'completed' ? '#2563eb' : 'transparent' ?>;">
        Completed Archive
      </a>
    </div>
  </div>

  <!-- Cards Grid View -->
  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
    <?php if (empty($filteredProjects)): ?>
      <div style="grid-column: 1 / -1; text-align: center; color: #94a3b8; padding: 3rem;">
        No <?= htmlspecialchars($selectedStatus) ?> projects found.
      </div>
    <?php else: ?>
      <?php foreach ($filteredProjects as $proj): ?>
        <div class="panel-card" style="display: flex; flex-direction: column; justify-content: space-between; background: #fff; padding: 1.25rem; border-radius: 8px;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span style="font-size: 0.75rem; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569;">
                <?= htmlspecialchars($proj['category_name'] ?? 'General') ?>
              </span>
              <span style="font-size: 0.75rem; font-weight: bold; color: <?= ($proj['status'] ?? '') === 'Completed' ? '#10b981' : '#f59e0b' ?>;">
                <?= htmlspecialchars($proj['status'] ?? 'In Progress') ?>
              </span>
            </div>

            <h4 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">
              <?= htmlspecialchars($proj['title']) ?>
            </h4>
            <p style="font-size: 0.825rem; color: #64748b; margin-bottom: 1rem;">
              <?= htmlspecialchars($proj['description'] ?: 'No description provided.') ?>
            </p>
          </div>

          <div>
            <!-- Corrected Dynamic Progress Bar -->
            <div style="font-size: 0.75rem; color: #475569; margin-bottom: 0.25rem; display: flex; justify-content: space-between;">
              <span>Work Progress</span>
              <strong><?= (int)($proj['progress'] ?? 0) ?>%</strong>
            </div>
            <div style="height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-bottom: 1rem;">
              <div style="width: <?= (int)($proj['progress'] ?? 0) ?>%; height: 100%; background: #2563eb;"></div>
            </div>

            <a href="project_detail.php?id=<?= $proj['id'] ?>" class="btn-blue" style="width: 100%; text-align: center; text-decoration: none; box-sizing: border-box; display: block; padding: 0.5rem 0;">
              View Project Details <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>