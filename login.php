<?php
session_start();

// Database connection (MySQL)
$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'customer', '1234');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle login
$msg = "";
if (isset($_POST['login'])) {
    $user_account = trim($_POST['login_username']);
    $password = $_POST['login_password'];
    $stmt = $db->prepare("SELECT * FROM users WHERE user_account = ?");
    $stmt->execute([$user_account]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    // 暫時不驗證密碼，只檢查帳號存在
    if ($user) {
        $_SESSION['user'] = $user['user_account']; // 儲存 user_account
        $_SESSION['user_id'] = $user['user_id'];   // 儲存 user_id
        header("Location: dashboard.php");
        exit;
    } else {
        $msg = "帳號不存在。";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .form-signin {
            width: 100%;
            max-width: 400px;
            padding: 15px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background-color: white;
        }
        .form-floating:focus-within {
            z-index: 2;
        }
        .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            padding: 10px;
            font-size: 16px;
        }
        h2 {
            color: #212529;
            font-weight: 600;
        }
        h3 {
            font-size: 1.1rem;
            color: #6c757d;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <main class="form-signin">
        <div class="card p-4">
            <h2 class="mb-4 text-center">記帳管理系統</h2>
            
            <?php if (isset($msg) && $msg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['user'])): ?>
                <p class="mb-3 text-center">歡迎，<?= htmlspecialchars($_SESSION['user']) ?>！</p>
                <a href="?logout=1" class="btn btn-outline-primary">登出</a>
            <?php else: ?>
            <form method="post">
                <h3 class="mb-3 text-center">請輸入帳號密碼</h3>
                
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="login_username" name="login_username" placeholder="電子郵件帳號" required>
                    <label for="login_username">電子郵件帳號</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="login_password" name="login_password" placeholder="密碼" required>
                    <label for="login_password">密碼</label>
                </div>
                
                <div class="mb-3">
                    <button class="w-100 btn btn-primary" type="submit" name="login">登入</button>
                </div>
            </form>
            
            <div>
                <a href="register.php" class="w-100 btn btn-outline-secondary">註冊新帳號</a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>