<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 設定當前頁面標識
$current_page = 'user_management';

$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'manager', '5678');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 處理刪除請求
if (isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $db->prepare("DELETE FROM blacklist WHERE blacklist_id = ?");
    $stmt->execute([$delete_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 搜尋功能
$search_sql = "SELECT * FROM blacklist_monitor";
$search_params = [];
if (!empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $search_sql .= " WHERE user_id LIKE ? OR user_name LIKE ?";
    $search_params = [$search, $search];
}
$search_sql .= " ORDER BY blacklist_id ASC";

$stmt = $db->prepare($search_sql);
$stmt->execute($search_params);
$blacklist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 統計數據
$totalBlocked = count($blacklist);
$uniqueUsers = $db->query("SELECT COUNT(DISTINCT user_id) FROM blacklist_monitor")->fetchColumn();
$recentBlocked = $db->query("SELECT COUNT(*) FROM blacklist_monitor WHERE blocked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>黑名單管理 - 管理者後台</title>
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
        
        .data-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
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
        
        /* 操作欄位樣式 - 加寬並美化 */
        .action-column {
            width: 100px !important; /* 加寬操作欄位 */
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
        
        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            color: white;
        }
        
        .btn-delete:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.4);
            color: white;
        }
        
        /* 搜尋表單 */
        .search-form {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            height: auto;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
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
        
        /* 統計卡片 */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .stats-card .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-card .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stats-card .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .stats-card.total .stats-icon { color: #e74c3c; }
        .stats-card.unique .stats-icon { color: #3498db; }
        .stats-card.recent .stats-icon { color: #f39c12; }
        
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
        
        /* 刪除確認模態框 */
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
            background-color: #1e1e1e;
            color: #e0e0e0;
            border: 1px solid #333;
        }

        .dark-mode .stats-card .stats-label {
            color: #b0b0b0;
        }

        .dark-mode .stats-card .stats-number {
            color: #ffffff;
        }

        /* 深色模式表格樣式 */
        .dark-mode .table {
            color: #e0e0e0 !important;
            background-color: #1e1e1e !important;
        }

        .dark-mode .table td,
        .dark-mode .table th {
            background-color: #1e1e1e !important;
            border-color: #404040 !important;
            color: #e0e0e0 !important;
        }

        .dark-mode .table-hover > tbody > tr:hover > * {
            background-color: #2a2a2a !important;
            color: #ffffff !important;
        }

        .dark-mode .data-table {
            background-color: #1e1e1e;
            border: 1px solid #333;
        }

        /* 深色模式頁面標題 */
        .dark-mode .page-title {
            color: #ffffff;
        }

        .dark-mode .page-header {
            border-bottom-color: #404040;
        }

        /* 深色模式搜尋表單 */
        .dark-mode .search-form {
            background-color: #2a2a2a;
            border: 1px solid #333;
        }
        
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
        
        /* 深色模式文字顏色 */
        .dark-mode .text-muted {
            color: #b0b0b0 !important;
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
                width: 70px !important;
            }
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
                <!-- 頁面標題 -->
                <div class="page-header">
                    <h2 class="page-title">
                        <i class="bi bi-shield-slash-fill me-3"></i>黑名單管理
                    </h2>
                    <p class="text-muted mb-0">管理與監控系統中的黑名單用戶</p>
                </div>

                <!-- 統計卡片 -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="stats-card total">
                            <div class="stats-icon text-center">
                                <i class="bi bi-person-x-fill"></i>
                            </div>
                            <div class="stats-number text-center"><?= $totalBlocked ?></div>
                            <div class="stats-label text-center">總封鎖紀錄</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card unique">
                            <div class="stats-icon text-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stats-number text-center"><?= $uniqueUsers ?></div>
                            <div class="stats-label text-center">不重複用戶數</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card recent">
                            <div class="stats-icon text-center">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                            <div class="stats-number text-center"><?= $recentBlocked ?></div>
                            <div class="stats-label text-center">最近7天封鎖</div>
                        </div>
                    </div>
                </div>

                <!-- 搜尋表單 -->
                <div class="search-form">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="search" class="form-label">
                                <i class="bi bi-search me-2"></i>搜尋黑名單
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="search" 
                                   name="search" 
                                   placeholder="輸入使用者 ID 或名稱..." 
                                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
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
                                    <th style="width: 8%;"><i class="bi bi-hash me-2"></i>用戶 ID</th>
                                    <th style="width: 15%;"><i class="bi bi-person me-2"></i>用戶名稱</th>
                                    <th style="width: 30%;"><i class="bi bi-exclamation-triangle me-2"></i>封鎖原因</th>
                                    <th style="width: 15%;"><i class="bi bi-calendar me-2"></i>封鎖時間</th>
                                    <th style="width: 15%;"><i class="bi bi-person-badge me-2"></i>封鎖者</th>
                                    <th class="action-column"><i class="bi bi-gear-fill me-2"></i>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($blacklist)): ?>
                                    <tr>
                                        <td colspan="6" class="empty-state">
                                            <i class="bi bi-shield-check"></i>
                                            <p class="mb-0">目前沒有任何封鎖紀錄</p>
                                            <?php if (!empty($_GET['search'])): ?>
                                                <small>嘗試使用不同的搜尋條件</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($blacklist as $row): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-danger"><?= htmlspecialchars($row['user_id']) ?></span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['user_name']) ?></strong>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($row['reason']) ?>
                                            </td>
                                            <td>
                                                <i class="bi bi-clock-history me-1 text-muted"></i>
                                                <?= date('Y-m-d H:i', strtotime($row['blocked_at'])) ?>
                                            </td>
                                            <td>
                                                <i class="bi bi-person-circle me-1 text-muted"></i>
                                                <?= htmlspecialchars($row['manager_name']) ?>
                                            </td>
                                            <td class="action-column">
                                                <button class="btn btn-action btn-delete delete-record"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal"
                                                        data-id="<?= $row['blacklist_id'] ?>"
                                                        data-name="<?= htmlspecialchars($row['user_name']) ?>"
                                                        title="解除封鎖">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- 資料統計摘要 -->
                <?php if (!empty($blacklist)): ?>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            共有 <strong><?= count($blacklist) ?></strong> 筆封鎖紀錄
                            <?php if (!empty($_GET['search'])): ?>
                                （搜尋條件：<strong><?= htmlspecialchars($_GET['search']) ?></strong>）
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 刪除確認模態框 -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        確認解除封鎖
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>確定要解除封鎖用戶 <strong id="deleteName"></strong> 嗎？</p>
                    <p class="text-muted">解除封鎖後，此用戶將能夠重新使用系統。</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="delete_id" id="deleteId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-shield-fill-x me-2"></i>確認解除封鎖
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 確保頁面載入時就應用正確的主題
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('manager-theme');
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

        // 填充刪除模態框內容
        document.querySelectorAll('.delete-record').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteName').textContent = name;
            });
        });
    </script>
</body>
</html>
