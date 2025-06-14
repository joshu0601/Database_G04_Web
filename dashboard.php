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
$stmt = $db->prepare("SELECT name FROM user_financial_summary WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user ? $user['name'] : $_SESSION['user'];

// 初始化訊息變數
$message = '';
$message_type = '';
$show_toast = false;

// 新增交易處理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'], $_POST['amount'], $_POST['category_id'], $_POST['transaction_date'])) {
    try {
        $type = $_POST['type'];
        $amount = $_POST['amount'];
        $category_id = $_POST['category_id'];
        $transaction_date = $_POST['transaction_date'];
        $description = $_POST['description'] ?? '';
        
        if(empty($description)){
            $description = 'null'; // 如果備註為空，則設為 null
        }
        // 驗證資料
        if (empty($type) || !in_array($type, ['Income', 'Expense'])) {
            throw new Exception('請選擇正確的交易類型');
        }
        
        if (empty($amount) || $amount <= 0) {
            throw new Exception('請輸入正確的金額');
        }
        
        if (empty($category_id)) {
            throw new Exception('請選擇分類');
        }
        
        if (empty($transaction_date)) {
            throw new Exception('請選擇交易日期');
        }
        
        // 驗證分類是否屬於該用戶且類型正確
        $verify_stmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE category_id = ? AND user_id = ? AND type = ?");
        $verify_stmt->execute([$category_id, $user_id, $type]);
        if ($verify_stmt->fetchColumn() == 0) {
            throw new Exception('選擇的分類無效');
        }
        
        // 開始交易
        $db->beginTransaction();
        
        // 新增交易記錄
        $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, category_id, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $type, $amount, $category_id, $transaction_date, $description]);
        
        // 更新總資產
        if ($type === 'Income') {
            // 收入：增加總資產
            $update_stmt = $db->prepare("UPDATE users SET total_assets = total_assets + ? WHERE user_id = ?");
            $update_stmt->execute([$amount, $user_id]);
        } else {
            // 支出：減少總資產
            $update_stmt = $db->prepare("UPDATE users SET total_assets = total_assets - ? WHERE user_id = ?");
            $update_stmt->execute([$amount, $user_id]);
        }
        
        $db->commit();
        
        $message = '交易記錄新增成功！總資產已更新。';
        $message_type = 'success';
        $show_toast = true;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $message = '交易記錄新增失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

$stmt = $db->prepare("SELECT year, month, total_income, total_expense FROM monthly_summary_view WHERE user_id = ? ORDER BY year, month");
$stmt->execute([$user_id]);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = $income_data = $expense_data = [];
foreach ($monthly_data as $row) {
    $labels[] = $row['year'] . '-' . sprintf('%02d', $row['month']);
    $income_data[] = $row['total_income'];
    $expense_data[] = $row['total_expense'];
}
if (empty($monthly_data)) {
    $msg = "未找到該用戶的每月摘要資料。";
    $labels = ['2025-01'];
    $income_data = [0];
    $expense_data = [0];
}

