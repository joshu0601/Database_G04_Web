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

// 取得 Expense 類別
try {
    $cat_stmt = $db->prepare("SELECT category_id, name FROM categories WHERE user_id = ? AND type = 'Expense' ORDER BY name");
    $cat_stmt->execute([$user_id]);
    $expense_categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $expense_categories = [];
    $message = '載入分類失敗：' . $e->getMessage();
    $message_type = 'warning';
    $show_toast = true;
}

// 新增發票
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_number'])) {
    try {
        $invoice_number = strtoupper(trim($_POST['invoice_number']));
        $amount = floatval($_POST['amount']);
        $issue_date = $_POST['issue_date'];
        $merchant_name = trim($_POST['merchant_name'] ?? '');
        $merchant_tax_id = trim($_POST['merchant_tax_id'] ?? '');
        $category_id = $_POST['category_id'] ?? null;
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;

        // 驗證發票號碼格式
        if (!preg_match('/^[A-Z]{2}[0-9]{8}$/', $invoice_number)) {
            throw new Exception("發票號碼格式錯誤，請輸入兩碼大寫英文加八碼數字");
        }
        
        if (empty($amount) || $amount <= 0) {
            throw new Exception("請輸入正確的金額");
        }
        
        if (empty($issue_date)) {
            throw new Exception("請選擇發票開立日期");
        }
        
        if (empty($category_id)) {
            throw new Exception("請選擇支出分類");
        }

        // 檢查發票號碼是否已存在
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND invoice_number = ?");
        $check_stmt->execute([$user_id, $invoice_number]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("此發票號碼已存在");
        }

        $db->beginTransaction();
        
        // 建立交易紀錄
        if (empty($description)) {
            $stmt2 = $db->prepare("INSERT INTO transactions (user_id, transaction_date, amount, type, category_id) VALUES (?, ?, ?, 'Expense', ?)");
            $stmt2->execute([$user_id, $issue_date, $amount, $category_id]);
        } else {
            $stmt2 = $db->prepare("INSERT INTO transactions (user_id, transaction_date, amount, type, category_id, description) VALUES (?, ?, ?, 'Expense', ?, ?)");
            $stmt2->execute([$user_id, $issue_date, $amount, $category_id, $description]);
        }
        $transaction_id = $db->lastInsertId();

        // 建立發票
        $stmt = $db->prepare("INSERT INTO invoices (user_id, invoice_number, amount, issue_date, merchant_name, merchant_tax_id, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $invoice_number, $amount, $issue_date, $merchant_name, $merchant_tax_id, $transaction_id]);

        // 更新總資產
        $stmt = $db->prepare("UPDATE users SET total_assets = total_assets - ? WHERE user_id = ?");
        $stmt->execute([$amount, $user_id]);

        $db->commit();
        $message = "發票新增成功，已同步建立交易記錄並更新總資產！";
        $message_type = 'success';
        $show_toast = true;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $message = "發票新增失敗：" . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 刪除發票
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    try {
        $stmt = $db->prepare("SELECT transaction_id FROM invoices WHERE invoice_id = ? AND user_id = ?");
        $stmt->execute([$delete_id, $user_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            throw new Exception("找不到該發票記錄");
        }

        $db->beginTransaction();
        
        $amount = 0;
        // 查出交易金額
        if (!empty($invoice['transaction_id'])) {
            $stmt = $db->prepare("SELECT amount FROM transactions WHERE transaction_id = ?");
            $stmt->execute([$invoice['transaction_id']]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);
            $amount = $tx ? $tx['amount'] : 0;
        }

        // 刪除發票
        $stmt = $db->prepare("DELETE FROM invoices WHERE invoice_id = ? AND user_id = ?");
        $stmt->execute([$delete_id, $user_id]);

        // 補回總資產並刪除交易記錄
        if (!empty($invoice['transaction_id']) && $amount > 0) {
            $stmt = $db->prepare("UPDATE users SET total_assets = total_assets + ? WHERE user_id = ?");
            $stmt->execute([$amount, $user_id]);

            $stmt = $db->prepare("DELETE FROM transactions WHERE transaction_id = ?");
            $stmt->execute([$invoice['transaction_id']]);
        }

        $db->commit();
        $message = "發票已刪除，相關交易記錄已移除，總資產已恢復！";
        $message_type = 'success';
        $show_toast = true;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $message = "刪除失敗：" . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 查詢發票紀錄
try {
    $stmt = $db->prepare("SELECT * FROM invoice_with_category WHERE user_id = ? ORDER BY issue_date DESC");
    $stmt->execute([$user_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $invoices = [];
    if (empty($message)) {
        $message = '載入發票記錄失敗：' . $e->getMessage();
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
    <title>發票管理</title>
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
            $current_page = 'invoice';
            include 'sidebar.php'; 
            ?>
            
            <!-- 主要內容區 -->
            <div class="col-md-10">
                <!-- 頁面標題與按鈕 -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>發票管理</h5>
                        <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#invoiceForm">
                            <i class="bi bi-plus-lg"></i> 新增發票
                        </button>
                    </div>
                </div>

                <!-- 新增發票表單 -->
                <div class="collapse mb-4" id="invoiceForm">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>新增發票記錄</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="addInvoiceForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="invoice_number" class="form-label">發票號碼 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="invoice_number" 
                                               maxlength="10" required placeholder="例如：AB12345678"
                                               pattern="[A-Z]{2}[0-9]{8}" title="請輸入兩位大寫英文字母加八位數字">
                                        <div class="form-text">格式：兩位大寫英文字母 + 八位數字</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="amount" class="form-label">發票金額 <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" name="amount" 
                                                   min="0" step="0.01" required placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="issue_date" class="form-label">開立日期 <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="issue_date" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="category_id" class="form-label">支出分類 <span class="text-danger">*</span></label>
                                        <select class="form-select" name="category_id" required>
                                            <option value="">請選擇分類</option>
                                            <?php foreach ($expense_categories as $cat): ?>
                                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($expense_categories)): ?>
                                            <div class="form-text text-warning">
                                                <i class="bi bi-exclamation-triangle"></i> 請先在交易記錄頁面新增支出分類
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="merchant_name" class="form-label">商家名稱</label>
                                        <input type="text" class="form-control" name="merchant_name" 
                                               maxlength="100" placeholder="選填">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="merchant_tax_id" class="form-label">商家統編</label>
                                        <input type="text" class="form-control" name="merchant_tax_id" 
                                               maxlength="8" placeholder="選填，8位數字">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">說明備註</label>
                                    <textarea class="form-control" name="description" rows="2" 
                                              maxlength="255" placeholder="選填，發票相關說明"></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary" onclick="submitInvoice()">
                                        <i class="bi bi-check-lg"></i> 新增發票
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#invoiceForm">
                                        <i class="bi bi-x-lg"></i> 取消
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 發票列表 -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>發票記錄列表</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($invoices)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>發票號碼</th>
                                            <th>金額</th>
                                            <th>開立日期</th>
                                            <th>分類</th>
                                            <th>商家名稱</th>
                                            <th>統編</th>
                                            <th class="text-center">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $invoice): ?>
                                            <tr>
                                                <td>
                                                    <span class="font-monospace fw-bold">
                                                        <?= htmlspecialchars($invoice['invoice_number']) ?>
                                                    </span>
                                                </td>
                                                <td class="transaction-expense">
                                                    $<?= number_format($invoice['amount'], 2) ?>
                                                </td>
                                                <td><?= htmlspecialchars($invoice['issue_date']) ?></td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <?= htmlspecialchars($invoice['category_name'] ?? '未分類') ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($invoice['merchant_name']) ?></td>
                                                <td><?= htmlspecialchars($invoice['merchant_tax_id']) ?></td>
                                                <td class="text-center">
                                                    <button class="btn btn-outline-danger btn-sm" 
                                                            onclick="confirmDelete(<?= $invoice['invoice_id'] ?>)">
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
                                <i class="bi bi-receipt fs-1 text-muted d-block mb-3"></i>
                                <h5 class="text-muted">尚無發票記錄</h5>
                                <p class="text-muted">開始新增您的第一張發票吧！</p>
                                <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#invoiceForm">
                                    <i class="bi bi-plus-lg"></i> 新增發票
                                </button>
                            </div>
                        <?php endif; ?>
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
                            window.location.replace('invoice.php');
                        });
                    <?php endif; ?>
                }
            });
        <?php endif; ?>

        // 提交發票表單
        function submitInvoice() {
            const form = document.getElementById('addInvoiceForm');
            const submitBtn = event.target;
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>處理中...';
            
            form.submit();
        }

        // 確認刪除
        function confirmDelete(invoiceId) {
            if (confirm("確定要刪除此發票記錄嗎？\n\n此操作將會：\n• 刪除發票記錄\n• 刪除相關交易記錄\n• 恢復對應的總資產金額\n\n此操作無法復原！")) {
                const deleteBtn = event.target.closest('button');
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
                
                window.location.href = "invoice.php?delete=" + invoiceId;
            }
        }

        // 發票號碼自動轉大寫
        document.addEventListener('DOMContentLoaded', function() {
            const invoiceInput = document.querySelector('input[name="invoice_number"]');
            if (invoiceInput) {
                invoiceInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            }

            // 設定預設日期為今天
            const dateInput = document.querySelector('input[name="issue_date"]');
            if (dateInput && !dateInput.value) {
                dateInput.value = new Date().toISOString().split('T')[0];
            }

            // 當表單展開時重置
            const invoiceForm = document.getElementById('invoiceForm');
            if (invoiceForm) {
                invoiceForm.addEventListener('show.bs.collapse', function () {
                    const form = document.getElementById('addInvoiceForm');
                    form.reset();
                    
                    // 重新設定日期
                    const dateInput = form.querySelector('input[name="issue_date"]');
                    if (dateInput) {
                        dateInput.value = new Date().toISOString().split('T')[0];
                    }
                    
                    // 重置按鈕狀態
                    const submitBtn = form.querySelector('.btn-primary');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> 新增發票';
                });
            }
        });
    </script>
</body>
</html>
