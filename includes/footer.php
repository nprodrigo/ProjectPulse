</div> <!-- container-xl -->
      </div> <!-- page-body -->

      <footer class="footer footer-transparent d-print-none border-top mt-auto py-3">
        <div class="container-xl text-center text-secondary small">
          ProjectPulse Executive Tracker &copy; <?= date('Y') ?>
        </div>
      </footer>
    </div> <!-- page-wrapper -->
  </div> <!-- page -->

  <!-- Modal: Create New Project -->
  <div class="modal modal-blur fade" id="addProjectModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <form action="api.php" method="POST">
          <input type="hidden" name="action" value="create_project">
          
          <div class="modal-header">
            <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Create New Project</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Project Title</label>
              <input type="text" name="title" class="form-control" placeholder="e.g. ERP Cloud System Migration" required>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">Category</label>
                <select name="category_id" class="form-select" required>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Business Unit (BU)</label>
                <select name="bu_id" class="form-select">
                  <option value="">Select BU...</option>
                  <?php foreach (getBusinessUnits() as $bu): ?>
                    <option value="<?= $bu['id'] ?>"><?= htmlspecialchars($bu['name']) ?> (<?= $bu['code'] ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">Project Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Expected Target Completion Date</label>
                <input type="date" name="target_completion_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                <span class="form-hint">Committed baseline finish date.</span>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Project Manager</label>
                <select name="manager_id" class="form-select">
                  <option value="">Select Manager...</option>
                  <?php foreach (getTeamMembers(true) as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                  <?php endforeach; ?>
                </select>
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
              <label class="form-label">Description / Scope Overview</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Provide project summary..."></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i>Create Project</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Tabler JS Core -->
  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>
</body>
</html>