<?php
session_start();
require_once '../dbPC.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date("Y-m-d");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // FOOD
    if (isset($_POST['save_food'])) {
        $stmt = $conn->prepare("INSERT INTO food_logs (user_id, log_date, description) VALUES (?, ?, ?)");
        $desc = $_POST['food_name'] . ": " . $_POST['food_description'];
        $stmt->execute([$user_id, $today, $desc]);
        $food_log_id = $conn->lastInsertId();

        if (!empty($_POST['food_calories'])) {
            $stmt2 = $conn->prepare("INSERT INTO calorie_intake (food_log_id, calories) VALUES (?, ?)");
            $stmt2->execute([$food_log_id, $_POST['food_calories']]);
        }
        if (!empty($_POST['carbs_percent']) || !empty($_POST['protein_percent']) || !empty($_POST['fats_percent'])) {
    // Check if nutrient log already exists for this user and date
    $stmtCheck = $conn->prepare("SELECT id FROM nutrient_logs WHERE user_id = ? AND log_date = ?");
    $stmtCheck->execute([$user_id, $today]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing nutrient log
        $stmtUpdate = $conn->prepare("UPDATE nutrient_logs SET carbs_percent = ?, protein_percent = ?, fats_percent = ? WHERE id = ?");
        $stmtUpdate->execute([
            $_POST['carbs_percent'] ?: null,
            $_POST['protein_percent'] ?: null,
            $_POST['fats_percent'] ?: null,
            $existing['id']
        ]);
    } else {
        // Insert new nutrient log
        $stmtInsert = $conn->prepare("INSERT INTO nutrient_logs (user_id, log_date, carbs_percent, protein_percent, fats_percent) VALUES (?, ?, ?, ?, ?)");
        $stmtInsert->execute([
            $user_id,
            $today,
            $_POST['carbs_percent'] ?: null,
            $_POST['protein_percent'] ?: null,
            $_POST['fats_percent'] ?: null,
        ]);
    }
}

    }

    // EXERCISE
    if (isset($_POST['save_exercise'])) {
        $stmt = $conn->prepare("INSERT INTO exercise_logs (user_id, activity_name, duration_minutes, log_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $_POST['exercise_name'], $_POST['exercise_duration'], $today]);
        $exercise_log_id = $conn->lastInsertId();

        if (!empty($_POST['calories_burned'])) {
            $stmt2 = $conn->prepare("INSERT INTO calorie_burned (exercise_log_id, calories_burned) VALUES (?, ?)");
            $stmt2->execute([$exercise_log_id, $_POST['calories_burned']]);
        }
    }

    // MEDICINE
    if (isset($_POST['save_medicine'])) {
        $stmt = $conn->prepare("INSERT INTO medicine_schedule (user_id, med_name, dosage, time, schedule_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $_POST['medicine_name'],
            $_POST['medicine_description'],
            $_POST['medicine_time'],
            $today
        ]);
    }
    //Steps
    // DAILY STEPS
    if (isset($_POST['save_steps'])) {
        $steps = $_POST['step_count'];
        $goal = !empty($_POST['step_goal']) ? $_POST['step_goal'] : 10000;

        // Check if already exists
        $stmtCheck = $conn->prepare("SELECT id FROM step_logs WHERE user_id = ? AND log_date = ?");
        $stmtCheck->execute([$user_id, $today]);

        if ($stmtCheck->rowCount() > 0) {
            $stmtUpdate = $conn->prepare("UPDATE step_logs SET steps = ?, goal = ? WHERE user_id = ? AND log_date = ?");
            $stmtUpdate->execute([$steps, $goal, $user_id, $today]);
        } else {
            $stmtInsert = $conn->prepare("INSERT INTO step_logs (user_id, log_date, steps, goal) VALUES (?, ?, ?, ?)");
            $stmtInsert->execute([$user_id, $today, $steps, $goal]);
        }
    }


    header("Location: add_entry.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Entry - CanCare</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            background: #f0f4f8;
        }

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        .section {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 12px;
            margin-bottom: 40px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

                    .carousel {
                position: relative;
                width: 300px;
                height: 300px;
                overflow: hidden;
                margin-left: 20px; /* Adds spacing between image and left edge */
            }

            .carousel img {
                position: absolute;
                width: 100%;
                height: 100%;
                object-fit: cover;
                opacity: 0;
                transition: opacity 1.5s ease-in-out;
                border-radius: 12px; /* Makes image corners rounded */
            }


        .carousel img.active {
            opacity: 1;
        }

        .form-area {
            flex: 1;
            padding: 30px;
        }

        .form-area h2 {
            color: #34495e;
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: 600;
            color: #333;
        }

        input, textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
        }

        button {
            margin-top: 20px;
            background-color: #2980b9;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
        }

        button:hover {
            background-color: #1c5a87;
        }
    </style>
