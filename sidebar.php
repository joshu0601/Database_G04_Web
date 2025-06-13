<?php
// 這裡不需要任何 PHP 代碼，但是需要確保 PHP 標籤正確關閉
?>

<!-- 側邊欄樣式 -->
<style>
    /* 基本樣式 */
    body {
        background-color: #f8f9fa;
        min-height: 100vh;
        padding-top: 15px;
        transition: all 0.3s ease;
    }
    
    /* 側邊欄樣式 */
    .sidebar {
        background-color: #ffffff;
        min-height: calc(100vh - 30px);
        border-radius: 10px;
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        padding: 20px 15px;
    }
    
    .sidebar .nav-link {
        border-radius: 8px;
        margin-bottom: 5px;
        color: #495057;
        transition: all 0.2s;
    }
    
    .sidebar .nav-link:hover {
        background-color: #f1f3f5;
    }
    
    .sidebar .nav-link.active {
        background-color: #0d6efd;
        color: white;
    }
    
    .sidebar .nav-link i {
        margin-right: 8px;
    }
    
    /* 卡片樣式 */
    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
    }
    
    .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-weight: 600;
    }
    
    /* 交易記錄顏色 - 收入和支出的顏色對比 */
    .transaction-income {
        color: #28a745 !important;  /* 綠色表示收入 */
        font-weight: bold;
    }
    
    .transaction-expense {
        color: #dc3545 !important;  /* 紅色表示支出 */
        font-weight: bold;
    }
    
    .badge.transaction-income-badge {
        background-color: #28a745 !important;
        color: white !important;
    }
    
    .badge.transaction-expense-badge {
        background-color: #dc3545 !important;
        color: white !important;
    }
    
    /* 深色模式樣式 */
    body.dark-mode {
        background-color: #121212;
        color: #ffffff;
    }
    
    .dark-mode .sidebar {
        background-color: #1e1e1e;
        color: #ffffff;
    }
    
    .dark-mode .sidebar .nav-link {
        color: #ffffff;
    }
    
    .dark-mode .sidebar .nav-link:hover {
        background-color: #333333;
    }
    
    .dark-mode .card {
        background-color: #252525;
        color: #ffffff;
        border-color: #333;
    }
    
    .dark-mode .card-header {
        background-color: #252525;
        border-color: #333;
        color: #ffffff;
    }
    
    /* 深色模式表格樣式 */
    .dark-mode .table {
        color: #ffffff !important;
        background-color: #252525 !important;
    }
    
    .dark-mode .table-responsive {
        background-color: #252525 !important;
    }
    
    .dark-mode .table td,
    .dark-mode .table th,
    .dark-mode .table > tbody > tr > td,
    .dark-mode .table > tbody > tr > th,
    .dark-mode .table > tfoot > tr > td,
    .dark-mode .table > tfoot > tr > th,
    .dark-mode .table > thead > tr > td,
    .dark-mode .table > thead > tr > th {
        background-color: #252525 !important;
        border-color: #333 !important;
        color: #ffffff !important;
    }
    
    .dark-mode .table-light,
    .dark-mode .table > thead.table-light > tr > th,
    .dark-mode .table > thead > tr.table-light > th {
        background-color: #1e1e1e !important;
        color: #ffffff !important;
        border-color: #333 !important;
    }
    
    .dark-mode .table-hover > tbody > tr:hover > *,
    .dark-mode .table-hover > tbody > tr:hover {
        background-color: #333 !important;
        color: #ffffff !important;
    }
    
    /* 深色模式下的交易記錄顏色 - 提高特異性以覆蓋表格樣式 */
    .dark-mode .table .transaction-income,
    .dark-mode .table > tbody > tr > td.transaction-income {
        color: #4cd964 !important;  /* 更亮的綠色表示收入 */
        font-weight: bold;
    }
    
    .dark-mode .table .transaction-expense,
    .dark-mode .table > tbody > tr > td.transaction-expense {
        color: #ff3b30 !important;  /* 更亮的紅色表示支出 */
        font-weight: bold;
    }
    
    /* 深色模式下的 Modal 樣式 */
    .dark-mode .modal-content {
        background-color: #2d2d2d !important;
        color: #ffffff !important;
        border: 1px solid #444 !important;
    }
    
    .dark-mode .modal-header {
        background-color: #333 !important;
        border-bottom: 1px solid #444 !important;
        color: #ffffff !important;
    }
    
    .dark-mode .modal-title {
        color: #ffffff !important;
    }
    
    .dark-mode .modal-body {
        background-color: #2d2d2d !important;
        color: #ffffff !important;
    }
    
    .dark-mode .modal-footer {
        background-color: #333 !important;
        border-top: 1px solid #444 !important;
    }
    
    /* 深色模式表單元素 */
    .dark-mode .form-control,
    .dark-mode .form-select {
        background-color: #3a3a3a !important;
        border-color: #555 !important;
        color: #ffffff !important;
    }
    
    .dark-mode .form-control:focus,
    .dark-mode .form-select:focus {
        background-color: #404040 !important;
        border-color: #0d6efd !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }
    
    .dark-mode .form-label {
        color: #ffffff !important;
    }
    
    /* 深色模式下的 placeholder 樣式 */
    .dark-mode .form-control::placeholder {
        color: #adb5bd !important;
        opacity: 0.8;
    }
    
    .dark-mode .form-control::-webkit-input-placeholder {
        color: #adb5bd !important;
        opacity: 0.8;
    }
    
    .dark-mode .form-control::-moz-placeholder {
        color: #adb5bd !important;
        opacity: 0.8;
    }
    
    .dark-mode .form-control:-ms-input-placeholder {
        color: #adb5bd !important;
        opacity: 0.8;
    }
    
    /* 深色模式下的 input-group-text 樣式 */
    .dark-mode .input-group-text {
        background-color: #404040 !important;
        border-color: #555 !important;
        color: #ffffff !important;
    }
    
    /* 深色模式下的日期輸入框樣式 */
    .dark-mode input[type="date"] {
        color-scheme: dark;
        background-color: #3a3a3a !important;
        border-color: #555 !important;
        color: #ffffff !important;
    }
    
    .dark-mode input[type="date"]::-webkit-calendar-picker-indicator {
        filter: invert(1);
        cursor: pointer;
    }
    
    .dark-mode input[type="date"]::-webkit-inner-spin-button,
    .dark-mode input[type="date"]::-webkit-clear-button {
        filter: invert(1);
    }
    
    /* Firefox 深色模式日期輸入框支援 */
    .dark-mode input[type="date"]::-moz-calendar-picker-indicator {
        filter: invert(1);
        cursor: pointer;
    }
    
    /* 深色模式下的關閉按鈕樣式 */
    .dark-mode .btn-close {
        filter: invert(1);
        opacity: 0.8;
    }
    
    .dark-mode .btn-close:hover {
        filter: invert(1);
        opacity: 1;
    }
    
    /* 深色模式下的按鈕樣式調整 */
    .dark-mode .btn-secondary {
        background-color: #495057 !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }
    
    .dark-mode .btn-secondary:hover {
        background-color: #5a6268 !important;
        border-color: #545b62 !important;
    }
    
    /* 深色模式下的 SweetAlert2 樣式 */
    .dark-mode .swal2-popup {
        background-color: #2d2d2d !important;
        color: #ffffff !important;
        border: 1px solid #444 !important;
    }
    
    .dark-mode .swal2-title {
        color: #ffffff !important;
    }
    
    .dark-mode .swal2-content {
        color: #ffffff !important;
    }
    
    .dark-mode .swal2-html-container {
        color: #ffffff !important;
    }
    
    /* 深色模式下的成功圖示 (打勾) */
    .dark-mode .swal2-success {
        border-color: #28a745 !important;
    }
    
    .dark-mode .swal2-success .swal2-success-ring {
        border: 4px solid rgba(40, 167, 69, 0.3) !important;
    }
    
    .dark-mode .swal2-success .swal2-success-fix {
        background-color: #2d2d2d !important;
    }
    
    .dark-mode .swal2-success [class^=swal2-success-line] {
        background-color: #28a745 !important;
    }
    
    .dark-mode .swal2-success [class^=swal2-success-line][class$=tip] {
        background-color: #28a745 !important;
        left: 1px;
        top: 19px;
        width: 25px;
    }
    
    .dark-mode .swal2-success [class^=swal2-success-line][class$=long] {
        background-color: #28a745 !important;
        right: 8px;
        top: 15px;
        width: 47px;
    }
    
    .dark-mode .swal2-success.swal2-icon-show .swal2-success-line-tip {
        animation: swal2-animate-success-line-tip 0.75s;
    }
    
    .dark-mode .swal2-success.swal2-icon-show .swal2-success-line-long {
        animation: swal2-animate-success-line-long 0.75s;
    }
    
    .dark-mode .swal2-success.swal2-icon-show .swal2-success-circular-line-right {
        animation: swal2-rotate-success-circular-line 4.25s ease-in;
    }
    
    /* 深色模式下的錯誤圖示 (打叉) */
    .dark-mode .swal2-error {
        border-color: #dc3545 !important;
    }
    
    .dark-mode .swal2-error .swal2-error-x {
        color: #dc3545 !important;
    }
    
    .dark-mode .swal2-error [class^=swal2-error-line] {
        background-color: #dc3545 !important;
    }
    
    .dark-mode .swal2-error [class^=swal2-error-line][class$=left] {
        background-color: #dc3545 !important;
        left: 26px;
        top: 25px;
        transform: rotate(45deg);
        width: 47px;
        height: 5px;
    }
    
    .dark-mode .swal2-error [class^=swal2-error-line][class$=right] {
        background-color: #dc3545 !important;
        right: 26px;
        top: 25px;
        transform: rotate(-45deg);
        width: 47px;
        height: 5px;
    }
    
    .dark-mode .swal2-error.swal2-icon-show .swal2-error-line-left {
        animation: swal2-animate-error-line-left 0.54s;
    }
    
    .dark-mode .swal2-error.swal2-icon-show .swal2-error-line-right {
        animation: swal2-animate-error-line-right 0.54s;
    }
    
    /* 深色模式下的警告圖示 */
    .dark-mode .swal2-warning {
        border-color: #ffc107 !important;
        color: #ffc107 !important;
    }
    
    .dark-mode .swal2-warning.swal2-icon-show {
        animation: swal2-animate-warning-icon 0.5s;
    }
    
    .dark-mode .swal2-warning.swal2-icon-show .swal2-warning-body {
        animation: swal2-animate-warning-body 0.5s;
    }
    
    .dark-mode .swal2-warning.swal2-icon-show .swal2-warning-dot {
        animation: swal2-animate-warning-dot 0.5s;
    }
    
    /* 深色模式下的資訊圖示 */
    .dark-mode .swal2-info {
        border-color: #17a2b8 !important;
        color: #17a2b8 !important;
    }
    
    /* 深色模式下的問號圖示 */
    .dark-mode .swal2-question {
        border-color: #6f42c1 !important;
        color: #6f42c1 !important;
    }
    
    /* 深色模式下的按鈕樣式 */
    .dark-mode .swal2-confirm {
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        color: #ffffff !important;
    }
    
    .dark-mode .swal2-confirm:hover {
        background-color: #0b5ed7 !important;
        border-color: #0a58ca !important;
    }
    
    .dark-mode .swal2-cancel {
        background-color: #6c757d !important;
        border-color: #6c757d !important;
        color: #ffffff !important;
    }
    
    .dark-mode .swal2-cancel:hover {
        background-color: #5c636a !important;
        border-color: #565e64 !important;
    }
    
    /* 深色模式下的載入動畫 */
    .dark-mode .swal2-loader {
        border-color: #ffffff transparent #ffffff transparent !important;
    }
    
    .dark-mode .swal2-loading .swal2-confirm {
        background-color: #495057 !important;
        color: #ffffff !important;
    }
    
    /* 深色模式下的進度條 */
    .dark-mode .swal2-timer-progress-bar {
        background: rgba(255, 255, 255, 0.6) !important;
    }
    
    /* 深色模式下的關閉按鈕 */
    .dark-mode .swal2-close {
        color: #ffffff !important;
    }
    
    .dark-mode .swal2-close:hover {
        color: #dc3545 !important;
    }
    
    /* 深色模式下的 backdrop */
    .dark-mode .swal2-container {
        background-color: rgba(0, 0, 0, 0.4) !important;
    }
    
    /* 深色模式下的輸入框 (如果有使用 input 功能) */
    .dark-mode .swal2-input,
    .dark-mode .swal2-textarea {
        background-color: #3a3a3a !important;
        border-color: #555 !important;
        color: #ffffff !important;
    }
    
    .dark-mode .swal2-input:focus,
    .dark-mode .swal2-textarea:focus {
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }
    
    /* 深色模式下的選擇框 */
    .dark-mode .swal2-select {
        background-color: #3a3a3a !important;
        border-color: #555 !important;
        color: #ffffff !important;
    }
    
    /* 深色模式下的單選/複選框 */
    .dark-mode .swal2-checkbox,
    .dark-mode .swal2-radio {
        background-color: #3a3a3a !important;
        border-color: #555 !important;
    }
    
    /* 深色模式下的驗證錯誤訊息 */
    .dark-mode .swal2-validation-message {
        background-color: #dc3545 !important;
        color: #ffffff !important;
    }
    
    /* 深色模式下的 toast 樣式 */
    .dark-mode .toast {
        background-color: #2d2d2d !important;
        border: 1px solid #444 !important;
        color: #ffffff !important;
    }
    
    .dark-mode .toast-header {
        background-color: #333 !important;
        border-bottom: 1px solid #444 !important;
        color: #ffffff !important;
    }
    
    .dark-mode .toast-body {
        color: #ffffff !important;
    }
    
    .dark-mode .toast .btn-close {
        filter: invert(1);
        opacity: 0.8;
    }
    
    .dark-mode .toast .btn-close:hover {
        filter: invert(1);
        opacity: 1;
    }
</style>

<!-- 深色模式切換 JavaScript -->
<script>
    // 主題切換功能
    function toggleTheme() {
        const body = document.body;
        const html = document.documentElement;
        const toggle = document.getElementById('darkModeToggle');
        const isDark = body.classList.contains('dark-mode');
        
        if (isDark) {
            // 切換到淺色模式
            body.classList.remove('dark-mode');
            html.classList.remove('dark-mode');
            localStorage.setItem('theme', 'light');
            // 清除預設樣式
            html.style.backgroundColor = '';
            html.style.color = '';
            if (toggle) toggle.checked = false;
        } else {
            // 切換到深色模式
            body.classList.add('dark-mode');
            html.classList.add('dark-mode');
            localStorage.setItem('theme', 'dark');
            // 設定預設樣式
            html.style.backgroundColor = '#1a1a1a';
            html.style.color = '#ffffff';
            if (toggle) toggle.checked = true;
        }
    }

    // 頁面載入時應用儲存的主題
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const toggle = document.getElementById('darkModeToggle');
        
        // 應用主題
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.body.classList.add('dark-mode');
            document.documentElement.classList.add('dark-mode');
            document.documentElement.style.backgroundColor = '#1a1a1a';
            document.documentElement.style.color = '#ffffff';
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
            toggle.addEventListener('change', toggleTheme);
        }
    });
    
    // 監聽系統主題變化
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        const savedTheme = localStorage.getItem('theme');
        const toggle = document.getElementById('darkModeToggle');
        
        // 只有在沒有手動設定主題時才跟隨系統
        if (!savedTheme) {
            if (e.matches) {
                // 系統切換到深色模式
                document.body.classList.add('dark-mode');
                document.documentElement.classList.add('dark-mode');
                document.documentElement.style.backgroundColor = '#1a1a1a';
                document.documentElement.style.color = '#ffffff';
                if (toggle) toggle.checked = true;
            } else {
                // 系統切換到淺色模式
                document.body.classList.remove('dark-mode');
                document.documentElement.classList.remove('dark-mode');
                document.documentElement.style.backgroundColor = '';
                document.documentElement.style.color = '';
                if (toggle) toggle.checked = false;
            }
        }
    });
