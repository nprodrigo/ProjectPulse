<?php
require_once __DIR__ . '/includes/header.php';

$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Handle Daily Log Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_daily_log'])) {
    $projectId = (int)$_POST['project_id'];
    $logText   = trim($_POST['log_text']);
    $isBlocked = isset($_POST['is_blocked']) ? 1 : 0;

    if ($projectId > 0 && !empty($logText)) {
        addDailyLog($projectId, $logText, $isBlocked);
        header("Location: daily_report.php?date=" . urlencode($selectedDate) . "&msg=success");
        exit;
    }
}

// Fetch active projects for the log entry dropdown
$allProjects = getProjects();

// Fetch daily logs for the selected date
$rawDailyLogs = getDailyReportData($selectedDate);

// Group daily logs by Business Unit -> Project
$groupedLogs = [];
foreach ($rawDailyLogs as $log) {
    $buName = $log['bu_name'] ?: 'General / Unassigned BU';
    $projectTitle = $log['project_title'];
    $groupedLogs[$buName][$projectTitle][] = $log;
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

.btn-blue {
  background-color: #2563eb !important;
  color: #ffffff !important;
  border: none !important;
  padding: 0.55rem 1.25rem;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: background 0.2s ease, transform 0.1s ease;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.btn-blue:hover {
  background-color: #1d4ed8 !important;
  transform: translateY(-1px);
}

/* Custom Styled Dark Form Controls */
.form-input-styled, 
.form-select-styled {
  background-color: #1e293b !important;
  color: #f8fafc !important;
  border: 1px solid #334155 !important;
  padding: 0.55rem 0.85rem;
  border-radius: 6px;
  font-size: 0.875rem;
  outline: none;
  width: 100%;
  box-sizing: border-box;
}
.form-input-styled:focus, 
.form-select-styled:focus {
  border-color: #3b82f6 !important;
}

/* Screen Table Styles */
.daily-report-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 0.5rem;
}
.daily-report-table th, 
.daily-report-table td {
  padding: 0.75rem 1rem;
  border: 1px solid var(--border-color, #334155);
  text-align: left;
}
.daily-report-table th {
  background-color: rgba(255, 255, 255, 0.05);
  color: var(--text-muted, #94a3b8);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Print-Friendly Overrides */
@media print {
  .navbar, .footer, .no-print, .log-entry-section, button { 
    display: none !important; 
  }
  body { 
    background: #ffffff !important; 
    color: #000000 !important; 
    font-size: 10pt; 
  }
  .main-container { 
    padding: 0 !important; 
    margin: 0 !important; 
    width: 100% !important; 
  }
  .report-card { 
    border: none !important; 
    box-shadow: none !important; 
    background: #ffffff !important; 
    color: #000000 !important;
    padding: 0 !important;
  }
  .report-card h3, .report-card h4, .report-card h5 { 
    color: #000000 !important; 
  }
  .daily-report-table {
    width: 100% !important;
    border-collapse: collapse !important;
    margin-bottom: 1.5rem !important;
    page-break-inside: avoid;
  }
  .daily-report-table th, 
  .daily-report-table td {
    border: 1px solid #333333 !important;
    padding: 6px 10px !important;
    color: #000000 !important;
  }
  .daily-report-table th {
    background-color: #f2f2f2 !important;
    font-weight: bold;
  }
  .print-header {
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #000000 !important;
    padding-bottom: 0.5rem;
  }
  .blocker-tag {
    color: #dc2626 !important;
    font-weight: bold;
  }
  .normal-tag {
    color: #16a34a !important;
  }
}
</style>

<div class="main-container">

  <!-- Top Action & Filter Bar (Screen Only) -->
  <div class="control-bar no-print" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h2 style="font-size: 1.4rem; font-weight: 700; color: #ffffff;">
        <i class="fa-solid fa-pen-to-square"></i> Daily Status & Report Workspace
      </h2>
      <p style="color: #94a3b8; font-size: 0.85rem;">
        Log updates for today or view daily executive summaries.
      </p>
    </div>

    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
      <!-- Date Filter -->
      <form method="GET" action="daily_report.php" style="display: flex; gap: 0.5rem; align-items: center;">
        <label for="date" style="font-size: 0.85rem; color: #94a3b8;">Date:</label>
        <input type="date" id="date" name="date" class="form-input-styled" style="width: auto;" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
      </form>

      <!-- Styled Theme Print Button -->
      <button onclick="window.print()" class="btn-emerald">
        <i class="fa-solid fa-print"></i> Print / Save PDF
      </button>
    </div>
  </div>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
    <div class="attention-banner no-print" style="background: rgba(16, 185, 129, 0.15); border-left: 4px solid #10b981; color: #6ee7b7; padding: 0.8rem 1rem; border-radius: 4px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
      <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
      <div>Daily update recorded successfully!</div>
    </div>
  <?php endif; ?>

  <!-- Section 1: Quick Log Entry Workspace (Hidden when printing) -->
  <div class="panel-card log-entry-section no-print" style="margin-bottom: 2rem;">
    <div class="panel-title" style="margin-bottom: 1rem;">
      <span style="font-size: 0.95rem; font-weight: 600; color: #f8fafc;">Log Progress Update for Today (<?= date('M d, Y', strtotime($selectedDate)) ?>)</span>
    </div>

    <form method="POST" action="daily_report.php?date=<?= urlencode($selectedDate) ?>" style="display: flex; flex-direction: column; gap: 1rem;">
      <div style="display: grid; grid-template-columns: 1fr 2fr 120px 100px; gap: 1rem; align-items: center;">
        
        <!-- Project Dropdown -->
        <select name="project_id" class="form-select-styled" required>
          <option value="">Select Project...</option>
          <?php foreach ($allProjects as $proj): ?>
            <option value="<?= $proj['id'] ?>"><?= htmlspecialchars($proj['title']) ?></option>
          <?php endforeach; ?>
        </select>

        <!-- Progress Text Input -->
        <input type="text" name="log_text" class="form-input-styled" placeholder="Type today's progress, milestone update, or status..." required>

        <!-- Blocker Toggle -->
        <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: #f87171; cursor: pointer; white-space: nowrap;">
          <input type="checkbox" name="is_blocked" value="1"> Blocker
        </label>

        <!-- Styled Theme Save Button -->
        <button type="submit" name="submit_daily_log" class="btn-blue">
          Save
        </button>
      </div>
    </form>
  </div>

  <!-- Section 2: Reader & Print-Friendly Executive Daily Report -->
  <div class="panel-card report-card">
    <div class="print-header" style="display: flex; justify-content: space-between; border-bottom: 1px solid #334155; padding-bottom: 1rem; margin-bottom: 1.5rem;">
      <div>
        <h3 style="font-size: 1.25rem; font-weight: 700; color: #ffffff;">Daily Executive Progress Summary</h3>
        <p style="font-size: 0.85rem; color: #94a3b8; margin-top: 0.2rem;">
          Report Date: <strong><?= date('F d, Y', strtotime($selectedDate)) ?></strong>
        </p>
      </div>
      <div style="text-align: right; font-size: 0.8rem; color: #64748b;">
        Report Generated: <?= date('H:i T') ?>
      </div>
    </div>

    <?php if (empty($groupedLogs)): ?>
      <div style="text-align: center; padding: 3rem; color: #94a3b8;">
        <i class="fa-solid fa-file-circle-exclamation" style="font-size: 2.5rem; margin-bottom: 1rem; color: #64748b;"></i>
        <p>No daily progress updates logged for <?= date('M d, Y', strtotime($selectedDate)) ?>.</p>
        <p class="no-print" style="font-size: 0.82rem; margin-top: 0.4rem;">Use the input form above to submit today's updates.</p>
      </div>
    <?php else: ?>
      <?php foreach ($groupedLogs as $buName => $projects): ?>
        <div style="margin-bottom: 2rem; page-break-inside: avoid;">
          <h4 style="font-size: 1.05rem; font-weight: 700; color: #38bdf8; border-bottom: 2px solid #334155; padding-bottom: 0.4rem; margin-bottom: 0.75rem;">
            Business Unit: <?= htmlspecialchars($buName) ?>
          </h4>

          <table class="daily-report-table">
            <thead>
              <tr>
                <th style="width: 25%;">Project</th>
                <th style="width: 55%;">Daily Progress / Log</th>
                <th style="width: 20%;">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($projects as $projectTitle => $logs): ?>
                <?php foreach ($logs as $index => $item): ?>
                  <tr>
                    <?php if ($index === 0): ?>
                      <td rowspan="<?= count($logs) ?>" style="vertical-align: top;">
                        <strong style="color: #f8fafc; font-size: 0.95rem;">
                          <?= htmlspecialchars($projectTitle) ?>
                        </strong>
                      </td>
                    <?php endif; ?>
                    
                    <td>
                      <div style="font-size: 0.9rem; color: #f8fafc;">
                        <?= htmlspecialchars($item['log_text']) ?>
                      </div>
                      <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;">
                        Logged at <?= date('H:i', strtotime($item['created_at'])) ?> by <strong><?= htmlspecialchars($item['owner_name']) ?></strong>
                      </div>
                    </td>
                    
                    <td style="vertical-align: top;">
                      <?php if ($item['is_blocked']): ?>
                        <span class="blocker-tag" style="color: #f87171; font-weight: 600; font-size: 0.85rem;">
                          <i class="fa-solid fa-triangle-exclamation"></i> BLOCKED
                        </span>
                      <?php else: ?>
                        <span class="normal-tag" style="color: #34d399; font-weight: 600; font-size: 0.85rem;">
                          <i class="fa-solid fa-circle-check"></i> Complete
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>