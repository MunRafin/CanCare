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
    $conn->prepare("DELETE FROM exercise_log WHERE id = ? AND user_id = ?")->execute([$deleteId, $user_id]);
    header("Location: ?page=exerciselog");
    exit();
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $editId = $_POST['edit_id'];
    $exerciseId = $_POST['exercise_id'];
    $repetitions = $_POST['repetitions'];
    $sets = $_POST['sets'];
    
    // Get exercise data
    $stmt = $conn->prepare("SELECT calorie_burn_per_rep FROM exercise_prs WHERE id = ?");
    $stmt->execute([$exerciseId]);
    $exercise = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($exercise) {
        // Get user weight
        $weight = 70; // Default weight if not available
        $stmt = $conn->prepare("SELECT weight_kg FROM patients WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($patient && $patient['weight_kg']) {
            $weight = $patient['weight_kg'];
        }
        
        // Calculate calories burned
        $calories = $exercise['calorie_burn_per_rep'] * $repetitions * $sets * $weight;
        
        $conn->prepare("UPDATE exercise_log SET exercise_id = ?, repetitions = ?, sets = ?, calorie_burned = ? WHERE id = ? AND user_id = ?")
             ->execute([$exerciseId, $repetitions, $sets, $calories, $editId, $user_id]);
    }
}

// Search
$searchDate = $_GET['date'] ?? '';
$searchExercise = $_GET['exercise'] ?? '';

$query = "SELECT el.id, el.log_date, ep.name AS exercise_name, 
                 el.repetitions, el.sets, el.calorie_burned
          FROM exercise_log el
          JOIN exercise_prs ep ON el.exercise_id = ep.id
          WHERE el.user_id = ?";
$params = [$user_id];
if ($searchDate) {
    $query .= " AND el.log_date = ?";
    $params[] = $searchDate;
}
if ($searchExercise) {
    $query .= " AND ep.name LIKE ?";
    $params[] = "%$searchExercise%";
}
$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all exercises for dropdown
$exercises = $conn->query("SELECT * FROM exercise_prs")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
  h1 {
    font-family: Arial, sans-serif;
    margin-bottom: 20px;
    color: #2c3e50;
  }
  form.search {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
    align-items: center;
    background: #f8f9ff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
  }
  form.search label {
    font-weight: 500;
    color: #5c7cfa;
    min-width: 100px;
  }
  form.search input, form.search select {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    width: 200px;
    background: white;
  }
  form.search button {
    background: #5c7cfa;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s ease;
  }
  form.search button:hover {
    background: #3b5bdb;
    transform: translateY(-2px);
  }
  table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  }
  th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
  }
  th {
    background-color: #5c7cfa;
    color: white;
    font-weight: 500;
    text-transform: uppercase;
    font-size: 14px;
  }
  tr:nth-child(even) {
    background-color: #f8f9ff;
  }
  tr:hover {
    background-color: #eef2ff;
  }
  form.inline-edit input, form.inline-edit select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    width: 100%;
    font-size: 14px;
    background: white;
  }
  .action-btn {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    color: white;
    cursor: pointer;
    font-size: 14px;
    display: inline-block;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s ease;
    margin: 2px;
  }
  .btn-update {
    background-color: #22c55e;
    width: 80px;
  }
  .btn-delete {
    background-color: #ef4444;
    width: 80px;
  }
  .btn-delete:hover, .btn-update:hover {
    opacity: 0.9;
    transform: translateY(-2px);
  }
  .calories-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 12px;
    background: #ffedd5;
    color: #f97316;
    font-weight: 500;
  }
  .exercise-stats {
    display: flex;
    gap: 10px;
  }
  .exercise-stat {
    background: #e0f4ff;
    color: #3b82f6;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 13px;
  }
</style>

<h1>Exercise Log Records</h1>
<form method="get" class="search">
  <input type="hidden" name="page" value="exerciselog">
  <div>
    <label>Date:</label>
    <input type="date" name="date" value="<?= htmlspecialchars($searchDate) ?>">
  </div>
  <div>
    <label>Exercise:</label>
    <input type="text" name="exercise" value="<?= htmlspecialchars($searchExercise) ?>" placeholder="Search exercises...">
  </div>
  <div>
    <button type="submit">Search</button>
  </div>
</form>

<table>
  <tr>
    <th>Date</th>
    <th>Exercise</th>
    <th>Reps & Sets</th>
    <th>Calories Burned</th>
    <th>Actions</th>
  </tr>
  <?php foreach ($logs as $log): ?>
    <tr>
      <form method="post" class="inline-edit">
        <input type="hidden" name="edit_id" value="<?= $log['id'] ?>">
        <td><?= htmlspecialchars($log['log_date']) ?></td>
        <td>
          <select name="exercise_id">
            <?php foreach ($exercises as $exercise): ?>
              <option value="<?= $exercise['id'] ?>" <?= $exercise['name'] == $log['exercise_name'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($exercise['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <div class="exercise-stats">
            <div>
              <span>Reps:</span>
              <input type="number" name="repetitions" value="<?= $log['repetitions'] ?>" min="1" style="width: 70px;">
            </div>
            <div>
              <span>Sets:</span>
              <input type="number" name="sets" value="<?= $log['sets'] ?>" min="1" style="width: 70px;">
            </div>
          </div>
        </td>
        <td>
          <span class="calories-badge"><?= number_format($log['calorie_burned']) ?> kcal</span>
        </td>
        <td>
          <button type="submit" class="action-btn btn-update">Update</button>
          <a href="?page=exerciselog&delete=<?= $log['id'] ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this exercise log?')">Delete</a>
        </td>
      </form>
    </tr>
  <?php endforeach; ?>
</table>