<?php
session_start();

// 添加調試信息
error_log("頁面載入，POST 數據: " . print_r($_POST, true));

// Handle login
$msg = "";
if (isset($_POST['login'])) {
    error_log("進入登入處理流程"); // 添加這行來確認是否進入
    $user_account = trim($_POST['login_username']);
    $password = $_POST['login_password'];
    $is_admin = isset($_POST['is_admin']) && $_POST['is_admin'] == '1';
    
    error_log("登入資料 - 帳號: $user_account, 管理者: " . ($is_admin ? '是' : '否'));
    
    try {
        if ($is_admin) {
            // 管理者登入邏輯 - 使用 manager 帳號連線
            $db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'manager', '5678');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 查詢 managers 表
            $stmt = $db->prepare("SELECT * FROM managers WHERE managers_account = ?");
            $stmt->execute([$user_account]);
            $manager = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("管理者查詢結果: " . ($manager ? "找到" : "未找到"));
            
            if ($manager) {
                $_SESSION['admin_id'] = $manager['managers_id'] ?? $manager['managers_id'];
                $_SESSION['admin_name'] = $manager['managersname'];
                $_SESSION['admin_account'] = $manager['managers_account'];
                $_SESSION['role'] = 'admin';
                error_log("管理者登入成功，準備跳轉");
                header("Location: managerdashboard.php");
                exit;
            } else {
                $msg = "管理者帳號不存在或權限不足。";
            }
        } else {
            // 一般用戶登入邏輯 - 使用 customer 帳號連線
            $db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'customer', '1234');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 查詢 users 表
            $stmt = $db->prepare("SELECT * FROM users WHERE user_account = ?");
            $stmt->execute([$user_account]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("用戶查詢結果: " . ($user ? "找到" : "未找到"));
            
            if ($user) {
                $_SESSION['user'] = $user['user_account'];
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = 'user';
                error_log("用戶登入成功，準備跳轉");
                header("Location: dashboard.php");
                exit;
            } else {
                $msg = "用戶帳號不存在。";
            }
        }
    } catch (Exception $e) {
        $msg = "登入過程發生錯誤：" . $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }
} else {
    error_log("未收到登入請求，POST 內容: " . print_r($_POST, true));
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// 檢查是否已登入
$is_logged_in = false;
$current_user = '';
$current_role = '';

if (isset($_SESSION['role'])) {
    $is_logged_in = true;
    $current_role = $_SESSION['role'];
    
    if ($current_role === 'admin') {
        $current_user = $_SESSION['admin_name'] ?? $_SESSION['admin_account'] ?? 'Administrator';
    } else {
        $current_user = $_SESSION['user'] ?? 'User';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - 記帳管理系統</title>
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
            max-width: 450px;
            position: relative;
            z-index: 1;
        }
        
        .login-card {
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
        
        .login-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            padding: 2rem 2rem 1rem;
            text-align: center;
            position: relative;
        }
        
        .login-body {
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
        
        .login-mode-switcher {
            margin-bottom: 1.5rem;
        }
        
        .mode-tabs {
            display: flex;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 16px;
            padding: 0.25rem;
            position: relative;
        }
        
        .mode-tab {
            flex: 1;
            padding: 0.75rem 1rem;
            text-align: center;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }
        
        .mode-tab:not(.active) {
            color: #667eea;
        }
        
        .mode-tab.active {
            background: white;
            color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
            font-weight: 600;
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
        
        .btn-login {
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
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-register {
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 16px;
            padding: 0.875rem;
            font-weight: 600;
            color: #667eea;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
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
        }
        
        .admin-indicator {
            display: none;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        
        .admin-indicator.show {
            display: block;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .welcome-section {
            text-align: center;
            padding: 1rem 0;
        }
        
        .welcome-message {
            color: #1f2937;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        
        .role-badge {
            display: inline-block;
            margin-top: 0.5rem;
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 20px;
        }
        
        .role-badge.admin {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .role-badge.user {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-dashboard {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            border-radius: 16px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
        }
        
        .btn-dashboard:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-logout {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            border-radius: 16px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
            color: white;
        }
        
        /* 響應式設計 */
        @media (max-width: 576px) {
            .form-signin {
                max-width: 100%;
                padding: 0 1rem;
            }
            
            .login-header {
                padding: 1.5rem 1.5rem 1rem;
            }
            
            .login-body {
                padding: 0 1.5rem 1.5rem;
            }
            
            .system-title {
                font-size: 1.5rem;
            }
            
            .btn-dashboard, .btn-logout {
                display: block;
                width: 100%;
                margin: 0.25rem 0;
            }
        }
    </style>
</head>
<body>
    <main class="form-signin">
        <div class="login-card">
            <div class="login-header">
                <div class="system-title">
                    <i class="bi bi-calculator me-2"></i>記帳管理系統
                </div>
                <p class="system-subtitle">財務管理，輕鬆掌控每一筆支出</p>
            </div>
            
            <div class="login-body">
                <?php if (isset($msg) && $msg): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <?php if ($is_logged_in): ?>
                    <div class="welcome-section">
                        <div class="welcome-message">
                            <i class="bi bi-person-check-fill me-2 text-success"></i>
                            歡迎回來，<?= htmlspecialchars($current_user) ?>！
                            <br>
                            <span class="role-badge <?= $current_role ?>">
                                <i class="bi bi-<?= $current_role === 'admin' ? 'shield-fill' : 'person-fill' ?> me-1"></i>
                                <?= $current_role === 'admin' ? '系統管理者' : '一般用戶' ?>
                            </span>
                        </div>
                        <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                            <?php if ($current_role === 'admin'): ?>
                                <button onclick="redirectTo('managerdashboard.php')" class="btn btn-dashboard">
                                    <i class="bi bi-speedometer2 me-2"></i>管理者儀表板
                                </button>
                            <?php else: ?>
                                <button onclick="redirectTo('dashboard.php')" class="btn btn-dashboard">
                                    <i class="bi bi-house-door me-2"></i>用戶儀表板
                                </button>
                            <?php endif; ?>
                            <button onclick="redirectTo('?logout=1')" class="btn btn-logout">
                                <i class="bi bi-box-arrow-right me-2"></i>登出
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="post" id="loginForm">
                        <!-- 登入模式切換 -->
                        <div class="login-mode-switcher">
                            <div class="mode-tabs">
                                <div class="mode-tab active" data-mode="user">
                                    <i class="bi bi-person me-2"></i>一般用戶
                                </div>
                                <div class="mode-tab" data-mode="admin">
                                    <i class="bi bi-shield-check me-2"></i>管理者
                                </div>
                            </div>
                        </div>

                        <!-- 管理者模式指示器 -->
                        <div class="admin-indicator" id="adminIndicator">
                            <i class="bi bi-shield-exclamation me-2"></i>
                            管理者登入模式 - 連接至管理者資料庫
                        </div>

                        <input type="hidden" name="is_admin" id="isAdmin" value="0">
                        
                        <!-- 帳號輸入框 -->
                        <div class="input-group-custom">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="text" class="form-control" id="login_username" name="login_username" placeholder="電子郵件帳號" required>
                        </div>
                        
                        <!-- 密碼輸入框 -->
                        <div class="input-group-custom">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" class="form-control" id="login_password" name="login_password" placeholder="密碼" required>
                        </div>
                        
                        <div class="mb-3">
                            <button class="w-100 btn btn-login" type="submit" name="login">
                                <i class="bi bi-box-arrow-in-right me-2"></i>
                                <span id="loginButtonText">登入系統</span>
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center">
                        <a href="register.php" class="w-100 btn btn-register" id="registerLink">
                            <i class="bi bi-person-plus me-2"></i>註冊新帳號
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 安全的重定向函數
        function redirectTo(url) {
            try {
                window.location.href = url;
            } catch (e) {
                console.error('重定向錯誤:', e);
                document.location = url;
            }
        }
        
        // DOM 載入完成後執行
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // 安全地獲取元素
                const modeTabs = document.querySelectorAll('.mode-tab');
                const adminIndicator = document.getElementById('adminIndicator');
                const isAdminInput = document.getElementById('isAdmin');
                const loginButtonText = document.getElementById('loginButtonText');
                const usernameInput = document.getElementById('login_username');
                const registerLink = document.getElementById('registerLink');
                
                // 檢查必要元素是否存在
                if (!modeTabs.length || !adminIndicator || !isAdminInput || !loginButtonText || !usernameInput) {
                    console.warn('某些必要的 DOM 元素未找到');
                    return;
                }
                
                modeTabs.forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        try {
                            // 移除所有 active 類別
                            modeTabs.forEach(function(t) {
                                t.classList.remove('active');
                            });
                            
                            // 添加 active 類別到當前點擊的標籤
                            this.classList.add('active');
                            
                            const mode = this.getAttribute('data-mode');
                            
                            if (mode === 'admin') {
                                // 管理者模式
                                adminIndicator.classList.add('show');
                                isAdminInput.value = '1';
                                loginButtonText.textContent = '管理者登入';
                                usernameInput.placeholder = '管理者帳號';
                                if (registerLink) {
                                    registerLink.style.display = 'none';
                                }
                            } else {
                                // 一般用戶模式
                                adminIndicator.classList.remove('show');
                                isAdminInput.value = '0';
                                loginButtonText.textContent = '登入系統';
                                usernameInput.placeholder = '電子郵件帳號';
                                if (registerLink) {
                                    registerLink.style.display = 'block';
                                }
                            }
                        } catch (e) {
                            console.error('模式切換錯誤:', e);
                        }
                    });
                });
                
                // 表單提交處理 - 移除會阻止提交的代碼
                const loginForm = document.getElementById('loginForm');
                if (loginForm) {
                    loginForm.addEventListener('submit', function(e) {
                        console.log('表單提交中...');
                        console.log('表單數據:', new FormData(this));
                        // 不阻止表單提交，讓它正常發送到 PHP
                    });
                }
                
            } catch (e) {
                console.error('JavaScript 初始化錯誤:', e);
            }
        });
        
        // 全域錯誤處理
        window.addEventListener('error', function(e) {
            console.error('全域錯誤:', e.error);
        });
        
        // Promise 錯誤處理
        window.addEventListener('unhandledrejection', function(e) {
            console.error('未處理的 Promise 錯誤:', e.reason);
            e.preventDefault();
        });
    </script>
</body>
</html>