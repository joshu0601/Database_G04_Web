<?php
session_start();

// Database connection (MySQL)
$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'customer', '1234');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle registration
$msg = "";
$success_msg = "";

if (isset($_POST['register'])) {
    $user_account = trim($_POST['reg_username']);
    $name = trim($_POST['reg_name']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];
    
    // 驗證輸入
    if (!$user_account || !$name || !$password || !$confirm_password) {
        $msg = "請填寫所有欄位。";
    } elseif ($password !== $confirm_password) {
        $msg = "密碼與確認密碼不相符。";
    } elseif (strlen($password) < 6) {
        $msg = "密碼長度至少需要 6 個字元。";
    } elseif (!filter_var($user_account, FILTER_VALIDATE_EMAIL)) {
        $msg = "請輸入有效的電子郵件格式。";
    } else {
        try {
            // 檢查帳號是否已存在
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_account = ?");
            $check_stmt->execute([$user_account]);
            
            if ($check_stmt->fetchColumn() > 0) {
                $msg = "此電子郵件已被註冊，請使用其他電子郵件。";
            } else {
                // 插入新用戶（不儲存密碼，因為登入時沒有驗證密碼）
                $stmt = $db->prepare("INSERT INTO users (user_account, name) VALUES (?, ?)");
                $stmt->execute([$user_account, $name]);
                $success_msg = "註冊成功！3 秒後將自動跳轉至登入頁面...";
                
                // 3秒後自動跳轉
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 3000);
                </script>";
            }
        } catch (PDOException $e) {
            $msg = "註冊失敗，請稍後再試。";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 - 記帳管理系統</title>
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
        
        .form-signin {
            width: 100%;
            max-width: 480px;
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
        
        .form-section-title {
            color: #4f46e5;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
        }
        
        .form-section-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
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
        
        .form-control.is-valid {
            border-color: #10b981;
        }
        
        .form-control.is-invalid {
            border-color: #ef4444;
        }
        
        .password-strength {
            font-size: 0.85rem;
            margin-top: 0.25rem;
            padding-left: 1rem;
        }
        
        .password-strength.weak { color: #ef4444; }
        .password-strength.medium { color: #f59e0b; }
        .password-strength.strong { color: #10b981; }
        
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
        
        .btn-login-link {
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 16px;
            padding: 0.875rem;
            font-weight: 600;
            color: #667eea;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            width: 100%;
            text-align: center;
        }
        
        .btn-login-link:hover {
            background: rgba(102, 126, 234, 0.15);
            border-color: rgba(102, 126, 234, 0.3);
            color: #4f46e5;
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        .alert {
            border-radius: 16px;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .form-text {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            padding-left: 1rem;
        }
        
        .loading-spinner {
            display: none;
        }
        
        .loading .loading-spinner {
            display: inline-block;
        }
        
        .loading .btn-text {
            display: none;
        }
        
        /* 響應式設計 */
        @media (max-width: 576px) {
            .form-signin {
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
    <main class="form-signin">
        <div class="register-card">
            <div class="register-header">
                <div class="system-title">
                    <i class="bi bi-calculator me-2"></i>記帳管理系統
                </div>
                <p class="system-subtitle">財務管理，輕鬆掌控每一筆支出</p>
            </div>
            
            <div class="register-body">
                <!-- 錯誤訊息 -->
                <?php if (isset($msg) && $msg): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <!-- 成功訊息 -->
                <?php if (isset($success_msg) && $success_msg): ?>
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?= htmlspecialchars($success_msg) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$success_msg): ?>
                    <form method="post" id="registerForm" novalidate>
                        <div class="form-section-title">
                            <i class="bi bi-person-plus me-2"></i>建立新帳號
                        </div>
                        
                        <!-- 電子郵件輸入框 -->
                        <div class="input-group-custom">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" 
                                   class="form-control" 
                                   id="reg_username" 
                                   name="reg_username" 
                                   placeholder="請輸入電子郵件地址" 
                                   required>
                        </div>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>這將作為您的登入帳號
                        </div>
                        
                        <!-- 姓名輸入框 -->
                        <div class="input-group-custom">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" 
                                   class="form-control" 
                                   id="reg_name" 
                                   name="reg_name" 
                                   placeholder="請輸入您的姓名" 
                                   required>
                        </div>
                        
                        <!-- 密碼輸入框 -->
                        <div class="input-group-custom">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="reg_password" 
                                   name="reg_password" 
                                   placeholder="請輸入密碼（至少6位）" 
                                   required>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                        
                        <!-- 確認密碼輸入框 -->
                        <div class="input-group-custom">
                            <i class="bi bi-shield-check input-icon"></i>
                            <input type="password" 
                                   class="form-control" 
                                   id="reg_confirm_password" 
                                   name="reg_confirm_password" 
                                   placeholder="請再次輸入密碼" 
                                   required>
                        </div>
                        <div class="form-text" id="passwordMatch"></div>
                        
                        <div class="mb-3 mt-4">
                            <button class="w-100 btn btn-register" type="submit" name="register" id="registerBtn">
                                <span class="loading-spinner">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    註冊中...
                                </span>
                                <span class="btn-text">
                                    <i class="bi bi-person-plus me-2"></i>立即註冊
                                </span>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
                
                <div class="text-center">
                    <a href="login.php" class="btn-login-link">
                        <i class="bi bi-arrow-left me-2"></i>已有帳號？返回登入
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('reg_password');
            const confirmPasswordInput = document.getElementById('reg_confirm_password');
            const passwordStrength = document.getElementById('passwordStrength');
            const passwordMatch = document.getElementById('passwordMatch');
            const registerForm = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            const emailInput = document.getElementById('reg_username');
            
            // 密碼強度檢查
            if (passwordInput && passwordStrength) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strength = checkPasswordStrength(password);
                    
                    passwordStrength.textContent = strength.text;
                    passwordStrength.className = 'password-strength ' + strength.class;
                    
                    // 更新輸入框樣式
                    if (password.length > 0) {
                        if (strength.class === 'strong') {
                            this.classList.add('is-valid');
                            this.classList.remove('is-invalid');
                        } else {
                            this.classList.add('is-invalid');
                            this.classList.remove('is-valid');
                        }
                    } else {
                        this.classList.remove('is-valid', 'is-invalid');
                    }
                });
            }
            
            // 密碼確認檢查
            if (confirmPasswordInput && passwordMatch) {
                confirmPasswordInput.addEventListener('input', function() {
                    const password = passwordInput.value;
                    const confirmPassword = this.value;
                    
                    if (confirmPassword.length > 0) {
                        if (password === confirmPassword) {
                            passwordMatch.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>密碼相符';
                            this.classList.add('is-valid');
                            this.classList.remove('is-invalid');
                        } else {
                            passwordMatch.innerHTML = '<i class="bi bi-x-circle text-danger me-1"></i>密碼不相符';
                            this.classList.add('is-invalid');
                            this.classList.remove('is-valid');
                        }
                    } else {
                        passwordMatch.textContent = '';
                        this.classList.remove('is-valid', 'is-invalid');
                    }
                });
            }
            
            // 電子郵件格式檢查
            if (emailInput) {
                emailInput.addEventListener('blur', function() {
                    const email = this.value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (email.length > 0) {
                        if (emailRegex.test(email)) {
                            this.classList.add('is-valid');
                            this.classList.remove('is-invalid');
                        } else {
                            this.classList.add('is-invalid');
                            this.classList.remove('is-valid');
                        }
                    }
                });
            }
            
            // 表單提交處理
            if (registerForm && registerBtn) {
                registerForm.addEventListener('submit', function(e) {
                    const isValid = validateForm();
                    
                    if (isValid) {
                        registerBtn.classList.add('loading');
                        registerBtn.disabled = true;
                        
                        // 防止重複提交
                        setTimeout(function() {
                            if (!registerBtn.classList.contains('loading')) return;
                            registerBtn.classList.remove('loading');
                            registerBtn.disabled = false;
                        }, 10000);
                    } else {
                        e.preventDefault();
                    }
                });
            }
            
            function checkPasswordStrength(password) {
                if (password.length === 0) {
                    return { text: '', class: '' };
                }
                
                if (password.length < 6) {
                    return { text: '密碼太短（至少需要6個字元）', class: 'weak' };
                }
                
                let score = 0;
                if (password.length >= 8) score++;
                if (/[a-z]/.test(password)) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^a-zA-Z0-9]/.test(password)) score++;
                
                if (score < 2) {
                    return { text: '密碼強度：弱', class: 'weak' };
                } else if (score < 4) {
                    return { text: '密碼強度：中等', class: 'medium' };
                } else {
                    return { text: '密碼強度：強', class: 'strong' };
                }
            }
            
            function validateForm() {
                const email = emailInput.value.trim();
                const name = document.getElementById('reg_name').value.trim();
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!email || !emailRegex.test(email)) {
                    alert('請輸入有效的電子郵件地址');
                    return false;
                }
                
                if (!name) {
                    alert('請輸入姓名');
                    return false;
                }
                
                if (password.length < 6) {
                    alert('密碼至少需要6個字元');
                    return false;
                }
                
                if (password !== confirmPassword) {
                    alert('密碼與確認密碼不相符');
                    return false;
                }
                
                return true;
            }
        });
        
        // 全域錯誤處理
        window.addEventListener('error', function(e) {
            console.error('全域錯誤:', e.error);
        });
    </script>
</body>
</html>