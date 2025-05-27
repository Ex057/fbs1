<?php
session_start();
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Fetch admin user from the database
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);



    

    if ($admin && password_verify($password, $admin['password'])) {
        // Login successful
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $admin['username'];
        header("Location: main");
        exit();
    } else {
        // Login failed
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <!-- Argon Dashboard CSS -->
    <link href="argon-dashboard-master/assets/css/argon-dashboard.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #d2e4e5 !important; /* Soft light gray-blue, gentle on eyes */
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-form {
            background: #fff;
            border-radius: 0.375rem;
            box-shadow: 0 0 2rem 0 rgba(136, 152, 170, 0.15);
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(87deg, #e3e8e8 0, #b4acc8 100%);
            padding: 1.5rem;
            text-align: center;
            color: white;
        }
        .login-header h2 {
            margin-bottom: 0;
            font-weight: 600;
        }
        .form-container {
            padding: 2rem;
        }
        .input-group {
            margin-bottom: 1.5rem;
        }
        .form-control {
            padding-left: 2.5rem;
            height: auto;
            border-radius: 0.25rem;
        }
        .btn-primary {
            background: #5e72e4;
            border: none;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .btn-primary:hover {
            background: #4a5acf;
        }
        .text-muted {
            color: #8898aa !important;
            font-size: 0.875rem;
        }
        .nav-link {
            color: #5e72e4;
            font-weight: 600;
        }
        .error-message {
            color: #f5365c;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-form">
            <div class="login-header">
                <h2>Admin Login</h2>
            </div>
            <div class="form-container">
                <?php if (isset($error)): ?>
                    <p class="error-message"><i class="fas fa-exclamation-circle mr-2"></i><?= $error ?></p>
                <?php endif; ?>
                <form method="POST" action="">
                    <!-- Username Field -->
                    <div class="form-group">
                        <div class="input-group input-group-alternative mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input class="form-control" type="text" name="username" placeholder="Username" required>
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <div class="input-group input-group-alternative mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            </div>
                            <input class="form-control" type="password" name="password" placeholder="Password" required>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-block">Login</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <a href="/../fbs.php" class="btn btn-secondary">Back</a>
                </div>
                
                <!-- Uncomment if you want to show register link -->
                <!-- <div class="text-center mt-3">
                    <p class="text-muted">Don't have an account? <a href="register" class="nav-link d-inline">Register here</a></p>
                </div> -->
            </div>
        </div>
    </div>
</body>
</html>