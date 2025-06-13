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
        
        $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount, category_id, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $type, $amount, $category_id, $transaction_date, $description]);
        
        $message = '交易記錄新增成功！';
        $message_type = 'success';
        $show_toast = true;
    } catch (Exception $e) {
        $message = '交易記錄新增失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 查詢交易紀錄與分類
try {
    $stmt = $db->prepare("SELECT transaction_date, type, category_name, amount, description FROM user_transaction_history WHERE user_name = ? ORDER BY transaction_date DESC, created_at DESC");
    $stmt->execute([$user_name]);
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
        // 在頁面載入前就檢查並應用深色模式
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.style.backgroundColor = '#1a1a1a';
                document.documentElement.style.color = '#ffffff';
                // 預先添加 dark-mode class 到 html 元素
                document.documentElement.classList.add('dark-mode');
            } else {
                // 確保淺色模式的預設樣式
                document.documentElement.style.backgroundColor = '#f8f9fa';
                document.documentElement.style.color = '#212529';
                document.documentElement.classList.remove('dark-mode');
            }
        })();
    </script>
    
    <style>
        /* 預設深色模式樣式，防止閃白 */
        .dark-mode {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
        
        .dark-mode body {
            background-color: #1a1a1a !important;
            color: #ffffff !important;
        }
        
        /* 預設淺色模式樣式 */
        body {
            background-color: #f8f9fa;
            color: #212529;
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
            $current_page = 'transactions'; // 設定當前頁面
            include 'sidebar.php'; 
            ?>
            
            <!-- 主要內容區 -->
            <div class="col-md-10">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">交易記錄</h5>
                        <div>
                            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                <i class="bi bi-plus-lg"></i> 新增交易
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="bi bi-folder-plus"></i> 新增分類
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($all_transactions)): ?>
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
                                        <?php foreach ($all_transactions as $tx): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($tx['transaction_date']) ?></td>
                                                <td>
                                                    <span class="badge <?= $tx['type'] === 'Income' ? 'transaction-income-badge' : 'transaction-expense-badge' ?>">
                                                        <?= $tx['type'] === 'Income' ? '收入' : '支出' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="small"><?= htmlspecialchars($tx['category_name']) ?></span>
                                                </td>
                                                <td class="<?= $tx['type'] === 'Income' ? 'transaction-income' : 'transaction-expense' ?>">
                                                    $<?= number_format($tx['amount'], 2) ?>
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
                            <label for="new_cat_name" class="form-label">分類名稱</label>
                            <input type="text" class="form-control" id="new_cat_name" name="new_cat_name" required placeholder="輸入分類名稱">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_cat_type" class="form-label">分類類型</label>
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

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 從 PHP 傳遞分類資料到 JavaScript
        const incomeCategories = <?= json_encode($income_categories) ?>;
        const expenseCategories = <?= json_encode($expense_categories) ?>;
        
        // 確保頁面載入時就應用正確的主題
        document.addEventListener('DOMContentLoaded', function() {
            // 確保深色模式正確應用到 body
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.body.classList.add('dark-mode');
                document.documentElement.classList.add('dark-mode');
            }
        });
        
        // 顯示 Toast 訊息
        <?php if ($show_toast && !empty($message)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const toastElement = document.getElementById('messageToast');
                if (toastElement) {
                    const toast = new bootstrap.Toast(toastElement, {
                        delay: <?= $message_type === 'success' ? '3000' : '5000' ?> // 縮短顯示時間
                    });
                    toast.show();
                    
                    // 如果是成功訊息，Toast 隱藏後重新載入頁面
                    <?php if ($message_type === 'success'): ?>
                        toastElement.addEventListener('hidden.bs.toast', function() {
                            // 使用 replace 而不是 href，避免在瀏覽器歷史中留下記錄
                            window.location.replace('transactions.php');
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
                noOption.textContent = `尚無${selectedType === 'Income' ? '收入' : '支出'}分類，請先新增分類`;
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
        
        // 提交分類表單
        function submitCategory() {
            const form = document.getElementById('categoryForm');
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
            
            document.getElementById('addCategoryModal').addEventListener('show.bs.modal', function () {
                const form = document.getElementById('categoryForm');
                form.reset();
                
                // 重置按鈕狀態
                const submitBtn = document.querySelector('#addCategoryModal .btn-success');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-folder-plus me-1"></i>新增';
            });
        });
    </script>
</body>
</html>
