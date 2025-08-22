<?php
require_once '../dbPC.php';
if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Deletion
if (isset($_GET['delete'])) {
    $deleteId = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM medicine_log WHERE id = ? AND user_id = ?");
    $stmt->execute([$deleteId, $user_id]);
    $_SESSION['success'] = "Medicine schedule deleted successfully!";
    header("Location: ?page=medicinelog");
    exit();
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $editId = $_POST['edit_id'];
    $med_name = trim($_POST['med_name']);
    $dosage = trim($_POST['dosage']);
    $time = $_POST['time'];
    $schedule_date = $_POST['schedule_date'];

    $stmt = $conn->prepare("UPDATE medicine_log 
                           SET med_name = ?, dosage = ?, time = ?, schedule_date = ? 
                           WHERE id = ? AND user_id = ?");
    $stmt->execute([$med_name, $dosage, $time, $schedule_date, $editId, $user_id]);
    
    if ($stmt->rowCount()) {
        $_SESSION['success'] = "Medicine schedule updated successfully!";
    } else {
        $_SESSION['error'] = "No changes made or record not found";
    }
    
    header("Location: ?page=medicinelog");
    exit();
}

// Search filters
$searchMed = $_GET['medicine'] ?? '';
$searchDate = $_GET['date'] ?? '';
$searchStatus = $_GET['status'] ?? '';

$query = "SELECT * FROM medicine_log WHERE user_id = ?";
$params = [$user_id];

if ($searchMed) {
    $query .= " AND med_name LIKE ?";
    $params[] = "%$searchMed%";
}
if ($searchDate) {
    $query .= " AND schedule_date = ?";
    $params[] = $searchDate;
}
if ($searchStatus && in_array($searchStatus, ['scheduled', 'done', 'missed'])) {
    $query .= " AND status = ?";
    $params[] = $searchStatus;
}

// Order by date and time
$query .= " ORDER BY schedule_date DESC, time DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Medicine Log | CanCare</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
        --primary: #4361ee;
        --secondary: #3f37c9;
        --success: #28a745;
        --danger: #dc3545;
        --warning: #ffc107;
        --light: #f8f9fa;
        --dark: #212529;
        --gray: #6c757d;
    }
    
    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        background: #f0f8ff; 
        padding: 20px;
    }
    
    .med-header {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        padding: 1.5rem;
        border-radius: 15px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .card {
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        border: none;
        margin-bottom: 25px;
    }
    
    .card-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 15px 20px;
        border-radius: 15px 15px 0 0 !important;
        font-weight: 600;
    }
    
    .action-btn {
        padding: 6px 12px;
        border: none;
        border-radius: 8px;
        color: white;
        cursor: pointer;
        font-size: 14px;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        transition: all 0.2s;
    }
    
    .btn-update { 
        background-color: var(--success);
    }
    
    .btn-delete { 
        background-color: var(--danger); 
        text-decoration: none;
    }
    
    .btn-update:hover, .btn-delete:hover { 
        opacity: 0.9; 
        transform: translateY(-2px);
    }
    
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        display: inline-block;
    }
    
    .badge-scheduled {
        background-color: var(--warning);
        color: var(--dark);
    }
    
    .badge-done {
        background-color: var(--success);
        color: white;
    }
    
    .badge-missed {
        background-color: var(--danger);
        color: white;
    }
    
    .form-control, .form-select {
        border-radius: 10px;
        padding: 10px 15px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
    }
    
    .btn-primary {
        background: var(--primary);
        border: none;
        border-radius: 10px;
        padding: 10px 20px;
        transition: all 0.2s;
    }
    
    .btn-primary:hover {
        background: var(--secondary);
        transform: translateY(-2px);
    }
    
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    th {
        background-color: var(--primary);
        color: white;
        padding: 15px;
        text-align: left;
    }
    
    td {
        padding: 12px 15px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    tr:last-child td {
        border-bottom: none;
    }
    
    tr:hover {
        background-color: rgba(67, 97, 238, 0.05);
    }
    
    .no-records {
        text-align: center;
        padding: 30px;
        color: var(--gray);
    }
    
    .no-records i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #e2e8f0;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="med-header">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h1><i class="fas fa-pills me-2"></i>Medicine Schedule</h1>
          <p class="mb-0">Manage your medication schedule</p>
        </div>
        <div>
          <a href="?page=addentry" class="btn btn-light">
            <i class="fas fa-plus me-1"></i> Add New
          </a>
        </div>
      </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $_SESSION['success'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $_SESSION['error'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <i class="fas fa-filter me-2"></i>Filter Medicine Schedule
      </div>
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Medicine Name</label>
            <input type="text" class="form-control" name="medicine" 
                   value="<?= htmlspecialchars($searchMed) ?>" placeholder="Search medicine...">
          </div>
          <div class="col-md-3">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="date" 
                   value="<?= htmlspecialchars($searchDate) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="">All Status</option>
              <option value="scheduled" <?= $searchStatus === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
              <option value="done" <?= $searchStatus === 'done' ? 'selected' : '' ?>>Taken</option>
              <option value="missed" <?= $searchStatus === 'missed' ? 'selected' : '' ?>>Missed</option>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
              <i class="fas fa-search me-1"></i> Apply
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <i class="fas fa-history me-2"></i>Your Medication Schedule
        </div>
        <div class="badge bg-primary">
          <?= count($logs) ?> entries
        </div>
      </div>
      
      <div class="card-body p-0">
        <?php if (empty($logs)): ?>
          <div class="no-records py-5">
            <i class="fas fa-pills"></i>
            <h4 class="mt-3">No Medication Records Found</h4>
            <p class="text-muted">You don't have any medication entries matching your filters.</p>
            <a href="?page=medicinelog" class="btn btn-outline-primary mt-2">
              <i class="fas fa-sync me-1"></i> Reset Filters
            </a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Medicine</th>
                  <th>Dosage</th>
                  <th>Time</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logs as $log): ?>
                  <tr>
                    <form method="post">
                      <input type="hidden" name="edit_id" value="<?= $log['id'] ?>">
                      <td>
                        <input type="text" class="form-control form-control-sm" 
                               name="med_name" value="<?= htmlspecialchars($log['med_name']) ?>">
                      </td>
                      <td>
                        <input type="text" class="form-control form-control-sm" 
                               name="dosage" value="<?= htmlspecialchars($log['dosage']) ?>">
                      </td>
                      <td>
                        <input type="time" class="form-control form-control-sm" 
                               name="time" value="<?= htmlspecialchars($log['time']) ?>">
                      </td>
                      <td>
                        <input type="date" class="form-control form-control-sm" 
                               name="schedule_date" value="<?= htmlspecialchars($log['schedule_date']) ?>">
                      </td>
                      <td>
                        <?php if ($log['status'] === 'scheduled'): ?>
                          <span class="status-badge badge-scheduled">Scheduled</span>
                        <?php elseif ($log['status'] === 'done'): ?>
                          <span class="status-badge badge-done">Taken</span>
                        <?php else: ?>
                          <span class="status-badge badge-missed">Missed</span>
                        <?php endif; ?>
                      </td>
                      <td class="d-flex">
                        <button type="submit" class="action-btn btn-update me-2">
                          <i class="fas fa-save me-1"></i> Update
                        </button>
                        <a href="?page=medicinelog&delete=<?= $log['id'] ?>" 
                           class="action-btn btn-delete" 
                           onclick="return confirm('Are you sure you want to delete this medicine schedule?')">
                          <i class="fas fa-trash me-1"></i> Delete
                        </a>
                      </td>
                    </form>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
      document.querySelectorAll('.alert').forEach(alert => {
        new bootstrap.Alert(alert).close();
      });
    }, 5000);
    
    // Confirm before deleting
    document.querySelectorAll('.btn-delete').forEach(button => {
      button.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this medication schedule?')) {
          e.preventDefault();
        }
      });
    });
  </script>
</body>
</html>