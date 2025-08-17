<?php
// Note: This file is designed to be included by patient_home.php
// It assumes the session and database connection are already handled.
if (!isset($conn)) {
    require_once '../dbPC.php'; // Adjust path if needed
}

// Get the current patient's user ID from the session
$patient_id = $_SESSION['user_id'];
$search_query = $_GET['search'] ?? '';

// Base query to fetch prescriptions for the logged-in patient
$sql = "SELECT
            p.id AS prescription_id,
            p.prescribed_at,
            u.name AS doctor_name,
            a.symptoms
        FROM
            prescriptions p
        JOIN
            appointments a ON p.appointment_id = a.id
        JOIN
            users u ON a.doctor_id = u.id
        WHERE
            a.patient_id = ?";

// Add search filter condition if a search query is provided
if (!empty($search_query)) {
    $sql .= " AND (u.name LIKE ? OR a.symptoms LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $stmt = $conn->prepare($sql . " ORDER BY p.prescribed_at DESC");
    $stmt->execute([$patient_id, $search_param, $search_param]);
} else {
    $stmt = $conn->prepare($sql . " ORDER BY p.prescribed_at DESC");
    $stmt->execute([$patient_id]);
}

$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medical Schedule</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing styles... */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .page-header h1 {
            color: #059669;
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-container input {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .search-container input:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        .search-container button {
            background: #059669;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .search-container button:hover {
            background: #047857;
        }
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .prescription-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .prescription-table thead {
            background: #f1f5f9;
            color: #1e293b;
            text-align: left;
        }
        .prescription-table th, .prescription-table td {
            padding: 18px 25px;
            border-bottom: 1px solid #e2e8f0;
        }
        .prescription-table th {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .prescription-table tbody tr:last-child td {
            border-bottom: none;
        }
        .prescription-table tbody tr:hover {
            background-color: #f8fafc;
        }
        .no-records {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        .btn-report {
            background: #059669;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-report:hover {
            background: #047857;
        }
    </style>
</head>
<body>

<div class="main-content-container">
    <div class="page-header">
        <h1><i class="fas fa-notes-medical"></i> My Prescriptions</h1>
        <form action="" method="GET" class="search-container">
            <input type="hidden" name="page" value="prescriptions">
            <input type="text" name="search" placeholder="Search by doctor or symptoms..." value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="table-container">
        <?php if (!empty($prescriptions)): ?>
            <table class="prescription-table">
                <thead>
                    <tr>
                        <th>Doctor Name</th>
                        <th>Symptoms</th>
                        <th>Date Prescribed</th>
                        <th>Report</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $prescription): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($prescription['symptoms']); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($prescription['prescribed_at']))); ?></td>
                            <td>
                                <a href="generate_prescription_report.php?id=<?php echo $prescription['prescription_id']; ?>" 
                                   class="btn-report" target="_blank">
                                    <i class="fas fa-file-alt"></i> Show Report
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-records">
                <p><i class="fas fa-info-circle"></i> No prescriptions found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>