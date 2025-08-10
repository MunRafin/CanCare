<?php
session_start();
require_once '../dbPC.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date("Y-m-d");

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['q'])) {
        // Food search AJAX
        $query = '%' . $_GET['q'] . '%';
        $stmt = $conn->prepare("SELECT id, food_name, calorie_per_g, 
                               protein_mg_pg, fat_mg_pg, carb_mg_pg, 
                               vitamin_mg_pg, mineral_mg_pg, water_mg_pg 
                               FROM food_datapg 
                               WHERE food_name LIKE ? LIMIT 10");
        $stmt->execute([$query]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    } elseif (isset($_GET['get_schedule'])) {
        // Get meal schedule with food names
        $stmt = $conn->prepare("SELECT ms.id, ms.day, ms.meal_time, ms.food_id, fd.food_name 
                               FROM meal_schedule ms
                               JOIN food_datapg fd ON ms.food_id = fd.id
                               WHERE ms.user_id = ?");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['record_entry'])) {
        // Food entry submission
        $food_id = $_POST['food_id'];
        $amount = floatval($_POST['amount']);
        $calories_per_g = floatval($_POST['calories_per_g']);
        $total_calories = $calories_per_g * $amount;

        $stmt = $conn->prepare("INSERT INTO food_logs (user_id, food_id, log_date, amount_in_grams, calculated_calorie) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $food_id, $today, $amount, $total_calories]);
        
        header("Location: patient_home.php?page=patient_dietplan&success=1");
        exit();
    } elseif (isset($_POST['save_schedule'])) {
        // Save multiple foods to schedule
        $day = $_POST['day'];
        $meal_time = $_POST['meal_time'];
        $food_ids = $_POST['food_ids'];
        
        // First delete old entries for this meal
        $stmt = $conn->prepare("DELETE FROM meal_schedule 
                               WHERE user_id = ? AND day = ? AND meal_time = ?");
        $stmt->execute([$user_id, $day, $meal_time]);
        
        // Insert new foods
        $stmt = $conn->prepare("INSERT INTO meal_schedule (user_id, day, meal_time, food_id) 
                               VALUES (?, ?, ?, ?)");
        foreach ($food_ids as $food_id) {
            $stmt->execute([$user_id, $day, $meal_time, $food_id]);
        }
        
        header("Location: patient_home.php?page=patient_dietplan&schedule_success=1");
        exit();
    } elseif (isset($_POST['remove_food'])) {
        // Remove a food from schedule
        $stmt = $conn->prepare("DELETE FROM meal_schedule WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['id'], $user_id]);
        echo json_encode(['status' => 'success']);
        exit();
    }
}

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$meal_times = ['Breakfast', 'Lunch', 'Dinner'];
?>

<div class="diet-plan-container">
    <?php if (isset($_GET['success'])): ?>
        <div class="success-msg">Food entry recorded successfully!</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['schedule_success'])): ?>
        <div class="success-msg">Meal schedule updated successfully!</div>
    <?php endif; ?>

    <!-- Food Images Carousel -->
    <div class="carousel" id="food-carousel">
        <img src="../zphotos/food-entry1.png" class="active">
        <img src="../zphotos/food-entry2.png">
        <img src="../zphotos/food-entry3.png">
        <img src="../zphotos/food-entry4.png">
    </div>

    <div class="diet-section">
        <h2>Daily Food Entry</h2>
        <div class="flex-container">
            <div class="form-area">
                <form id="diet-form" method="POST" action="">
                    <label for="food_search">Search Food</label>
                    <input type="text" id="food_search" name="food_search" placeholder="Start typing food name...">
                    <div id="suggestionBox"></div>
                    <input type="hidden" name="food_id" id="food_id">

                    <label for="amount">Amount Consumed (grams)</label>
                    <input type="number" id="amount" name="amount" min="1">

                    <button type="button" id="calculate_btn">Calculate Nutrition</button>

                    <div id="calculation_result" class="calculation-result" style="display:none;">
                        <h3>Nutrition Calculation</h3>
                        <div id="calc_results"></div>
                    </div>

                    <input type="hidden" name="calories_per_g" id="calories_per_g">
                    <button type="submit" name="record_entry" style="display:none;" id="record_btn">Record Food Entry</button>
                </form>
            </div>

            <div class="nutrient-info" id="nutrient_info">
                <h3>Nutrient Information (per gram)</h3>
                <div class="nutrient-row"><span>Calories:</span><span id="nutr_calories">0</span> kcal</div>
                <div class="nutrient-row"><span>Protein:</span><span id="nutr_protein">0</span> mg</div>
                <div class="nutrient-row"><span>Carbs:</span><span id="nutr_carbs">0</span> mg</div>
                <div class="nutrient-row"><span>Fat:</span><span id="nutr_fat">0</span> mg</div>
                <div class="nutrient-row"><span>Vitamins:</span><span id="nutr_vitamins">0</span> mg</div>
                <div class="nutrient-row"><span>Minerals:</span><span id="nutr_minerals">0</span> mg</div>
                <div class="nutrient-row"><span>Water:</span><span id="nutr_water">0</span> mg</div>
            </div>
        </div>
    </div>

    <div class="diet-section">
        <h2>Weekly Meal Schedule</h2>
        <div class="schedule-form">
            <div class="form-group">
                <label>Day</label>
                <select id="day">
                    <?php foreach($days_of_week as $day): ?>
                        <option value="<?= $day ?>"><?= $day ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Meal Time</label>
                <select id="meal_time">
                    <?php foreach($meal_times as $time): ?>
                        <option value="<?= $time ?>"><?= $time ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Add Food</label>
                <input type="text" id="schedule_food_search" placeholder="Search food...">
                <div id="scheduleSuggestionBox"></div>
                <div class="selected-foods" id="selected_foods"></div>
            </div>
            
            <button id="save_schedule_btn">Save to Schedule</button>
        </div>
        
        <table id="meal_schedule">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Breakfast</th>
                    <th>Lunch</th>
                    <th>Dinner</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($days_of_week as $day): ?>
                <tr>
                    <td><?= $day ?></td>
                    <?php foreach($meal_times as $time): ?>
                    <td class="meal-cell" data-day="<?= $day ?>" data-time="<?= $time ?>">
                        <div class="food-items" id="food-items-<?= $day ?>-<?= $time ?>"></div>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.diet-plan-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.diet-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.flex-container {
    display: flex;
    gap: 20px;
}

.form-area {
    flex: 2;
}

.nutrient-info {
    flex: 1;
    background: #f0f8ff;
    padding: 15px;
    border-radius: 8px;
}

.schedule-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.form-group {
    flex: 1;
}

#suggestionBox, #scheduleSuggestionBox { 
    border: 1px solid #ccc;
    max-height: 200px;
    overflow-y: auto;
    display: none;
    position: absolute;
    background: white;
    z-index: 1000;
    width: 300px;
}

.suggestion-item {
    padding: 8px;
    cursor: pointer;
}

.suggestion-item:hover {
    background: #f0f0f0;
}

.selected-foods {
    margin-top: 10px;
}

.food-item {
    display: flex;
    justify-content: space-between;
    padding: 8px;
    background: #f8f8f8;
    margin: 5px 0;
    border-radius: 4px;
}

.remove-food {
    color: red;
    cursor: pointer;
    margin-left: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: left;
}

th {
    background: #4CAF50;
    color: white;
}

button {
    padding: 8px 15px;
    background: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button:hover {
    background: #45a049;
}

.success-msg {
    color: green;
    font-weight: bold;
    margin: 10px 0;
    padding: 10px;
    background: #e8f5e9;
    border-radius: 4px;
}

.calculation-result {
    margin-top: 15px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
    border-left: 4px solid #4CAF50;
}

.nutrient-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

input[type="text"], input[type="number"], select { 
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
    margin-top: 5px;
}

label {
    font-weight: bold;
    margin-top: 10px;
    display: block;
}

/* Carousel styles */
.carousel {
    width: 100%;
    height: 200px;
    position: relative;
    overflow: hidden;
    margin-bottom: 20px;
    border-radius: 8px;
}

.carousel img {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0;
    transition: opacity 1s ease-in-out;
}

.carousel img.active {
    opacity: 1;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up food image carousel
    const foodImages = document.querySelectorAll('#food-carousel img');
    let currentImage = 0;
    
    function rotateCarousel() {
        foodImages[currentImage].classList.remove('active');
        currentImage = (currentImage + 1) % foodImages.length;
        foodImages[currentImage].classList.add('active');
    }
    
    setInterval(rotateCarousel, 3000);

    // Food entry functionality
    const foodInput = document.getElementById('food_search');
    const foodIdInput = document.getElementById('food_id');
    const suggestionBox = document.getElementById('suggestionBox');
    const amountInput = document.getElementById('amount');
    const calculateBtn = document.getElementById('calculate_btn');
    const recordBtn = document.getElementById('record_btn');
    const caloriesPerGInput = document.getElementById('calories_per_g');
    const calculationResult = document.getElementById('calculation_result');
    const calcResults = document.getElementById('calc_results');
    
    let currentSelectedFood = null;

    // Schedule functionality
    const scheduleFoodInput = document.getElementById('schedule_food_search');
    const scheduleSuggestionBox = document.getElementById('scheduleSuggestionBox');
    const saveScheduleBtn = document.getElementById('save_schedule_btn');
    const selectedFoodsContainer = document.getElementById('selected_foods');
    let selectedFoods = [];
    
    // Load schedule on page load
    loadSchedule();

    // Food search for daily entry
    foodInput.addEventListener('input', function() {
        const query = this.value.trim();
        foodIdInput.value = '';
        currentSelectedFood = null;
        calculationResult.style.display = 'none';
        recordBtn.style.display = 'none';
        caloriesPerGInput.value = '';
        calcResults.innerHTML = '';

        if (query.length < 2) {
            suggestionBox.style.display = 'none';
            resetNutrientInfo();
            return;
        }

        fetch(`patient_dietplan.php?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(foods => {
                suggestionBox.innerHTML = '';
                if(foods.length === 0) {
                    suggestionBox.style.display = 'none';
                    return;
                }
                
                foods.forEach(food => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.textContent = food.food_name;
                    div.onclick = () => {
                        foodInput.value = food.food_name;
                        foodIdInput.value = food.id;
                        currentSelectedFood = food;
                        updateNutrientInfo(food);
                        suggestionBox.style.display = 'none';
                    };
                    suggestionBox.appendChild(div);
                });
                
                const rect = foodInput.getBoundingClientRect();
                suggestionBox.style.top = (rect.bottom + window.scrollY) + 'px';
                suggestionBox.style.left = (rect.left + window.scrollX) + 'px';
                suggestionBox.style.width = foodInput.offsetWidth + 'px';
                suggestionBox.style.display = 'block';
            });
    });

    // Food search for schedule
    scheduleFoodInput.addEventListener('input', function() {
        const query = this.value.trim();
        if(query.length < 2) {
            scheduleSuggestionBox.style.display = 'none';
            return;
        }
        
        fetch(`patient_dietplan.php?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(foods => {
                scheduleSuggestionBox.innerHTML = '';
                
                foods.forEach(food => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.textContent = food.food_name;
                    div.onclick = () => {
                        selectedFoods.push({
                            id: food.id,
                            name: food.food_name
                        });
                        updateSelectedFoods();
                        scheduleFoodInput.value = '';
                        scheduleSuggestionBox.style.display = 'none';
                    };
                    scheduleSuggestionBox.appendChild(div);
                });
                
                const rect = scheduleFoodInput.getBoundingClientRect();
                scheduleSuggestionBox.style.top = (rect.bottom + window.scrollY) + 'px';
                scheduleSuggestionBox.style.left = (rect.left + window.scrollX) + 'px';
                scheduleSuggestionBox.style.width = scheduleFoodInput.offsetWidth + 'px';
                scheduleSuggestionBox.style.display = 'block';
            });
    });

    // Calculate nutrition for daily entry
    calculateBtn.addEventListener('click', function() {
        if (!currentSelectedFood) {
            alert('Please select a food first');
            return;
        }
        
        const amount = parseFloat(amountInput.value);
        if (isNaN(amount) || amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }

        const food = currentSelectedFood;
        const calories = (food.calorie_per_g || 0) * amount;
        caloriesPerGInput.value = food.calorie_per_g || 0;

        calcResults.innerHTML = `
            <p><strong>Total for ${amount}g:</strong></p>
            <p>Calories: ${calories.toFixed(2)} kcal</p>
            <p>Protein: ${((food.protein_mg_pg || 0) * amount).toFixed(2)} mg</p>
            <p>Carbs: ${((food.carb_mg_pg || 0) * amount).toFixed(2)} mg</p>
            <p>Fat: ${((food.fat_mg_pg || 0) * amount).toFixed(2)} mg</p>
        `;
        
        calculationResult.style.display = 'block';
        recordBtn.style.display = 'inline-block';
    });

    // Save schedule
    saveScheduleBtn.addEventListener('click', function() {
        const day = document.getElementById('day').value;
        const meal_time = document.getElementById('meal_time').value;
        
        if(selectedFoods.length === 0) {
            alert('Please add at least one food item');
            return;
        }

        const formData = new FormData();
        formData.append('save_schedule', '1');
        formData.append('day', day);
        formData.append('meal_time', meal_time);
        selectedFoods.forEach(food => formData.append('food_ids[]', food.id));
        
        fetch('patient_dietplan.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if(res.ok) {
                selectedFoods = [];
                updateSelectedFoods();
                scheduleFoodInput.value = '';
                loadSchedule();
                window.location.href = 'patient_home.php?page=patient_dietplan&schedule_success=1';
            }
        });
    });

    // Helper functions
    function resetNutrientInfo() {
        document.getElementById('nutr_calories').textContent = '0';
        document.getElementById('nutr_protein').textContent = '0';
        document.getElementById('nutr_carbs').textContent = '0';
        document.getElementById('nutr_fat').textContent = '0';
        document.getElementById('nutr_vitamins').textContent = '0';
        document.getElementById('nutr_minerals').textContent = '0';
        document.getElementById('nutr_water').textContent = '0';
    }

    function updateNutrientInfo(food) {
        document.getElementById('nutr_calories').textContent = food.calorie_per_g?.toFixed(2) || '0';
        document.getElementById('nutr_protein').textContent = food.protein_mg_pg?.toFixed(2) || '0';
        document.getElementById('nutr_carbs').textContent = food.carb_mg_pg?.toFixed(2) || '0';
        document.getElementById('nutr_fat').textContent = food.fat_mg_pg?.toFixed(2) || '0';
        document.getElementById('nutr_vitamins').textContent = food.vitamin_mg_pg?.toFixed(2) || '0';
        document.getElementById('nutr_minerals').textContent = food.mineral_mg_pg?.toFixed(2) || '0';
        document.getElementById('nutr_water').textContent = food.water_mg_pg?.toFixed(2) || '0';
    }

    function updateSelectedFoods() {
        selectedFoodsContainer.innerHTML = '';
        
        selectedFoods.forEach((food, index) => {
            const div = document.createElement('div');
            div.className = 'food-item';
            div.innerHTML = `
                ${food.name}
                <span class="remove-food" onclick="removeSelectedFood(${index})">×</span>
            `;
            selectedFoodsContainer.appendChild(div);
        });
    }

    function loadSchedule() {
        fetch('patient_dietplan.php?get_schedule=1')
            .then(res => res.json())
            .then(data => {
                document.querySelectorAll('.food-items').forEach(el => {
                    el.innerHTML = '';
                });
                
                const schedule = {};
                data.forEach(item => {
                    const key = `${item.day}-${item.meal_time}`;
                    if(!schedule[key]) schedule[key] = [];
                    schedule[key].push(item);
                });
                
                Object.keys(schedule).forEach(key => {
                    const [day, time] = key.split('-');
                    const container = document.getElementById(`food-items-${day}-${time}`);
                    
                    schedule[key].forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'food-item';
                        div.innerHTML = `
                            ${item.food_name}
                            <span class="remove-food" onclick="removeFromSchedule(${item.id})">×</span>
                        `;
                        container.appendChild(div);
                    });
                });
            });
    }

    // Global functions for HTML onclick
    window.removeSelectedFood = function(index) {
        selectedFoods.splice(index, 1);
        updateSelectedFoods();
    };

    window.removeFromSchedule = function(id) {
        if(!confirm('Remove this food from schedule?')) return;
        
        fetch('patient_dietplan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `remove_food=1&id=${id}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                loadSchedule();
            }
        });
    };
});
</script>