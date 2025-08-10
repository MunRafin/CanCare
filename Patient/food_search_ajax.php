<?php
require_once '../dbPC.php';

$q = $_GET['q'] ?? '';
$q = trim($q);

if(strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT id, food_name, calories_per_g, protein_mg, fat_mg, carbohydrate_mg, vitamins_mg, minerals_mg, fiber_mg 
                        FROM food_datapg WHERE food_name LIKE ? LIMIT 10");
$stmt->execute(["%$q%"]);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
