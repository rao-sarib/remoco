<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if company is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'company') {
    header('Location: login.php');
    exit;
}

$company_id = $_SESSION['company_id'];
$company_name = $_SESSION['company_name'];

require_once __DIR__ . '/../includes/config.php';

$pdo = null;

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error. Please try again later.");
}

// Initialize variables
$message = '';
$message_type = '';

// Process delete request
if (isset($_GET['delete_id'])) {
    // Triggered by a link, so the token travels in the query string.
    csrf_require_page();

    $delete_id = (int)$_GET['delete_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ? AND company_id = ?");
        $stmt->execute([$delete_id, $company_id]);
        
        if ($stmt->rowCount() > 0) {
            // Set session message and redirect to dashboard
            $_SESSION['message'] = "Employee deleted successfully!";
            $_SESSION['message_type'] = "success";
            header('Location: admin_dashboard.php?page=employees');
            exit;
        } else {
            $message = "Employee not found or you don't have permission";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $message = "Database error. Please try again later.";
        $message_type = "error";
    }
}

// Process update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    csrf_require_page();

    $employee_id = (int)$_POST['employee_id'];
    $designation = trim($_POST['designation']);
    
    if (!empty($designation)) {
        try {
            $stmt = $pdo->prepare("UPDATE employees SET designation = ? WHERE employee_id = ? AND company_id = ?");
            $stmt->execute([$designation, $employee_id, $company_id]);
            
            if ($stmt->rowCount() > 0) {
                // Set session message and redirect to dashboard
                $_SESSION['message'] = "Employee designation updated successfully!";
                $_SESSION['message_type'] = "success";
                header('Location: admin_dashboard.php?page=employees');
                exit;
            } else {
                $message = "No changes made or you don't have permission";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $message = "Database error. Please try again later.";
            $message_type = "error";
        }
    } else {
        $message = "Designation cannot be empty";
        $message_type = "error";
    }
}

// Fetch employees for the current company
$employees = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching employees: " . $e->getMessage());
    $message = "Error fetching employees. Please try again later.";
    $message_type = "error";
}

