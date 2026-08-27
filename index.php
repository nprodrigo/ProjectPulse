<?php
require_once __DIR__ . '/includes/header.php';

$allProjects = getProjects();

$totalProjects = count($allProjects);
$blockedCount = 0;
$completedCount = 0;
$activeProjects = [];
$totalProgressSum = 0;

foreach ($allProjects as $proj) {
    $progVal = (int)($proj['progress'] ?? 0);
    $totalProgressSum += $progVal;

    if (($proj['status'] ?? '') === 'Completed') {
        $completedCount++;
    } else {
        $activeProjects[] = $proj;
        if (!empty($proj['needs_attention']) || ($proj['status'] ?? '') === 'Needs Attention') {
            $blockedCount++;
        }
    }
}

$activeTotal = count($activeProjects);
$avgCompletion = $totalProjects > 0 ? round($totalProgressSum / $totalProjects) : 0;
$healthRate = $activeTotal > 0 ? round((($activeTotal - $blockedCount) / $activeTotal) * 100) : 100;
?>

<style>
.dashboard-container { padding: 1.5rem 0; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; }
.kpi-title { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
.kpi-value { font-size: 1.85rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem; }

.exec-grid { display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; }
@media (max-width: 1024px) { .exec-grid { grid-template-columns: 1fr; } }

.exec-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; }
.exec-table th, .exec-table td { padding: 0.85rem 1rem; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
.exec-table th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; }
</style>

<div class="dashboard-container">

  <!-- Executive KPIs -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-title">Portfolio Health</div>
      <div class="kpi-value" style="color: <?= $healthRate >= 80 ? '#10b981' : '#ef4444' ?>;"><?= $healthRate ?>%</div>
      <small style="color: #64748b;"><?= $activeTotal - $blockedCount ?> of <?= $activeTotal ?> active projects on track</small>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">Average Progress</div>
      <div class="kpi-value" style="color: #2563eb;"><?= $avgCompletion ?>%</div>
      <small style="color: #64748b;">Across all accessible projects</small>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">Critical Blockers</div>
      <div class="kpi-value" style="color: <?= $blockedCount > 0 ? '#ef4444' : '#10b981' ?>;"><?= $blockedCount ?></div>
      <small style="color: #64748b;">Needs management attention</small>
    </div>

    <div class="kpi-card">
      <div class="kpi-title">Completed Projects</div>
      <div class="kpi-value" style="color: #10b981;"><?= $completedCount ?></div>
      <small style="color: #64748b;">Archived from active view</small>
    </div>
  </div>

  <!-- Executive Hub Split View -->
  <div class="exec-grid">
    
    <!-- Action Required Box -->
    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; height: fit-content;">
      <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem;">
        <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> Decision Needed
      </h3>

      <?php 
      $hasBlockers = false;
      foreach ($activeProjects as $proj):
        if (!empty($proj['needs_attention']) || ($proj['status'] ?? '') === 'Needs Attention'):
          $hasBlockers = true;
      ?>
        <div style="border-left: 3px solid #ef4444; background: #fef2f2; padding: 0.75rem; border-radius: 4px; margin-bottom: 0.75rem;">
          <strong style="color: #991b1b; font-size: 0.85rem;"><?= htmlspecialchars($proj['title']) ?></strong>
          <p style="font-size: 0.775rem; color: #7f1d1d; margin-top: 0.2rem;">
            <?= htmlspecialchars($proj['blocker_reason'] ?? 'Requires administrative sign-off.') ?>
          </p>
        </div>
      <?php 
        endif;
      endforeach;

      if (!$hasBlockers): ?>
        <p style="color: #94a3b8; font-size: 0.85rem; text-align: center; padding: 1rem 0;">No active operational bottlenecks.</p>
      <?php endif; ?>
    </div>

    <!-- Active Projects Executive Summary Table -->
    <div>
      <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem;">Active Project Executive Summary</h3>
      <table class="exec-table">
        <thead>
          <tr>
            <th>Project Title</th>
            <th>Manager</th>
            <th>Category</th>
            <th>Progress</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activeProjects as $proj): ?>
            <tr>
              <td><strong><a href="project_detail.php?id=<?= $proj['id'] ?>" style="color: #0f172a; text-decoration: none;"><?= htmlspecialchars($proj['title']) ?></a></strong></td>
              <td><?= htmlspecialchars($proj['owner_name'] ?? 'Unassigned') ?></td>
              <td><?= htmlspecialchars($proj['category_name'] ?? 'General') ?></td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <span style="font-weight: 700; min-width: 35px;"><?= (int)($proj['progress'] ?? 0) ?>%</span>
                  <div style="flex-grow: 1; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                    <div style="width: <?= (int)($proj['progress'] ?? 0) ?>%; height: 100%; background: #2563eb;"></div>
                  </div>
                </div>
              </td>
              <td>
                <?php if (!empty($proj['needs_attention']) || ($proj['status'] ?? '') === 'Needs Attention'): ?>
                  <span style="color: #ef4444; font-weight: 600;">Needs Attention</span>
                <?php else: ?>
                  <span style="color: #10b981; font-weight: 600;"><?= htmlspecialchars($proj['status'] ?? 'On Track') ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>