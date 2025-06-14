<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 設定當前頁面標識
$current_page = 'managerdashboard';

$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'manager', '5678');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 處理封鎖請求
if (isset($_POST['block_user'])) {
    $user_id = $_POST['user_id'];
    $user_name = $_POST['user_name'];
    $reason = $_POST['reason'];
    $manager_id = $_SESSION['admin_id'];
    
    try {
        // 檢查用戶是否已經在黑名單中
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM blacklist WHERE user_id = ?");
        $check_stmt->execute([$user_id]);
        $already_blocked = $check_stmt->fetchColumn() > 0;
        
        if (!$already_blocked) {
            // 新增黑名單紀錄
            $stmt = $db->prepare("INSERT INTO blacklist (user_id, blocked_by, reason, blocked_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, $manager_id, $reason]);
            
            // 設置提示訊息
            $_SESSION['success_message'] = "用戶 $user_name (ID: $user_id) 已成功加入黑名單";
        } else {
            $_SESSION['error_message'] = "用戶 $user_name (ID: $user_id) 已經在黑名單中";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "封鎖用戶時發生錯誤：" . $e->getMessage();
    }
    
    // 重定向回當前頁面
    header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['search']) ? "?search=" . urlencode($_GET['search']) : ""));
    exit;
}

// 處理搜尋條件
$keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT so.*, 
        CASE WHEN bl.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_blocked
        FROM system_overview so
        LEFT JOIN blacklist bl ON so.user_id = bl.user_id";
$params = [];

if ($keyword !== '') {
    $sql .= " WHERE (so.user_name LIKE :kw OR so.transaction_date LIKE :kw)";
    $params[':kw'] = "%$keyword%";
}

