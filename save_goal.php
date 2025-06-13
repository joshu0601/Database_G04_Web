<?php
session_start();
$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'customer', '1234');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 登入檢查
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

// 處理刪除
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $goal_id = intval($_GET['delete']);
    try {
        $stmt = $db->prepare("DELETE FROM saving_goals WHERE goal_id = ? AND user_id = ?");
        $stmt->execute([$goal_id, $user_id]);
        
        $message = '儲蓄目標已成功刪除！';
        $message_type = 'success';
        $show_toast = true;
    } catch (Exception $e) {
        $message = '刪除失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 處理加錢
if (isset($_POST['add_money']) && is_numeric($_POST['goal_id']) && is_numeric($_POST['add_amount'])) {
    try {
        $goal_id = intval($_POST['goal_id']);
        $add_amount = intval($_POST['add_amount']);
        
        if ($add_amount <= 0) {
            throw new Exception('請輸入正確的金額');
        }
        
        // 先檢查目標是否已完成
        $check_stmt = $db->prepare("SELECT current_amount, target_amount FROM saving_goals WHERE goal_id = ? AND user_id = ?");
        $check_stmt->execute([$goal_id, $user_id]);
        $goal_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$goal_data) {
            throw new Exception('找不到該儲蓄目標');
        }
        
        // 檢查是否已完成
        if ($goal_data['current_amount'] >= $goal_data['target_amount']) {
            throw new Exception('此儲蓄目標已完成，無法再新增金額');
        }
        
        $new_amount = $goal_data['current_amount'] + $add_amount;
        
        // 限制不能超過目標金額
        if ($new_amount > $goal_data['target_amount']) {
            $new_amount = $goal_data['target_amount'];
            $actual_add = $goal_data['target_amount'] - $goal_data['current_amount'];
        } else {
            $actual_add = $add_amount;
        }
        
        // 更新金額
        $stmt = $db->prepare("UPDATE saving_goals SET current_amount = ? WHERE goal_id = ? AND user_id = ?");
        $stmt->execute([$new_amount, $goal_id, $user_id]);
        
        // 檢查是否達到目標
        if ($new_amount >= $goal_data['target_amount']) {
            $message = '🎉 恭喜！您已達成儲蓄目標！已新增 $' . number_format($actual_add) . '！';
            $stmt = $db->prepare("UPDATE saving_goals SET status = 'Completed' WHERE goal_id = ? AND user_id = ?");
            $stmt->execute([$goal_id, $user_id]);
            $message_type = 'success';
        } else {
            $message = '已成功新增 $' . number_format($actual_add) . ' 到儲蓄目標！';
            $message_type = 'success';
        }
        $show_toast = true;
    } catch (Exception $e) {
        $message = '新增金額失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 新增目標表單送出
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name']) && !isset($_POST['add_money'])) {
    try {
        $name = trim($_POST['name']);
        $target_amount = intval($_POST['target_amount']);
        $current_amount = isset($_POST['current_amount']) && $_POST['current_amount'] !== "" ? intval($_POST['current_amount']) : 0;
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        if (empty($name)) {
            throw new Exception('請輸入目標名稱');
        }
        
        if ($target_amount <= 0) {
            throw new Exception('目標金額必須大於 0');
        }
        
        if ($current_amount < 0) {
            throw new Exception('目前金額不能為負數');
        }
        
        if (empty($start_date) || empty($end_date)) {
            throw new Exception('請選擇開始和結束日期');
        }
        
        if (strtotime($start_date) >= strtotime($end_date)) {
            throw new Exception('結束日期必須晚於開始日期');
        }
        
        // 檢查是否已存在相同名稱的目標
        $check_stmt = $db->prepare("SELECT COUNT(*) FROM saving_goals WHERE user_id = ? AND name = ?");
        $check_stmt->execute([$user_id, $name]);
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception('此目標名稱已存在');
        }

        $stmt = $db->prepare("INSERT INTO saving_goals (user_id, name, target_amount, current_amount, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $name, $target_amount, $current_amount, $start_date, $end_date]);
        
        $message = '儲蓄目標新增成功！';
        $message_type = 'success';
        $show_toast = true;
    } catch (Exception $e) {
        $message = '新增失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 撈出資料 from saving_goal_status VIEW
try {
    $stmt = $db->prepare("SELECT goal_id, goal_name, target_amount, current_amount, start_date, end_date, status, remaining_days FROM saving_goal_status WHERE user_id = ? ORDER BY 
        CASE 
            WHEN status = '進行中' THEN 1
            WHEN status = '已逾期' THEN 2
            WHEN status = '已完成' THEN 3
        END, start_date DESC");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $goals = [];
    if (empty($message)) {
        $message = '載入儲蓄目標失敗：' . $e->getMessage();
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
    <title>儲蓄目標管理</title>
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
        
        /* 響應式設計 */
        @media (max-width: 768px) {
            .page-header {
                padding: 2rem 1.5rem;
            }
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
        
        /* 深色模式下的金額文字顏色修正 */
        .dark-mode .text-primary {
            color: #6ea8fe !important;
        }
        
        .dark-mode .text-success {
            color: #75b798 !important;
        }
        
        .dark-mode .text-muted {
            color: #adb5bd !important;
        }
        
        .dark-mode .goal-info {
            color: #adb5bd !important;
        }
        
        .dark-mode .goal-title {
            color: #ffffff !important;
        }
        
        .dark-mode .fw-bold {
            color: inherit !important;
        }
        
        /* 確保深色模式下的小標題可見 */
        .dark-mode .small {
            color: #adb5bd !important;
        }
        
        /* 深色模式下的卡片內容 */
        .dark-mode .card-body .small {
            color: #adb5bd !important;
        }
        
        .dark-mode .card-body .fw-bold.text-primary {
            color: #6ea8fe !important;
        }
        
        .dark-mode .card-body .fw-bold.text-success {
            color: #75b798 !important;
        }
        
        /* 深色模式下的 Bootstrap 文字顏色覆蓋 */
        .dark-mode .text-primary {
            color: #6ea8fe !important;
        }
        
        .dark-mode .text-success {
            color: #75b798 !important;
        }
        
        .dark-mode .text-danger {
            color: #f1aeb5 !important;
        }
        
        .dark-mode .text-warning {
            color: #ffda6a !important;
        }
        
        .dark-mode .text-info {
            color: #6edff6 !important;
        }
        
        /* 深色模式下的表單文字 */
        .dark-mode .form-text {
            color: #adb5bd !important;
        }
        
        /* 深色模式下的進度條文字 */
        .dark-mode .goal-info {
            color:rgb(255, 255, 255) !important;
        }
        
        /* 深色模式下確保所有文字都可見 */
        .dark-mode .card .small.text-muted {
            color:rgb(255, 255, 255) !important;
        }
        
        .dark-mode .card .fw-bold {
            color: inherit !important;
        }
        
        /* 儲蓄目標特定樣式 */
        .goal-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .goal-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .goal-card.completed {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
        }
        
        .goal-progress {
            height: 10px;
            border-radius: 8px;
            overflow: hidden;
            background: #e9ecef;
        }
        
        .goal-progress .progress-bar {
            border-radius: 8px;
            transition: width 1s ease-in-out;
        }
        
        /* 深色模式下的卡片樣式 */
        .dark-mode .card {
            background-color: #2d2d2d !important;
            border-color: #404040 !important;
            color: #ffffff !important;
        }
        
        .dark-mode .card-header {
            background-color: #3d3d3d !important;
            border-color: #404040 !important;
            color: #ffffff !important;
        }
        
        .dark-mode .card-footer {
            background-color: #3d3d3d !important;
            border-color: #404040 !important;
        }
        
        .dark-mode .goal-card.completed {
            background: linear-gradient(135deg, #1e4620 0%, #2d5a2f 100%);
            border-color: #28a745;
        }
        
        .dark-mode .goal-progress {
            background: #404040;
        }
        
        /* 表單樣式美化 */
        .form-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            background: white;
        }
        
        .form-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 20px 20px 0 0;
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
        
        /* 深色模式下的表單樣式 */
        .dark-mode .form-card {
            background: #2d2d2d;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .form-header {
            background: linear-gradient(135deg, #374151 0%, #4b5563 100%);
            border-color: #4b5563;
        }
        
        .dark-mode .form-control, .dark-mode .form-select {
            background-color: #404040;
            border-color: #555555;
            color: #ffffff;
        }
        
        .dark-mode .form-control:focus, .dark-mode .form-select:focus {
            background-color: #404040;
            border-color: #667eea;
            color: #ffffff;
        }
        
        .dark-mode .input-group-text {
            background-color: #404040;
            border-color: #555555;
            color: #ffffff;
        }
        
        .dark-mode .form-label {
            color: #ffffff;
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
            $current_page = 'save_goal';
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
                                    <i class="bi bi-bullseye me-3"></i>儲蓄目標管理
                                </h1>
                                <p class="mb-0 opacity-75 fs-5">
                                    設定和追蹤您的儲蓄目標，實現財務夢想
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <button class="btn btn-light btn-lg" data-bs-toggle="collapse" data-bs-target="#addGoalForm" aria-expanded="false">
                                    <i class="bi bi-plus-circle me-2"></i>新增儲蓄目標
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 新增目標表單 -->
                <div class="collapse mb-4" id="addGoalForm">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>新增儲蓄目標</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="goalForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">目標名稱</label>
                                        <input type="text" class="form-control" name="name" required placeholder="例如：買新車、度假基金">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="target_amount" class="form-label">目標金額</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" name="target_amount" required min="1" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="current_amount" class="form-label">目前金額（預設為0）</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" name="current_amount" min="0" value="0" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="start_date" class="form-label">開始日期</label>
                                        <input type="date" class="form-control" name="start_date" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="end_date" class="form-label">結束日期</label>
                                        <input type="date" class="form-control" name="end_date" required>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary" onclick="submitGoal()">
                                        <i class="bi bi-check-lg"></i> 新增目標
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#addGoalForm">
                                        <i class="bi bi-x-lg"></i> 取消
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 儲蓄目標列表 -->
                <?php if (empty($goals)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-bullseye fs-1 text-muted d-block mb-3"></i>
                            <h5 class="text-muted">尚未設定任何儲蓄目標</h5>
                            <p class="text-muted">開始設定您的第一個儲蓄目標吧！</p>
                            <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#addGoalForm">
                                <i class="bi bi-plus-lg"></i> 新增儲蓄目標
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($goals as $goal): 
                            $rate = $goal['target_amount'] > 0 ? ($goal['current_amount'] / $goal['target_amount']) : 0;
                            $rate_pct = round($rate * 100);
                            $progress_class = $rate_pct < 50 ? 'bg-danger' : ($rate_pct < 80 ? 'bg-warning' : 'bg-success');
                            $status_class = $goal['status'] === '已完成' ? 'success' : ($goal['remaining_days'] < 0 ? 'danger' : 'primary');
                            $is_completed = $goal['current_amount'] >= $goal['target_amount'];
                        ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card h-100 <?= $is_completed ? 'border-success' : '' ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h3 class="mb-0 goal-title"><?= htmlspecialchars($goal['goal_name']) ?></h3>
                                        <span class="badge bg-<?= $status_class ?>">
                                            <?= $is_completed ? '已完成' : htmlspecialchars($goal['status']) ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <!-- 進度條 -->
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="goal-info">進度</small>
                                                <small class="goal-info"><?= min($rate_pct, 100) ?>%</small>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar <?= $progress_class ?>" 
                                                     style="width: <?= min($rate_pct, 100) ?>%"></div>
                                            </div>
                                        </div>

                                        <!-- 金額資訊 -->
                                        <div class="row text-center mb-3">
                                            <div class="col-6">
                                                <div class="small text-muted">目前金額</div>
                                                <div class="fw-bold text-primary">$<?= number_format($goal['current_amount']) ?></div>
                                            </div>
                                            <div class="col-6">
                                                <div class="small text-muted">目標金額</div>
                                                <div class="fw-bold text-success">$<?= number_format($goal['target_amount']) ?></div>
                                            </div>
                                        </div>

                                        <!-- 日期資訊 -->
                                        <div class="small goal-info mb-2">
                                            <i class="bi bi-calendar-range me-1"></i>
                                            <?= htmlspecialchars($goal['start_date']) ?> ~ <?= htmlspecialchars($goal['end_date']) ?>
                                        </div>
                                        
                                        <div class="small goal-info mb-3">
                                            <i class="bi bi-clock me-1"></i>
                                            <?= $goal['remaining_days'] >= 0 ? '剩餘 ' . $goal['remaining_days'] . ' 天' : '已逾期 ' . abs($goal['remaining_days']) . ' 天' ?>
                                        </div>

                                        <!-- 完成提示或加錢表單 -->
                                        <?php if ($is_completed): ?>
                                            <div class="alert alert-success mb-3">
                                                <i class="bi bi-trophy-fill me-2"></i>
                                                <strong>恭喜！</strong> 您已達成此儲蓄目標！
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" class="mb-3" id="addMoneyForm<?= $goal['goal_id'] ?>">
                                                <input type="hidden" name="goal_id" value="<?= $goal['goal_id'] ?>">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" name="add_amount" 
                                                           placeholder="增加金額" required min="1" 
                                                           max="<?= $goal['target_amount'] - $goal['current_amount'] ?>">
                                                    <button type="button" class="btn btn-success" 
                                                            onclick="submitAddMoney(<?= $goal['goal_id'] ?>)">
                                                        <i class="bi bi-plus-lg"></i> 加錢
                                                    </button>
                                                </div>
                                                <small class="text-muted">
                                                    還需要 $<?= number_format($goal['target_amount'] - $goal['current_amount']) ?> 即可達成目標
                                                </small>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer d-flex justify-content-end">
                                        <button class="btn btn-outline-danger btn-sm" 
                                                onclick="confirmDelete(<?= $goal['goal_id'] ?>)">
                                            <i class="bi bi-trash"></i> 刪除
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                        delay: <?= $message_type === 'success' ? '4000' : '6000' ?> // 成功訊息4秒，錯誤訊息6秒
                    });
                    toast.show();
                    
                    // 如果是成功訊息，Toast 隱藏後重新載入頁面
                    <?php if ($message_type === 'success'): ?>
                        toastElement.addEventListener('hidden.bs.toast', function() {
                            window.location.replace('save_goal.php');
                        });
                    <?php endif; ?>
                }
            });
        <?php endif; ?>

        // 提交新增目標表單
        function submitGoal() {
            const form = document.getElementById('goalForm');
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

        // 提交加錢表單
        function submitAddMoney(goalId) {
            const form = document.getElementById('addMoneyForm' + goalId);
            const submitBtn = event.target;
            
            // 驗證表單
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // 顯示載入狀態
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>處理中...';
            
            // 添加 add_money 標記
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'add_money';
            hiddenInput.value = '1';
            form.appendChild(hiddenInput);
            
            form.submit();
        }

        function confirmDelete(goal_id) {
            if (confirm("確定要刪除此儲蓄目標嗎？此操作無法復原。")) {
                // 顯示載入提示
                const deleteBtn = event.target.closest('button');
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> 刪除中...';
                
                window.location.href = "save_goal.php?delete=" + goal_id;
            }
        }

        // 設定預設日期
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const startDateInput = document.querySelector('input[name="start_date"]');
            const endDateInput = document.querySelector('input[name="end_date"]');
            
            if (startDateInput) {
                startDateInput.value = today;
            }
            
            // 設定結束日期為30天後
            if (endDateInput) {
                const endDate = new Date();
                endDate.setDate(endDate.getDate() + 30);
                endDateInput.value = endDate.toISOString().split('T')[0];
            }
            
            // 當表單摺疊顯示時重置表單
            const addGoalForm = document.getElementById('addGoalForm');
            if (addGoalForm) {
                addGoalForm.addEventListener('show.bs.collapse', function () {
                    const form = document.getElementById('goalForm');
                    form.reset();
                    startDateInput.value = today;
                    
                    const endDate = new Date();
                    endDate.setDate(endDate.getDate() + 30);
                    endDateInput.value = endDate.toISOString().split('T')[0];
                    
                    // 重置按鈕狀態
                    const submitBtn = form.querySelector('.btn-primary');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> 新增目標';
                });
            }
        });
    </script>
</body>
</html>
