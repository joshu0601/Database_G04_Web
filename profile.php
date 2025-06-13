<?php
session_start();
$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'customer', '1234');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 初始化訊息變數
$message = '';
$message_type = '';
$show_toast = false;

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $total_assets = floatval($_POST['total_assets']);

        if (empty($name)) {
            throw new Exception('請輸入姓名');
        }
        
        if (empty($email)) {
            throw new Exception('請輸入電子郵件');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('請輸入有效的電子郵件格式');
        }
        
        if ($total_assets < 0) {
            throw new Exception('總資產不可為負數');
        }

        // 檢查 email 是否被其他用戶使用
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE user_account = ? AND user_id != ?");
        $check_stmt->execute([$email, $user_id]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception('此電子郵件已被其他用戶使用');
        }

        $stmt = $db->prepare("UPDATE users SET name = ?, user_account = ?, total_assets = ? WHERE user_id = ?");
        $stmt->execute([$name, $email, $total_assets, $user_id]);
        
        $message = "個人資料已成功更新！";
        $message_type = 'success';
        $show_toast = true;
        $_SESSION['user'] = $name; // 更新 session 中的使用者名稱
    } catch (Exception $e) {
        $message = "更新失敗：" . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 獲取使用者資料
try {
    $stmt = $db->prepare("SELECT name, user_account, total_assets FROM user_financial_summary WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('找不到使用者資料');
    }
} catch (Exception $e) {
    $user = ['name' => '', 'user_account' => '', 'total_assets' => 0];
    if (empty($message)) {
        $message = '載入使用者資料失敗：' . $e->getMessage();
        $message_type = 'warning';
        $show_toast = true;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人設定</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- 防止深色模式閃白的預處理腳本 -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.style.backgroundColor = '#1a1a1a';
                document.documentElement.style.color = '#ffffff';
                document.documentElement.classList.add('dark-mode');
            } else {
                document.documentElement.style.backgroundColor = '#f8f9fa';
                document.documentElement.style.color = '#212529';
                document.documentElement.classList.remove('dark-mode');
            }
        })();
    </script>
    
    <style>
        .dark-mode {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
        
        .dark-mode body {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
        
        body {
            background-color: #f8f9fa;
            color: #212529;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body>
    <!-- Toast 容器 -->
    <div aria-live="polite" aria-atomic="true" class="position-relative">
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <?php if ($show_toast && !empty($message)): ?>
                <div id="messageToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill text-success' : ($message_type === 'danger' ? 'exclamation-triangle-fill text-danger' : 'info-circle-fill text-warning') ?> me-2"></i>
                        <strong class="me-auto"><?= $message_type === 'success' ? '成功' : ($message_type === 'danger' ? '錯誤' : '警告') ?></strong>
                        <small>剛剛</small>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        <?= htmlspecialchars($message) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- 側邊欄 -->
            <?php 
            $current_page = 'profile';
            include 'sidebar.php'; 
            ?>
            
            <!-- 主要內容區 -->
            <div class="col-md-10">
                <!-- 頁面標題 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>個人設定</h5>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <!-- 個人資料卡片 -->
                        <div class="card">
                            <div class="card-body p-4">
                                <!-- 使用者頭像 -->
                                <div class="text-center mb-4">
                                    <div class="profile-avatar">
                                        <?php
                                            $name = trim($user['name']);
                                            if ($name !== '') {
                                                // 取第一個中文字或英文字母
                                                $firstChar = mb_substr($name, 0, 1, 'UTF-8');
                                                echo htmlspecialchars(mb_strtoupper($firstChar, 'UTF-8'));
                                            } else {
                                                echo 'U';
                                            }
                                        ?>
                                    </div>
                                    <h4 class="mb-1"><?= htmlspecialchars($user['name'] ?: '使用者') ?></h4>
                                    <p class="text-muted"><?= htmlspecialchars($user['user_account']) ?></p>
                                </div>

                                <!-- 個人資料表單 -->
                                <form method="POST" id="profileForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">
                                                <i class="bi bi-person me-1"></i>姓名 <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" name="name" 
                                                   value="<?= htmlspecialchars($user['name']) ?>" 
                                                   required maxlength="50" placeholder="請輸入您的姓名">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">
                                                <i class="bi bi-envelope me-1"></i>電子郵件 <span class="text-danger">*</span>
                                            </label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?= htmlspecialchars($user['user_account']) ?>" 
                                                   required maxlength="100" placeholder="example@email.com">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="total_assets" class="form-label">
                                            <i class="bi bi-wallet2 me-1"></i>總資產 <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" name="total_assets" 
                                                   value="<?= htmlspecialchars($user['total_assets']) ?>" 
                                                   min="0" step="0.01" required placeholder="0.00">
                                        </div>
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>
                                            這是您的總資產金額，包含現金、銀行存款等
                                        </div>
                                    </div>

                                

                                    <!-- 操作按鈕 -->
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                            <i class="bi bi-arrow-clockwise"></i> 重置
                                        </button>
                                        <button type="button" class="btn btn-primary" onclick="submitProfile()">
                                            <i class="bi bi-check-lg"></i> 儲存變更
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- 安全設定提示 -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>安全提示</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-0" role="alert">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>資料安全：</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>請定期檢查並更新您的個人資料</li>
                                        <li>電子郵件地址用於系統通知和帳戶恢復</li>
                                        <li>總資產金額會影響您的財務報表計算</li>
                                        <li>如有任何異常，請立即聯繫系統管理員</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 確保頁面載入時就應用正確的主題
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.body.classList.add('dark-mode');
                document.documentElement.classList.add('dark-mode');
                document.documentElement.style.backgroundColor = '#1a1a1a';
                document.documentElement.style.color = '#ffffff';
            } else {
                document.body.classList.remove('dark-mode');
                document.documentElement.classList.remove('dark-mode');
                document.documentElement.style.backgroundColor = '#f8f9fa';
                document.documentElement.style.color = '#212529';
            }
        });

        // 顯示 Toast 訊息
        <?php if ($show_toast && !empty($message)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const toastElement = document.getElementById('messageToast');
                if (toastElement) {
                    const toast = new bootstrap.Toast(toastElement, {
                        delay: <?= $message_type === 'success' ? '4000' : '6000' ?>
                    });
                    toast.show();
                    
                    <?php if ($message_type === 'success'): ?>
                        toastElement.addEventListener('hidden.bs.toast', function() {
                            // 更新最後更新時間顯示
                            const today = new Date().toISOString().split('T')[0];
                            const lastUpdateElement = document.getElementById('lastUpdate');
                            if (lastUpdateElement) {
                                lastUpdateElement.textContent = today;
                            }
                        });
                    <?php endif; ?>
                }
            });
        <?php endif; ?>

        // 提交個人資料表單
        function submitProfile() {
            const form = document.getElementById('profileForm');
            const submitBtn = event.target;
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>儲存中...';
            
            form.submit();
        }

        // 重置表單
        function resetForm() {
            if (confirm('確定要重置所有變更嗎？')) {
                document.getElementById('profileForm').reset();
                // 恢復原始值
                document.querySelector('input[name="name"]').value = '<?= htmlspecialchars($user['name']) ?>';
                document.querySelector('input[name="email"]').value = '<?= htmlspecialchars($user['user_account']) ?>';
                document.querySelector('input[name="total_assets"]').value = '<?= htmlspecialchars($user['total_assets']) ?>';
            }
        }

        // 即時驗證電子郵件格式
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput) {
                emailInput.addEventListener('blur', function() {
                    const email = this.value.trim();
                    if (email && !isValidEmail(email)) {
                        this.setCustomValidity('請輸入有效的電子郵件格式');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });

        // 驗證電子郵件格式
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    </script>
</body>
</html>
