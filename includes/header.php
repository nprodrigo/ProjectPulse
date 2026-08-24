<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/functions.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$metrics     = getDashboardMetrics();
$categories  = getCategories();
$dbConnected = (getDBConnection() !== null);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>ProjectPulse Tracker - Executive Dashboard</title>
  
  <!-- Tabler Core CSS & Webfont Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

  <style>
    /* 2-Tier Header Styling */
    .header-tier-top {
      background-color: #0b1329 !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding-top: 0.65rem;
      padding-bottom: 0.65rem;
    }

    .header-tier-nav {
      background-color: #0f172a !important;
      border-bottom: 2px solid #334155 !important;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .navbar-brand span {
      color: #ffffff !important;
      font-weight: 700;
      letter-spacing: -0.3px;
    }

    /* Single-line Nav Tabs */
    .nav-link-custom {
      color: #94a3b8 !important;
      font-weight: 500;
      white-space: nowrap !important;
      padding: 0.6rem 1rem !important;
      border-radius: 6px;
      transition: all 0.15s ease-in-out;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
    }

    .nav-link-custom:hover {
      color: #ffffff !important;
      background: rgba(255, 255, 255, 0.06) !important;
    }

    .nav-item.active .nav-link-custom {
      color: #ffffff !important;
      background-color: #6366f1 !important;
      box-shadow: 0 2px 6px rgba(99, 102, 241, 0.35);
      font-weight: 600;
    }

    .user-pill {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      padding: 0.3rem 0.65rem;
      border-radius: 8px;
    }

    .user-pill:hover {
      background: rgba(255, 255, 255, 0.1);
    }
  </style>
</head>
<body class="theme-dark">
  <div class="page">
    
    <!-- Top Tier: Brand, Global Action, User Profile -->
    <div class="header-tier-top">
      <div class="container-xl d-flex align-items-center justify-content-between">
        
        <!-- Brand Logo -->
        <a href="index.php" class="navbar-brand text-decoration-none d-flex align-items-center gap-2">
          <span class="avatar bg-primary text-white rounded shadow-sm">
            <i class="ti ti-activity fs-2"></i>
          </span>
          <span class="fs-2">ProjectPulse <small class="fs-5 text-indigo-lt fw-normal ms-1">Tracker</small></span>
        </a>

        <!-- Right Side: Action Button + User Profile -->
        <div class="d-flex align-items-center gap-3">
          <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
            <i class="ti ti-plus me-1"></i> New Project
          </button>

          <div class="dropdown">
            <a href="#" class="user-pill d-flex align-items-center lh-1 text-reset text-decoration-none" data-bs-toggle="dropdown">
              <span class="avatar avatar-sm bg-indigo-lt text-indigo fw-bold rounded">
                <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
              </span>
              <div class="d-none d-md-block ps-2 text-start">
                <div class="fw-bold text-white fs-4"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="small text-muted" style="font-size: 0.72rem;">Authorized User</div>
              </div>
            </a>
            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
              <a href="logout.php" class="dropdown-item text-danger"><i class="ti ti-logout me-2"></i> Logout</a>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Bottom Tier: Navigation Bar -->
    <header class="navbar navbar-expand-md navbar-dark header-tier-nav d-print-none sticky-top">
      <div class="container-xl">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbar-menu">
          <ul class="navbar-nav">
            <li class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
              <a class="nav-link nav-link-custom" href="index.php">
                <i class="ti ti-layout-dashboard"></i> Overview
              </a>
            </li>
            <li class="nav-item <?= $currentPage === 'projects.php' ? 'active' : '' ?>">
              <a class="nav-link nav-link-custom" href="projects.php">
                <i class="ti ti-folders"></i> All Projects
              </a>
            </li>
            <li class="nav-item <?= $currentPage === 'timeline.php' ? 'active' : '' ?>">
              <a class="nav-link nav-link-custom" href="timeline.php">
                <i class="ti ti-calendar-event"></i> Timeline
              </a>
            </li>
            <li class="nav-item <?= $currentPage === 'attention.php' ? 'active' : '' ?>">
              <a class="nav-link nav-link-custom" href="attention.php">
                <i class="ti ti-alert-triangle"></i> Needs Attention
                <?php if ($metrics['attention_needed'] > 0): ?>
                  <span class="badge bg-danger ms-1"><?= $metrics['attention_needed'] ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li class="nav-item <?= $currentPage === 'weekly_report.php' ? 'active' : '' ?>">
              <a class="nav-link nav-link-custom" href="weekly_report.php">
                <i class="ti ti-file-analytics"></i> Weekly Report
              </a>
            </li>
            <li class="nav-item <?= $currentPage === 'daily_report.php' ? 'active' : '' ?>">
              <a class="nav-link nav-link-custom" href="daily_report.php">
                <i class="ti ti-edit-circle"></i> Daily Report
              </a>
            </li>
            <li class="nav-item <?= $currentPage === 'team.php' ? 'active' : '' ?>">
              <a class="nav-link nav-link-custom" href="team.php">
                <i class="ti ti-users"></i> Team
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <!-- Main Container -->
    <div class="page-wrapper">
      <div class="page-body">
        <div class="container-xl">

          <!-- Executive Metrics Grid -->
          <div class="row row-deck row-cards mb-4">
            <div class="col-6 col-sm-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader text-muted">Total Projects</div>
                  <div class="h1 mb-0 mt-2"><?= $metrics['total_projects'] ?></div>
                </div>
              </div>
            </div>
            <div class="col-6 col-sm-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader text-muted">Avg Completion</div>
                  <div class="h1 mb-0 mt-2"><?= $metrics['avg_progress'] ?>%</div>
                </div>
              </div>
            </div>
            <div class="col-6 col-sm-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader text-muted">Blocked / Attention</div>
                  <div class="h1 mb-0 mt-2 text-danger"><?= $metrics['attention_needed'] ?></div>
                </div>
              </div>
            </div>
            <div class="col-6 col-sm-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader text-muted">Finishing Soon</div>
                  <div class="h1 mb-0 mt-2 text-warning"><?= $metrics['finishing_soon'] ?></div>
                </div>
              </div>
            </div>
          </div>