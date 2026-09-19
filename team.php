<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

// Handle Team Member Creation or Editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = getDBConnection();
    
    if ($_POST['action'] === 'create_team_member') {
        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $roleTitle = trim($_POST['role_title'] ?? 'Team Member');

        if ($fullName && $email) {
            $stmt = $db->prepare("INSERT INTO team_members (full_name, email, role_title) VALUES (:name, :email, :role)");
            $stmt->execute(['name' => $fullName, 'email' => $email, 'role' => $roleTitle]);
            header("Location: team.php?msg=added");
            exit;
        }
    } elseif ($_POST['action'] === 'update_team_member') {
        $memberId  = (int)($_POST['member_id'] ?? 0);
        $fullName  = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $roleTitle = trim($_POST['role_title'] ?? '');
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($memberId > 0 && $fullName && $email) {
            $stmt = $db->prepare("UPDATE team_members SET full_name = :name, email = :email, role_title = :role, is_active = :active WHERE id = :id");
            $stmt->execute(['name' => $fullName, 'email' => $email, 'role' => $roleTitle, 'active' => $isActive, 'id' => $memberId]);
            header("Location: team.php?msg=updated");
            exit;
        }
    }
}

$teamMembers = getTeamMembers();
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Title & Header Bar -->
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h2 class="card-title h1 mb-1"><i class="ti ti-users me-2 text-primary"></i>Team Directory & Workload Center</h2>
        <div class="text-secondary small">Manage global team members, role titles, and contact details.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeamMemberModal">
        <i class="ti ti-user-plus me-1"></i> Add Team Member
      </button>
    </div>
  </div>
</div>

<!-- Success Alert Banner -->
<?php if (isset($_GET['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <div class="d-flex align-items-center">
      <i class="ti ti-circle-check fs-2 me-2"></i>
      <div>
        <?= $_GET['msg'] === 'updated' ? 'Team member details updated successfully!' : 'New team member added successfully!' ?>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<!-- Team Directory Grid -->
<div class="row row-cards">
  <?php if (empty($teamMembers)): ?>
    <div class="col-12">
      <div class="card card-body text-center py-5">
        <p class="text-secondary mb-0">No team members registered yet.</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($teamMembers as $member): ?>
      <?php $initial = strtoupper(substr($member['full_name'], 0, 1)); ?>
      <div class="col-md-6 col-lg-4">
        <div class="card card-sm">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <div class="d-flex align-items-center gap-3">
                <span class="avatar avatar-md bg-blue-lt rounded-circle fw-bold fs-3">
                  <?= $initial ?>
                </span>
                <div>
                  <h3 class="card-title mb-0"><?= htmlspecialchars($member['full_name']) ?></h3>
                  <div class="text-secondary small"><?= htmlspecialchars($member['role_title'] ?: 'Team Member') ?></div>
                </div>
              </div>
              <span class="badge <?= !empty($member['is_active']) ? 'bg-success-lt text-success' : 'bg-secondary-lt text-secondary' ?>">
                <?= !empty($member['is_active']) ? 'Active' : 'Inactive' ?>
              </span>
              <button class="btn btn-icon btn-ghost-secondary btn-sm" 
                      title="Edit Member"
                      onclick="editMember(<?= $member['id'] ?>, '<?= htmlspecialchars(addslashes($member['full_name'])) ?>', '<?= htmlspecialchars(addslashes($member['email'])) ?>', '<?= htmlspecialchars(addslashes($member['role_title'])) ?>', <?= !empty($member['is_active']) ? 'true' : 'false' ?>)">
                <i class="ti ti-edit fs-3"></i>
              </button>
            </div>
            
            <div class="d-flex align-items-center text-secondary small pt-2 border-top">
              <i class="ti ti-mail me-2"></i>
              <a href="mailto:<?= htmlspecialchars($member['email']) ?>" class="text-reset text-truncate">
                <?= htmlspecialchars($member['email']) ?>
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Modal: Add Team Member -->
<div class="modal modal-blur fade" id="addTeamMemberModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form action="team.php" method="POST">
        <input type="hidden" name="action" value="create_team_member">

        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-user-plus me-2"></i>Add New Team Member</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label required">Full Name</label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
          </div>

          <div class="mb-3">
            <label class="form-label required">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="john.d@company.com" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Role Title</label>
            <input type="text" name="role_title" class="form-control" placeholder="e.g. Senior Solution Architect">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i>Save Member</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Edit Team Member -->
<div class="modal modal-blur fade" id="editTeamMemberModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form action="team.php" method="POST">
        <input type="hidden" name="action" value="update_team_member">
        <input type="hidden" name="member_id" id="edit_member_id">

        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-edit me-2"></i>Edit Team Member</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label required">Full Name</label>
            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label required">Email Address</label>
            <input type="email" name="email" id="edit_email" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Role Title</label>
            <input type="text" name="role_title" id="edit_role_title" class="form-control">
          </div>
          <label class="form-check">
            <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input">
            <span class="form-check-label">Active member</span>
          </label>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i>Update Details</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editMember(id, name, email, role, isActive) {
    document.getElementById('edit_member_id').value = id;
    document.getElementById('edit_full_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role_title').value = role;
    document.getElementById('edit_is_active').checked = isActive;

    var editModal = new bootstrap.Modal(document.getElementById('editTeamMemberModal'));
    editModal.show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>