$sql .= " GROUP BY so.user_id, so.transaction_date ORDER BY so.transaction_date DESC, so.user_id ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 取得統計數據
try {
    // 總用戶數
    $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // 今日交易數
    $todayTransactions = $db->query("SELECT COUNT(*) FROM system_overview WHERE transaction_date = CURDATE()")->fetchColumn();
    
    // 本月交易數
    $monthTransactions = $db->query("SELECT COUNT(*) FROM system_overview WHERE YEAR(transaction_date) = YEAR(CURDATE()) AND MONTH(transaction_date) = MONTH(CURDATE())")->fetchColumn();
    
    // 黑名單用戶數
    $blacklistCount = $db->query("SELECT COUNT(DISTINCT user_id) FROM blacklist_monitor")->fetchColumn();
    
} catch (Exception $e) {
    $userCount = 0;
    $todayTransactions = 0;
    $monthTransactions = 0;
    $blacklistCount = 0;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者後台 - 系統總覽</title>
    <link rel="icon" type="image/png" href="icon.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- 防止深色模式閃白的預處理腳本 -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('manager-theme');
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
        /* 容器基本設定 */
        .container-fluid {
            padding: 15px;
        }
        
        /* 彈性佈局容器 */
        .dashboard-container {
            display: flex;
            gap: 20px; /* 間距 */
        }
        
        /* 主內容區域 */
        .main-content-container {
            flex: 1;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.1);
            padding: 25px;
            transition: all 0.3s ease;
        }
        
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .page-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        /* 搜尋表單 */
        .search-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        /* 資料表格 */
        .data-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }
        
        .table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px;
        }
        
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-color: #e9ecef;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        /* 按鈕樣式 */
        .btn-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 10px 25px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        /* 輸入框 */
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* 空資料狀態 */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* 統計卡片樣式 */
        .stats-section {
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .stats-icon {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        
        .stats-number {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .stats-card.users .stats-icon { color: #007bff; }
        .stats-card.transactions .stats-icon { color: #28a745; }
        .stats-card.revenue .stats-icon { color: #ffc107; }
        .stats-card.reports .stats-icon { color:rgb(53, 220, 59); }
        .stats-card.blacklist .stats-icon { color: #e74c3c; }
        
        /* 徽章樣式 */
        .badge {
            padding: 6px 10px;
            font-weight: 500;
            border-radius: 6px;
        }
        
        /* 操作按鈕樣式 */
        .action-column {
            width: 100px;
            text-align: center;
        }
        
        .btn-action {
            width: 38px;
            height: 38px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin: 0 2px;
            transition: all 0.2s ease;
        }
        
        .btn-action i {
            font-size: 1.1rem;
        }
        
        .btn-block {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            color: white;
        }
        
        .btn-block:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.4);
            color: white;
        }
        
        .btn-blocked {
            background: #6c757d;
            border: none;
            color: white;
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        /* 訊息提示樣式 */
        .alert-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.95) 0%, rgba(32, 134, 55, 0.95) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.95) 0%, rgba(189, 33, 48, 0.95) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        /* 模態框樣式 */
        .modal-content {
            border-radius: 15px;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        /* 深色模式樣式 */
        body.dark-mode {
            background-color: #121212;
            color: #e0e0e0;
        }
        
        .dark-mode .main-content-container {
            background-color: #1e1e1e;
            color: #e0e0e0;
            border: 1px solid #333;
        }
        
        .dark-mode .stats-card {
            background-color: #2a2a2a;
            color: #e0e0e0;
            border: 1px solid #333;
        }
        
        .dark-mode .stats-card .stats-label {
            color: #b0b0b0;
        }
        
        .dark-mode .stats-card .stats-number {
            color: #ffffff;
        }
        
        .dark-mode .search-form {
            background-color: #2a2a2a;
            border-color: #333;
        }
        
        .dark-mode .data-table {
            background-color: #2a2a2a;
            border-color: #333;
        }
        
        /* 深色模式表格樣式 */
        .dark-mode .table {
            color: #e0e0e0 !important;
            background-color: #2a2a2a !important;
        }
        
        .dark-mode .table td,
        .dark-mode .table th {
            background-color: #2a2a2a !important;
            border-color: #404040 !important;
            color: #e0e0e0 !important;
        }
        
        .dark-mode .table-hover > tbody > tr:hover > * {
            background-color: #333 !important;
            color: #ffffff !important;
        }
        
        /* 深色模式頁面標題 */
        .dark-mode .page-title {
            color: #ffffff;
        }
        
        .dark-mode .page-header {
            border-bottom-color: #404040;
        }
        
        /* 深色模式表單控制項 */
        .dark-mode .form-control {
            background-color: #333;
            border-color: #404040;
            color: #e0e0e0;
        }
        
        .dark-mode .form-control:focus {
            background-color: #333;
            border-color: #667eea;
            color: #e0e0e0;
        }
        
        .dark-mode .form-control::placeholder {
            color: #888;
        }
        
        /* 深色模式空狀態 */
        .dark-mode .empty-state {
            color: #b0b0b0;
        }
        
        /* 深色模式徽章 */
        .dark-mode .badge.bg-primary {
            background-color: #667eea !important;
        }
        
        .dark-mode .badge.bg-success {
            background-color: #10b981 !important;
        }
        
        /* 深色模式文字顏色 */
        .dark-mode .text-muted {
            color: #b0b0b0 !important;
        }
        
        .dark-mode small.text-muted {
            color: #b0b0b0 !important;
        }
        
        /* 深色模式模態框 */
        .dark-mode .modal-content {
            background-color: #2d2d2d;
            color: #ffffff;
            border: 1px solid #444;
        }
        
        .dark-mode .modal-body {
            background-color: #2d2d2d;
            color: #ffffff;
        }
        
        .dark-mode .modal-footer {
            background-color: #333;
            border-top: 1px solid #444;
        }
        
        /* 響應式設計 */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .main-content-container {
                padding: 15px;
            }
            
            .stats-card {
                margin-bottom: 15px;
            }
            
            .action-column {
                width: 70px;
            }
        }
        /* 強制模態框用戶名稱顯示為黑色 */
        #displayUserName {
            color: #000 !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="dashboard-container">
            <?php 
            // 引入管理者側邊欄
            include 'manager_sidebar.php'; 
            ?>
            
            <div class="main-content-container">
                <!-- 訊息提示 -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <!-- 頁面標題 -->
                <div class="page-header">
                    <h2 class="page-title">
                        <i class="bi bi-speedometer2 me-3"></i>系統使用總覽
                    </h2>
                    <p class="text-muted mb-0">監控系統使用狀況與用戶活動</p>
                </div>

                <!-- 統計卡片 -->
                <div class="stats-section">
                    <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5">
                        <div class="col">
                            <div class="stats-card users h-100">
                                <div class="stats-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div class="stats-number"><?= number_format($userCount) ?></div>
                                <div class="stats-label">註冊用戶</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stats-card blacklist h-100">
                                <div class="stats-icon">
                                    <i class="bi bi-person-x-fill"></i>
                                </div>
                                <div class="stats-number"><?= number_format($blacklistCount) ?></div>
                                <div class="stats-label">黑名單用戶</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stats-card transactions h-100">
                                <div class="stats-icon">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div class="stats-number"><?= number_format($todayTransactions) ?></div>
                                <div class="stats-label">今日交易</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stats-card revenue h-100">
                                <div class="stats-icon">
                                    <i class="bi bi-calendar-month"></i>
                                </div>
                                <div class="stats-number"><?= number_format($monthTransactions) ?></div>
                                <div class="stats-label">本月交易</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stats-card reports h-100">
                                <div class="stats-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div class="stats-number">正常</div>
                                <div class="stats-label">系統狀態</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 搜尋表單 -->
                <div class="search-form">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="search" class="form-label">
                                <i class="bi bi-search me-2"></i>搜尋條件
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="search"
                                   name="search" 
                                   placeholder="輸入使用者名稱或日期..." 
                                   value="<?= htmlspecialchars($keyword) ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-search w-100">
                                <i class="bi bi-search me-2"></i>搜尋
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 資料表格 -->
                <div class="data-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-hash me-2"></i>使用者 ID</th>
                                    <th><i class="bi bi-person me-2"></i>使用者名稱</th>
                                    <th><i class="bi bi-calendar me-2"></i>交易日期</th>
                                    <th><i class="bi bi-bar-chart me-2"></i>當日交易筆數</th>
                                    <th class="action-column"><i class="bi bi-shield-fill me-2"></i>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($results)): ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <p class="mb-0">查無相關資料</p>
                                            <?php if ($keyword): ?>
                                                <small>嘗試使用不同的搜尋條件</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($results as $row): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?= htmlspecialchars($row['user_id']) ?></span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['user_name']) ?></strong>
                                            </td>
                                            <td>
                                                <i class="bi bi-calendar-date me-2 text-muted"></i>
                                                <?= htmlspecialchars($row['transaction_date']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?= htmlspecialchars($row['daily_transaction_count']) ?> 筆
                                                </span>
                                            </td>
                                            <td class="action-column">
                                                <?php if ($row['is_blocked']): ?>
                                                    <button type="button" class="btn btn-action btn-blocked" disabled title="已封鎖">
                                                        <i class="bi bi-person-x-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-action btn-block block-user" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#blockModal"
                                                            data-id="<?= $row['user_id'] ?>"
                                                            data-name="<?= htmlspecialchars($row['user_name']) ?>"
                                                            title="封鎖用戶">
                                                        <i class="bi bi-person-x"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- 資料統計摘要 -->
                <?php if (!empty($results)): ?>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            共找到 <strong><?= count($results) ?></strong> 筆記錄
                            <?php if ($keyword): ?>
                                （搜尋條件：<strong><?= htmlspecialchars($keyword) ?></strong>）
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 封鎖用戶模態框 -->
    <div class="modal fade" id="blockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-x-fill me-2"></i>
                        封鎖用戶
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="blockForm" method="POST">
                        <input type="hidden" name="user_id" id="blockUserId">
                        <input type="hidden" name="user_name" id="blockUserName">
                        
                        <div class="mb-3">
                            <label class="form-label">您即將封鎖用戶：</label>
                            <div class="d-flex align-items-center p-3 mb-3 bg-light rounded">
                                <i class="bi bi-person-circle fs-4 me-3 text-primary"></i>
                                <div>
                                    <strong id="displayUserName"></strong>
                                    <div class="small text-muted">用戶 ID: <span id="displayUserId"></span></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">封鎖原因</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required placeholder="請輸入封鎖此用戶的原因..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            封鎖後，該用戶將無法登入系統。
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                            <button type="submit" name="block_user" class="btn btn-danger">
                                <i class="bi bi-shield-x me-2"></i>確認封鎖
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 封鎖用戶模態框
        document.addEventListener('DOMContentLoaded', function() {
            const blockButtons = document.querySelectorAll('.block-user');
            
            blockButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    const userName = this.getAttribute('data-name');
                    
                    document.getElementById('blockUserId').value = userId;
                    document.getElementById('blockUserName').value = userName;
                    document.getElementById('displayUserId').textContent = userId;
                    document.getElementById('displayUserName').textContent = userName;
                });
            });
            
            // 自動隱藏提示訊息
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const closeButton = alert.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }, 5000); // 5秒後自動隱藏
            });
        });
    </script>
</body>
</html>
