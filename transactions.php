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

// 處理刪除交易
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $transaction_id = intval($_GET['delete']);
    
    try {
        // 檢查交易是否屬於該用戶
        $check_stmt = $db->prepare("SELECT transaction_id, type, amount FROM transactions WHERE transaction_id = ? AND user_id = ?");
        $check_stmt->execute([$transaction_id, $user_id]);
        $transaction = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            throw new Exception('找不到該交易記錄或您沒有權限刪除');
        }
        
        $db->beginTransaction();
        
        // 先刪除相關的發票記錄（如果有的話）
        $delete_invoice_stmt = $db->prepare("DELETE FROM invoices WHERE transaction_id = ? AND user_id = ?");
        $delete_invoice_stmt->execute([$transaction_id, $user_id]);
        
        // 刪除交易
        $delete_stmt = $db->prepare("DELETE FROM transactions WHERE transaction_id = ? AND user_id = ?");
        $delete_stmt->execute([$transaction_id, $user_id]);
        
        // 更新總資產（如果需要的話）
        if ($transaction['type'] === 'Income') {
            // 刪除收入記錄，需要從總資產中減去
            $update_stmt = $db->prepare("UPDATE users SET total_assets = total_assets - ? WHERE user_id = ?");
            $update_stmt->execute([$transaction['amount'], $user_id]);
        } else {
            // 刪除支出記錄，需要加回總資產
            $update_stmt = $db->prepare("UPDATE users SET total_assets = total_assets + ? WHERE user_id = ?");
            $update_stmt->execute([$transaction['amount'], $user_id]);
        }
        
        $db->commit();
        
        // 刪除成功後重定向，避免重複執行刪除邏輯
        $_SESSION['delete_success'] = true;
        header("Location: transactions.php");
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $message = '刪除失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 檢查是否有刪除成功的 session 訊息
if (isset($_SESSION['delete_success']) && $_SESSION['delete_success']) {
    $message = '交易記錄已成功刪除！';
    $message_type = 'success';
    $show_toast = true;
    unset($_SESSION['delete_success']); // 清除 session 訊息
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

// 新增交易
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'], $_POST['amount'], $_POST['category_id'], $_POST['transaction_date'])) {
    try {
        $type = $_POST['type'];
        $amount = $_POST['amount'];
        $category_id = $_POST['category_id'];
        $transaction_date = $_POST['transaction_date'];
        $description = $_POST['description'] ?? '';
        
        if(empty($description)){
            $description = ''; 
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

// 查詢交易紀錄與分類 - 修改查詢以包含 transaction_id
try {
    $stmt = $db->prepare("SELECT t.transaction_id, t.transaction_date, t.type, c.name as category_name, t.amount, t.description 
                          FROM transactions t 
                          LEFT JOIN categories c ON t.category_id = c.category_id 
                          WHERE t.user_id = ? 
                          ORDER BY t.transaction_date DESC, t.created_at DESC");
    $stmt->execute([$user_id]);
    $all_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_transactions = [];
    if (empty($message)) {
        $message = '載入交易記錄失敗：' . $e->getMessage();
        $message_type = 'warning';
        $show_toast = true;
    }
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
    <title>交易記錄</title>
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
        /* 頁面標題樣式 */
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
        
        /* 交易類型樣式 */
        .transaction-income {
            color: #198754 !important;
            font-weight: 600;
        }
        
        .transaction-expense {
            color: #dc3545 !important;
            font-weight: 600;
        }
        
        .transaction-income-badge {
            background-color: #198754 !important;
        }
        
        .transaction-expense-badge {
            background-color: #dc3545 !important;
        }
        
        /* 深色模式下的交易類型顏色 */
        .dark-mode .transaction-income {
            color: #75b798 !important;
        }
        
        .dark-mode .transaction-expense {
            color: #f1aeb5 !important;
        }
        
        /* 操作按鈕樣式 */
        .action-buttons {
            white-space: nowrap;
        }
        
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* 響應式設計 */
        @media (max-width: 768px) {
            .page-header {
                padding: 2rem 1.5rem;
            }
        }
        
        /* 美化確認刪除模態框 */
        .delete-confirm-modal .modal-content {
            border: 2px solid #dc3545;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(220, 53, 69, 0.3);
        }
        
        .delete-confirm-modal .modal-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border-top-left-radius: 13px;
            border-top-right-radius: 13px;
            border-bottom: none;
        }
        
        .delete-confirm-modal .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .delete-confirm-modal .modal-body {
            padding: 2rem;
            text-align: center;
        }
        
        .delete-confirm-modal .warning-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .delete-confirm-modal .transaction-details {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
            border-left: 4px solid #dc3545;
        }
        
        .delete-confirm-modal .transaction-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .delete-confirm-modal .transaction-details .detail-row:last-child {
            margin-bottom: 0;
            border-top: 1px solid #dee2e6;
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }
        
        .delete-confirm-modal .transaction-details .detail-label {
            font-weight: 600;
            color: #6c757d;
        }
        
        .delete-confirm-modal .transaction-details .detail-value {
            font-weight: 500;
        }
        
        .delete-confirm-modal .warning-text {
            color: #dc3545;
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 1rem;
        }
        
        .delete-confirm-modal .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .delete-confirm-modal .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }
        
        .delete-confirm-modal .btn-secondary {
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .delete-confirm-modal .btn-secondary:hover {
            transform: translateY(-2px);
        }
        
        /* 深色模式下的確認刪除樣式 */
        .dark-mode .delete-confirm-modal .modal-content {
            background-color: #2d2d2d;
            color: #ffffff;
            border-color: #dc3545;
        }
        
        .dark-mode .delete-confirm-modal .modal-body {
            color: #ffffff;
        }
        
        .dark-mode .delete-confirm-modal .transaction-details {
            background-color: #404040;
            color: #ffffff;
        }
        
        .dark-mode .delete-confirm-modal .transaction-details .detail-label {
            color: #adb5bd;
        }
        
        .dark-mode .delete-confirm-modal .transaction-details .detail-value {
            color: #ffffff;
        }
        
        .dark-mode .delete-confirm-modal .transaction-details .detail-row:last-child {
            border-top-color: #555555;
        }
        
        /* 深色模式下的模態框 */
        .dark-mode .modal-content {
            background-color: #2d2d2d !important;
            color: #ffffff !important;
            border: 1px solid #404040 !important;
        }
        
        .dark-mode .modal-header {
            background-color: #2d2d2d !important;
            border-bottom: 1px solid #404040 !important;
            color: #ffffff !important;
        }
        
        .dark-mode .modal-body {
            background-color: #2d2d2d !important;
            color: #ffffff !important;
        }
        
        .dark-mode .modal-footer {
            background-color: #2d2d2d !important;
            border-top: 1px solid #404040 !important;
        }
        
        .dark-mode .form-control {
            background-color: #404040 !important;
            border-color: #555555 !important;
            color: #ffffff !important;
        }
        
        .dark-mode .form-control:focus {
            background-color: #404040 !important;
            border-color: #6ea8fe !important;
            box-shadow: 0 0 0 0.2rem rgba(110, 168, 254, 0.25) !important;
            color: #ffffff !important;
        }
        
        .dark-mode .form-select {
            background-color: #404040 !important;
            border-color: #555555 !important;
            color: #ffffff !important;
        }
        
        .dark-mode .form-label {
            color: #ffffff !important;
        }
        
        .dark-mode .input-group-text {
            background-color: #404040 !important;
            border-color: #555555 !important;
            color: #ffffff !important;
        }
        
        .dark-mode .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
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
            $current_page = 'transactions';
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
                                    <i class="bi bi-journal-text me-3"></i>交易記錄
                                </h1>
                                <p class="mb-0 opacity-75 fs-5">
                                    記錄和管理您的收入支出，掌握資金流向
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <div class="d-flex gap-2 justify-content-md-end justify-content-center">
                                    <button class="btn btn-outline-light btn-lg" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                        <i class="bi bi-folder-plus me-2"></i>新增分類
                                    </button>
                                    <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                        <i class="bi bi-plus-circle me-2"></i>新增交易
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body p-0">
                        <?php if (!empty($all_transactions)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>日期</th>
                                            <th>類型</th>
                                            <th>分類</th>
                                            <th>金額</th>
                                            <th>備註</th>
                                            <th class="text-center">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_transactions as $tx): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($tx['transaction_date']) ?></td>
                                                <td>
                                                    <span class="badge <?= $tx['type'] === 'Income' ? 'transaction-income-badge' : 'transaction-expense-badge' ?>">
                                                        <?= $tx['type'] === 'Income' ? '收入' : '支出' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="small"><?= htmlspecialchars($tx['category_name'] ?? '未分類') ?></span>
                                                </td>
                                                <td class="<?= $tx['type'] === 'Income' ? 'transaction-income' : 'transaction-expense' ?>">
                                                    $<?= number_format($tx['amount'], 0) ?>
                                                </td>
                                                <td class="text-muted small"><?= $tx['description'] !== null ? htmlspecialchars($tx['description']) : '' ?></td>
                                                <td class="text-center action-buttons">
                                                    <button class="btn btn-outline-danger btn-sm" 
                                                            onclick="confirmDelete(<?= $tx['transaction_id'] ?>, '<?= htmlspecialchars($tx['type'] === 'Income' ? '收入' : '支出') ?>', '<?= number_format($tx['amount'], 0) ?>', '<?= htmlspecialchars($tx['category_name'] ?? '未分類') ?>', '<?= htmlspecialchars($tx['transaction_date']) ?>', '<?= htmlspecialchars($tx['description']) ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-5">
                                <i class="bi bi-journal-x fs-1 text-muted d-block mb-3"></i>
                                <h5 class="text-muted">尚無交易紀錄</h5>
                                <p class="text-muted">開始記錄您的第一筆交易吧！</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                    <i class="bi bi-plus-lg"></i> 新增交易
                                </button>
                            </div>
                        <?php endif; ?>
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
                            <label for="type" class="form-label">類型 <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required onchange="filterCategories()">
                                <option value="">請選擇類型</option>
                                <option value="Income">收入</option>
                                <option value="Expense">支出</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">金額 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="amount" name="amount" min="1" step="1" required placeholder="0">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_id" class="form-label">分類 <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">請先選擇交易類型</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="transaction_date" class="form-label">日期 <span class="text-danger">*</span></label>
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
                    <button type="button" class="btn btn-success" onclick="submitCategory()">
                        <i class="bi bi-folder-plus me-1"></i>新增
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 刪除確認 Modal -->
    <div class="modal fade delete-confirm-modal" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        確認刪除交易記錄
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="warning-icon">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    
                    <h6 class="mb-3">您即將刪除以下交易記錄：</h6>
                    
                    <div class="transaction-details">
                        <div class="detail-row">
                            <span class="detail-label">交易日期：</span>
                            <span class="detail-value" id="delete-date"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">交易類型：</span>
                            <span class="detail-value" id="delete-type"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">分類：</span>
                            <span class="detail-value" id="delete-category"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">金額：</span>
                            <span class="detail-value" id="delete-amount"></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">備註：</span>
                            <span class="detail-value" id="delete-description"></span>
                        </div>
                    </div>
                    
                    <div class="warning-text">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        此操作無法復原！
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary me-3" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>取消
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash3 me-1"></i>確定刪除
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
        
        // 刪除確認變數
        let deleteTransactionId = null;
        
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
                    
                    <?php if ($message_type === 'success'): ?>
                        toastElement.addEventListener('hidden.bs.toast', function() {
                            // 立即應用主題避免閃白
                            const savedTheme = localStorage.getItem('theme');
                            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                                document.documentElement.style.backgroundColor = '#1a1a1a';
                                document.documentElement.style.color = '#ffffff';
                            }
                            location.reload();
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
            
            categorySelect.innerHTML = '';
            
            if (selectedType === '') {
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = '請先選擇交易類型';
                defaultOption.disabled = true;
                defaultOption.selected = true;
                categorySelect.appendChild(defaultOption);
                return;
            }
            
            const categories = selectedType === 'Income' ? incomeCategories : expenseCategories;
            
            if (categories.length === 0) {
                const noOption = document.createElement('option');
                noOption.value = '';
                noOption.textContent = `尚無${selectedType === 'Income' ? '收入' : '支出'}分類，請先新增分類`;
                noOption.disabled = true;
                noOption.selected = true;
                categorySelect.appendChild(noOption);
            } else {
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = '請選擇分類';
                defaultOption.disabled = true;
                defaultOption.selected = true;
                categorySelect.appendChild(defaultOption);
                
                categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.category_id;
                    option.textContent = category.name;
                    categorySelect.appendChild(option);
                });
            }
        }
        
        // 美化的確認刪除交易
        function confirmDelete(transactionId, type, amount, category, date = '', description = '') {
            deleteTransactionId = transactionId;
            
            // 填充刪除確認模態框的內容
            document.getElementById('delete-date').textContent = date || '未知';
            document.getElementById('delete-type').textContent = type;
            document.getElementById('delete-category').textContent = category;
            document.getElementById('delete-amount').textContent = '$' + Math.round(parseFloat(amount.replace(/,/g, '')));
            document.getElementById('delete-description').textContent = description || '無';
            
            // 顯示模態框
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            deleteModal.show();
        }
        
        // 確定刪除按鈕事件
        document.addEventListener('DOMContentLoaded', function() {
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', function() {
                    if (deleteTransactionId) {
                        // 顯示載入狀態
                        this.disabled = true;
                        this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>刪除中...';
                        
                        // 執行刪除
                        window.location.href = "transactions.php?delete=" + deleteTransactionId;
                    }
                });
            }
        });
        
        // 提交交易表單
        function submitTransaction() {
            const form = document.getElementById('transactionForm');
            const submitBtn = event.target;
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>處理中...';
            
            form.submit();
        }
        
        // 提交分類表單
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
        
        // 當頁面載入完成時初始化
        document.addEventListener('DOMContentLoaded', function() {
            filterCategories();
            
            // 當模態框顯示時重置表單
            document.getElementById('addTransactionModal').addEventListener('show.bs.modal', function () {
                const form = document.getElementById('transactionForm');
                form.reset();
                document.getElementById('transaction_date').value = '<?= date('Y-m-d') ?>';
                filterCategories();
                
                const submitBtn = document.querySelector('#addTransactionModal .btn-primary');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>新增';
            });
            
            document.getElementById('addCategoryModal').addEventListener('show.bs.modal', function () {
                const form = document.getElementById('categoryForm');
                form.reset();
                
                const submitBtn = document.querySelector('#addCategoryModal .btn-success');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-folder-plus me-1"></i>新增';
            });
            
            // 重置刪除確認模態框
            document.getElementById('deleteConfirmModal').addEventListener('show.bs.modal', function () {
                const confirmBtn = document.getElementById('confirmDeleteBtn');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i>確定刪除';
            });
        });
    </script>
</body>
</html>
