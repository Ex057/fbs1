<?php
session_start();
require 'connect.php';

global $pdo; // Use the PDO connection from connect.php

$error = ""; // Variable to store error messages

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(htmlspecialchars($_POST['username']));
    $password = trim($_POST['password']);
    $email = trim(htmlspecialchars($_POST['email']));

    // Check if the username already exists
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        $error = "Username already exists. Please choose another one.";
    } else {
        // Hash the password securely
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new admin user into the database
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
        $success = $stmt->execute([$username, $hashed_password, $email]);

        if ($success) {
            header("Location: login.php");
            exit();
        } else {
            $error = "Error: Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration</title>
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
        .register-form {
            background: #fff;
            border-radius: 0.375rem;
            box-shadow: 0 0 2rem 0 rgba(136, 152, 170, 0.15);
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(87deg, #e3e8e8 0, #b4acc8 100%);
            padding: 1.5rem;
            text-align: center;
            color: white;
        }
        .register-header h2 {
            margin-bottom: 0;
            font-weight: 600;
        }
        .form-container {
            padding: 2rem;
        }
        .input-group {
            margin-bottom: 1rem;
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
        .password-error {
            color: #f5365c;
            font-size: 0.75rem;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-form">
            <div class="register-header">
                <h2>Create Account</h2>
            </div>
            <div class="form-container">
                <?php if (!empty($error)) echo "<p class='error-message'><i class='fas fa-exclamation-circle mr-2'></i>$error</p>"; ?>
                <form id="registrationForm" method="POST" action="">
                    <!-- Username Field -->
                    <div class="form-group">
                        <div class="input-group input-group-alternative mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input class="form-control" type="text" name="username" placeholder="Username" required>
                        </div>
                    </div>

                    <!-- Email Field -->
                    <div class="form-group">
                        <div class="input-group input-group-alternative mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            </div>
                            <input class="form-control" type="email" name="email" placeholder="Email" required>
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <div class="input-group input-group-alternative mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            </div>
                            <input class="form-control" type="password" id="password" name="password" placeholder="Password" required>
                        </div>
                    </div>

                    <!-- Confirm Password Field -->
                    <div class="form-group">
                        <div class="input-group input-group-alternative mb-3">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            </div>
                            <input class="form-control" type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" required>
                        </div>
                        <div class="password-error" id="passwordError">
                            <i class="fas fa-exclamation-circle mr-1"></i>Passwords do not match
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-block">Register</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <p class="text-muted">Already have an account? <a href="login" class="nav-link d-inline">Login here</a></p>
                </div>
                 <div class="text-center mt-3">
                    <a href="/../fbs.php" class="btn btn-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const passwordError = document.getElementById('passwordError');
            
            if (password !== confirmPassword) {
                e.preventDefault();
                passwordError.style.display = 'block';
                document.getElementById('password').value = '';
                document.getElementById('confirmPassword').value = '';
                document.getElementById('password').focus();
            } else {
                passwordError.style.display = 'none';
            }
        });

        // Also validate on password field changes
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const passwordError = document.getElementById('passwordError');
            
            if (password !== confirmPassword && confirmPassword.length > 0) {
                passwordError.style.display = 'block';
            } else {
                passwordError.style.display = 'none';
            }
        });
    </script>
</body>
</html>