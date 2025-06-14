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

// 自動更新預算支出金額的函數
function updateBudgetSpentAmounts($db, $user_id) {
    try {
        // 更新所有該用戶的預算支出金額
        $update_sql = "
            UPDATE budgets b 
            SET spent_amount = (
                SELECT COALESCE(SUM(t.amount), 0)
                FROM transactions t 
                WHERE t.user_id = b.user_id 
                AND t.category_id = b.category_id 
                AND YEAR(t.transaction_date) = b.year 
                AND MONTH(t.transaction_date) = b.month 
                AND t.type = 'Expense'
            )
            WHERE b.user_id = ?
        ";
        
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->execute([$user_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("更新預算支出金額失敗: " . $e->getMessage());
        return false;
    }
}

// 手動同步預算數據（當用戶點擊同步按鈕時）
if (isset($_POST['sync_budgets'])) {
    try {
        if (updateBudgetSpentAmounts($db, $user_id)) {
            $message = '預算數據同步成功！所有支出金額已更新至最新狀態。';
            $message_type = 'success';
            $show_toast = true;
        } else {
            throw new Exception('預算數據同步過程中發生錯誤');
        }
    } catch (Exception $e) {
        $message = '同步失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 初始化訊息變數
$message = $message ?? '';
$message_type = $message_type ?? '';
$show_toast = $show_toast ?? false;

// 取得所有 Expense 類別分類
try {
    $cat_stmt = $db->prepare("SELECT category_id, name FROM categories WHERE user_id = ? AND type = 'Expense' ORDER BY name");
    $cat_stmt->execute([$user_id]);
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
    if (empty($message)) {
        $message = '載入分類失敗：' . $e->getMessage();
        $message_type = 'warning';
        $show_toast = true;
    }
}

// 刪除預算
if (isset($_GET['delete']) && isset($_GET['category_id']) && isset($_GET['year']) && isset($_GET['month'])) {
    try {
        $delete_id = intval($_GET['delete']);
        $category_id = intval($_GET['category_id']);
        $year = intval($_GET['year']);
        $month = intval($_GET['month']);
        
        // 驗證預算是否屬於當前用戶
        $check_stmt = $db->prepare("SELECT category_name FROM monthly_budget_summary WHERE user_id = ? AND category_id = ? AND year = ? AND month = ?");
        $check_stmt->execute([$user_id, $category_id, $year, $month]);
        $budget_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$budget_info) {
            throw new Exception('找不到該預算紀錄或您沒有權限刪除');
        }
        
        $del_stmt = $db->prepare("DELETE FROM budgets WHERE user_id = ? AND category_id = ? AND year = ? AND month = ?");
        $del_stmt->execute([$user_id, $category_id, $year, $month]);
        
        $_SESSION['delete_success'] = true;
        $_SESSION['deleted_budget'] = $budget_info['category_name'];
        header("Location: budget.php");
        exit;
        
    } catch (Exception $e) {
        $message = '刪除失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 檢查是否有刪除成功的 session 訊息
if (isset($_SESSION['delete_success']) && $_SESSION['delete_success']) {
    $deleted_name = $_SESSION['deleted_budget'] ?? '預算';
    $message = '預算「' . $deleted_name . '」已成功刪除！';
    $message_type = 'success';
    $show_toast = true;
    unset($_SESSION['delete_success']);
    unset($_SESSION['deleted_budget']);
}

// 新增預算紀錄
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['sync_budgets'])) {
    try {
        $category_id = $_POST['category_id'] ?? null;
        $year = $_POST['year'] ?? null;
        $month = $_POST['month'] ?? null;
        $amount = $_POST['amount'] ?? null;

        if (!$category_id || !$year || !$month || !is_numeric($amount) || $amount < 0) {
            throw new Exception('請填寫所有欄位且金額需為非負值');
        }
        
        // 檢查分類是否屬於該用戶
        $check_cat = $db->prepare("SELECT name FROM categories WHERE category_id = ? AND user_id = ? AND type = 'Expense'");
        $check_cat->execute([$category_id, $user_id]);
        $category_info = $check_cat->fetch(PDO::FETCH_ASSOC);
        
        if (!$category_info) {
            throw new Exception('無效的分類選擇');
        }
        
        // 檢查是否已存在相同的預算設定
        $check_budget = $db->prepare("SELECT budget_limit FROM budgets WHERE user_id = ? AND category_id = ? AND year = ? AND month = ?");
        $check_budget->execute([$user_id, $category_id, $year, $month]);
        if ($check_budget->fetch()) {
            throw new Exception('該分類在 ' . $year . ' 年 ' . $month . ' 月的預算已存在，請選擇其他月份或編輯現有預算');
        }

        // 計算該月份該分類的實際支出
        $spent_stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) as spent_amount 
            FROM transactions  t
            WHERE user_id = ? AND category_id = ? AND YEAR(t.transaction_date) = ? AND MONTH(t.transaction_date) = ? AND type = 'Expense'
        ");
        $spent_stmt->execute([$user_id, $category_id, $year, $month]);
        $spent_result = $spent_stmt->fetch(PDO::FETCH_ASSOC);
        $spent_amount = $spent_result['spent_amount'] ?? 0;

        // 插入新預算，同時設定支出金額
        $stmt = $db->prepare("INSERT INTO budgets (user_id, category_id, year, month, budget_limit, spent_amount) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $category_id, $year, $month, $amount, $spent_amount]);
        
        $message = '預算設定成功！分類：' . $category_info['name'] . '，預算：$' . number_format($amount) . '，目前支出：$' . number_format($spent_amount);
        $message_type = 'success';
        $show_toast = true;
        
    } catch (Exception $e) {
        $message = '新增失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 自動更新所有預算的支出金額（每次載入頁面時）
updateBudgetSpentAmounts($db, $user_id);

// 查詢現有預算紀錄
try {
    $stmt = $db->prepare("SELECT * FROM monthly_budget_summary WHERE user_id = ? ORDER BY year DESC, month DESC, category_name");
    $stmt->execute([$user_id]);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $budgets = [];
    if (empty($message)) {
        $message = '載入預算資料失敗：' . $e->getMessage();
        $message_type = 'warning';
        $show_toast = true;
    }
}

// 計算預算統計
$total_budgets = count($budgets);
$over_budget_count = 0;
$total_budget_amount = 0;
$total_spent_amount = 0;
$last_sync_time = date('Y-m-d H:i:s');

foreach ($budgets as $budget) {
    $total_budget_amount += $budget['budget_limit'];
    $total_spent_amount += $budget['spent_amount'];
    if ($budget['spent_amount'] > $budget['budget_limit']) {
        $over_budget_count++;
    }
}

// 檢查是否有過期的預算數據需要提醒用戶同步
$need_sync_check = false;
try {
    $sync_check_stmt = $db->prepare("
        SELECT COUNT(*) as outdated_count 
        FROM budgets b
        LEFT JOIN (
            SELECT category_id, YEAR(date) as year, MONTH(date) as month, SUM(amount) as actual_spent
            FROM transactions 
            WHERE user_id = ? AND type = 'Expense'
            GROUP BY category_id, YEAR(date), MONTH(date)
        ) t ON b.category_id = t.category_id AND b.year = t.year AND b.month = t.month
        WHERE b.user_id = ? 
        AND (b.spent_amount != COALESCE(t.actual_spent, 0))
    ");
    $sync_check_stmt->execute([$user_id, $user_id]);
    $sync_result = $sync_check_stmt->fetch(PDO::FETCH_ASSOC);
    $need_sync_check = ($sync_result['outdated_count'] > 0);
} catch (Exception $e) {
    // 如果檢查失敗，不影響主要功能
    $need_sync_check = false;
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>預算管理</title>
    <link rel="icon" type="image/png" href="icon.png">
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
        /* 同步狀態指示器 */
        .sync-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(0, 0, 0, 0.1);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .sync-indicator.outdated {
            background: rgba(239, 68, 68, 0.95);
            color: white;
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .sync-indicator.up-to-date {
            background: rgba(16, 185, 129, 0.95);
            color: white;
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .sync-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: inherit;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .sync-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: inherit;
        }
        
        /* 預算卡片樣式 */
        .budget-card {
            border: none;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 2rem;
            overflow: hidden;
            position: relative;
            background: #ffffff;
        }
        
        .budget-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .budget-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--progress-color), var(--progress-color-light));
            opacity: 0.8;
        }
        
        .budget-normal {
            --progress-color: #10b981;
            --progress-color-light: #34d399;
            --card-bg: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
        }
        
        .budget-warning {
            --progress-color: #f59e0b;
            --progress-color-light: #fbbf24;
            --card-bg: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        }
        
        .budget-danger {
            --progress-color: #ef4444;
            --progress-color-light: #f87171;
            --card-bg: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        }
        
        .budget-header {
            padding: 2rem 2rem 1rem;
            background: var(--card-bg);
            position: relative;
        }
        
        .budget-icon-wrapper {
            width: 70px;
            height: 70px;
            border-radius: 24px;
            background: var(--progress-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .budget-icon-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%);
        }
        
        .budget-icon {
            font-size: 2rem;
            color: white;
            z-index: 1;
        }
        
        .budget-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .budget-period {
            font-size: 1rem;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .budget-amounts {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .amount-item {
            text-align: center;
        }
        
        .amount-value {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .amount-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .spent-amount { color: var(--progress-color); }
        .budget-amount { color:rgb(255, 255, 255); }
        .remaining-amount { color: #059669; }
        .over-amount { color: #dc2626; }
        
        /* 進度條樣式 */
        .custom-progress {
            height: 12px;
            background: #f3f4f6;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .custom-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--progress-color), var(--progress-color-light));
            border-radius: 8px;
            transition: width 1s ease;
            position: relative;
        }
        
        .custom-progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .progress-percentage {
            font-size: 1rem;
            font-weight: 700;
            color: var(--progress-color);
            text-align: center;
        }
        
        /* 統計卡片 */
        .stat-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        
        .total-budgets-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }
        
        .over-budget-card {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .budget-usage-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        /* 頁面標題 */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            color: white;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 40%;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        /* 表單樣式 */
        .form-card {
            border: none;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            background: white;
        }
        
        .form-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 24px 24px 0 0;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-body {
            padding: 2rem;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* 動作按鈕 */
        .action-buttons {
            position: absolute;
            top: 2rem;
            right: 2rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: none;
            background: rgba(255, 255, 255, 0.9);
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .action-btn:hover {
            background: white;
            color: #dc2626;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        /* 空狀態 */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 24px;
            margin: 2rem 0;
        }
        
        .empty-icon {
            font-size: 5rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }
        
        /* 同步提醒橫幅 */
        .sync-banner {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        .sync-banner-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sync-banner-icon {
            font-size: 1.5rem;
        }
        
        /* 深色模式適配 */
        .dark-mode .budget-card {
            background: #1f2937;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .budget-normal {
            --card-bg: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
        }
        
        .dark-mode .budget-warning {
            --card-bg: linear-gradient(135deg, #451a03 0%, #78350f 100%);
        }
        
        .dark-mode .budget-danger {
            --card-bg: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
        }
        
        .dark-mode .budget-title {
            color: #f9fafb;
        }
        
        .dark-mode .budget-period {
            color: #d1d5db;
        }
        
        .dark-mode .amount-label {
            color: #d1d5db;
        }
        
        .dark-mode .stat-card {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .form-card {
            background: #1f2937;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .form-header {
            background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
            border-color: #4b5563;
        }
        
        .dark-mode .empty-state {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        }
        
        .dark-mode .action-btn {
            background: rgba(0, 0, 0, 0.3);
            color: #d1d5db;
        }
        
        .dark-mode .action-btn:hover {
            background: rgba(0, 0, 0, 0.5);
            color: #ef4444;
        }
        
        .dark-mode .sync-indicator {
            background: rgba(31, 41, 55, 0.95);
            border-color: rgba(75, 85, 99, 0.3);
            color: #d1d5db;
        }
        
        /* 響應式設計 */
        @media (max-width: 768px) {
            .budget-header {
                padding: 1.5rem;
            }
            
            .budget-amounts {
                flex-direction: column;
                gap: 1rem;
            }
            
            .action-buttons {
                position: relative;
                top: auto;
                right: auto;
                justify-content: center;
                margin-top: 1rem;
            }
            
            .page-header {
                padding: 2rem 1.5rem;
            }
            
            .sync-indicator {
                bottom: 80px;
                right: 10px;
                left: 10px;
                width: auto;
            }
            
            .sync-banner {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
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

    <!-- 同步狀態指示器 -->
    <div class="sync-indicator <?= $need_sync_check ? 'outdated' : 'up-to-date' ?>">
        <i class="bi bi-<?= $need_sync_check ? 'exclamation-triangle' : 'check-circle' ?>"></i>
        <span><?= $need_sync_check ? '數據需要同步' : '數據已是最新' ?></span>
        <small class="opacity-75">最後更新: <?= date('H:i') ?></small>
        <?php if ($need_sync_check): ?>
            <form method="POST" class="d-inline ms-2">
                <button type="submit" name="sync_budgets" class="sync-btn">
                    <i class="bi bi-arrow-clockwise me-1"></i>同步
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- 側邊欄 -->
            <?php 
            $current_page = 'budget';
            include 'sidebar.php'; 
            ?>
            
            <!-- 主要內容區 -->
            <div class="col-md-10">
                <!-- 頁面標題 -->
                <div class="page-header">
                    <div class="header-content">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="mb-2">
                                    <i class="bi bi-piggy-bank me-3"></i>預算管理
                                </h1>
                                <p class="mb-0 opacity-75 fs-5">
                                    設定和監控您的支出預算，掌握財務狀況
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <div class="d-flex gap-2 justify-content-md-end justify-content-center">
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="sync_budgets" class="btn btn-outline-light btn-lg" title="同步預算數據" >
                                            <i class="bi bi-arrow-clockwise me-2"></i>同步數據
                                        </button>
                                    </form>
                                    <button class="btn btn-light btn-lg" data-bs-toggle="collapse" data-bs-target="#budgetForm" aria-expanded="false">
                                        <i class="bi bi-plus-circle me-2"></i>新增預算
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 同步提醒橫幅 -->
                <?php if ($need_sync_check): ?>
                <div class="sync-banner">
                    <div class="sync-banner-content">
                        <i class="bi bi-exclamation-triangle sync-banner-icon"></i>
                        <div>
                            <strong>預算數據需要同步</strong>ㄒ
                            <div class="small">您的交易紀錄已更新，但預算支出金額尚未同步，請點擊同步按鈕更新數據。</div>
                        </div>
                    </div>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="sync_budgets" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-clockwise me-1"></i>立即同步
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- 統計概覽 -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card total-budgets-card">
                            <div class="card-body text-center">
                                <i class="bi bi-list-ul display-4 mb-3"></i>
                                <h3 class="mb-2"><?= $total_budgets ?></h3>
                                <p class="mb-0">設定預算數</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card over-budget-card">
                            <div class="card-body text-center">
                                <i class="bi bi-exclamation-triangle display-4 mb-3"></i>
                                <h3 class="mb-2"><?= $over_budget_count ?></h3>
                                <p class="mb-0">超支預算數</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card budget-usage-card">
                            <div class="card-body text-center">
                                <i class="bi bi-graph-up display-4 mb-3"></i>
                                <h3 class="mb-2">
                                    <?= $total_budget_amount > 0 ? number_format(($total_spent_amount / $total_budget_amount) * 100, 1) : 0 ?>%
                                </h3>
                                <p class="mb-0">總體預算使用率</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 新增預算表單 -->
                <div class="collapse mb-4" id="budgetForm">
                    <div class="form-card">
                        <div class="form-header">
                            <h5 class="mb-0">
                                <i class="bi bi-plus-circle me-2"></i>新增預算設定
                            </h5>
                        </div>
                        <div class="form-body">
                            <?php if (empty($categories)): ?>
                                <div class="alert alert-warning" role="alert">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    您還沒有建立任何支出分類，請先到 <a href="report.php" class="alert-link">財務報表</a> 頁面新增支出分類。
                                </div>
                            <?php else: ?>
                                <form method="POST" id="budgetForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="category_id" class="form-label">
                                                <i class="bi bi-tag me-1"></i>支出分類 <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="category_id" name="category_id" required>
                                                <option value="">請選擇分類</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="year" class="form-label">
                                                <i class="bi bi-calendar me-1"></i>年份 <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" class="form-control" id="year" name="year" 
                                                   value="<?= date('Y') ?>" min="<?= date('Y') - 1 ?>" max="<?= date('Y') + 2 ?>" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="month" class="form-label">
                                                <i class="bi bi-calendar-month me-1"></i>月份 <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="month" name="month" required>
                                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                                    <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= $m ?>月</option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="amount" class="form-label">
                                                <i class="bi bi-currency-dollar me-1"></i>預算金額 <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" class="form-control" id="amount" name="amount" 
                                                   step="0.01" min="0" required placeholder="輸入預算金額">
                                        </div>
                                        <div class="col-md-6 mb-3 d-flex align-items-end">
                                            <div class="d-flex gap-2 w-100">
                                                <button type="submit" class="btn btn-primary flex-fill">
                                                    <i class="bi bi-check-lg me-2"></i>儲存預算
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#budgetForm">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 預算列表 -->
                <?php if (!empty($budgets)): ?>
                    <div class="row">
                        <?php foreach ($budgets as $budget): 
                            $percentage = ($budget['budget_limit'] > 0) ? min(100, round(($budget['spent_amount'] / $budget['budget_limit']) * 100)) : 0;
                            $remaining = $budget['budget_limit'] - $budget['spent_amount'];
                            $card_class = $percentage >= 100 ? 'budget-danger' : ($percentage >= 80 ? 'budget-warning' : 'budget-normal');
                        ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="budget-card <?= $card_class ?>">
                                <div class="budget-header">
                                    <div class="budget-icon-wrapper">
                                        <i class="bi bi-<?= $percentage >= 100 ? 'exclamation-triangle' : ($percentage >= 80 ? 'exclamation-circle' : 'check-circle') ?> budget-icon"></i>
                                    </div>
                                    
                                    <div class="budget-title"><?= htmlspecialchars($budget['category_name']) ?></div>
                                    <div class="budget-period">
                                        <i class="bi bi-calendar-event me-1"></i>
                                        <?= $budget['year'] ?> 年 <?= $budget['month'] ?> 月
                                    </div>
                                    
                                    <div class="action-buttons">
                                        <button class="action-btn" 
                                                onclick="confirmDelete(<?= $budget['category_id'] ?>, <?= $budget['year'] ?>, <?= $budget['month'] ?>, '<?= htmlspecialchars($budget['category_name']) ?>')"
                                                title="刪除預算">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="px-4">
                                    <div class="budget-amounts">
                                        <div class="amount-item">
                                            <div class="amount-value spent-amount">$<?= number_format($budget['spent_amount'], 0) ?></div>
                                            <div class="amount-label">已支出</div>
                                        </div>
                                        <div class="amount-item">
                                            <div class="amount-value budget-amount">$<?= number_format($budget['budget_limit'], 0) ?></div>
                                            <div class="amount-label">預算</div>
                                        </div>
                                        <div class="amount-item">
                                            <div class="amount-value <?= $remaining >= 0 ? 'remaining-amount' : 'over-amount' ?>">
                                                <?= $remaining >= 0 ? '$' . number_format($remaining, 0) : '-$' . number_format(abs($remaining), 0) ?>
                                            </div>
                                            <div class="amount-label"><?= $remaining >= 0 ? '剩餘' : '超支' ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="custom-progress">
                                        <div class="custom-progress-bar" style="width: <?= min($percentage, 100) ?>%;"></div>
                                    </div>
                                    
                                    <div class="progress-percentage mb-3">
                                        <?= $percentage ?>% 
                                        <?php if ($percentage >= 100): ?>
                                            <i class="bi bi-exclamation-triangle ms-1"></i>
                                        <?php elseif ($percentage >= 80): ?>
                                            <i class="bi bi-exclamation-circle ms-1"></i>
                                        <?php else: ?>
                                            <i class="bi bi-check-circle ms-1"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-piggy-bank"></i>
                        </div>
                        <h3 class="text-muted mb-3">尚無預算設定</h3>
                        <p class="text-muted mb-4">
                            開始設定您的支出預算，更好地管理財務狀況<br>
                            建議從主要支出分類開始設定預算
                        </p>
                        <div class="d-flex gap-2 justify-content-center">
                            <?php if (!empty($categories)): ?>
                                <button class="btn btn-primary btn-lg" data-bs-toggle="collapse" data-bs-target="#budgetForm">
                                    <i class="bi bi-plus-circle me-2"></i>設定第一個預算
                                </button>
                            <?php endif; ?>
                            <a href="report.php" class="btn btn-outline-primary btn-lg">
                                <i class="bi bi-tags me-2"></i>管理分類
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
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
                        delay: <?= $message_type === 'success' ? '3000' : '5000' ?>
                    });
                    toast.show();
                }
            });
        <?php endif; ?>
        
        // 確認刪除預算
        function confirmDelete(categoryId, year, month, categoryName) {
            const confirmMessage = `確定要刪除預算設定嗎？\n\n` +
                                 `分類：${categoryName}\n` +
                                 `期間：${year} 年 ${month} 月\n\n` +
                                 `此操作無法復原！`;
            
            if (confirm(confirmMessage)) {
                window.location.href = `budget.php?delete=1&category_id=${categoryId}&year=${year}&month=${month}`;
            }
        }
        
        // 表單重置
        document.addEventListener('DOMContentLoaded', function() {
            const budgetFormCollapse = document.getElementById('budgetForm');
            if (budgetFormCollapse) {
                budgetFormCollapse.addEventListener('show.bs.collapse', function () {
                    const form = document.querySelector('#budgetForm form');
                    if (form) {
                        // 重置到當前年月
                        document.getElementById('year').value = new Date().getFullYear();
                        document.getElementById('month').value = new Date().getMonth() + 1;
                    }
                });
            }
        });
        
        // 表單驗證
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#budgetForm form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.name) { // 避免影響同步按鈕
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>處理中...';
                    }
                });
            }
        });
        
        // 自動同步檢查
        let autoSyncInterval;
        
        function startAutoSyncCheck() {
            // 每5分鐘檢查一次是否需要同步
            autoSyncInterval = setInterval(function() {
                fetch('budget.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'check_sync_status=1'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.needSync) {
                        updateSyncIndicator(true);
                    }
                })
                .catch(error => {
                    console.log('自動同步檢查失敗:', error);
                });
            }, 300000); // 5分鐘 = 300000毫秒
        }
        
        function updateSyncIndicator(needSync) {
            const indicator = document.querySelector('.sync-indicator');
            if (indicator) {
                if (needSync) {
                    indicator.classList.remove('up-to-date');
                    indicator.classList.add('outdated');
                    indicator.innerHTML = `
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>數據需要同步</span>
                        <small class="opacity-75">最後更新: ${new Date().toLocaleTimeString('zh-TW', {hour: '2-digit', minute: '2-digit'})}</small>
                        <form method="POST" class="d-inline ms-2">
                            <button type="submit" name="sync_budgets" class="sync-btn">
                                <i class="bi bi-arrow-clockwise me-1"></i>同步
                            </button>
                        </form>
                    `;
                } else {
                    indicator.classList.remove('outdated');
                    indicator.classList.add('up-to-date');
                    indicator.innerHTML = `
                        <i class="bi bi-check-circle"></i>
                        <span>數據已是最新</span>
                        <small class="opacity-75">最後更新: ${new Date().toLocaleTimeString('zh-TW', {hour: '2-digit', minute: '2-digit'})}</small>
                    `;
                }
            }
        }
        
        // 頁面載入時開始自動檢查
        document.addEventListener('DOMContentLoaded', function() {
            // 延遲開始，避免影響頁面載入性能
            setTimeout(startAutoSyncCheck, 10000); // 10秒後開始
        });
        
        // 頁面卸載時清除定時器
        window.addEventListener('beforeunload', function() {
            if (autoSyncInterval) {
                clearInterval(autoSyncInterval);
            }
        });
    </script>
</body>
</html>
