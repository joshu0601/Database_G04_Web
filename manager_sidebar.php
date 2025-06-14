<?php

// 這裡不需要任何 PHP 代碼，但是需要確保 PHP 標籤正確關閉
?>

<!-- 管理者側邊欄樣式 -->
<style>
    /* 基本樣式 */
    body {
        background-color: #f8f9fa;
        min-height: 100vh;
        padding-top: 15px;
        transition: all 0.3s ease;
    }
    
    /* 調整側邊欄寬度和容器 */
    .sidebar-container {
        width: 300px; /* 將寬度從 col-md-3 (25%) 調整為固定 240px */
        flex: 0 0 300px;
    }
    
    .main-content-container {
        width: calc(100% - 260px); /* 主內容寬度 = 100% - sidebar寬度 - 間距 */
        flex: 1;
        margin-left: 20px;
    }
    
    /* 管理者側邊欄樣式 */
    .manager-sidebar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: calc(100vh - 30px);
        border-radius: 15px;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        padding: 20px 15px; /* 減少內邊距 */
        color: white;
        width: 100%;
    }
    
    .manager-sidebar .nav-link {
        border-radius: 8px; /* 調小圓角 */
        margin-bottom: 6px; /* 減少間距 */
        color: rgba(255, 255, 255, 0.9);
        transition: all 0.3s ease;
        padding: 10px 12px; /* 減少內邊距 */
        font-weight: 500;
        font-size: 0.9rem; /* 調小字體 */
    }
    
    .manager-sidebar .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.15);
        color: white;
        transform: translateX(5px);
    }
    
    .manager-sidebar .nav-link.active {
        background-color: rgba(255, 255, 255, 0.25);
        color: white;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    
    .manager-sidebar .nav-link i {
        margin-right: 10px; /* 減少圖示間距 */
        font-size: 1em;
        width: 18px; /* 固定圖示寬度 */
        text-align: center;
    }
    
    .manager-sidebar .system-title {
        color: white;
        font-weight: 700;
        font-size: 1.2rem; /* 調小標題 */
        text-align: center;
        margin-bottom: 0.4rem;
    }
    
    .manager-sidebar .system-subtitle {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.8rem; /* 調小副標題 */
        text-align: center;
        margin-bottom: 1.2rem;
    }
    
    .manager-sidebar hr {
        border-color: rgba(255, 255, 255, 0.3);
        margin: 1rem 0; /* 減少分隔線間距 */
    }
    
    /* 深色模式切換開關調整 */
    .form-check-input {
        width: 2.5em;
    }
    
    .form-check-label {
        font-size: 0.85rem;
    }
    
    /* 管理者卡片樣式 */
    .manager-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        margin-bottom: 25px;
        overflow: hidden;
    }
    
    .manager-card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-bottom: none;
        font-weight: 600;
        padding: 1rem 1.5rem;
    }
    
    .manager-card-body {
        padding: 1.5rem;
    }
    
    /* 統計卡片樣式 */
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
    
    .stats-card.users .stats-icon { color: #007bff; }
    .stats-card.transactions .stats-icon { color: #28a745; }
    .stats-card.revenue .stats-icon { color: #ffc107; }
    .stats-card.reports .stats-icon { color: #dc3545; }
    
    /* 快速統計調整 */
    .manager-sidebar .mt-4 h6 {
        font-size: 0.9rem;
        margin-bottom: 0.8rem;
    }
    
    .manager-sidebar .small {
        font-size: 0.8rem;
    }
    
    .manager-sidebar .small .d-flex {
        margin-bottom: 6px;
    }
    
    /* 深色模式樣式 */
    body.dark-mode {
        background-color: #121212;
        color: #e0e0e0;
    }
    
    .dark-mode .manager-sidebar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .dark-mode .manager-card {
        background-color: #252525;
        color: #e0e0e0;
    }
    
    .dark-mode .manager-card-header {
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    }
    
    .dark-mode .stats-card {
        background-color: #2d2d2d;
        color: #e0e0e0;
    }
    
    .dark-mode .stats-card .stats-label {
        color: #adb5bd;
    }
    
    /* 深色模式表格樣式 */
    .dark-mode .table {
        color: #e0e0e0 !important;
        background-color: #252525 !important;
    }
    
    .dark-mode .table td,
    .dark-mode .table th {
        background-color: #252525 !important;
        border-color: #333 !important;
        color: #e0e0e0 !important;
    }
    
    .dark-mode .table-hover > tbody > tr:hover > * {
        background-color: #333 !important;
        color: #e0e0e0 !important;
    }
    
    /* 響應式設計 */
    @media (max-width: 992px) {
        .sidebar-container {
            width: 200px;
            flex: 0 0 200px;
        }
        
        .main-content-container {
            width: calc(100% - 215px);
            margin-left: 15px;
        }
        
        .manager-sidebar .nav-link {
            padding: 8px 10px;
            font-size: 0.85rem;
        }
        
        .manager-sidebar .system-title {
            font-size: 1.1rem;
        }
        
        .manager-sidebar .system-subtitle {
            font-size: 0.75rem;
        }
    }
    
    @media (max-width: 768px) {
        .sidebar-container {
            width: 100%;
            flex: 0 0 100%;
            margin-bottom: 15px;
        }
        
        .main-content-container {
            width: 100%;
            margin-left: 0;
        }
        
        .manager-sidebar {
            min-height: auto;
        }
    }
</style>

<!-- 深色模式切換 JavaScript -->
<script>
    // 主題切換功能
    function toggleManagerTheme() {
        const body = document.body;
        const html = document.documentElement;
        const toggle = document.getElementById('managerDarkModeToggle');
        const isDark = body.classList.contains('dark-mode');
        
        if (isDark) {
            // 切換到淺色模式
            body.classList.remove('dark-mode');
            html.classList.remove('dark-mode');
            localStorage.setItem('manager-theme', 'light');
            html.style.backgroundColor = '';
            html.style.color = '';
            if (toggle) toggle.checked = false;
        } else {
            // 切換到深色模式
            body.classList.add('dark-mode');
            html.classList.add('dark-mode');
            localStorage.setItem('manager-theme', 'dark');
            html.style.backgroundColor = '#1a1a1a';
            html.style.color = '#e0e0e0';
            if (toggle) toggle.checked = true;
        }
    }

    // 頁面載入時應用儲存的主題
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('manager-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const toggle = document.getElementById('managerDarkModeToggle');
        
        // 應用主題
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.body.classList.add('dark-mode');
            document.documentElement.classList.add('dark-mode');
            document.documentElement.style.backgroundColor = '#1a1a1a';
            document.documentElement.style.color = '#e0e0e0';
            if (toggle) toggle.checked = true;
        } else {
            document.body.classList.remove('dark-mode');
            document.documentElement.classList.remove('dark-mode');
            document.documentElement.style.backgroundColor = '';
            document.documentElement.style.color = '';
            if (toggle) toggle.checked = false;
        }
        
        // 綁定切換事件
        if (toggle) {
            toggle.addEventListener('change', toggleManagerTheme);
        }
    });
</script>

<!-- 管理者側邊欄 HTML 內容 -->
<div class="sidebar-container">
    <div class="manager-sidebar sticky-top">
        <div class="text-center mb-3">
            <div class="system-title">
                <i class="bi bi-shield-check me-2"></i>管理者後台
            </div>
            <p class="system-subtitle">系統管理與監控中心</p>
        </div>
        
        <div class="d-flex justify-content-center mb-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="managerDarkModeToggle">
                <label class="form-check-label text-white" for="managerDarkModeToggle">
                    <i class="bi bi-moon-stars"></i> 深色模式
                </label>
            </div>
        </div>
        
        <hr>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'managerdashboard' ? 'active' : '' ?>" href="managerdashboard.php">
                    <i class="bi bi-speedometer2"></i> <strong>系統總覽</strong>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'blacklistview' ? 'active' : '' ?>" href="blacklistview.php">
                    <i class="bi bi-people"></i> <strong>黑名單</strong>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'user_feedback' ? 'active' : '' ?>" href="feedback_bug_reports.php">
                    <i class="bi bi-chat-square-dots"></i> <strong>用戶回饋</strong>
                </a>
            </li>      
            <hr>
    
            <li class="nav-item">
                <a class="nav-link text-warning" href="login.php?logout=1" onclick="return confirm('確定要登出嗎？')">
                    <i class="bi bi-box-arrow-right"></i> <strong>安全登出</strong>
                </a>
            </li>
        </ul>
        
        <hr>
        
        <!-- 快速統計 -->
        <div class="mt-4">
            <h6 class="text-white mb-3">
                <i class="bi bi-graph-up me-2"></i>快速統計
            </h6>
            <div class="small text-white-50">
                <div class="d-flex justify-content-between mb-2">
                    <span>在線用戶</span>
                    <span class="text-success fw-bold">--</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>今日交易</span>
                    <span class="text-info fw-bold">--</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>系統狀態</span>
                    <span class="text-success fw-bold">
                        <i class="bi bi-check-circle-fill"></i> 正常
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>