<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../loginPC.html");
    exit();
}

require_once '../dbPC.php';

// Get user ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No user ID provided";
    header("Location: admin_home.php?page=users");
    exit();
}

$user_id = $_GET['id'];
$user = null;

// Handle user edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    $role = $_POST['role'];
    
    try {
        $stmt = $conn->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, dob = ?, gender = ?, address = ?, role = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $phone, $dob, $gender, $address, $role, $user_id]);
        $_SESSION['success_message'] = "User updated successfully!";
        header("Location: admin_home.php?page=users");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating user: " . $e->getMessage();
    }
}

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error_message'] = "User not found";
        header("Location: admin_home.php?page=users");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching user: " . $e->getMessage();
    header("Location: admin_home.php?page=users");
    exit();
}

// Get success/error messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Personal Care Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f0f7ff;
            color: #333;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e7ff;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        h1 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
            flex-grow: 1;
        }
        
        .user-info {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5c7cfa, #748ffc);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
        }
        
        .user-details h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 18px;
        }
        
        .user-details p {
            margin: 5px 0 0 0;
            color: #6c757d;
            font-size: 14px;
        }
        
        .edit-form {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .form-header {
            background: #f8f9ff;
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-header h2 {
            color: #5c7cfa;
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-body {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dbe4ff;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #5c7cfa;
            outline: none;
            box-shadow: 0 0 0 3px rgba(92, 124, 250, 0.2);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .btn {
            background: #5c7cfa;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn:hover {
            background: #3b5bdb;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #37b24d;
        }
        
        .btn-success:hover {
            background: #2b8a3e;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .form-footer {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .message-success {
            background: #d3f9d8;
            color: #2b8a3e;
            border-left: 4px solid #2b8a3e;
        }
        
        .message-error {
            background: #ffe3e3;
            color: #c92a2a;
            border-left: 4px solid #c92a2a;
        }
        
        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            background: #e7f5ff;
            color: #1971c2;
            display: inline-block;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f3f9;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            color: #2c3e50;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .form-footer {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <!-- Updated back button to go to admin_home.php with users page -->
            <a href="admin_home.php?page=users" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            <h1><i class="fas fa-user-edit"></i> Edit User</h1>
        </div>

        <?php if ($success_message): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i>
                <div><?= $success_message ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= $error_message ?></div>
            </div>
        <?php endif; ?>

        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div class="user-details">
                <h3><?= htmlspecialchars($user['name']) ?></h3>
                <p>@<?= htmlspecialchars($user['username']) ?> • <span class="role-badge"><?= ucfirst($user['role']) ?></span></p>
                <p>Member since <?= date('M d, Y', strtotime($user['created_at'])) ?></p>
            </div>
        </div>

        <div class="edit-form">
            <div class="form-header">
                <h2><i class="fas fa-edit"></i> User Information</h2>
            </div>
            
            <form method="POST">
                <input type="hidden" name="edit_user" value="1">
                
                <div class="form-body">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user"></i> Personal Information
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="dob">Date of Birth *</label>
                                <input type="date" id="dob" name="dob" value="<?= htmlspecialchars($user['dob']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" placeholder="Enter full address"><?= htmlspecialchars($user['address']) ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-cog"></i> Account Settings
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="gender">Gender *</label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?= $user['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $user['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="role">Role *</label>
                                <select id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="patient" <?= $user['role'] === 'patient' ? 'selected' : '' ?>>Patient</option>
                                    <option value="doctor" <?= $user['role'] === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                                    <option value="nutritionist" <?= $user['role'] === 'nutritionist' ? 'selected' : '' ?>>Nutritionist</option>
                                    <option value="trainer" <?= $user['role'] === 'trainer' ? 'selected' : '' ?>>Trainer</option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Username (Read-only)</label>
                            <input type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly style="background-color: #f8f9fa; color: #6c757d;">
                        </div>
                    </div>
                </div>
                
                <div class="form-footer">
                    <!-- Updated cancel button to go to admin_home.php with users page -->
                    <a href="admin_home.php?page=users" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['name', 'email', 'phone', 'dob', 'gender', 'role'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#f03e3e';
                } else {
                    input.style.borderColor = '#dbe4ff';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
        
        // Email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#f03e3e';
                alert('Please enter a valid email address');
            } else {
                this.style.borderColor = '#dbe4ff';
            }
        });
        
        // Phone validation
        document.getElementById('phone').addEventListener('input', function() {
            // Remove non-numeric characters except + and spaces
            this.value = this.value.replace(/[^+\d\s-]/g, '');
        });
    </script>
</body>
</html>