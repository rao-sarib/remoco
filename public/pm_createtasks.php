<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';

// Check if user is logged in and is a Project Manager
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'employee' || $_SESSION['designation'] !== 'Project Manager') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/config.php';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Pick up the flash messages left by the task-creation handler in
// pm_dashboard.php and clear them, so a validation failure or a successful
// creation is actually reported back to the user on this panel.
$errors  = $_SESSION['form_errors'] ?? [];
$success = $_SESSION['form_success'] ?? '';
unset($_SESSION['form_errors'], $_SESSION['form_success']);

// Fetch team leads for dropdown
$team_leads = [];
try {
    $stmt = $pdo->prepare("SELECT employee_id, employee_name 
                           FROM employees 
                           WHERE designation = 'Team Lead' 
                           AND company_id = :company_id");
    $stmt->bindParam(':company_id', $_SESSION['company_id'], PDO::PARAM_INT);
    $stmt->execute();
    $team_leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching team leads: " . $e->getMessage());
    $errors[] = "Error fetching team leads. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --accent-color: #36b9cc;
            --light-bg: #f8f9fc;
            --dark-bg: #2e4374;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Nunito', sans-serif;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border: none;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.25rem 1.5rem;
            font-weight: 700;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #d1d3e2;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .task-form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 8px;
        }
        
        .required:after {
            content: " *";
            color: #e74a3b;
        }
    </style>
</head>
<body>
    <div class="task-form-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800"><i class="fas fa-tasks me-2"></i>Create New Task</h1>
        </div>
        
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Error!</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-white">Task Details</h6>
            </div>
            <div class="card-body">
                <!-- FIXED: Form action points to dashboard with page parameter -->
                <form id="createTaskForm" method="POST" action="pm_dashboard.php?page=pm_createtasks">
                        <?php echo csrf_field(); ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="title" class="form-label required">Task Title</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   maxlength="150" required placeholder="Enter task title">
                            <div class="form-text text-end"><span id="charCount">0</span>/150 characters</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Task Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="4" placeholder="Enter task details"></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="due_date" class="form-label required">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="priority" class="form-label required">Priority</label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="" disabled selected>Select priority</option>
                                <option value="High">High</option>
                                <option value="Medium">Medium</option>
                                <option value="Low">Low</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label for="team_lead_id" class="form-label required">Assigned to Team Lead</label>
                            <select class="form-select" id="team_lead_id" name="team_lead_id" required>
                                <option value="" disabled selected>Select team lead</option>
                                <?php foreach ($team_leads as $lead): ?>
                                    <option value="<?php echo $lead['employee_id']; ?>">
                                        <?php echo $lead['employee_id'] . ' - ' . htmlspecialchars($lead['employee_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i>Create Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Character count for task title
            $('#title').on('input', function() {
                const length = $(this).val().length;
                $('#charCount').text(length);
                if (length > 140) {
                    $('#charCount').css('color', 'red');
                } else {
                    $('#charCount').css('color', 'inherit');
                }
            });
            
            // Set minimum date to today for due date
            const today = new Date().toISOString().split('T')[0];
            $('#due_date').attr('min', today);
            
            // Form submission handler for AJAX
            $('#createTaskForm').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const formData = form.serialize();
                
                // Show loading state on button
                const submitBtn = form.find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Creating...');
                
                $.ajax({
                    type: 'POST',
                    url: form.attr('action'),
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success message and reset form
                            $('.alert').remove();
                            form.before('<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> ' + response.message + '</div>');
                            form[0].reset();
                            $('#charCount').text('0');
                            
                            // Optional: Redirect or refresh task list
                        } else {
                            // Show errors
                            let errorHtml = '<div class="alert alert-danger"><strong>Error!</strong><ul>';
                            response.errors.forEach(error => {
                                errorHtml += '<li>' + error + '</li>';
                            });
                            errorHtml += '</ul></div>';
                            $('.alert').remove();
                            form.before(errorHtml);
                        }
                    },
                    error: function() {
                        $('.alert').remove();
                        form.before('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> An error occurred while creating the task. Please try again.</div>');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html('<i class="fas fa-plus-circle me-2"></i>Create Task');
                    }
                });
            });
        });
    </script>
</body>
</html>