$stmt = $db->prepare("SELECT transaction_date, type, category_name, amount, description FROM user_transaction_history WHERE user_name = ? ORDER BY transaction_date DESC, created_at DESC LIMIT 10");
$stmt->execute([$user_name]);
$latest_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 儲蓄目標 Active 中
$stmt = $db->prepare("SELECT goal_name, target_amount, current_amount, remaining_days 
                      FROM saving_goal_status 
                      WHERE user_name = ? AND status = 'Active' 
                      ORDER BY remaining_days ASC 
                      LIMIT 2");
$stmt->execute([$user_name]);
$saving_goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 查詢總資產信息
try {
    $stmt = $db->prepare("SELECT total_assets FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_assets = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_assets = $user_assets ? $user_assets['total_assets'] : 0;
} catch (Exception $e) {
    $total_assets = 0;
}

// 查詢該使用者的所有分類 - 分別查詢收入和支出
try {
    $stmt = $db->prepare("SELECT category_id, name FROM categories WHERE user_id = ? AND type = 'Income' ORDER BY name");
    $stmt->execute([$user_id]);
    $income_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT category_id, name FROM categories WHERE user_id = ? AND type = 'Expense' ORDER BY name");
    $stmt->execute([$user_id]);
    $expense_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $income_categories = [];
    $expense_categories = [];
    if (empty($message)) {
        $message = '載入分類資料失敗：' . $e->getMessage();
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
    <title>儀表板</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- 防止深色模式閃白的預處理腳本 -->
    <script>
        // 在頁面載入前就檢查並應用深色模式
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
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            padding-top: 15px;
            transition: all 0.3s ease;
        }
        
        /* 預設深色模式樣式，防止閃白 */
        .dark-mode {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
        
        .dark-mode body {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
        
        /* 深色模式樣式 */
        body.dark-mode {
            background-color: #121212;
            color: #e0e0e0;
        }
        
        .dark-mode .sidebar {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        
        .dark-mode .sidebar .nav-link {
            color: #c8c8c8;
        }
        
        .dark-mode .sidebar .nav-link:hover {
            background-color: #333333;
        }
        
        .dark-mode .card {
            background-color: #252525;
            color: #e0e0e0;
            border-color: #333;
        }
        
        .dark-mode .card-header {
            background-color: #252525;
            border-color: #333;
        }
        
        .dark-mode .welcome-card {
            background-color: #252525;
        }
        
        .dark-mode table {
            color: #e0e0e0;
        }
        
        .dark-mode .table {
            color: #e0e0e0 !important;
            background-color: #252525 !important;
        }
        
        .dark-mode .table-responsive {
            background-color: #252525 !important;
        }
        
        /* 強制設置表格每個單元格的背景色 */
        .dark-mode .table > tbody > tr > td,
        .dark-mode .table > tbody > tr > th,
        .dark-mode .table > tfoot > tr > td,
        .dark-mode .table > tfoot > tr > th,
        .dark-mode .table > thead > tr > td,
        .dark-mode .table > thead > tr > th {
            background-color: #252525 !important;
            border-color: #333 !important;
        }
        
        /* 表格頭部加強 */
        .dark-mode .table-light,
        .dark-mode .table > thead.table-light > tr > th,
        .dark-mode .table > thead > tr.table-light > th {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
            border-color: #333 !important;
        }
        
        /* 懸停效果加強 */
        .dark-mode .table-hover > tbody > tr:hover > *,
        .dark-mode .table-hover > tbody > tr:hover {
            background-color: #333 !important;
            color: #fff !important;
        }
        
        /* 表格卡片容器 */
        .dark-mode .card .table-responsive,
        .dark-mode .card .table {
            background-color: #252525 !important;
        }
        
        /* 表格內文字顏色 */
        .dark-mode .card .table td,
        .dark-mode .card .table th {
            color: #e0e0e0 !important;
        }
        
        /* 調整表格內文字和徽章 */
        .dark-mode .text-muted.small {
            color: #adb5bd !important;
        }
        
        .dark-mode .badge.bg-success {
            background-color: #198754 !important;
        }
        
        .dark-mode .badge.bg-danger {
            background-color: #dc3545 !important;
        }
        
        /* 改善表格空數據時的樣式 */
        .dark-mode .text-center.p-4 i, 
        .dark-mode .text-center.p-4 p {
            color: #6c757d !important;
        }
        
        /* 確保在深色模式下卡片和表格頭部一致 */
        .dark-mode .card-header {
            border-bottom-color: #333 !important;
        }
        
        /* 深色模式下進度條樣式調整 */
        .dark-mode .progress {
            background-color: #333 !important;
        }
        
        .dark-mode .progress-bar {
            box-shadow: 0 0 5px rgba(255, 255, 255, 0.1);
        }
        
        .dark-mode .goal-title {
            color: #e0e0e0;
        }
        
        .dark-mode .goal-info {
            color: #adb5bd !important;
        }
        
        /* 確保 danger, warning, success 顏色在深色模式下更飽和 */
        .dark-mode .progress-bar.bg-danger {
            background-color: #dc3545 !important;
        }
        
        .dark-mode .progress-bar.bg-warning {
            background-color: #ffc107 !important;
        }
        
        .dark-mode .progress-bar.bg-success {
            background-color: #28a745 !important;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            $current_page = 'dashboard'; // 設定當前頁面
            include 'sidebar.php'; 
            ?>
            
            <!-- 主要內容區 -->
            <div class="col-md-10">
                <div class="card mb-4 border-0 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="p-4 p-md-5" style="background: linear-gradient(135deg,rgb(215, 98, 233), #0d6efd); border-radius: 10px; color: white;">
                            <div class="row">
                                <!-- 歡迎訊息部分 -->
                                <div class="col-md-7 mb-4 mb-md-0">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-person-circle fs-3 me-3"></i>
                                        <h3 class="m-0 fw-bold fs-1">歡迎回來，<?= htmlspecialchars($user_name) ?></h3>
                                    </div>
                                    <p class="mb-3 opacity-75"><?= date('Y年m月d日') ?> | <?= date('l') ?></p>
                                    
                                    <!-- 總資產顯示 -->
                                    <div class="mb-4 p-3 rounded-3" style="background: rgba(255,255,255,0.85); color: #212529;">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <span class="bg-primary bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
                                                    <i class="bi bi-wallet2 fs-2 text-primary"></i>
                                                </span>
                                                <div>
                                                    <h6 class="mb-0" style="color:#6c757d;">總資產</h6>
                                                    <h2 class="mb-0 fw-bold" style="color:#0d6efd;">$<?= number_format($total_assets, 0) ?></h2>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <?php if ($total_assets >= 0): ?>
                                                    <i class="bi bi-trend-up fs-3 text-success"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-trend-down fs-3 text-warning"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button class="btn btn-light me-2 rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                            <i class="bi bi-plus-lg"></i> 新增交易
                                        </button>
                                        <a href="report.php" class="btn btn-outline-light rounded-pill px-4">
                                            <i class="bi bi-bar-chart"></i> 檢視報表
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- 本月概覽部分 -->
                                <div class="col-md-5">
                                    <div class="p-3 p-md-4 bg-white bg-opacity-10 rounded-3">
                                        <h1 class="text-center fw-bold mb-2">本月概覽</h1>
                                        
                                        <!-- 收入卡片 -->
                                        <div class="card mb-3 border-0 shadow-sm bg-white bg-opacity-10">
                                            <div class="card-body p-0">
                                                <div class="d-flex align-items-center">
                                                    <div class="p-3 bg-white bg-opacity-25 rounded-start" style="height: 100%;">
                                                        <i class="bi bi-arrow-down-circle text-white fs-4"></i>
                                                    </div>
                                                    <div class="p-3 flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="fw-medium text-white fw-bold fs-3">收入</span>
                                                            <span class="text-white fw-bold fs-3">
                                                                $<?= number_format(end($income_data) ?: 0, 0) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- 支出卡片 -->
                                        <div class="card mb-3 border-0 shadow-sm bg-white bg-opacity-10">
                                            <div class="card-body p-0">
                                                <div class="d-flex align-items-center">
                                                    <div class="p-3 bg-white bg-opacity-25 rounded-start" style="height: 100%;">
                                                        <i class="bi bi-arrow-up-circle text-white fs-4"></i>
                                                    </div>
                                                    <div class="p-3 flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="fw-medium text-white fw-bold fs-3">支出</span>
                                                            <span class="text-white fw-bold fs-3">
                                                                $<?= number_format(end($expense_data) ?: 0, 0) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php
                                        $balance = (end($income_data) ?: 0) - (end($expense_data) ?: 0);
                                        $balanceIcon = $balance >= 0 ? 'bi-piggy-bank' : 'bi-exclamation-triangle';
                                        ?>
                                        
                                        <!-- 結餘部分 -->
                                        <div class="pt-2 border-top border-white border-opacity-25">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-medium text-white fs-3">本月結餘</span>
                                                <span class="text-white fw-bold fs-3">
                                                    <i class="bi <?= $balanceIcon ?> me-1"></i>
                                                    $<?= number_format(abs($balance), 0) ?>
                                                    <?= $balance >= 0 ? '' : ' (赤字)' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($msg)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- 收支圖表 -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>每月收支概覽</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary">月</button>
                            <button type="button" class="btn btn-outline-secondary">季</button>
                            <button type="button" class="btn btn-outline-secondary">年</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="myChart" height="300"></canvas>
                    </div>
                </div>
                
                <div class="row">
                    <!-- 儲蓄目標進度 -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">儲蓄目標進度</div>
                            <div class="card-body">
                                <?php if (!empty($saving_goals)): ?>
                                    <?php foreach ($saving_goals as $goal): ?>
                                        <?php
                                            $rate = $goal['target_amount'] > 0
                                                ? round($goal['current_amount'] / $goal['target_amount'] * 100, 2)
                                                : 0;
                                            $rate = min($rate, 100);
                                            
                                            if ($rate < 30) {
                                                $barColor = 'danger';
                                            } elseif ($rate < 70) {
                                                $barColor = 'warning';
                                            } else {
                                                $barColor = 'success';
                                            }
                                        ?>
                                        <div class="mb-4">
                                            <div class="goal-title d-flex justify-content-between">
                                                <span><?= htmlspecialchars($goal['goal_name']) ?></span>
                                                <span><?= $rate ?>%</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-<?= $barColor ?>" role="progressbar" style="width: <?= $rate ?>%" aria-valuenow="<?= $rate ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <div class="goal-info">
                                                <span>$<?= number_format($goal['current_amount'], 0) ?> / $<?= number_format($goal['target_amount'], 0) ?></span>
                                                <span>剩餘<?= $goal['remaining_days'] ?>天</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-center text-muted my-4">
                                        <i class="bi bi-piggy-bank fs-1 d-block mb-3"></i>
                                        尚無啟用中的儲蓄目標
                                    </p>
                                    <div class="text-center">
                                        <a href="save_goal.php" class="btn btn-sm btn-outline-primary">新增儲蓄目標</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 最近交易 -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>最近交易記錄</span>
                                <a href="transactions.php" class="btn btn-sm btn-outline-primary">查看全部</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($latest_transactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>日期</th>
                                                    <th>類型</th>
                                                    <th>分類</th>
                                                    <th>金額</th>
                                                    <th>備註</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($latest_transactions as $tx): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($tx['transaction_date']) ?></td>
                                                        <td>
                                                            <span class="badge <?= $tx['type'] === 'Income' ? 'bg-success' : 'bg-danger' ?>">
                                                                <?= $tx['type'] === 'Income' ? '收入' : ($tx['type'] === 'Expense' ? '支出' : $tx['type']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="small"><?= htmlspecialchars($tx['category_name']) ?></span>
                                                        </td>
                                                        <td class="<?= $tx['type'] === 'Income' ? 'text-success' : 'text-danger' ?>">
                                                            <?= $tx['type'] === 'Income' ? '+' : '-' ?> $<?= number_format($tx['amount'], 0) ?>
                                                        </td>
                                                        <td class="text-muted small"><?= htmlspecialchars($tx['description']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-4">
                                        <i class="bi bi-journal-x fs-1 text-muted d-block mb-3"></i>
                                        <p class="text-muted">尚無交易紀錄</p>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">新增交易</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 新增交易 Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新增交易</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="transactionForm">
                        <div class="mb-3">
                            <label for="type" class="form-label">類型</label>
                            <select class="form-select" id="type" name="type" required onchange="filterCategories()">
                                <option value="">請選擇類型</option>
                                <option value="Income">收入</option>
                                <option value="Expense">支出</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">金額</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="amount" name="amount" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_id" class="form-label">分類</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">請先選擇交易類型</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="transaction_date" class="form-label">日期</label>
                            <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">備註</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="輸入交易備註（選填）"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="submitTransaction()">
                        <i class="bi bi-check-lg me-1"></i>新增
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 從 PHP 傳遞分類資料到 JavaScript
    const incomeCategories = <?= json_encode($income_categories) ?>;
    const expenseCategories = <?= json_encode($expense_categories) ?>;
    
    // 確保頁面載入時就應用正確的主題
    document.addEventListener('DOMContentLoaded', function() {
        // 確保主題正確應用
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
                    delay: <?= $message_type === 'success' ? '2000' : '4000' ?> // 縮短顯示時間
                });
                toast.show();
                
                // 如果是成功訊息，Toast 隱藏後重新載入頁面
                <?php if ($message_type === 'success'): ?>
                    toastElement.addEventListener('hidden.bs.toast', function() {
                        // 使用 replace 避免在瀏覽器歷史中留下記錄
                        window.location.replace('dashboard.php');
                    });
                <?php endif; ?>
            }
        });
    <?php endif; ?>
    
    // 根據選擇的類型篩選分類
    function filterCategories() {
        const typeSelect = document.getElementById('type');
        const categorySelect = document.getElementById('category_id');
        const selectedType = typeSelect.value;
        
        // 清空分類選項
        categorySelect.innerHTML = '';
        
        if (selectedType === '') {
            // 如果沒有選擇類型，顯示提示
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = '請先選擇交易類型';
            defaultOption.disabled = true;
            defaultOption.selected = true;
            categorySelect.appendChild(defaultOption);
            return;
        }
        
        // 根據選擇的類型載入對應的分類
        const categories = selectedType === 'Income' ? incomeCategories : expenseCategories;
        
        if (categories.length === 0) {
            // 如果沒有該類型的分類，顯示提示
            const noOption = document.createElement('option');
            noOption.value = '';
            noOption.textContent = `尚無${selectedType === 'Income' ? '收入' : '支出'}分類，請先到交易記錄頁面新增分類`;
            noOption.disabled = true;
            noOption.selected = true;
            categorySelect.appendChild(noOption);
        } else {
            // 添加預設選項
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = '請選擇分類';
            defaultOption.disabled = true;
            defaultOption.selected = true;
            categorySelect.appendChild(defaultOption);
            
            // 載入分類選項
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.category_id;
                option.textContent = category.name;
                categorySelect.appendChild(option);
            });
        }
    }
    
    // 提交交易表單
    function submitTransaction() {
        const form = document.getElementById('transactionForm');
        const submitBtn = event.target;
        
        // 驗證表單
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // 顯示載入狀態
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>處理中...';
        
        form.submit();
    }
    
    // 當頁面載入完成時初始化
    document.addEventListener('DOMContentLoaded', function() {
        // 初始化分類選項
        filterCategories();
        
        // 當模態框顯示時重置表單
        document.getElementById('addTransactionModal').addEventListener('show.bs.modal', function () {
            const form = document.getElementById('transactionForm');
            form.reset();
            document.getElementById('transaction_date').value = '<?= date('Y-m-d') ?>';
            filterCategories();
            
            // 重置按鈕狀態
            const submitBtn = document.querySelector('#addTransactionModal .btn-primary');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>新增';
        });
    });
    
    // 收支圖表
    const data = {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            { 
                label: '總收入', 
                data: <?= json_encode($income_data) ?>, 
                backgroundColor: 'rgba(40, 167, 69, 0.5)',
                borderColor: 'rgb(40, 167, 69)',
                borderWidth: 1
            },
            { 
                label: '總支出', 
                data: <?= json_encode($expense_data) ?>, 
                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                borderColor: 'rgb(220, 53, 69)',
                borderWidth: 1
            }
        ]
    };
    
    // 更新圖表顏色
    function updateChartColors(isDarkMode) {
        const chart = Chart.getChart('myChart');
        if (!chart) return;
        
        if (isDarkMode) {
            // 深色模式圖表顏色
            chart.options.scales.y.ticks.color = '#e0e0e0';
            chart.options.scales.x.ticks.color = '#e0e0e0';
            chart.options.plugins.legend.labels.color = '#e0e0e0';
        } else {
            // 淺色模式圖表顏色
            chart.options.scales.y.ticks.color = '#666';
            chart.options.scales.x.ticks.color = '#666';
            chart.options.plugins.legend.labels.color = '#666';
        }
        chart.update();
    }
    
    // 圖表初始化
    const config = {
        type: 'bar',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('zh-TW', { 
                                    style: 'currency', 
                                    currency: 'TWD',
                                    currencyDisplay: 'symbol'
                                }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    };
    const myChart = new Chart(document.getElementById('myChart'), config);
    </script>
</body>
</html>
