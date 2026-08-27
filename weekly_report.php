<?php
require_once __DIR__ . '/includes/header.php';

// Fetch weekly data grouped by project and task status
$rawLogs = getWeeklyReportData(); 

$report = [];
foreach ($rawLogs as $log) {
    $buName = $log['bu_name'] ?: 'General / Unassigned BU';
    $projectTitle = $log['project_title'];
    $report[$buName][$projectTitle][] = $log;
}
?>

<style>
/* ProjectPulse Custom UI Theme Buttons */
.btn-emerald {
  background-color: #10b981 !important;
  color: #ffffff !important;
  border: none !important;
  padding: 0.55rem 1.25rem;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: background 0.2s ease, transform 0.1s ease;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.btn-emerald:hover {
  background-color: #059669 !important;
  transform: translateY(-1px);
}

/* Print-Friendly Overrides */
@media print {
  .navbar, .footer, .no-print, button { 
    display: none !important; 
  }
  body { 
    background: #ffffff !important; 
    color: #000000 !important; 
    font-size: 10pt; 
  }
  .container { 
    padding: 0 !important; 
    margin: 0 !important; 
    width: 100% !important; 
  }
  .report-card { 
    border: 1px solid #dddddd !important; 
    box-shadow: none !important; 
    background: #ffffff !important; 
    color: #000000 !important;
    padding: 1rem !important;
    margin-bottom: 1.5rem !important;
    page-break-inside: avoid;
  }
  .report-card h3, .report-card h4, .report-card h5 { 
    color: #000000 !important; 
  }
  .status-section {
    page-break-inside: avoid;
  }
  .badge-print {
    border: 1px solid #dc2626 !important;
    color: #dc2626 !important;
    font-weight: bold;
  }
}
</style>

<div class="container" style="padding: 1.5rem 0;">
  
  <!-- Header & Print Action (Screen Only) -->
  <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
      <h2 style="font-size: 1.4rem; font-weight: 700; color: #ffffff;">
        <i class="fa-solid fa-file-invoice"></i> Executive Weekly Status Report
      </h2>
      <p style="color: #94a3b8; font-size: 0.85rem;">
        Planned vs. Completed Work summary for the week ending <?= date('M d, Y') ?>
      </p>
    </div>
    
    <!-- Styled Theme Print Button -->
    <button onclick="window.print()" class="btn-emerald">
      <i class="fa-solid fa-print"></i> Print / Save PDF
    </button>
  </div>

  <?php if (empty($report)): ?>
    <div class="panel-card" style="padding: 3rem; text-align: center; color: #94a3b8;">
      <i class="fa-solid fa-folder-open" style="font-size: 2.5rem; margin-bottom: 1rem; color: #64748b;"></i>
      <p>No weekly status logs recorded for this period.</p>
    </div>
  <?php else: ?>
    <?php foreach ($report as $buName => $projects): ?>
      <div class="panel-card report-card" style="display: block; margin-bottom: 1.5rem; padding: 1.5rem;">
        <h3 style="border-bottom: 2px solid #334155; padding-bottom: 0.5rem; margin-bottom: 1.25rem; color: #38bdf8; font-size: 1.1rem; font-weight: 700;">
          Business Unit: <?= htmlspecialchars($buName) ?>
        </h3>

        <?php foreach ($projects as $projectTitle => $logs): ?>
          <div style="margin-bottom: 1.5rem; margin-left: 0.5rem;">
            <h4 style="font-size: 1.05rem; margin-bottom: 0.75rem; color: #f8fafc; font-weight: 600;">
              Project: <?= htmlspecialchars($projectTitle) ?>
            </h4>

            <!-- 1. Completed Work Section -->
            <div class="status-section" style="margin-bottom: 1rem;">
              <strong style="color: #34d399; font-size: 0.9rem;">
                <i class="fa-solid fa-circle-check"></i> Completed Work (Against Plan):
              </strong>
              <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.35rem; color: #f8fafc; font-size: 0.9rem;">
                <?php 
                $completedFound = false;
                foreach ($logs as $item): 
                    if (($item['status'] ?? '') === 'completed' || strpos(strtolower($item['log_text']), '[done]') !== false):
                        $completedFound = true;
                ?>
                    <li style="margin-bottom: 0.3rem;">
                      <?= htmlspecialchars($item['log_text']) ?>
                      <small style="color: #94a3b8;">(Owner: <?= htmlspecialchars($item['owner_name']) ?>)</small>
                    </li>
                <?php 
                    endif;
                endforeach; 
                if (!$completedFound): ?>
                    <li style="color: #64748b; list-style: none; font-style: italic;">No completed tasks recorded for this project.</li>
                <?php endif; ?>
              </ul>
            </div>

            <!-- 2. Planned / Ongoing Work Section -->
            <div class="status-section" style="margin-bottom: 1rem;">
              <strong style="color: #60a5fa; font-size: 0.9rem;">
                <i class="fa-solid fa-calendar-days"></i> Planned / In-Progress Work:
              </strong>
              <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.35rem; color: #f8fafc; font-size: 0.9rem;">
                <?php 
                $plannedFound = false;
                foreach ($logs as $item): 
                    if (!($item['is_blocked'] ?? 0) && ($item['status'] ?? 'planned') === 'planned' && strpos(strtolower($item['log_text']), '[done]') === false):
                        $plannedFound = true;
                ?>
                    <li style="margin-bottom: 0.3rem;">
                      <?= htmlspecialchars($item['log_text']) ?>
                      <small style="color: #94a3b8;">(<?= date('M d', strtotime($item['created_at'])) ?>)</small>
                    </li>
                <?php 
                    endif;
                endforeach; 
                if (!$plannedFound): ?>
                    <li style="color: #64748b; list-style: none; font-style: italic;">No active planned items logged.</li>
                <?php endif; ?>
              </ul>
            </div>

            <!-- 3. Planned But Not Completed / Blocked Section -->
            <div class="status-section">
              <strong style="color: #f87171; font-size: 0.9rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> Planned But Not Completed (Carried Over / Blocked):
              </strong>
              <ul style="list-style: disc; padding-left: 1.5rem; margin-top: 0.35rem; color: #f8fafc; font-size: 0.9rem;">
                <?php 
                $uncompletedFound = false;
                foreach ($logs as $item): 
                    if (!empty($item['is_blocked']) || ($item['status'] ?? '') === 'incomplete'):
                        $uncompletedFound = true;
                ?>
                    <li style="margin-bottom: 0.3rem; color: #f87171;">
                      <?= htmlspecialchars($item['log_text']) ?>
                      <?php if ($item['is_blocked']): ?>
                        <span class="badge-print" style="background: rgba(248, 113, 113, 0.2); color: #f87171; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; margin-left: 0.4rem;">BLOCKER</span>
                      <?php endif; ?>
                    </li>
                <?php 
                    endif;
                endforeach; 
                if (!$uncompletedFound): ?>
                    <li style="color: #64748b; list-style: none; font-style: italic;">None reported.</li>
                <?php endif; ?>
              </ul>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>