// Check for session messages from redirects
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<div class="employees-container">
    <h2 class="page-title">Employees</h2>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo ($message_type == 'success') ? 'success-message' : 'error-message'; ?>">
            <i class="fas <?php echo ($message_type == 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>
    
    <div class="table-responsive">
        <?php if (count($employees) > 0): ?>
            <table class="employees-table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>CNIC</th>
                        <th>Email</th>
                        <th>Designation</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($employee['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars($employee['cnic']); ?></td>
                            <td><?php echo htmlspecialchars($employee['email']); ?></td>
                            <td>
                                <form method="POST" action="employees.php" class="designation-form">
                        <?php echo csrf_field(); ?>
                                    <input type="hidden" name="employee_id" value="<?php echo $employee['employee_id']; ?>">
                                    <select name="designation" class="designation-select">
                                        <option value="Project Manager" <?php echo ($employee['designation'] == 'Project Manager') ? 'selected' : ''; ?>>Project Manager</option>
                                        <option value="Team Lead" <?php echo ($employee['designation'] == 'Team Lead') ? 'selected' : ''; ?>>Team Lead</option>
                                        <option value="Team Member" <?php echo ($employee['designation'] == 'Team Member') ? 'selected' : ''; ?>>Team Member</option>
                                        <option value="Guest" <?php echo ($employee['designation'] == 'Guest') ? 'selected' : ''; ?>>Guest</option>
                                    </select>
                                    <button type="submit" name="update" class="action-btn update-btn">
                                        <i class="fas fa-sync-alt"></i> Update
                                    </button>
                                </form>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="employees.php?delete_id=<?php echo $employee['employee_id']; ?>&<?php echo csrf_query(); ?>" 
                                       class="action-btn delete-btn"
                                       onclick="return confirm('Are you sure you want to delete this employee?');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-employees">
                <i class="fas fa-users"></i>
                <h3>No Employees Found</h3>
                <p>You haven't registered any employees yet.</p>
                <a href="admin_dashboard.php?page=employee_registration" class="add-employee-link">
                    <i class="fas fa-user-plus"></i> Register New Employee
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .employees-container {
        padding: 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin: 20px;
    }
    
    .page-title {
        text-align: center;
        margin: 0 0 25px;
        color: #2c3e50;
        font-size: 1.8rem;
        position: relative;
        padding-bottom: 15px;
    }
    
    .page-title::after {
        content: '';
        display: block;
        width: 60px;
        height: 4px;
        background: #3498db;
        margin: 10px auto 0;
        border-radius: 2px;
    }
    
    .message {
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .success-message {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .error-message {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .table-responsive {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    .employees-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }
    
    .employees-table th {
        background: #2c3e50;
        color: white;
        text-align: left;
        padding: 15px 20px;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .employees-table td {
        padding: 14px 20px;
        border-bottom: 1px solid #e0e0e0;
        color: #34495e;
        font-size: 0.95rem;
    }
    
    .employees-table tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    
    .employees-table tr:hover {
        background-color: #f1f8ff;
    }
    
    .designation-select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 0.95rem;
        width: 100%;
        background-color: white;
        min-width: 150px;
        transition: all 0.2s ease;
        margin-bottom: 10px;
    }
    
    .designation-select:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    }
    
    .action-btn {
        padding: 8px 15px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        white-space: nowrap;
    }
    
    .update-btn {
        background: #2ecc71;
        color: white;
        width: 100%;
    }
    
    .update-btn:hover {
        background: #27ae60;
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .delete-btn {
        background: #e74c3c;
        color: white;
    }
    
    .delete-btn:hover {
        background: #c0392b;
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .btn-group {
        display: flex;
        gap: 10px;
    }
    
    .no-employees {
        text-align: center;
        padding: 40px 20px;
        color: #7f8c8d;
        background: #f9f9f9;
        border-radius: 8px;
    }
    
    .no-employees i {
        font-size: 3rem;
        margin-bottom: 15px;
        color: #bdc3c7;
    }
    
    .no-employees h3 {
        font-size: 1.5rem;
        margin-bottom: 10px;
        color: #2c3e50;
    }
    
    .no-employees p {
        margin-bottom: 20px;
        font-size: 1.1rem;
    }
    
    .add-employee-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .add-employee-link:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    /* Responsive design */
    @media (max-width: 1200px) {
        .employees-table {
            min-width: 900px;
        }
    }
    
    @media (max-width: 768px) {
        .btn-group {
            flex-direction: column;
        }
        
        .action-btn {
            width: 100%;
        }
        
        .employees-container {
            margin: 10px;
            padding: 15px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add confirmation for update actions
    const updateButtons = document.querySelectorAll('.update-btn');
    updateButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const form = this.closest('form');
            const currentDesignation = form.querySelector('.designation-select').value;
            const employeeName = form.closest('tr').querySelector('td:nth-child(2)').textContent;
            
            if (!confirm(`Update ${employeeName}'s designation to ${currentDesignation}?`)) {
                e.preventDefault();
            }
        });
    });
    
    // Highlight row on hover
    const tableRows = document.querySelectorAll('.employees-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f1f8ff';
        });
        
        row.addEventListener('mouseleave', function() {
            if (this.rowIndex % 2 === 0) {
                this.style.backgroundColor = '#f8f9fa';
            } else {
                this.style.backgroundColor = 'white';
            }
        });
    });
    
    // Auto-expand table to fill available space
    function adjustTableWidth() {
        const container = document.querySelector('.table-responsive');
        if (container) {
            const availableWidth = container.parentElement.clientWidth;
            container.style.width = Math.max(availableWidth, 1000) + 'px';
        }
    }
    
    // Initial adjustment
    adjustTableWidth();
    
    // Adjust on window resize
    window.addEventListener('resize', adjustTableWidth);
});
</script>