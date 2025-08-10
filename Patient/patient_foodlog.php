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
    $conn->prepare("DELETE FROM food_logs WHERE id = ? AND user_id = ?")->execute([$deleteId, $user_id]);
    header("Location: ?page=foodlog");
    exit();
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $editId = $_POST['edit_id'];
    $foodId = $_POST['food_id'];
    $amount = $_POST['amount'];
    $desc = $_POST['description'];
    
    // Get food data
    $stmt = $conn->prepare("SELECT * FROM food_datapg WHERE id = ?");
    $stmt->execute([$foodId]);
    $food = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($food) {
        $calories = $food['calorie_per_g'] * $amount;
        $conn->prepare("UPDATE food_logs SET food_id = ?, amount_in_grams = ?, description = ?, calculated_calorie = ? WHERE id = ? AND user_id = ?")
             ->execute([$foodId, $amount, $desc, $calories, $editId, $user_id]);
    }
}

// Search
$searchDate = $_GET['date'] ?? '';
$searchDesc = $_GET['description'] ?? '';

$query = "SELECT f.id, f.log_date, f.description, f.amount_in_grams, f.calculated_calorie, 
                 fd.food_name, fd.protein_mg_pg, fd.carb_mg_pg, fd.fat_mg_pg
          FROM food_logs f
          JOIN food_datapg fd ON f.food_id = fd.id
          WHERE f.user_id = ?";
$params = [$user_id];
if ($searchDate) {
    $query .= " AND f.log_date = ?";
    $params[] = $searchDate;
}
if ($searchDesc) {
    $query .= " AND f.description LIKE ?";
    $params[] = "%$searchDesc%";
}
$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all foods for dropdown
$foods = $conn->query("SELECT * FROM food_datapg")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
  h1 { margin-bottom: 20px; font-family: Arial, sans-serif; color: #2c3e50; }
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
  .nutrient-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 12px;
    margin: 2px;
    background: #e0f4ff;
    color: #3b82f6;
  }
  .calories-badge {
    background: #ffedd5;
    color: #f97316;
    font-weight: 500;
  }
</style>

<h1>Food Log Records</h1>
<form method="get" class="search">
  <input type="hidden" name="page" value="foodlog">
  <div>
    <label>Date:</label>
    <input type="date" name="date" value="<?= htmlspecialchars($searchDate) ?>">
  </div>
  <div>
    <label>Description:</label>
    <input type="text" name="description" value="<?= htmlspecialchars($searchDesc) ?>" placeholder="Search food...">
  </div>
  <div>
    <button type="submit">Search</button>
  </div>
</form>

<table>
  <tr>
    <th>Date</th>
    <th>Food Item</th>
    <th>Amount (g)</th>
    <th>Calories</th>
    <th>Nutrients</th>
    <th>Actions</th>
  </tr>
  <?php foreach ($logs as $log): ?>
    <tr>
      <form method="post" class="inline-edit">
        <input type="hidden" name="edit_id" value="<?= $log['id'] ?>">
        <td><?= htmlspecialchars($log['log_date']) ?></td>
        <td>
          <select name="food_id">
            <?php foreach ($foods as $food): ?>
              <option value="<?= $food['id'] ?>" <?= $food['id'] == $log['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($food['food_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="description" value="<?= htmlspecialchars($log['description']) ?>" placeholder="Description">
        </td>
        <td>
          <input type="number" name="amount" value="<?= $log['amount_in_grams'] ?>" min="1" step="1">
        </td>
        <td>
          <span class="calories-badge"><?= number_format($log['calculated_calorie']) ?> kcal</span>
        </td>
        <td>
          <span class="nutrient-badge">Protein: <?= number_format($log['protein_mg_pg'] * $log['amount_in_grams'], 1) ?>mg</span>
          <span class="nutrient-badge">Carbs: <?= number_format($log['carb_mg_pg'] * $log['amount_in_grams'], 1) ?>mg</span>
          <span class="nutrient-badge">Fat: <?= number_format($log['fat_mg_pg'] * $log['amount_in_grams'], 1) ?>mg</span>
        </td>
        <td>
          <button type="submit" class="action-btn btn-update">Update</button>
          <a href="?page=foodlog&delete=<?= $log['id'] ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure you want to delete this food log?')">Delete</a>
        </td>
      </form>
    </tr>
  <?php endforeach; ?>
</table>