<?php
// patient_records.php
require_once '../dbPC.php';
if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Search Filters
$searchDate = $_GET['date'] ?? '';
$searchDesc = $_GET['description'] ?? '';
$searchActivity = $_GET['activity'] ?? '';

// Food Logs
$foodQuery = "SELECT f.id, f.log_date, f.description, f.media_path, ci.calories, nl.carbs_percent, nl.protein_percent, nl.fats_percent
              FROM food_logs f
              LEFT JOIN calorie_intake ci ON f.id = ci.food_log_id
              LEFT JOIN nutrient_logs nl ON f.user_id = nl.user_id AND f.log_date = nl.log_date
              WHERE f.user_id = ?";
$foodParams = [$user_id];
if ($searchDate) {
    $foodQuery .= " AND f.log_date = ?";
    $foodParams[] = $searchDate;
}
if ($searchDesc) {
    $foodQuery .= " AND f.description LIKE ?";
    $foodParams[] = "%$searchDesc%";
}
$stmt = $conn->prepare($foodQuery);
$stmt->execute($foodParams);
$foodLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Exercise Logs
$exerciseQuery = "SELECT e.id, e.log_date, e.activity_name, e.duration_minutes, cb.calories_burned
                  FROM exercise_logs e
                  LEFT JOIN calorie_burned cb ON e.id = cb.exercise_log_id
                  WHERE e.user_id = ?";
$exerciseParams = [$user_id];
if ($searchDate) {
    $exerciseQuery .= " AND e.log_date = ?";
    $exerciseParams[] = $searchDate;
}
if ($searchActivity) {
    $exerciseQuery .= " AND e.activity_name LIKE ?";
    $exerciseParams[] = "%$searchActivity%";
}
$stmt = $conn->prepare($exerciseQuery);
$stmt->execute($exerciseParams);
$exerciseLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CanCare | Patient Records</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f2f4f8; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; background: white; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #695CFE; color: white; }
        h2 { color: #333; margin-top: 40px; }
        .search-box input { padding: 8px; margin-right: 10px; }
        .search-box button { padding: 8px 12px; background: #695CFE; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
<h1>Patient Records</h1>

<div class="search-box">
    <form method="GET">
        <label>Date:</label>
        <input type="date" name="date" value="<?= htmlspecialchars($searchDate) ?>">
        <label>Food Desc:</label>
        <input type="text" name="description" placeholder="e.g. chicken" value="<?= htmlspecialchars($searchDesc) ?>">
        <label>Exercise:</label>
        <input type="text" name="activity" placeholder="e.g. jogging" value="<?= htmlspecialchars($searchActivity) ?>">
        <button type="submit">Search</button>
    </form>
</div>

<h2>Food Logs</h2>
<table>
    <tr>
        <th>Date</th>
        <th>Description</th>
        <th>Calories</th>
        <th>Carbs%</th>
        <th>Protein%</th>
        <th>Fats%</th>
        <th>Photo</th>
    </tr>
    <?php foreach ($foodLogs as $log): ?>
        <tr>
            <td><?= htmlspecialchars($log['log_date']) ?></td>
            <td><?= htmlspecialchars($log['description']) ?></td>
            <td><?= $log['calories'] ?? 'N/A' ?></td>
            <td><?= $log['carbs_percent'] ?? '-' ?></td>
            <td><?= $log['protein_percent'] ?? '-' ?></td>
            <td><?= $log['fats_percent'] ?? '-' ?></td>
            <td>
                <?php if (!empty($log['media_path'])): ?>
                    <img src="../<?= htmlspecialchars($log['media_path']) ?>" alt="food" width="60">
                <?php else: ?>N/A<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Exercise Logs</h2>
<table>
    <tr>
        <th>Date</th>
        <th>Activity</th>
        <th>Duration (min)</th>
        <th>Calories Burned</th>
    </tr>
    <?php foreach ($exerciseLogs as $log): ?>
        <tr>
            <td><?= htmlspecialchars($log['log_date']) ?></td>
            <td><?= htmlspecialchars($log['activity_name']) ?></td>
            <td><?= $log['duration_minutes'] ?></td>
            <td><?= $log['calories_burned'] ?? 'N/A' ?></td>
        </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
