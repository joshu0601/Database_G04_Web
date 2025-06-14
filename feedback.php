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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_type'])) {
    try {
        $report_type = $_POST['report_type'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);

        if (!in_array($report_type, ['Bug', 'Suggestion'])) {
            throw new Exception('請選擇正確的回報類型');
        }
        
        if (empty($title)) {
            throw new Exception('請填寫標題');
        }
        
        if (empty($content)) {
            throw new Exception('請填寫內容');
        }
        
        if (strlen($title) > 100) {
            throw new Exception('標題不可超過100個字元');
        }
        
        if (strlen($content) > 1000) {
            throw new Exception('內容不可超過1000個字元');
        }

        $stmt = $db->prepare("INSERT INTO feedback_reports (user_id, report_type, title, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $report_type, $title, $content]);
        
        $message = "回報已成功送出，感謝您的意見！我們會儘快處理您的回報。";
        $message_type = 'success';
        $show_toast = true;
        
    } catch (Exception $e) {
        $message = '送出失敗：' . $e->getMessage();
        $message_type = 'danger';
        $show_toast = true;
    }
}

// 讀取現有回報紀錄
try {
    $stmt = $db->prepare("SELECT report_id, report_type, title, created_at, content FROM feedback_reports WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $reports = [];
    if (empty($message)) {
        $message = '載入回報紀錄失敗：' . $e->getMessage();
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
    <title>意見回饋</title>
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
        
        .bug-reports-card {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .suggestion-reports-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .total-reports-card {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }
        
        /* 表單樣式 */
        .form-card {
            border: none;
            border-radius: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            background: white;
            margin-bottom: 2rem;
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
        
        /* 回報卡片樣式 */
        .report-card {
            border: none;
            border-radius: 24px;
            box-shadow: 
                0 4px 20px rgba(0, 0, 0, 0.06),
                0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 2rem;
            overflow: hidden;
            background: white;
            position: relative;
        }
        
        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.12),
                0 4px 16px rgba(0, 0, 0, 0.1);
        }
        
        .report-card:hover::before {
            opacity: 1;
        }
        
        /* 標題增強 */
        .report-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
            line-height: 1.4;
            letter-spacing: -0.02em;
            position: relative;
        }
        
        /* 元數據區域美化 */
        .report-meta {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            font-size: 0.9rem;
            color: #6b7280;
            flex-wrap: wrap;
        }
        
        /* 徽章樣式增強 */
        .report-type-badge {
            padding: 0.4rem 1rem;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: relative;
            overflow: hidden;
        }
        
        .badge-bug {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            border: 1px solid #fecaca;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.15);
        }
        
        .badge-suggestion {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #16a34a;
            border: 1px solid #bbf7d0;
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15);
        }
        
        /* 時間顯示美化 */
        .report-meta span:has(.bi-calendar) {
            background: rgba(102, 126, 234, 0.1);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            color: #667eea;
            font-weight: 500;
        }
        
        /* 回報頭部美化 */
        .report-header {
            padding: 2rem 2.5rem 1.5rem;
            background: 
                linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%),
                linear-gradient(90deg, transparent 0%, rgba(102, 126, 234, 0.02) 50%, transparent 100%);
            position: relative;
        }
        
        .report-body {
            padding: 0 2.5rem 2.5rem;
        }
        
        /* 回報內容展開動畫 */
        .report-content {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 16px;
            padding: 0;
            border-left: 4px solid #667eea;
            white-space: pre-wrap;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.7;
            color: #374151;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            margin-top: 1rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
        }
        
        .report-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.3), transparent);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .report-content.show {
            opacity: 1;
            max-height: 600px;
            overflow-y: auto;
            padding: 2rem;
            box-shadow: 
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                0 4px 20px rgba(102, 126, 234, 0.08),
                0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .report-content.show::before {
            opacity: 1;
        }
        
        /* 內容文字美化 */
        .report-content p {
            margin-bottom: 1rem;
            text-align: justify;
        }
        
        .report-content p:last-child {
            margin-bottom: 0;
        }
        
        /* 自定義滾動條 */
        .report-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .report-content::-webkit-scrollbar-track {
            background: rgba(241, 245, 249, 0.8);
            border-radius: 10px;
            margin: 8px 0;
        }
        
        .report-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #cbd5e1, #94a3b8);
            border-radius: 10px;
            border: 2px solid rgba(248, 250, 252, 0.8);
            transition: all 0.3s ease;
        }
        
        .report-content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #94a3b8, #64748b);
            border-color: rgba(248, 250, 252, 0.6);
        }
        
        /* 按鈕樣式增強 */
        .toggle-btn {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(102, 126, 234, 0.05));
            border: 1px solid rgba(102, 126, 234, 0.2);
            color: #667eea;
            font-weight: 600;
            cursor: pointer;
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .toggle-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s ease;
        }
        
        .toggle-btn:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(102, 126, 234, 0.1));
            border-color: rgba(102, 126, 234, 0.3);
            color: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .toggle-btn:hover::before {
            left: 100%;
        }
        
        .toggle-btn:active {
            transform: translateY(-1px);
        }
        
        .toggle-btn i {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-block;
        }
        
        .toggle-btn.expanded i {
            transform: rotate(180deg);
        }
        
        /* 回報卡片增強 */
        .report-card {
            border: none;
            border-radius: 24px;
            box-shadow: 
                0 4px 20px rgba(0, 0, 0, 0.06),
                0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 2rem;
            overflow: hidden;
            background: white;
            position: relative;
        }
        
        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.3),
                0 4px 16px rgba(0, 0, 0, 0.2);
        }
        
        .report-card:hover::before {
            opacity: 1;
        }
        
        /* 深色模式樣式 */
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
        
        /* 深色模式增強 */
        .dark-mode .report-content {
            background: linear-gradient(135deg, #374151 0%, #334155 100%);
            color: #e5e7eb;
            border-left-color: #60a5fa;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }
        
        .dark-mode .report-content::before {
            background: linear-gradient(90deg, transparent, rgba(96, 165, 250, 0.4), transparent);
        }
        
        .dark-mode .report-content.show {
            box-shadow: 
                inset 0 1px 0 rgba(255, 255, 255, 0.05),
                0 4px 20px rgba(96, 165, 250, 0.1),
                0 1px 3px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .report-content::-webkit-scrollbar-track {
            background: rgba(75, 85, 99, 0.8);
        }
        
        .dark-mode .report-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            border-color: rgba(55, 65, 81, 0.8);
        }
        
        .dark-mode .report-content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #9ca3af, #d1d5db);
        }
        
        .dark-mode .toggle-btn {
            background: linear-gradient(135deg, rgba(147, 197, 253, 0.1), rgba(147, 197, 253, 0.05));
            border-color: rgba(147, 197, 253, 0.2);
            color: #93c5fd;
        }
        
        .dark-mode .toggle-btn:hover {
            background: linear-gradient(135deg, rgba(147, 197, 253, 0.15), rgba(147, 197, 253, 0.1));
            border-color: rgba(147, 197, 253, 0.3);
            color: #60a5fa;
            box-shadow: 0 4px 12px rgba(147, 197, 253, 0.2);
        }
        
        .dark-mode .report-card {
            background: #1f2937;
            box-shadow: 
                0 4px 20px rgba(0, 0, 0, 0.2),
                0 1px 3px rgba(0, 0, 0, 0.3);
        }
        
        .dark-mode .report-card::before {
            background: linear-gradient(90deg, #60a5fa, #3b82f6);
        }
        
        .dark-mode .report-card:hover {
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.3),
                0 4px 16px rgba(0, 0, 0, 0.2);
        }
        
        .dark-mode .report-header {
            background: 
                linear-gradient(135deg, #374151 0%, #4b5563 100%),
                linear-gradient(90deg, transparent 0%, rgba(96, 165, 250, 0.03) 50%, transparent 100%);
        }
        
        .dark-mode .report-title {
            color: #f9fafb;
        }
        
        .dark-mode .report-meta {
            color: #d1d5db;
        }
        
        .dark-mode .report-meta span:has(.bi-calendar) {
            background: rgba(96, 165, 250, 0.15);
            color: #93c5fd;
        }
        
        .dark-mode .badge-bug {
            background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
            color: #fca5a5;
            border-color: #dc2626;
            box-shadow: 0 2px 8px rgba(248, 113, 113, 0.2);
        }
        
        .dark-mode .badge-suggestion {
            background: linear-gradient(135deg, #14532d 0%, #166534 100%);
            color: #86efac;
            border-color: #16a34a;
            box-shadow: 0 2px 8px rgba(134, 239, 172, 0.2);
        }
        
        /* 深色模式下的表單樣式修改 */
        .dark-mode .form-card {
            background-color: #2a2a2a;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .dark-mode .form-header {
            background: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            border-bottom: 1px solid #4b5563;
            color: #e5e7eb;
        }

        .dark-mode .form-body {
            background-color: #2a2a2a;
            color: #e5e7eb;
        }

        .dark-mode .form-control, 
        .dark-mode .form-select {
            background-color: #374151;
            border-color: #4b5563;
            color: #e5e7eb;
        }

        .dark-mode .form-control:focus, 
        .dark-mode .form-select:focus {
            background-color: #374151;
            border-color: #60a5fa;
            box-shadow: 0 0 0 0.2rem rgba(96, 165, 250, 0.25);
            color: #ffffff;
        }

        .dark-mode .form-control::placeholder, 
        .dark-mode .form-select::placeholder {
            color: #9ca3af;
        }

        .dark-mode .form-text {
            color: #9ca3af;
        }

        .dark-mode .form-text.text-warning {
            color: #fbbf24 !important;
        }

        /* 深色模式下的表單按鈕 */
        .dark-mode .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-color: #2563eb;
            color: #ffffff;
        }

        .dark-mode .btn-primary:hover {
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .dark-mode .btn-outline-secondary {
            color: #d1d5db;
            border-color: #6b7280;
            background-color: transparent;
        }

        .dark-mode .btn-outline-secondary:hover {
            background-color: #4b5563;
            border-color: #9ca3af;
            color: #f9fafb;
        }

        /* 深色模式下的摺疊按鈕 */
        .dark-mode .btn-light {
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            border-color: #6b7280;
            color: #f9fafb;
        }

        .dark-mode .btn-light:hover {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            border-color: #9ca3af;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.4);
        }

        /* 深色模式下的選項文字顏色 */
        .dark-mode select option {
            background-color: #1f2937;
            color: #e5e7eb;
        }

        /* 深色模式下的空狀態樣式 */
        .dark-mode .empty-state {
            color: #e5e7eb;
        }

        .dark-mode .empty-icon i {
            color: #60a5fa;
            opacity: 0.6;
        }

        .dark-mode .empty-title {
            color: #f9fafb;
        }

        .dark-mode .empty-description {
            color: #d1d5db;
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
            $current_page = 'feedback';
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
                                    <i class="bi bi-chat-dots me-3"></i>意見回饋
                                </h1>
                                <p class="mb-0 opacity-75 fs-5">
                                    回報問題或提供建議，幫助我們改善系統功能
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <button class="btn btn-light btn-lg" data-bs-toggle="collapse" data-bs-target="#feedbackForm" aria-expanded="false">
                                    <i class="bi bi-plus-circle me-2"></i>新增回報
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 統計概覽 -->
                <?php
                $bug_count = array_filter($reports, function($r) { return $r['report_type'] === 'Bug'; });
                $suggestion_count = array_filter($reports, function($r) { return $r['report_type'] === 'Suggestion'; });
                ?>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card total-reports-card">
                            <div class="card-body text-center">
                                <i class="bi bi-list-ul display-4 mb-3"></i>
                                <h3 class="mb-2"><?= count($reports) ?></h3>
                                <p class="mb-0">總回報數</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bug-reports-card">
                            <div class="card-body text-center">
                                <i class="bi bi-bug display-4 mb-3"></i>
                                <h3 class="mb-2"><?= count($bug_count) ?></h3>
                                <p class="mb-0">錯誤回報</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card suggestion-reports-card">
                            <div class="card-body text-center">
                                <i class="bi bi-lightbulb display-4 mb-3"></i>
                                <h3 class="mb-2"><?= count($suggestion_count) ?></h3>
                                <p class="mb-0">建議回報</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 新增回報表單 -->
                <div class="collapse mb-4" id="feedbackForm">
                    <div class="form-card">
                        <div class="form-header">
                            <h5 class="mb-0">
                                <i class="bi bi-plus-circle me-2"></i>新增意見回報
                            </h5>
                        </div>
                        <div class="form-body">
                            <form method="POST" id="feedbackFormElement">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="report_type" class="form-label">
                                            <i class="bi bi-tag me-1"></i>回報類型 <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" name="report_type" id="report_type" required>
                                            <option value="">請選擇回報類型</option>
                                            <option value="Bug">🐛 錯誤回報 (Bug)</option>
                                            <option value="Suggestion">💡 功能建議 (Suggestion)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="title" class="form-label">
                                            <i class="bi bi-card-text me-1"></i>標題 <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" name="title" id="title" maxlength="100" required placeholder="簡短描述問題或建議">
                                        <div class="form-text">最多100個字元</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="content" class="form-label">
                                        <i class="bi bi-file-text me-1"></i>詳細內容 <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" name="content" id="content" rows="6" maxlength="1000" required placeholder="請詳細描述問題的發生情況、重現步驟，或具體的功能建議..."></textarea>
                                    <div class="form-text">最多1000個字元，請盡可能詳細說明</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-fill">
                                        <i class="bi bi-send me-2"></i>送出回報
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#feedbackForm">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- 回報列表 -->
                <?php if (!empty($reports)): ?>
                    <div class="row">
                        <div class="col-12">
                            <h4 class="mb-3">
                                <i class="bi bi-clock-history me-2"></i>您的回報記錄
                            </h4>
                        </div>
                        <?php foreach ($reports as $report): ?>
                            <div class="col-12">
                                <div class="report-card">
                                    <div class="report-header">
                                        <div class="report-title"><?= htmlspecialchars($report['title']) ?></div>
                                        <div class="report-meta">
                                            <span class="report-type-badge <?= $report['report_type'] === 'Bug' ? 'badge-bug' : 'badge-suggestion' ?>">
                                                <?= $report['report_type'] === 'Bug' ? '🐛 Bug' : '💡 Suggestion' ?>
                                            </span>
                                            <span>
                                                <i class="bi bi-calendar me-1"></i>
                                                <?= date('Y/m/d H:i', strtotime($report['created_at'])) ?>
                                            </span>
                                            <button class="toggle-btn" onclick="toggleContent(<?= $report['report_id'] ?>)">
                                                <i class="bi bi-eye me-1"></i>
                                                <span id="toggle-text-<?= $report['report_id'] ?>">顯示內容</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="report-body">
                                        <div class="report-content" id="content-<?= $report['report_id'] ?>">
                                            <?= nl2br(htmlspecialchars($report['content'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-chat-dots"></i>
                        </div>
                        <h3 class="empty-title">尚無回報記錄</h3>
                        <p class="empty-description">
                            歡迎提供您的寶貴意見！<br>
                            無論是發現系統錯誤還是有功能改善建議，我們都非常樂意聽取
                        </p>
                        <button class="btn btn-primary btn-lg" data-bs-toggle="collapse" data-bs-target="#feedbackForm">
                            <i class="bi bi-plus-circle me-2"></i>提交第一個回報
                        </button>
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
                        delay: <?= $message_type === 'success' ? '4000' : '6000' ?>
                    });
                    toast.show();
                }
            });
        <?php endif; ?>
        
        // 切換內容顯示/隱藏
        function toggleContent(id) {
            const content = document.getElementById('content-' + id);
            const toggleText = document.getElementById('toggle-text-' + id);
            const toggleBtn = toggleText.parentElement;
            const isVisible = content.classList.contains('show');
            
            if (isVisible) {
                // 隱藏內容
                content.classList.remove('show');
                toggleText.textContent = '顯示內容';
                toggleBtn.classList.remove('expanded');
                
                // 更新圖示
                const icon = toggleText.previousElementSibling;
                icon.className = 'bi bi-eye me-1';
            } else {
                // 顯示內容
                content.classList.add('show');
                toggleText.textContent = '隱藏內容';
                toggleBtn.classList.add('expanded');
                
                // 更新圖示
                const icon = toggleText.previousElementSibling;
                icon.className = 'bi bi-eye-slash me-1';
            }
        }
        
        // 表單重置
        document.addEventListener('DOMContentLoaded', function() {
            const feedbackFormCollapse = document.getElementById('feedbackForm');
            if (feedbackFormCollapse) {
                feedbackFormCollapse.addEventListener('show.bs.collapse', function () {
                    const form = document.getElementById('feedbackFormElement');
                    if (form) {
                        form.reset();
                    }
                });
            }
        });
        
        // 表單驗證
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('feedbackFormElement');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>送出中...';
                    }
                });
            }
        });
        
        // 字數計算
        document.addEventListener('DOMContentLoaded', function() {
            const titleInput = document.getElementById('title');
            const contentTextarea = document.getElementById('content');
            
            function updateCharCount(element, maxLength) {
                const current = element.value.length;
                const formText = element.nextElementSibling;
                if (formText && formText.classList.contains('form-text')) {
                    formText.textContent = `${current}/${maxLength} 個字元`;
                    if (current > maxLength * 0.9) {
                        formText.classList.add('text-warning');
                    } else {
                        formText.classList.remove('text-warning');
                    }
                }
            }
            
            if (titleInput) {
                titleInput.addEventListener('input', function() {
                    updateCharCount(this, 100);
                });
            }
            
            if (contentTextarea) {
                contentTextarea.addEventListener('input', function() {
                    updateCharCount(this, 1000);
                });
            }
        });
    </script>
</body>
</html>
