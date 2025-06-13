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

// 處理刪除分類
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    try {
        // 檢查分類是否屬於該用戶
        $check_stmt = $db->prepare("SELECT category_id, name, type FROM categories WHERE category_id = ? AND user_id = ?");
        $check_stmt->execute([$category_id, $user_id]);
        $category = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            throw new Exception('找不到該分類或您沒有權限刪除');
        }
        
        // 檢查是否有交易使用此分類
        $transaction_check = $db->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ? AND user_id = ?");
        $transaction_check->execute([$category_id, $user_id]);
        $transaction_count = $transaction_check->fetchColumn();
        
        if ($transaction_count > 0) {
            throw new Exception('此分類已被 ' . $transaction_count . ' 筆交易使用，無法刪除');
        }
        
        // 刪除分類
        $delete_stmt = $db->prepare("DELETE FROM categories WHERE category_id = ? AND user_id = ?");
        $delete_stmt->execute([$category_id, $user_id]);
        
        $_SESSION['delete_success'] = true;
        header("Location: report.php");
        exit;
        
    } catch (Exception $e) {
        $message = '刪除失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 檢查是否有刪除成功的 session 訊息
if (isset($_SESSION['delete_success']) && $_SESSION['delete_success']) {
    $message = '分類已成功刪除！';
    $message_type = 'success';
    $show_toast = true;
    unset($_SESSION['delete_success']);
}

// 新增分類
if (isset($_POST['new_category'])) {
    try {
        $new_cat_name = trim($_POST['new_cat_name']);
        $new_cat_type = $_POST['new_cat_type'];
        
        if (empty($new_cat_name)) {
            throw new Exception('分類名稱不能為空');
        }
        
        if (!in_array($new_cat_type, ['Income', 'Expense'])) {
            throw new Exception('請選擇正確的分類類型');
        }
        
        // 檢查是否已存在相同名稱的分類
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE user_id = ? AND name = ? AND type = ?");
        $check_stmt->execute([$user_id, $new_cat_name, $new_cat_type]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception('此分類名稱已存在');
        }
        
        $stmt = $db->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $new_cat_name, $new_cat_type]);
        
        $message = '分類新增成功！';
        $message_type = 'success';
        $show_toast = true;
    } catch (Exception $e) {
        $message = '分類新增失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 編輯分類
if (isset($_POST['edit_category'])) {
    try {
        $category_id = $_POST['category_id'];
        $edit_cat_name = trim($_POST['edit_cat_name']);
        
        if (empty($edit_cat_name)) {
            throw new Exception('分類名稱不能為空');
        }
        
        // 檢查分類是否屬於該用戶
        $check_stmt = $db->prepare("SELECT type FROM categories WHERE category_id = ? AND user_id = ?");
        $check_stmt->execute([$category_id, $user_id]);
        $category = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            throw new Exception('找不到該分類或您沒有權限編輯');
        }
        
        // 檢查是否已存在相同名稱的分類（排除自己）
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE user_id = ? AND name = ? AND type = ? AND category_id != ?");
        $check_stmt->execute([$user_id, $edit_cat_name, $category['type'], $category_id]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception('此分類名稱已存在');
        }
        
        $stmt = $db->prepare("UPDATE categories SET name = ? WHERE category_id = ? AND user_id = ?");
        $stmt->execute([$edit_cat_name, $category_id, $user_id]);
        
        $message = '分類更新成功！';
        $message_type = 'success';
        $show_toast = true;
    } catch (Exception $e) {
        $message = '分類更新失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 獲取當前月份和年份
$current_year = date('Y');
$current_month = date('m');
if (isset($_GET['year']) && isset($_GET['month'])) {
    $selected_year = intval($_GET['year']);
    $selected_month = intval($_GET['month']);
} else {
    $selected_year = $current_year;
    $selected_month = $current_month;
}

// 查詢月度財務報表資料
try {
    $stmt = $db->prepare("
        SELECT 
            total_income,
            total_expense
        FROM monthly_summary_view 
        WHERE user_id = ? 
        AND year = ? 
        AND month = ?
    ");
    $stmt->execute([$user_id, $selected_year, $selected_month]);
    $financial_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($financial_summary) {
        $financial_summary['net_income'] = $financial_summary['total_income'] - $financial_summary['total_expense'];
    } else {
        $financial_summary = [
            'total_income' => 0,
            'total_expense' => 0,
            'net_income' => 0
        ];
    }
} catch (Exception $e) {
    $financial_summary = [
        'total_income' => 0,
        'total_expense' => 0,
        'net_income' => 0
    ];
}

// 查詢分類詳細資料 - 使用 monthly_report_summary view
try {
    // 先獲取該用戶所有分類（包括沒有本月交易的分類）
    $all_categories_stmt = $db->prepare("
        SELECT 
            c.category_id,
            c.name as category_name,
            c.type,
            COALESCE(SUM(t.amount), 0) as total_amount,
            COUNT(t.transaction_id) as total_count
        FROM categories c
        LEFT JOIN transactions t ON c.category_id = t.category_id AND t.user_id = ?
        WHERE c.user_id = ?
        GROUP BY c.category_id, c.name, c.type
        ORDER BY c.type DESC, c.name ASC
    ");
    $all_categories_stmt->execute([$user_id, $user_id]);
    $all_user_categories = $all_categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 獲取該用戶在選定月份的分類統計
    $monthly_stmt = $db->prepare("
        SELECT 
            mrs.category_id,
            mrs.category_name,
            mrs.total_income,
            mrs.total_expense,
            mrs.category_expense,
            mrs.expense_percentage,
            CASE 
                WHEN mrs.total_income > 0 THEN 'Income'
                WHEN mrs.total_expense > 0 THEN 'Expense'
                ELSE 'Unknown'
            END as type
        FROM monthly_report_summary mrs
        WHERE mrs.user_id = ? 
        AND mrs.year = ? 
        AND mrs.month = ?
    ");
    $monthly_stmt->execute([$user_id, $selected_year, $selected_month]);
    $monthly_categories = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 建立月度分類資料的對應表
    $monthly_data = [];
    foreach ($monthly_categories as $monthly_cat) {
        $monthly_data[$monthly_cat['category_id']] = $monthly_cat;
    }
    
    // 合併所有分類資料
    $categories = [];
    foreach ($all_user_categories as $category) {
        $monthly_cat = $monthly_data[$category['category_id']] ?? null;
        
        // 查詢該分類在選定月份的交易筆數
        $month_count_stmt = $db->prepare("
            SELECT COUNT(transaction_id) as month_count
            FROM transactions 
            WHERE user_id = ? AND category_id = ? 
            AND YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?
        ");
        $month_count_stmt->execute([$user_id, $category['category_id'], $selected_year, $selected_month]);
        $month_count = $month_count_stmt->fetchColumn();
        
        $categories[] = [
            'category_id' => $category['category_id'],
            'name' => $category['category_name'],
            'type' => $category['type'],
            'month_total' => $monthly_cat ? ($monthly_cat['type'] === 'Income' ? $monthly_cat['total_income'] : $monthly_cat['total_expense']) : 0,
            'month_count' => $month_count,
            'total_amount' => $category['total_amount'],
            'total_count' => $category['total_count'],
            'expense_percentage' => $monthly_cat['expense_percentage'] ?? 0
        ];
    }
    
} catch (Exception $e) {
    $categories = [];
    if (empty($message)) {
        $message = '載入分類資料失敗：' . $e->getMessage();
        $message_type = 'warning';
        $show_toast = true;
    }
}

// 獲取可選的年月範圍
try {
    $stmt = $db->prepare("
        SELECT DISTINCT YEAR(transaction_date) as year, MONTH(transaction_date) as month 
        FROM transactions 
        WHERE user_id = ? 
        ORDER BY year DESC, month DESC
    ");
    $stmt->execute([$user_id]);
    $available_months = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $available_months = [];
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>財務報表</title>
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
        /* 財務報表卡片樣式 */
        .financial-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
            position: relative;
        }
        
        .financial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        
        .income-card {
            background: linear-gradient(135deg, #00c851 0%, #00b347 50%, #00a03e 100%);
            color: white;
        }
        
        .expense-card {
            background: linear-gradient(135deg, #ff4444 0%, #e53e3e 50%, #c53030 100%);
            color: white;
        }
        
        .net-income-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .net-income-card.negative {
            background: linear-gradient(135deg, #fd746c 0%, #ff9068 100%);
        }
        
        /* 分類卡片樣式 */
        .category-card {
            border: none;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 2rem;
            overflow: hidden;
            position: relative;
            background: #ffffff;
        }
        
        .category-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--card-accent), var(--card-accent-light));
            opacity: 0.8;
        }
        
        .category-income {
            --card-accent: #10b981;
            --card-accent-light: #34d399;
            --card-bg: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            background: var(--card-bg);
        }
        
        .category-expense {
            --card-accent: #ef4444;
            --card-accent-light: #f87171;
            --card-bg: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            background: var(--card-bg);
        }
        
        .category-header {
            padding: 1.5rem 1.5rem 1rem;
            position: relative;
        }
        
        .category-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 20px;
            background: var(--card-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .category-icon-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%);
        }
        
        .category-icon {
            font-size: 1.5rem;
            color: white;
            z-index: 1;
        }
        
        .category-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .category-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            padding: 0 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(10px);
        }
        
        .stat-value {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--card-accent);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .progress-section {
            padding: 0 1.5rem 1.5rem;
        }
        
        .expense-progress {
            background: #f3f4f6;
            border-radius: 12px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .expense-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--card-accent), var(--card-accent-light));
            border-radius: 12px;
            transition: width 0.8s ease;
        }
        
        .expense-percentage {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--card-accent);
            text-align: center;
        }
        
        .card-actions {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
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
            color: var(--card-accent);
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .summary-footer {
            background: rgba(255, 255, 255, 0.5);
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-value {
            font-size: 0.875rem;
            font-weight: 700;
            color: #374151;
        }
        
        .summary-label {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.25rem;
        }
        
        /* 分類區塊標題 */
        .section-header {
            background: linear-gradient(135deg, var(--section-color) 0%, var(--section-color-light) 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .section-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .income-section {
            --section-color: #059669;
            --section-color-light: #10b981;
        }
        
        .expense-section {
            --section-color: #dc2626;
            --section-color-light: #ef4444;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .section-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .section-icon {
            font-size: 2rem;
            margin-right: 1rem;
        }
        
        /* 月份選擇器樣式 */
        .month-selector {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .month-selector::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        /* 深色模式適配 */
        .dark-mode .financial-card {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .category-card {
            background: #1f2937;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .category-income {
            --card-bg: linear-gradient(135deg, #064e3b 0%, #065f46 100%);
        }
        
        .dark-mode .category-expense {
            --card-bg: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
        }
        
        .dark-mode .category-title {
            color: #f9fafb;
        }
        
        .dark-mode .category-subtitle {
            color: #d1d5db;
        }
        
        .dark-mode .stat-card {
            background: rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        .dark-mode .summary-footer {
            background: rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        .dark-mode .action-btn {
            background: rgba(0, 0, 0, 0.3);
            color: #d1d5db;
        }
        
        .dark-mode .action-btn:hover {
            background: rgba(0, 0, 0, 0.5);
            color: var(--card-accent-light);
        }
        
        /* 空狀態優化 */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 24px;
            margin: 2rem 0;
        }
        
        .dark-mode .empty-state {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        }
        
        .empty-icon {
            font-size: 5rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }
        
        /* 響應式優化 */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .category-header {
                padding: 1rem;
            }
            
            .section-header {
                padding: 1.5rem;
            }
            
            .card-actions {
                position: relative;
                top: auto;
                right: auto;
                flex-direction: row;
                justify-content: center;
                margin-top: 1rem;
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

    <div class="container-fluid">
        <div class="row">
            <!-- 側邊欄 -->
            <?php 
            $current_page = 'report';
            include 'sidebar.php'; 
            ?>
            
            <!-- 主要內容區 -->
            <div class="col-md-10">
                <!-- 月份選擇器 -->
                <div class="month-selector">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-2">
                                <i class="bi bi-graph-up me-2"></i>財務報表與分類管理
                            </h4>
                            <p class="mb-0 opacity-75">查看您的財務狀況、管理收支分類並分析各分類的詳細統計</p>
                        </div>
                        <div class="col-md-4">
                            <form method="GET" class="d-flex align-items-center">
                                <label class="me-2 fw-bold">查看月份：</label>
                                <select name="year" class="form-select form-select-sm me-2" style="width: auto;">
                                    <?php for ($y = $current_year; $y >= $current_year - 2; $y--): ?>
                                        <option value="<?= $y ?>" <?= $y == $selected_year ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select name="month" class="form-select form-select-sm me-2" style="width: auto;">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $m == $selected_month ? 'selected' : '' ?>><?= $m ?>月</option>
                                    <?php endfor; ?>
                                </select>
                                <button type="submit" class="btn btn-light btn-sm">查看</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 財務概覽 -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="financial-card income-card">
                            <div class="card-body text-center">
                                <i class="bi bi-arrow-down-circle display-4 mb-3"></i>
                                <h3 class="mb-2">$<?= number_format($financial_summary['total_income']) ?></h3>
                                <p class="mb-0">本月總收入</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="financial-card expense-card">
                            <div class="card-body text-center">
                                <i class="bi bi-arrow-up-circle display-4 mb-3"></i>
                                <h3 class="mb-2">$<?= number_format($financial_summary['total_expense']) ?></h3>
                                <p class="mb-0">本月總支出</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="financial-card net-income-card <?= $financial_summary['net_income'] < 0 ? 'negative' : '' ?>">
                            <div class="card-body text-center">
                                <i class="bi bi-<?= $financial_summary['net_income'] >= 0 ? 'plus' : 'dash' ?>-circle display-4 mb-3"></i>
                                <h3 class="mb-2">$<?= number_format($financial_summary['net_income']) ?></h3>
                                <p class="mb-0">本月淨收入</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 操作按鈕 -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-tags me-2"></i>
                            分類管理 - <?= $selected_year ?>年<?= $selected_month ?>月
                        </h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="bi bi-plus-lg"></i> 新增分類
                        </button>
                    </div>
                </div>

                <!-- 分類列表 -->
                <?php if (!empty($categories)): ?>
                    <div class="row">
                        <?php 
                        $income_categories = array_filter($categories, function($cat) { return $cat['type'] === 'Income'; });
                        $expense_categories = array_filter($categories, function($cat) { return $cat['type'] === 'Expense'; });
                        ?>
                        
                        <!-- 收入分類 -->
                        <?php if (!empty($income_categories)): ?>
                            <div class="col-lg-6">
                                <div class="section-header income-section">
                                    <div class="section-title">
                                        <i class="bi bi-arrow-down-circle section-icon"></i>
                                        <div>
                                            <div>收入分類</div>
                                            <div class="section-subtitle"><?= count($income_categories) ?> 個分類</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php foreach ($income_categories as $category): ?>
                                    <div class="category-card category-income">
                                        <div class="category-header">
                                            <div class="category-icon-wrapper">
                                                <i class="bi bi-plus-circle category-icon"></i>
                                            </div>
                                            
                                            <div class="category-title"><?= htmlspecialchars($category['name']) ?></div>
                                            <div class="category-subtitle">
                                                收入分類 · <?= $category['month_count'] > 0 ? '本月活躍' : '本月無交易' ?>
                                            </div>
                                            
                                            <div class="card-actions">
                                                <button class="action-btn" 
                                                        onclick="editCategory(<?= $category['category_id'] ?>, '<?= htmlspecialchars($category['name']) ?>')"
                                                        title="編輯分類">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="action-btn" 
                                                        onclick="confirmDelete(<?= $category['category_id'] ?>, '<?= htmlspecialchars($category['name']) ?>', '收入', <?= $category['total_count'] ?>)"
                                                        title="刪除分類">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="stats-grid">
                                            <div class="stat-card">
                                                <div class="stat-value">$<?= number_format($category['month_total'], 0) ?></div>
                                                <div class="stat-label">本月收入</div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-value"><?= $category['month_count'] ?></div>
                                                <div class="stat-label">本月筆數</div>
                                            </div>
                                        </div>
                                        
                                        <div class="summary-footer">
                                            <div class="summary-item">
                                                <div class="summary-value">$<?= number_format($category['total_amount'], 0) ?></div>
                                                <div class="summary-label">歷史總計</div>
                                            </div>
                                            <div class="summary-item">
                                                <div class="summary-value"><?= $category['total_count'] ?></div>
                                                <div class="summary-label">總筆數</div>
                                            </div>
                                            <div class="summary-item">
                                                <div class="summary-value"><?= $category['month_count'] > 0 ? '$' . number_format($category['month_total'] / $category['month_count'], 0) : '$0' ?></div>
                                                <div class="summary-label">平均單筆</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 支出分類 -->
                        <?php if (!empty($expense_categories)): ?>
                            <div class="col-lg-6">
                                <div class="section-header expense-section">
                                    <div class="section-title">
                                        <i class="bi bi-arrow-up-circle section-icon"></i>
                                        <div>
                                            <div>支出分類</div>
                                            <div class="section-subtitle"><?= count($expense_categories) ?> 個分類</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php foreach ($expense_categories as $category): ?>
                                    <div class="category-card category-expense">
                                        <div class="category-header">
                                            <div class="category-icon-wrapper">
                                                <i class="bi bi-dash-circle category-icon"></i>
                                            </div>
                                            
                                            <div class="category-title"><?= htmlspecialchars($category['name']) ?></div>
                                            <div class="category-subtitle">
                                                支出分類 · <?= $category['expense_percentage'] > 0 ? number_format($category['expense_percentage'], 1) . '% 佔比' : '本月無支出' ?>
                                            </div>
                                            
                                            <div class="card-actions">
                                                <button class="action-btn" 
                                                        onclick="editCategory(<?= $category['category_id'] ?>, '<?= htmlspecialchars($category['name']) ?>')"
                                                        title="編輯分類">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="action-btn" 
                                                        onclick="confirmDelete(<?= $category['category_id'] ?>, '<?= htmlspecialchars($category['name']) ?>', '支出', <?= $category['total_count'] ?>)"
                                                        title="刪除分類">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="stats-grid">
                                            <div class="stat-card">
                                                <div class="stat-value">$<?= number_format($category['month_total'], 0) ?></div>
                                                <div class="stat-label">本月支出</div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-value"><?= $category['month_count'] ?></div>
                                                <div class="stat-label">本月筆數</div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($category['expense_percentage'] > 0): ?>
                                            <div class="progress-section">
                                                <div class="expense-progress">
                                                    <div class="expense-progress-bar" style="width: <?= min($category['expense_percentage'], 100) ?>%"></div>
                                                </div>
                                                <div class="expense-percentage">
                                                    佔本月總支出 <?= number_format($category['expense_percentage'], 1) ?>%
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="summary-footer">
                                            <div class="summary-item">
                                                <div class="summary-value">$<?= number_format($category['total_amount'], 0) ?></div>
                                                <div class="summary-label">歷史總計</div>
                                            </div>
                                            <div class="summary-item">
                                                <div class="summary-value"><?= $category['total_count'] ?></div>
                                                <div class="summary-label">總筆數</div>
                                            </div>
                                            <div class="summary-item">
                                                <div class="summary-value"><?= $category['month_count'] > 0 ? '$' . number_format($category['month_total'] / $category['month_count'], 0) : '$0' ?></div>
                                                <div class="summary-label">平均單筆</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-pie-chart"></i>
                        </div>
                        <h3 class="text-muted mb-3">尚無分類資料</h3>
                        <p class="text-muted mb-4">
                            您還沒有建立任何收支分類<br>
                            請先新增分類，然後開始記錄您的收支
                        </p>
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="bi bi-plus-lg me-2"></i>新增分類
                            </button>
                            <a href="transactions.php" class="btn btn-outline-primary btn-lg">
                                <i class="bi bi-journal-text me-2"></i>記錄交易
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 新增分類 Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新增分類</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="categoryForm">
                        <input type="hidden" name="new_category" value="1">
                        
                        <div class="mb-3">
                            <label for="new_cat_name" class="form-label">分類名稱 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_cat_name" name="new_cat_name" required placeholder="輸入分類名稱">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_cat_type" class="form-label">分類類型 <span class="text-danger">*</span></label>
                            <select class="form-select" id="new_cat_type" name="new_cat_type" required>
                                <option value="">請選擇類型</option>
                                <option value="Income">收入</option>
                                <option value="Expense">支出</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="submitCategory()">
                        <i class="bi bi-plus-lg me-1"></i>新增
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 編輯分類 Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">編輯分類</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editCategoryForm">
                        <input type="hidden" name="edit_category" value="1">
                        <input type="hidden" name="category_id" id="edit_category_id" value="">
                        
                        <div class="mb-3">
                            <label for="edit_cat_name" class="form-label">分類名稱 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_cat_name" name="edit_cat_name" required placeholder="輸入分類名稱">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="submitEditCategory()">
                        <i class="bi bi-check-lg me-1"></i>更新
                    </button>
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
                        delay: <?= $message_type === 'success' ? '3000' : '5000' ?>
                    });
                    toast.show();
                }
            });
        <?php endif; ?>
        
        // 編輯分類
        function editCategory(categoryId, categoryName) {
            document.getElementById('edit_category_id').value = categoryId;
            document.getElementById('edit_cat_name').value = categoryName;
            
            const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            editModal.show();
        }
        
        // 確認刪除分類
        function confirmDelete(categoryId, categoryName, categoryType, transactionCount) {
            if (transactionCount > 0) {
                alert(`無法刪除分類「${categoryName}」\n\n此${categoryType}分類已被 ${transactionCount} 筆交易使用。\n請先刪除或更改相關交易的分類後再試。`);
                return;
            }
            
            const confirmMessage = `確定要刪除分類「${categoryName}」嗎？\n\n` +
                                 `類型：${categoryType}\n` +
                                 `此操作無法復原！`;
            
            if (confirm(confirmMessage)) {
                window.location.href = "report.php?delete=" + categoryId;
            }
        }
        
        // 提交新增分類表單
        function submitCategory() {
            const form = document.getElementById('categoryForm');
            const submitBtn = event.target;
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>處理中...';
            
            form.submit();
        }
        
        // 提交編輯分類表單
        function submitEditCategory() {
            const form = document.getElementById('editCategoryForm');
            const submitBtn = event.target;
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>處理中...';
            
            form.submit();
        }
        
        // 重置模態框
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('addCategoryModal').addEventListener('show.bs.modal', function () {
                const form = document.getElementById('categoryForm');
                form.reset();
                
                const submitBtn = document.querySelector('#addCategoryModal .btn-primary');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i>新增';
            });
            
            document.getElementById('editCategoryModal').addEventListener('show.bs.modal', function () {
                const submitBtn = document.querySelector('#editCategoryModal .btn-primary');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>更新';
            });
        });
    </script>
</body>
</html>