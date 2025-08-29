<?php
session_start();
require_once __DIR__ . "/../dbPC.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainer') {
    header("Location: ../loginPC.php");
    exit();
}
$trainer_id = $_SESSION['user_id'];

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = $_POST['patient_id'];
    $assigned_date = date("Y-m-d");
    $stmt = $conn->prepare("INSERT INTO trainer_patient (trainer_id, patient_id, assigned_date) VALUES (?,?,?)");
    $stmt->bind_param("iis", $trainer_id, $patient_id, $assigned_date);
    $stmt->execute();
    $stmt->close();
    $msg = "✅ Patient assigned successfully!";
}

// Fetch patients not yet assigned
$patients = $conn->query("SELECT id, name FROM users WHERE role='patient' AND id NOT IN (SELECT patient_id FROM trainer_patient WHERE trainer_id=$trainer_id)");
$assigned = $conn->query("SELECT u.name FROM trainer_patient tp JOIN users u ON tp.patient_id=u.id WHERE tp.trainer_id=$trainer_id");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Trainer Patients</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-4">
  <h2>Manage Patients</h2>
  <?php if (isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>
  <form method="post" class="card p-3 shadow">
    <label>Select Patient</label>
    <select name="patient_id" class="form-select" required>
      <?php while($p=$patients->fetch_assoc()){ ?>
        <option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
      <?php } ?>
    </select>
    <button class="btn btn-primary mt-2">Assign</button>
  </form>
  <h4 class="mt-4">Already Assigned Patients</h4>
  <ul class="list-group">
    <?php while($a=$assigned->fetch_assoc()){ ?>
      <li class="list-group-item"><?= $a['name'] ?></li>
    <?php } ?>
  </ul>
</div>
</body>
</html>