</head>
<body>


<div class="container">
    <h1>Add New Entry</h1>

    <!-- FOOD ENTRY -->
    <div class="section">
        <div class="carousel" id="food-carousel">
            <img src="../zphotos/food-entry1.png" class="active">
            <img src="../zphotos/food-entry2.png">
            <img src="../zphotos/food-entry3.png">
            <img src="../zphotos/food-entry4.png">
        </div>

        <form method="POST" class="form-area">
                <h2>🥗 Food Entry</h2>

                <label>Food Name</label>
                <input type="text" name="food_name" required>

                <label>Description</label>
                <textarea name="food_description" rows="2" required></textarea>

                <label>Calories Intake</label>
                <input type="number" name="food_calories" required>

                <div style="display: flex; gap: 15px; margin-top: 10px;">
                <div style="flex: 1;">
                    <label>Carbohydrates (%)</label>
                    <input type="number" name="carbs_percent" min="0" max="100" placeholder="e.g. 50">
                </div>
                <div style="flex: 1;">
                    <label>Protein (%)</label>
                    <input type="number" name="protein_percent" min="0" max="100" placeholder="e.g. 30">
                </div>
                <div style="flex: 1;">
                    <label>Fats (%)</label>
                    <input type="number" name="fats_percent" min="0" max="100" placeholder="e.g. 20">
                </div>
                </div>

                <button type="submit" name="save_food">Save Food Entry</button>

        </form>
    </div>

    <!-- EXERCISE ENTRY -->
    <div class="section">
        <div class="carousel" id="exercise-carousel">
            <img src="../zphotos/exercise-entry1.png" class="active">
            <img src="../zphotos/exercise-entry2.png">
            <img src="../zphotos/exercise-entry3.png">
        </div>

        <form method="POST" class="form-area">
            <h2>🏋️ Exercise Entry</h2>
            <label>Exercise Name</label>
            <input type="text" name="exercise_name" required>

            <label>Duration (in minutes)</label>
            <input type="number" name="exercise_duration" required>

            <label>Calories Burned</label>
            <input type="number" name="calories_burned" required>

            <button type="submit" name="save_exercise">Save Exercise Entry</button>
        </form>
    </div>

    <!-- MEDICINE ENTRY -->
    <div class="section">
        <div class="carousel" id="medicine-carousel">
            <img src="../zphotos/medicine-entry1.png" class="active">
            <img src="../zphotos/medicine-entry2.png">
            <img src="../zphotos/medicine-entry3.png">
        </div>

        <form method="POST" class="form-area">
            <h2>💊 Medicine Entry</h2>
            <label>Medicine Name</label>
            <input type="text" name="medicine_name" required>

            <label>Dosage / Description</label>
            <input type="text" name="medicine_description" placeholder="e.g. 500mg after lunch" required>

            <label>Time</label>
            <input type="time" name="medicine_time" required>

            <button type="submit" name="save_medicine">Save Medicine Entry</button>
        </form>
    </div>

    <!-- STEPS ENTRY -->
    <div class="section">
        <div class="carousel" id="steps-carousel">
            <img src="../zphotos/steps1.png" class="active">
            <img src="../zphotos/steps2.png">
            <img src="../zphotos/steps3.png">
        </div>

        <form method="POST" class="form-area">
            <h2>🚶 Daily Step Entry</h2>

            <label>Total Steps</label>
            <input type="number" name="step_count" placeholder="e.g. 8000" required>

            <label>Step Goal (default: 10000)</label>
            <input type="number" name="step_goal" placeholder="e.g. 12000">

            <button type="submit" name="save_steps">Save Step Entry</button>
        </form>
</div>

</div>

<script>
    function setupCarousel(carouselId) {
        const images = document.querySelectorAll(`#${carouselId} img`);
        let index = 0;
        setInterval(() => {
            images[index].classList.remove('active');
            index = (index + 1) % images.length;
            images[index].classList.add('active');
        }, 3500);
    }

    setupCarousel('food-carousel');
    setupCarousel('exercise-carousel');
    setupCarousel('medicine-carousel');
    setupCarousel('steps-carousel'); 

</script>
</body>
</html>
