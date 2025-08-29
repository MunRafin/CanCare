<?php
session_start();
require_once __DIR__ . "/../dbPC.php"; // fixed path

// Only allow trainer access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainer') {
    header("Location: ../login.php");
    exit();
}

$trainer_id = $_SESSION['user_id'];

// Fetch patients
$patients = $conn->query("SELECT id, name FROM users WHERE role='patient' ORDER BY name");

// Handle new exercise assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_exercise'])) {
    $patient_id = $_POST['patient_id'];
    $exercise_id = $_POST['exercise_id'];
    $reps = $_POST['repetitions'];
    $sets = $_POST['sets'];
    $log_date = date("Y-m-d");

    // Get calorie per rep
    $calQry = $conn->prepare("SELECT calorie_burn_per_rep FROM exercise_prs WHERE id=?");
    $calQry->bind_param("i", $exercise_id);
    $calQry->execute();
    $calQry->bind_result($calPerRep);
    $calQry->fetch();
    $calQry->close();

    $calories = $calPerRep * $reps * $sets * 70; // assume avg 70kg weight

    $stmt = $conn->prepare("INSERT INTO exercise_log (user_id, exercise_id, log_date, repetitions, sets, calorie_burned) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("iisidd", $patient_id, $exercise_id, $log_date, $reps, $sets, $calories);
    $stmt->execute();
    $stmt->close();

    $msg = "✅ Exercise assigned successfully!";
}

// Fetch exercise list
$exerciseList = $conn->query("SELECT id, name, muscle_group FROM exercise_prs ORDER BY muscle_group");

// Weekly progress (last 7 days)
$progress = $conn->query("
    SELECT u.name, 
           SUM(s.steps) AS total_steps, 
           SUM(l.calorie_burned) AS total_calories
    FROM users u
    LEFT JOIN step_logs s ON u.id = s.user_id AND s.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    LEFT JOIN exercise_log l ON u.id = l.user_id AND l.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    WHERE u.role='patient'
    GROUP BY u.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trainer Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-4">

    <h2 class="mb-4">Trainer Dashboard</h2>
    <?php if (isset($msg)) { echo "<div class='alert alert-success'>$msg</div>"; } ?>

    <!-- Assign Exercise -->
    <div class="card mb-4 shadow">
        <div class="card-header bg-primary text-white">Assign Exercise</div>
        <div class="card-body">
            <form method="post">
                <div class="row mb-2">
                    <div class="col-md-3">
                        <label>Patient</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select</option>
                            <?php while ($p = $patients->fetch_assoc()) { ?>
                                <option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Exercise</label>
                        <select name="exercise_id" class="form-select" required>
                            <option value="">Select</option>
                            <?php while ($ex = $exerciseList->fetch_assoc()) { ?>
                                <option value="<?= $ex['id'] ?>"><?= $ex['name'] ?> (<?= $ex['muscle_group'] ?>)</option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label>Reps</label>
                        <input type="number" name="repetitions" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Sets</label>
                        <input type="number" name="sets" class="form-control" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="assign_exercise" class="btn btn-success w-100">Assign</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Recent Exercises -->
    <div class="card mb-4 shadow">
        <div class="card-header bg-dark text-white">Recent Exercise Logs</div>
        <div class="card-body">
            <table class="table table-striped">
                <thead class="table-secondary">
                    <tr>
                        <th>Patient</th>
                        <th>Exercise</th>
                        <th>Date</th>
                        <th>Reps</th>
                        <th>Sets</th>
                        <th>Calories</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $logs = $conn->query("
                    SELECT u.name as patient, e.name as exercise, l.log_date, l.repetitions, l.sets, l.calorie_burned
                    FROM exercise_log l
                    JOIN users u ON l.user_id=u.id
                    JOIN exercise_prs e ON l.exercise_id=e.id
                    ORDER BY l.log_date DESC LIMIT 10
                ");
                while ($row = $logs->fetch_assoc()) {
                    echo "<tr>
                        <td>{$row['patient']}</td>
                        <td>{$row['exercise']}</td>
                        <td>{$row['log_date']}</td>
                        <td>{$row['repetitions']}</td>
                        <td>{$row['sets']}</td>
                        <td>{$row['calorie_burned']}</td>
                    </tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Weekly Progress -->
    <div class="card shadow">
        <div class="card-header bg-success text-white">Weekly Progress (Last 7 Days)</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead class="table-success">
                    <tr>
                        <th>Patient</th>
                        <th>Total Steps</th>
                        <th>Total Calories Burned</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($pr = $progress->fetch_assoc()) { ?>
                    <tr>
                        <td><?= $pr['name'] ?></td>
                        <td><?= $pr['total_steps'] ?? 0 ?></td>
                        <td><?= $pr['total_calories'] ?? 0 ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
