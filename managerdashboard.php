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

// 處理搜尋條件
$keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT * FROM system_overview";
$params = [];

if ($keyword !== '') {
    $sql .= " WHERE user_name LIKE :kw OR transaction_date LIKE :kw";
    $params[':kw'] = "%$keyword%";
}

$sql .= " ORDER BY user_id ASC, transaction_date DESC";
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
    
} catch (Exception $e) {
    $userCount = 0;
    $todayTransactions = 0;
    $monthTransactions = 0;
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
    
    <style>
        .container-fluid {
            padding: 0 15px;
        }
        
        .main-content {
            padding: 20px;
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
        
        .search-form {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        .data-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php 
            // 引入管理者側邊欄
            include 'manager_sidebar.php'; 
            ?>
            
            <div class="col-md-9 main-content">
                <!-- 頁面標題 -->
                <div class="page-header">
                    <h2 class="page-title">
                        <i class="bi bi-speedometer2 me-3"></i>系統使用總覽
                    </h2>
                    <p class="text-muted mb-0">監控系統使用狀況與用戶活動</p>
                </div>

                <!-- 統計卡片 -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card users">
                            <div class="stats-icon text-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="stats-number text-center"><?= $userCount ?></div>
                            <div class="stats-label text-center">註冊用戶</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card transactions">
                            <div class="stats-icon text-center">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="stats-number text-center"><?= $todayTransactions ?></div>
                            <div class="stats-label text-center">今日交易</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card revenue">
                            <div class="stats-icon text-center">
                                <i class="bi bi-calendar-month"></i>
                            </div>
                            <div class="stats-number text-center"><?= $monthTransactions ?></div>
                            <div class="stats-label text-center">本月交易</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card reports">
                            <div class="stats-icon text-center">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stats-number text-center">正常</div>
                            <div class="stats-label text-center">系統狀態</div>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($results)): ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">
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
                                                <span class="badge bg-success fs-6">
                                                    <?= htmlspecialchars($row['daily_transaction_count']) ?> 筆
                                                </span>
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

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
