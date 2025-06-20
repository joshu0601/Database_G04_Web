<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 設定當前頁面標識
$current_page = 'user_feedback';

$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'manager', '5678');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->query("SELECT * FROM feedback_bug_reports ORDER BY created_at DESC");
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 統計數據
$bugCount = $db->query("SELECT COUNT(*) FROM feedback_bug_reports WHERE report_type = 'Bug'")->fetchColumn();
$suggestionCount = $db->query("SELECT COUNT(*) FROM feedback_bug_reports WHERE report_type = 'Suggestion'")->fetchColumn();
$totalCount = count($reports);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用戶回饋管理 - 管理者後台</title>
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
        
        /* 回報類型徽章 */
        .badge-bug {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .badge-suggestion {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        }
        
        .content-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            text-align: left;
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
        
        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .btn-view:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.4);
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
        
        .stats-card.bugs .stats-icon { color: #dc3545; }
        .stats-card.suggestions .stats-icon { color: #28a745; }
        .stats-card.total .stats-icon { color: #007bff; }
        
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
        
        .dark-mode .empty-state {
            color: #b0b0b0;
        }
        
        /* 回報內容彈出視窗 */
        .modal-content {
            border-radius: 15px;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
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
        
        .report-content {
            white-space: pre-wrap;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .dark-mode .report-content {
            background: #333;
            border-left-color: #667eea;
        }
        
        .report-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .report-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .dark-mode .report-meta-item {
            color: #adb5bd;
        }
        .dark-mode .text-muted {
            color: #adb5bd !important;
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
            
            .content-cell {
                max-width: 150px;
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
                        <i class="bi bi-chat-square-dots me-3"></i>用戶回饋管理
                    </h2>
                    <p class="text-muted mb-0">查看並管理用戶的問題回報與建議</p>
                </div>

                <!-- 統計卡片 -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="stats-card bugs">
                            <div class="stats-icon text-center">
                                <i class="bi bi-bug"></i>
                            </div>
                            <div class="stats-number text-center"><?= $bugCount ?></div>
                            <div class="stats-label text-center">問題回報</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card suggestions">
                            <div class="stats-icon text-center">
                                <i class="bi bi-lightbulb"></i>
                            </div>
                            <div class="stats-number text-center"><?= $suggestionCount ?></div>
                            <div class="stats-label text-center">功能建議</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card total">
                            <div class="stats-icon text-center">
                                <i class="bi bi-chat-dots"></i>
                            </div>
                            <div class="stats-number text-center"><?= $totalCount ?></div>
                            <div class="stats-label text-center">總回饋數</div>
                        </div>
                    </div>
                </div>

                <!-- 資料表格 -->
                <div class="data-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%;"><i class="bi bi-hash me-2"></i>ID</th>
                                    <th style="width: 10%;"><i class="bi bi-person me-2"></i>用戶</th>
                                    <th style="width: 10%;"><i class="bi bi-tag me-2"></i>類型</th>
                                    <th style="width: 15%;"><i class="bi bi-card-heading me-2"></i>標題</th>
                                    <th style="width: 30%;"><i class="bi bi-card-text me-2"></i>內容摘要</th>
                                    <th style="width: 15%;"><i class="bi bi-calendar me-2"></i>建立時間</th>
                                    <th class="action-column"><i class="bi bi-gear-fill me-2"></i>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reports)): ?>
                                    <tr>
                                        <td colspan="7" class="empty-state">
                                            <i class="bi bi-inbox"></i>
                                            <p class="mb-0">目前沒有任何回報紀錄</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reports as $row): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?= htmlspecialchars($row['report_id']) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <strong><?= htmlspecialchars($row['user_name']) ?></strong>
                                                    <small class="text-muted">#<?= htmlspecialchars($row['user_id']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (strtolower($row['report_type']) == 'bug'): ?>
                                                    <span class="badge badge-bug bg-danger">
                                                        <i class="bi bi-bug me-1"></i>問題
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-suggestion bg-success">
                                                        <i class="bi bi-lightbulb me-1"></i>建議
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($row['title']) ?>
                                            </td>
                                            <td class="content-cell">
                                                <?= mb_substr(htmlspecialchars($row['content']), 0, 50) . (mb_strlen($row['content']) > 50 ? '...' : '') ?>
                                            </td>
                                            <td>
                                                <i class="bi bi-clock-history me-1 text-muted"></i>
                                                <?= date('Y-m-d H:i', strtotime($row['created_at'])) ?>
                                            </td>
                                            <td class="action-column">
                                                <button class="btn btn-action btn-view view-report" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#reportModal" 
                                                        data-id="<?= $row['report_id'] ?>"
                                                        data-title="<?= htmlspecialchars($row['title']) ?>"
                                                        data-type="<?= htmlspecialchars($row['report_type']) ?>"
                                                        data-content="<?= htmlspecialchars($row['content']) ?>"
                                                        data-user="<?= htmlspecialchars($row['user_name']) ?>"
                                                        data-userid="<?= htmlspecialchars($row['user_id']) ?>"
                                                        data-time="<?= htmlspecialchars($row['created_at']) ?>"
                                                        title="查看詳情">
                                                    <i class="bi bi-eye-fill"></i>
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
                <?php if (!empty($reports)): ?>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            共有 <strong><?= $totalCount ?></strong> 筆回報紀錄
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 回報詳情模態框 -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-chat-text me-2"></i>
                        <span id="reportTitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="report-meta">
                        <div class="report-meta-item">
                            <i class="bi bi-person-circle"></i>
                            <span id="reportUser"></span>
                        </div>
                        <div class="report-meta-item">
                            <i class="bi bi-calendar-event"></i>
                            <span id="reportTime"></span>
                        </div>
                        <div class="report-meta-item">
                            <i class="bi bi-tag"></i>
                            <span id="reportType"></span>
                        </div>
                    </div>
                    <div class="report-content" id="reportContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
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

        // 填充模態框內容
        document.querySelectorAll('.view-report').forEach(button => {
            button.addEventListener('click', function() {
                const title = this.getAttribute('data-title');
                const type = this.getAttribute('data-type');
                const content = this.getAttribute('data-content');
                const user = this.getAttribute('data-user');
                const userId = this.getAttribute('data-userid');
                const time = this.getAttribute('data-time');
                
                document.getElementById('reportTitle').textContent = title;
                document.getElementById('reportContent').textContent = content;
                document.getElementById('reportUser').textContent = `${user} (#${userId})`;
                document.getElementById('reportTime').textContent = new Date(time).toLocaleString('zh-TW');
                
                const reportType = document.getElementById('reportType');
                if (type.toLowerCase() === 'bug') {
                    reportType.textContent = '問題回報';
                    reportType.className = 'text-danger';
                } else {
                    reportType.textContent = '功能建議';
                    reportType.className = 'text-success';
                }
            });
        });
    </script>
</body>
</html>
