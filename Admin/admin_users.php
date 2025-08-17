<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../loginPC.html");
    exit();
}

require_once '../dbPC.php';

// Initialize variables
$users = [];
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$edit_user = null;

// Handle user deletion
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success_message'] = "User deleted successfully!";
        header("Location: admin_users.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
        header("Location: admin_users.php");
        exit();
    }
}

// Handle user edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $role = $_POST['role'];
    
    try {
        $stmt = $conn->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, role = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $phone, $role, $user_id]);
        $_SESSION['success_message'] = "User updated successfully!";
        header("Location: admin_users.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating user: " . $e->getMessage();
        header("Location: admin_users.php");
        exit();
    }
}

// Handle new user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $role = $_POST['role'];
    $password = password_hash('Password123', PASSWORD_DEFAULT); // Default password
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, phone, dob, gender, role, username, password, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $username = strtolower(str_replace(' ', '', $name)) . rand(100, 999);
        $stmt->execute([$name, $email, $phone, $dob, $gender, $role, $username, $password]);
        $_SESSION['success_message'] = "User created successfully!";
        header("Location: admin_users.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error creating user: " . $e->getMessage();
        header("Location: admin_users.php");
        exit();
    }
}

// Fetch users with filters
try {
    $query = "SELECT * FROM users WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    
    if (!empty($role_filter)) {
        $query .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching users: " . $e->getMessage();
}

// Fetch specific user for editing
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error fetching user: " . $e->getMessage();
    }
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
    <title>Admin User Management - Personal Care</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e7ff;
            flex-wrap: wrap;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .welcome {
            font-size: 18px;
            color: #5c7cfa;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            flex-grow: 1;
        }
        
        .btn {
            background: #5c7cfa;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
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
        
        .btn-danger {
            background: #f03e3e;
        }
        
        .btn-danger:hover {
            background: #c92a2a;
        }
        
        .search-box {
            position: relative;
            min-width: 200px;
            flex-grow: 1;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 2px solid #dbe4ff;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            border-color: #5c7cfa;
            outline: none;
            box-shadow: 0 0 0 3px rgba(92, 124, 250, 0.2);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #748ffc;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        select {
            padding: 10px 15px;
            border: 2px solid #dbe4ff;
            border-radius: 8px;
            font-size: 15px;
            background: white;
            cursor: pointer;
        }
        
        select:focus {
            border-color: #5c7cfa;
            outline: none;
            box-shadow: 0 0 0 3px rgba(92, 124, 250, 0.2);
        }
        
        .user-table {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #f1f3f9;
        }
        
        th {
            background-color: #f8f9ff;
            color: #5c7cfa;
            font-weight: 600;
            font-size: 15px;
        }
        
        tr:hover {
            background-color: #f8f9ff;
        }
        
        .role {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            background: #e7f5ff;
            color: #1971c2;
            display: inline-block;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            background: #f1f3f9;
            color: #495057;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .action-edit:hover {
            background: #d0ebff;
            color: #1971c2;
        }
        
        .action-delete:hover {
            background: #ffe3e3;
            color: #c92a2a;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .modal {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            width: 90%;
            max-width: 600px;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        
        .modal-overlay.active .modal {
            transform: translateY(0);
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f3f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 20px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #adb5bd;
            transition: color 0.3s ease;
        }
        
        .close-btn:hover {
            color: #f03e3e;
        }
        
        .modal-body {
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
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dbe4ff;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus {
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
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #f1f3f9;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
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
        
        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #868e96;
        }
        
        .no-results i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ced4da;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            padding: 20px;
            gap: 10px;
        }
        
        .pagination-btn {
            padding: 8px 15px;
            border: 1px solid #dbe4ff;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pagination-btn:hover {
            background: #f8f9ff;
        }
        
        .pagination-btn.active {
            background: #5c7cfa;
            color: white;
            border-color: #5c7cfa;
        }
        
        @media (max-width: 768px) {
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .filters {
                width: 100%;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .filter-group {
                flex: 1;
                min-width: 45%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users-cog"></i> User Management</h1>
            <div class="welcome">
                <i class="fas fa-user-shield"></i> Welcome, Administrator
            </div>
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

        <div class="actions">
            <div class="filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="filter-group">
                    <label>Role:</label>
                    <select id="role-filter">
                        <option value="">All Roles</option>
                        <option value="patient" <?= $role_filter === 'patient' ? 'selected' : '' ?>>Patient</option>
                        <option value="doctor" <?= $role_filter === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                        <option value="nutritionist" <?= $role_filter === 'nutritionist' ? 'selected' : '' ?>>Nutritionist</option>
                        <option value="trainer" <?= $role_filter === 'trainer' ? 'selected' : '' ?>>Trainer</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
            </div>
            
            <button class="btn btn-success" id="create-user-btn">
                <i class="fas fa-user-plus"></i> Create User
            </button>
        </div>

        <div class="user-table">
            <?php if (count($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($user['name']) ?></div>
                                    <div style="font-size: 14px; color: #868e96;">@<?= htmlspecialchars($user['username']) ?></div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($user['email']) ?></div>
                                    <div style="font-size: 14px; color: #495057;"><?= htmlspecialchars($user['phone']) ?></div>
                                </td>
                                <td>
                                    <span class="role"><?= ucfirst($user['role']) ?></span>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit_id=<?= $user['id'] ?>" class="action-btn action-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="?delete_id=<?= $user['id'] ?>" class="action-btn action-delete" onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="pagination">
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">Next</button>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-user-slash"></i>
                    <h3>No users found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal-overlay <?= $edit_user ? 'active' : '' ?>" id="edit-modal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Edit User</div>
                <button class="close-btn" id="close-edit-modal">&times;</button>
            </div>
            <?php if ($edit_user): ?>
                <form method="POST">
                    <input type="hidden" name="edit_user" value="1">
                    <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
                    
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit-name">Full Name</label>
                                <input type="text" id="edit-name" name="name" value="<?= htmlspecialchars($edit_user['name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-email">Email</label>
                                <input type="email" id="edit-email" name="email" value="<?= htmlspecialchars($edit_user['email']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit-phone">Phone</label>
                                <input type="tel" id="edit-phone" name="phone" value="<?= htmlspecialchars($edit_user['phone']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-dob">Date of Birth</label>
                                <input type="date" id="edit-dob" name="dob" value="<?= htmlspecialchars($edit_user['dob']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit-gender">Gender</label>
                                <select id="edit-gender" name="gender" required>
                                    <option value="Male" <?= $edit_user['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $edit_user['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="edit-role">Role</label>
                                <select id="edit-role" name="role" required>
                                    <option value="patient" <?= $edit_user['role'] === 'patient' ? 'selected' : '' ?>>Patient</option>
                                    <option value="doctor" <?= $edit_user['role'] === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                                    <option value="nutritionist" <?= $edit_user['role'] === 'nutritionist' ? 'selected' : '' ?>>Nutritionist</option>
                                    <option value="trainer" <?= $edit_user['role'] === 'trainer' ? 'selected' : '' ?>>Trainer</option>
                                    <option value="admin" <?= $edit_user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn" id="cancel-edit-btn">Cancel</button>
                        <button type="submit" class="btn btn-success">Update User</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div class="modal-overlay" id="create-modal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Create New User</div>
                <button class="close-btn" id="close-create-modal">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="create_user" value="1">
                
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create-name">Full Name</label>
                            <input type="text" id="create-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="create-email">Email</label>
                            <input type="email" id="create-email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create-phone">Phone</label>
                            <input type="tel" id="create-phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label for="create-dob">Date of Birth</label>
                            <input type="date" id="create-dob" name="dob" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create-gender">Gender</label>
                            <select id="create-gender" name="gender" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create-role">Role</label>
                            <select id="create-role" name="role" required>
                                <option value="patient">Patient</option>
                                <option value="doctor">Doctor</option>
                                <option value="nutritionist">Nutritionist</option>
                                <option value="trainer">Trainer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" id="cancel-create-btn">Cancel</button>
                    <button type="submit" class="btn btn-success">Create User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Filter functionality
        const searchInput = document.getElementById('search');
        const roleFilter = document.getElementById('role-filter');
        
        function applyFilters() {
            const params = new URLSearchParams();
            
            if (searchInput.value) params.append('search', searchInput.value);
            if (roleFilter.value) params.append('role', roleFilter.value);
            
            window.location.href = `admin_users.php?${params.toString()}`;
        }
        
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') applyFilters();
        });
        
        roleFilter.addEventListener('change', applyFilters);
        
        // Modal functionality
        const editModal = document.getElementById('edit-modal');
        const createModal = document.getElementById('create-modal');
        const createUserBtn = document.getElementById('create-user-btn');
        
        // Close modals
        document.getElementById('close-edit-modal').addEventListener('click', () => {
            editModal.classList.remove('active');
            window.location.href = 'admin_users.php';
        });
        
        document.getElementById('close-create-modal').addEventListener('click', () => {
            createModal.classList.remove('active');
        });
        
        document.getElementById('cancel-edit-btn').addEventListener('click', () => {
            editModal.classList.remove('active');
            window.location.href = 'admin_users.php';
        });
        
        document.getElementById('cancel-create-btn').addEventListener('click', () => {
            createModal.classList.remove('active');
        });
        
        // Open create modal
        createUserBtn.addEventListener('click', () => {
            createModal.classList.add('active');
        });
        
        // Close modals when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                    if (overlay === editModal) {
                        window.location.href = 'admin_users.php';
                    }
                }
            });
        });
    </script>
</body>
</html>