</script>

<!-- 側邊欄 HTML 內容 -->
<div class="col-md-2 sidebar-container">
    <div class="sidebar sticky-top">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="ps-2 mb-0">個人記帳系統</h5>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="darkModeToggle">
                <label class="form-check-label" for="darkModeToggle">
                    <i class="bi bi-moon-stars"></i>
                </label>
            </div>
        </div>
        <hr class="my-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> <strong>儀表板</strong>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'transactions' ? 'active' : '' ?>" href="transactions.php">
                    <i class="bi bi-journal-text"></i> <strong>交易記錄</strong>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'budget' ? 'active' : '' ?>" href="budget.php">
                    <i class="bi bi-wallet2"></i> <strong>預算管理</strong>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'save_goal' ? 'active' : '' ?>" href="save_goal.php">
                    <i class="bi bi-bullseye"></i> <strong>儲蓄目標</strong>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'invoice' ? 'active' : '' ?>" href="invoice.php">
                    <i class="bi bi-receipt"></i> <strong>發票管理</strong>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>" href="reports.php">
                    <i class="bi bi-bar-chart"></i> <strong>財務報表</strong>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>" href="profile.php">
                    <i class="bi bi-person-circle"></i> <strong>個人設定</strong>
                </a>
            </li>
            <hr class="my-2">
            <li class="nav-item">
                <a class="nav-link text-danger" href="?logout=1">
                    <i class="bi bi-box-arrow-right"></i> <strong>登出</strong>
                </a>
            </li>
        </ul>
    </div>
</div>
