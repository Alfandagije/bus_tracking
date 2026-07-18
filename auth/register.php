<?php session_start(); if (isset($_SESSION['user_id'])) { header('Location: /index.php'); exit; } require_once __DIR__ . '/../config/helpers.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Smart Bus Tracking</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?= icon('bus') ?> Smart Bus</h1>
                <p>Create your account</p>
            </div>
            <form id="registerForm" class="auth-form">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required placeholder="Alfa Manzi">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="alfa@example.com">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" required placeholder="+2507XXXXXXXX">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="6" placeholder="Min 6 characters">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </form>
            <div id="message" class="message"></div>
            <p class="auth-link">Already have an account? <a href="login.php">Login</a></p>
        </div>
    </div>
    <script>
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button');
        btn.disabled = true;
        btn.textContent = 'Registering...';
        
        const data = Object.fromEntries(new FormData(form));
        try {
            const res = await fetch('../api/register.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });
            const result = await res.json();
            const msg = document.getElementById('message');
            msg.className = 'message ' + (result.status === 'success' ? 'success' : 'error');
            msg.textContent = result.message;
            if (result.status === 'success') {
                setTimeout(() => window.location.href = '../index.php', 1000);
            }
        } catch (err) {
            document.getElementById('message').className = 'message error';
            document.getElementById('message').textContent = 'Network error';
        }
        btn.disabled = false;
        btn.textContent = 'Register';
    });
    </script>
</body>
</html>
