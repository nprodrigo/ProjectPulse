<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/functions.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$metrics = getDashboardMetrics();
$categories = getCategories();
$dbConnected = (getDBConnection() !== null);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>ProjectPulse Tracker - Executive Dashboard</title>
  
  <!-- Tabler Core CSS & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body class="theme-dark">
  <div class="page">
    
    <!-- Navbar Header -->
    <header class="navbar navbar-expand-md navbar-dark d-print-none">
      <div class="container-xl">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
          <span class="navbar-toggler-icon"></span>
        </button>
        
        <h1 class="navbar-brand navbar-brand-autodark me-md-3">
          <a href="index.php" class="d-flex align-items-center gap-2 text-decoration-none text-white">
            <span class="avatar bg-primary text-white rounded"><i class="ti ti-activity icon"></i></span>
            <span>ProjectPulse</span>
          </a>
        </h1>

        <div class="navbar-nav flex-row order-md-last ms-auto">
          <div class="nav-item dropdown">
            <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown">
              <span class="avatar avatar-sm bg-blue-lt"><i class="ti ti-user"></i></span>
              <div class="d-none d-xl-block ps-2">
                <div><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="mt-1 small text-secondary">Authorized User</div>
              </div>
            </a>
            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
              <a href="logout.php" class="dropdown-item text-danger"><i class="ti ti-logout me-2"></i> Logout</a>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Navigation Bar -->
    <header class="navbar-expand-md">
      <div class="collapse navbar-collapse" id="navbar-menu">
        <div class="navbar">
          <div class="container-xl">
            <ul class="navbar-nav">
              <li class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                <a class="nav-link" href="index.php">
                  <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-layout-dashboard"></i></span>
                  <span class="nav-link-title">Overview</span>
                </a>
              </li>
              <li class="nav-item <?= $currentPage === 'projects.php' ? 'active' : '' ?>">
                <a class="nav-link" href="projects.php">
                  <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-folders"></i></span>
                  <span class="nav-link-title">All Projects</span>
                </a>
              </li>
              <li class="nav-item <?= $currentPage === 'timeline.php' ? 'active' : '' ?>">
                <a class="nav-link" href="timeline.php">
                  <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-calendar-event"></i></span>
                  <span class="nav-link-title">Timeline</span>
                </a>
              </li>
              <li class="nav-item <?= $currentPage === 'attention.php' ? 'active' : '' ?>">
                <a class="nav-link" href="attention.php">
                  <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-alert-triangle"></i></span>
                  <span class="nav-link-title">Needs Attention</span>
                  <?php if ($metrics['attention_needed'] > 0): ?>
                    <span class="badge bg-danger ms-2"><?= $metrics['attention_needed'] ?></span>
                  <?php endif; ?>
                </a>
              </li>
              <li class="nav-item <?= $currentPage === 'weekly_report.php' ? 'active' : '' ?>">
                <a class="nav-link" href="weekly_report.php">
                  <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-file-analytics"></i></span>
                  <span class="nav-link-title">Weekly Report</span>
                </a>
              </li>
              <li class="nav-item <?= $currentPage === 'daily_report.php' ? 'active' : '' ?>">
                <a class="nav-link" href="daily_report.php">
                  <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-edit-circle"></i></span>
                  <span class="nav-link-title">Daily Report</span>
                </a>
              </li>
              <li class="nav-item <?= $currentPage === 'team.php' ? 'active' : '' ?>">
                <a class="nav-link" href="team.php">
                  <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users"></i></span>
                  <span class="nav-link-title">Team</span>
                </a>
              </li>
            </ul>

            <div class="my-2 my-md-0 flex-grow-1 flex-md-grow-0 order-first order-md-last">
              <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                <i class="ti ti-plus me-1"></i> New Project
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Container -->
    <div class="page-wrapper">
      <div class="page-body">
        <div class="container-xl">

          <!-- Executive Summary Metrics Cards -->
          <div class="row row-deck row-cards mb-4">
            <div class="col-6 col-sm-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Total Projects</div>
                  </div>
                  <div class="h1 mb-3 me-2"><?= $metrics['total_projects'] ?></div>
                </div>
              </div>
            </div>
            <div class="col-6 col-sm-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Avg Completion</div>
                  </div>
                  <div class="h1 mb-3 me-2"><?= $metrics['avg_progress'] ?>%</div>
                </div>
              </div>
            </div>
            <div class="col-6 col-sm-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Blocked / Attention</div>
                  </div>
                  <div class="h1 mb-3 me-2 text-danger"><?= $metrics['attention_needed'] ?></div>
                </div>
              </div>
            </div>
            <div class="col-6 col-sm-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Finishing Soon</div>
                  </div>
                  <div class="h1 mb-3 me-2 text-warning"><?= $metrics['finishing_soon'] ?></div>
                </div>
              </div>
            </div>
          </div>