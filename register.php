<?php
session_start();

// 添加調試信息
error_log("註冊頁面載入，POST 數據: " . print_r($_POST, true));

// Database connection (MySQL)
$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'customer', '1234');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$msg = "";
if (isset($_POST['register'])) {
    error_log("偵測到註冊表單提交");
    
    // 獲取表單數據
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    error_log("註冊數據 - 用戶名: $username, 郵箱: $email");
    
    // 基本驗證
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $msg = "所有欄位都是必填的。";
    } elseif ($password !== $confirm_password) {
        $msg = "密碼和確認密碼不匹配。";
    } elseif (strlen($password) < 6) {
        $msg = "密碼長度必須至少為6個字符。";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "請提供有效的電子郵件地址。";
    } else {
        try {
            // 檢查用戶名和電子郵件是否已經存在
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_account = ? OR name = ?");
            $check_stmt->execute([$email, $username]);
            $user_exists = $check_stmt->fetchColumn();
            
            if ($user_exists) {
                $msg = "用戶名或電子郵件已經被使用。";
            } else {
                // 創建新用戶
                $insert_stmt = $db->prepare("INSERT INTO users (name, user_account) VALUES (?, ?)");
                $insert_stmt->execute([$username, $email]);
                
                $msg = "註冊成功！現在您可以登錄了。";
                
                // 重定向到儀表板
                header("Location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            $msg = "註冊過程中發生錯誤：" . $e->getMessage();
            error_log("註冊錯誤: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊新帳號 - 記帳管理系統</title>
    <link rel="icon" type="image/png" href="icon.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 0;
        }
        
        body::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            z-index: 0;
        }
        
        .form-register {
            width: 100%;
            max-width: 500px;
            position: relative;
            z-index: 1;
        }
        
        .register-card {
            border-radius: 24px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.2),
                0 8px 25px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            position: relative;
        }
        
        .register-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            padding: 2rem 2rem 1rem;
            text-align: center;
            position: relative;
        }
        
        .register-body {
            padding: 0 2rem 2rem;
        }
        
        .system-title {
            color: #1f2937;
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        .system-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 0;
        }
        
        .input-group-custom {
            margin-bottom: 1rem;
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            z-index: 10;
            pointer-events: none;
        }
        
        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        
        .form-control::placeholder {
            color: #9ca3af;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 16px;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-register::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-register:hover::before {
            left: 100%;
        }
        
        .btn-login {
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 16px;
            padding: 0.875rem;
            font-weight: 600;
            color: #667eea;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: rgba(102, 126, 234, 0.15);
            border-color: rgba(102, 126, 234, 0.3);
            color: #4f46e5;
            transform: translateY(-1px);
        }
        
        .alert {
            border-radius: 16px;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
            padding: 1rem 1.25rem;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.95) 0%, rgba(189, 33, 48, 0.95) 100%);
            color: white;
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(25, 135, 84, 0.95) 0%, rgba(20, 108, 67, 0.95) 100%);
            color: white;
        }
        
        .form-label {
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 0.5rem;
            margin-left: 0.5rem;
        }
        
        @media (max-width: 576px) {
            .form-register {
                max-width: 100%;
                padding: 0 1rem;
            }
            
            .register-header {
                padding: 1.5rem 1.5rem 1rem;
            }
            
            .register-body {
                padding: 0 1.5rem 1.5rem;
            }
            
            .system-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <main class="form-register">
        <div class="register-card">
            <div class="register-header">
                <div class="system-title">
                    <i class="bi bi-person-plus me-2"></i>註冊新帳號
                </div>
                <p class="system-subtitle">加入我們，開始追蹤您的財務狀況</p>
            </div>
            
            <div class="register-body">
                <?php if (isset($msg) && $msg): ?>
                    <div class="alert <?= strpos($msg, '成功') !== false ? 'alert-success' : 'alert-danger' ?> d-flex align-items-center">
                        <i class="bi <?= strpos($msg, '成功') !== false ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <form method="post" id="registerForm">
                    <!-- 用戶名 -->
                    <div class="mb-3">
                        <label for="username" class="form-label">用戶名稱</label>
                        <div class="input-group-custom">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" class="form-control" id="username" name="username" placeholder="輸入您的用戶名稱" required>
                        </div>
                    </div>
                    
                    <!-- 電子郵件 -->
                    <div class="mb-3">
                        <label for="email" class="form-label">電子郵件</label>
                        <div class="input-group-custom">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" class="form-control" id="email" name="email" placeholder="輸入您的電子郵件" required>
                        </div>
                    </div>
                    
                    <!-- 密碼 -->
                    <div class="mb-3">
                        <label for="password" class="form-label">密碼</label>
                        <div class="input-group-custom">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" class="form-control" id="password" name="password" placeholder="設定您的密碼" required>
                        </div>
                    </div>
                    
                    <!-- 確認密碼 -->
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">確認密碼</label>
                        <div class="input-group-custom">
                            <i class="bi bi-lock-fill input-icon"></i>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="再次輸入您的密碼" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <button class="w-100 btn btn-register" type="submit" name="register" id="submitBtn">
                            <i class="bi bi-person-plus-fill me-2"></i>建立帳號
                        </button>
                    </div>
                </form>
                
                <div class="text-center">
                    <a href="login.php" class="w-100 btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>返回登入
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 表單驗證
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            
            // 僅進行前端驗證，不阻止表單提交
            form.addEventListener('submit', function(event) {
                console.log("表單提交觸發");
                
                // 清除之前的錯誤消息
                clearErrors();
                
                let isValid = true;
                
                // 檢查密碼長度
                if (password.value.length < 6) {
                    showError(password, '密碼長度必須至少為6個字符');
                    isValid = false;
                }
                
                // 檢查密碼匹配
                if (password.value !== confirmPassword.value) {
                    showError(confirmPassword, '密碼不匹配');
                    isValid = false;
                }
                
                // 如果有驗證錯誤，顯示提示並阻止提交
                if (!isValid) {
                    event.preventDefault();
                    console.log("表單驗證失敗，已阻止提交");
                } else {
                    console.log("表單驗證成功，允許提交");
                    // 這裡允許表單正常提交，不做任何阻止
                }
            });
            
            // 顯示錯誤消息的函數
            function showError(input, message) {
                const formGroup = input.closest('.mb-3');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'text-danger mt-1 error-message';
                errorDiv.style.marginLeft = '0.5rem';
                errorDiv.innerHTML = `<small><i class="bi bi-exclamation-circle me-1"></i>${message}</small>`;
                formGroup.appendChild(errorDiv);
                input.classList.add('is-invalid');
            }
            
            // 清除所有錯誤消息
            function clearErrors() {
                document.querySelectorAll('.error-message').forEach(error => error.remove());
                document.querySelectorAll('.is-invalid').forEach(input => input.classList.remove('is-invalid'));
            }
            
            // 實時密碼確認匹配
            confirmPassword.addEventListener('input', function() {
                clearErrors();
                if (password.value !== confirmPassword.value) {
                    showError(confirmPassword, '密碼不匹配');
                }
            });
        });
    </script>
</body>
</html>