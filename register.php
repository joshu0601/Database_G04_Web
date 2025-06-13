<?php
session_start();

// Database connection (MySQL)
$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'customer', '1234');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle registration
$msg = "";
if (isset($_POST['register'])) {
    $user_account = trim($_POST['reg_username']);
    $name = trim($_POST['reg_name']);
    $password = password_hash($_POST['reg_password'], PASSWORD_DEFAULT);
    if ($user_account && $name && $_POST['reg_password']) {
        try {
            $stmt = $db->prepare("INSERT INTO users (user_account, name, password) VALUES (?, ?, ?)");
            $stmt->execute([$user_account, $name, $password]);
            header("Location: login.php");
            exit;
        } catch (PDOException $e) {
            $msg = "帳號已存在或格式錯誤。";
        }
    } else {
        $msg = "請填寫所有欄位。";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊</title>
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
        .form-register {
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
    <main class="form-register">
        <div class="card p-4">
            <h2 class="mb-4 text-center">註冊系統</h2>
            
            <?php if (isset($msg) && $msg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="post">
                <h3 class="mb-3 text-center">建立您的帳號</h3>
                
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="reg_username" name="reg_username" placeholder="電子郵件帳號" required>
                    <label for="reg_username">電子郵件帳號</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="reg_name" name="reg_name" placeholder="姓名" required>
                    <label for="reg_name">姓名</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="reg_password" name="reg_password" placeholder="密碼" required>
                    <label for="reg_password">密碼</label>
                </div>
                
                <div class="mb-3">
                    <button class="w-100 btn btn-primary" type="submit" name="register">註冊</button>
                </div>
            </form>
            
            <div>
                <a href="login.php" class="w-100 btn btn-outline-secondary">返回登入</a>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>