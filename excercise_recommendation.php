<?php
session_start();
require_once "dbPC.php"; // DB connection

// Only allow patients
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: loginPC.php");
    exit();
}

$patient_id = $_SESSION['user_id'];
$recommendation = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fatigue = $_POST['fatigue_level'];
    $pain = $_POST['pain_level'];
    $mobility = $_POST['mobility_status'];
    $sleep = $_POST['sleep_quality'];
    $stress = $_POST['stress_level'];

    // Save patient condition
    $stmt = $conn->prepare("INSERT INTO patient_health_conditions (patient_id, fatigue_level, pain_level, mobility_status, sleep_quality, stress_level) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("isssss", $patient_id, $fatigue, $pain, $mobility, $sleep, $stress);
    $stmt->execute();
    $stmt->close();

    // Fetch exercise recommendation
    $qry = $conn->prepare("SELECT suggested_exercise, notes FROM exercise_recommendations 
                           WHERE fatigue_level=? AND pain_level=? AND mobility_status=? AND sleep_quality=? AND stress_level=? LIMIT 1");
    $qry->bind_param("sssss", $fatigue, $pain, $mobility, $sleep, $stress);
    $qry->execute();
    $result = $qry->get_result();
    $recommendation = $result->fetch_assoc();
    $qry->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Health Form</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">

    <h2 class="mb-4">Submit Your Health Condition</h2>

    <form method="post" class="card p-4 shadow">
        <div class="row mb-3">
            <div class="col-md-4">
                <label>Fatigue Level</label>
                <select name="fatigue_level" class="form-select" required>
                    <option>Low</option><option>Medium</option><option>High</option>
                </select>
            </div>
            <div class="col-md-4">
                <label>Pain Level</label>
                <select name="pain_level" class="form-select" required>
                    <option>None</option><option>Mild</option><option>Moderate</option><option>Severe</option>
                </select>
            </div>
            <div class="col-md-4">
                <label>Mobility Status</label>
                <select name="mobility_status" class="form-select" required>
                    <option>Normal</option><option>Limited</option><option>Severely Limited</option>
                </select>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label>Sleep Quality</label>
                <select name="sleep_quality" class="form-select" required>
                    <option>Good</option><option>Average</option><option>Poor</option>
                </select>
            </div>
            <div class="col-md-6">
                <label>Stress Level</label>
                <select name="stress_level" class="form-select" required>
                    <option>Low</option><option>Medium</option><option>High</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-success">Submit & Get Recommendation</button>
    </form>

    <?php if ($recommendation): ?>
        <div class="card mt-4 shadow">
            <div class="card-header bg-info text-white">Your Exercise Recommendation</div>
            <div class="card-body">
                <h5><?= $recommendation['suggested_exercise'] ?></h5>
                <p><?= $recommendation['notes'] ?></p>
            </div>
        </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
        <div class="alert alert-warning mt-4">⚠ No exact recommendation found. Please consult your trainer.</div>
    <?php endif; ?>

</div>
</body>
</html>
