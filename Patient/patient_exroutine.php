<?php
session_start();
require_once '../dbPC.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../loginPC.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date("Y-m-d");

// Get patient weight
$stmt = $conn->prepare("SELECT weight_kg FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
$patient_weight = $patient['weight_kg'] ?? 70; // Default to 70kg if not set

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['q'])) {
        // Exercise search AJAX
        $query = '%' . $_GET['q'] . '%';
        $stmt = $conn->prepare("SELECT id, name, benefit, muscle_group, calorie_burn_per_rep 
                               FROM exercise_prs 
                               WHERE name LIKE ? LIMIT 10");
        $stmt->execute([$query]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    } elseif (isset($_GET['get_today_exercises'])) {
        // Get today's exercises
        $stmt = $conn->prepare("SELECT el.id, el.repetitions, el.sets, el.calorie_burned, 
                               ep.name, ep.muscle_group
                               FROM exercise_log el
                               JOIN exercise_prs ep ON el.exercise_id = ep.id
                               WHERE el.user_id = ? AND el.log_date = ?");
        $stmt->execute([$user_id, $today]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    } elseif (isset($_GET['get_today_steps'])) {
        // Get today's step count
        $stmt = $conn->prepare("SELECT steps, goal FROM step_logs 
                               WHERE user_id = ? AND log_date = ?");
        $stmt->execute([$user_id, $today]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($result ? $result : ['steps' => 0, 'goal' => 10000]);
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['record_exercise'])) {
        // Exercise entry submission
        $exercise_id = $_POST['exercise_id'];
        $repetitions = intval($_POST['repetitions']);
        $sets = intval($_POST['sets']);
        $calorie_burned = floatval($_POST['calorie_burned']);

        $stmt = $conn->prepare("INSERT INTO exercise_log 
                               (user_id, exercise_id, log_date, repetitions, sets, calorie_burned) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $exercise_id, $today, $repetitions, $sets, $calorie_burned]);
        
        header("Location: patient_home.php?page=patient_exroutine&success=1");
        exit();
    } elseif (isset($_POST['record_steps'])) {
        // Step count submission
        $steps = intval($_POST['steps']);
        $goal = intval($_POST['goal']);

        // Check if entry exists for today
        $stmt = $conn->prepare("SELECT id FROM step_logs 
                               WHERE user_id = ? AND log_date = ?");
        $stmt->execute([$user_id, $today]);
        
        if ($stmt->rowCount() > 0) {
            // Update existing entry
            $stmt = $conn->prepare("UPDATE step_logs 
                                   SET steps = ?, goal = ?
                                   WHERE user_id = ? AND log_date = ?");
            $stmt->execute([$steps, $goal, $user_id, $today]);
        } else {
            // Insert new entry
            $stmt = $conn->prepare("INSERT INTO step_logs 
                                   (user_id, log_date, steps, goal) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $today, $steps, $goal]);
        }
        
        header("Location: patient_home.php?page=patient_exroutine&steps_success=1");
        exit();
    } elseif (isset($_POST['remove_exercise'])) {
        // Remove exercise from log
        $stmt = $conn->prepare("DELETE FROM exercise_log WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['id'], $user_id]);
        echo json_encode(['status' => 'success']);
        exit();
    }
}
?>

<div class="exercise-container">
    <?php if (isset($_GET['success'])): ?>
        <div class="success-msg">Exercise recorded successfully!</div>
    <?php endif; ?>
    
    <?php if (isset($_GET['steps_success'])): ?>
        <div class="success-msg">Step count updated successfully!</div>
    <?php endif; ?>

    <!-- Exercise Images Carousel -->
    <div class="carousel" id="exercise-carousel">
        <img src="../zphotos/exercise-entry1.png" class="active">
        <img src="../zphotos/exercise-entry2.png">
        <img src="../zphotos/exercise-entry3.png">
    </div>

    <div class="exercise-section">
        <h2>Today's Exercise Routine</h2>
        <div class="flex-container">
            <div class="form-area">
                <form id="exercise-form">
                    <label for="exercise_search">Search Exercise</label>
                    <input type="text" id="exercise_search" placeholder="Start typing exercise name...">
                    <div id="suggestionBox"></div>
                    <input type="hidden" name="exercise_id" id="exercise_id">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="repetitions">Repetitions</label>
                            <input type="number" id="repetitions" name="repetitions" min="1" value="10">
                        </div>
                        <div class="form-group">
                            <label for="sets">Sets</label>
                            <input type="number" id="sets" name="sets" min="1" value="3">
                        </div>
                    </div>

                    <button type="button" id="calculate_btn">Calculate Calories</button>

                    <div id="calculation_result" class="calculation-result" style="display:none;">
                        <h3>Calorie Calculation</h3>
                        <div id="calc_results"></div>
                    </div>

                    <input type="hidden" name="calorie_burned" id="calorie_burned">
                    <button type="button" name="record_exercise" style="display:none;" id="record_btn">Record Exercise</button>
                </form>
            </div>

            <div class="exercise-info" id="exercise_info">
                <h3>Exercise Information</h3>
                <div class="info-row"><span>Name:</span><span id="exr_name">-</span></div>
                <div class="info-row"><span>Muscle Group:</span><span id="exr_muscle">-</span></div>
                <div class="info-row"><span>Benefits:</span><span id="exr_benefit">-</span></div>
                <div class="info-row"><span>Calories/rep/kg:</span><span id="exr_calorie">-</span></div>
                <div class="info-row"><span>Your Weight:</span><span id="user_weight"><?= $patient_weight ?> kg</span></div>
            </div>
        </div>

        <div class="exercise-log">
            <h3>Today's Exercises</h3>
            <table id="exercise_log">
                <thead>
                    <tr>
                        <th>Exercise</th>
                        <th>Muscle Group</th>
                        <th>Reps x Sets</th>
                        <th>Calories</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="exercise_log_body">
                    <!-- Filled by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <div class="steps-section">
        <h2>Step Count</h2>
        <div class="flex-container">
            <div class="form-area">
                <form id="steps-form">
                    <label for="steps">Steps Today</label>
                    <input type="number" id="steps" name="steps" min="0" value="0">
                    
                    <label for="goal">Daily Goal</label>
                    <input type="number" id="goal" name="goal" min="1" value="10000">
                    
                    <button type="button" id="save_steps_btn">Save Step Count</button>
                </form>
            </div>
            
            <div class="steps-progress">
                <div class="progress-container">
                    <div class="progress-bar" id="progress_bar"></div>
                </div>
                <div class="steps-info">
                    <span id="steps_count">0</span> / <span id="steps_goal">10000</span> steps
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.exercise-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.exercise-section, .steps-section {
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

.exercise-info, .steps-progress {
    flex: 1;
    background: #f0f8ff;
    padding: 15px;
    border-radius: 8px;
}

.form-row {
    display: flex;
    gap: 10px;
}

.form-group {
    flex: 1;
}

#suggestionBox { 
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

.exercise-log {
    margin-top: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
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

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding: 5px 0;
    border-bottom: 1px solid #e0e0e0;
}

.info-row span:first-child {
    font-weight: bold;
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

/* Steps progress */
.progress-container {
    width: 100%;
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    margin: 15px 0;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: #4CAF50;
    width: 0%;
    transition: width 0.3s ease;
}

.steps-info {
    text-align: center;
    font-size: 18px;
    font-weight: bold;
}

.steps-info span:first-child {
    color: #4CAF50;
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set up exercise image carousel
    const exerciseImages = document.querySelectorAll('#exercise-carousel img');
    let currentImage = 0;
    
    function rotateCarousel() {
        exerciseImages[currentImage].classList.remove('active');
        currentImage = (currentImage + 1) % exerciseImages.length;
        exerciseImages[currentImage].classList.add('active');
    }
    
    setInterval(rotateCarousel, 3000);

    // Exercise functionality
    const exerciseInput = document.getElementById('exercise_search');
    const exerciseIdInput = document.getElementById('exercise_id');
    const suggestionBox = document.getElementById('suggestionBox');
    const repetitionsInput = document.getElementById('repetitions');
    const setsInput = document.getElementById('sets');
    const calculateBtn = document.getElementById('calculate_btn');
    const recordBtn = document.getElementById('record_btn');
    const calorieBurnedInput = document.getElementById('calorie_burned');
    const calculationResult = document.getElementById('calculation_result');
    const calcResults = document.getElementById('calc_results');
    
    let currentSelectedExercise = null;
    const patientWeight = <?= $patient_weight ?>;

    // Steps functionality
    const stepsInput = document.getElementById('steps');
    const goalInput = document.getElementById('goal');
    const saveStepsBtn = document.getElementById('save_steps_btn');
    const progressBar = document.getElementById('progress_bar');
    const stepsCount = document.getElementById('steps_count');
    const stepsGoal = document.getElementById('steps_goal');

    // Load today's exercises and steps
    loadTodayExercises();
    loadTodaySteps();

    // Exercise search
    exerciseInput.addEventListener('input', function() {
        const query = this.value.trim();
        exerciseIdInput.value = '';
        currentSelectedExercise = null;
        calculationResult.style.display = 'none';
        recordBtn.style.display = 'none';
        calorieBurnedInput.value = '';
        calcResults.innerHTML = '';

        if (query.length < 2) {
            suggestionBox.style.display = 'none';
            resetExerciseInfo();
            return;
        }

        fetch(`patient_exroutine.php?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(exercises => {
                suggestionBox.innerHTML = '';
                if(exercises.length === 0) {
                    suggestionBox.style.display = 'none';
                    return;
                }
                
                exercises.forEach(exercise => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.textContent = exercise.name;
                    div.onclick = () => {
                        exerciseInput.value = exercise.name;
                        exerciseIdInput.value = exercise.id;
                        currentSelectedExercise = exercise;
                        updateExerciseInfo(exercise);
                        suggestionBox.style.display = 'none';
                    };
                    suggestionBox.appendChild(div);
                });
                
                const rect = exerciseInput.getBoundingClientRect();
                suggestionBox.style.top = (rect.bottom + window.scrollY) + 'px';
                suggestionBox.style.left = (rect.left + window.scrollX) + 'px';
                suggestionBox.style.width = exerciseInput.offsetWidth + 'px';
                suggestionBox.style.display = 'block';
            });
    });

    // Calculate calories
    calculateBtn.addEventListener('click', function() {
        if (!currentSelectedExercise) {
            alert('Please select an exercise first');
            return;
        }
        
        const repetitions = parseInt(repetitionsInput.value);
        const sets = parseInt(setsInput.value);
        
        if (isNaN(repetitions) || repetitions <= 0 || isNaN(sets) || sets <= 0) {
            alert('Please enter valid repetitions and sets');
            return;
        }

        const exercise = currentSelectedExercise;
        const totalReps = repetitions * sets;
        const calories = exercise.calorie_burn_per_rep * totalReps * patientWeight;
        calorieBurnedInput.value = calories.toFixed(2);

        calcResults.innerHTML = `
            <p><strong>Total for ${sets} sets of ${repetitions} reps:</strong></p>
            <p>Calories Burned: ${calories.toFixed(2)} kcal</p>
            <p>Calculation: ${exercise.calorie_burn_per_rep} kcal/rep/kg × ${totalReps} reps × ${patientWeight} kg</p>
        `;
        
        calculationResult.style.display = 'block';
        recordBtn.style.display = 'inline-block';
    });

    // Record exercise
    recordBtn.addEventListener('click', function() {
        if (!currentSelectedExercise) {
            alert('Please select an exercise first');
            return;
        }
        
        const formData = new FormData();
        formData.append('record_exercise', '1');
        formData.append('exercise_id', exerciseIdInput.value);
        formData.append('repetitions', repetitionsInput.value);
        formData.append('sets', setsInput.value);
        formData.append('calorie_burned', calorieBurnedInput.value);
        
        fetch('patient_exroutine.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if(res.ok) {
                loadTodayExercises();
                exerciseInput.value = '';
                exerciseIdInput.value = '';
                currentSelectedExercise = null;
                calculationResult.style.display = 'none';
                recordBtn.style.display = 'none';
                resetExerciseInfo();
                window.location.href = 'patient_home.php?page=patient_exroutine&success=1';
            }
        });
    });

    // Save steps
    saveStepsBtn.addEventListener('click', function() {
        const steps = parseInt(stepsInput.value);
        const goal = parseInt(goalInput.value);
        
        if (isNaN(steps) || steps < 0 || isNaN(goal) || goal <= 0) {
            alert('Please enter valid step count and goal');
            return;
        }

        const formData = new FormData();
        formData.append('record_steps', '1');
        formData.append('steps', steps);
        formData.append('goal', goal);
        
        fetch('patient_exroutine.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if(res.ok) {
                loadTodaySteps();
                window.location.href = 'patient_home.php?page=patient_exroutine&steps_success=1';
            }
        });
    });

    // Helper functions
    function resetExerciseInfo() {
        document.getElementById('exr_name').textContent = '-';
        document.getElementById('exr_muscle').textContent = '-';
        document.getElementById('exr_benefit').textContent = '-';
        document.getElementById('exr_calorie').textContent = '-';
    }

    function updateExerciseInfo(exercise) {
        document.getElementById('exr_name').textContent = exercise.name;
        document.getElementById('exr_muscle').textContent = exercise.muscle_group || '-';
        document.getElementById('exr_benefit').textContent = exercise.benefit || '-';
        document.getElementById('exr_calorie').textContent = exercise.calorie_burn_per_rep;
    }

    function loadTodayExercises() {
        fetch('patient_exroutine.php?get_today_exercises=1')
            .then(res => res.json())
            .then(exercises => {
                const tbody = document.getElementById('exercise_log_body');
                tbody.innerHTML = '';
                
                if(exercises.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="5" style="text-align:center;">No exercises recorded today</td>';
                    tbody.appendChild(row);
                    return;
                }
                
                exercises.forEach(exercise => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${exercise.name}</td>
                        <td>${exercise.muscle_group}</td>
                        <td>${exercise.repetitions} × ${exercise.sets}</td>
                        <td>${exercise.calorie_burned} kcal</td>
                        <td><span class="remove-exercise" onclick="removeExercise(${exercise.id})">×</span></td>
                    `;
                    tbody.appendChild(row);
                });
            });
    }

    function loadTodaySteps() {
        fetch('patient_exroutine.php?get_today_steps=1')
            .then(res => res.json())
            .then(data => {
                stepsInput.value = data.steps;
                goalInput.value = data.goal;
                stepsCount.textContent = data.steps;
                stepsGoal.textContent = data.goal;
                
                const percentage = Math.min(100, (data.steps / data.goal) * 100);
                progressBar.style.width = `${percentage}%`;
            });
    }

    // Global functions for HTML onclick
    window.removeExercise = function(id) {
        if(!confirm('Remove this exercise from log?')) return;
        
        fetch('patient_exroutine.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `remove_exercise=1&id=${id}`
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                loadTodayExercises();
            }
        });
    };
});
</script>