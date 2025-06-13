<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: managerlogin.php");
    exit;
}

$db = new PDO('mysql:host=database-g04.cj48gosu0lpo.ap-northeast-1.rds.amazonaws.com;dbname=accounting_system;charset=utf8', 'manager', '5678');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->query("SELECT * FROM feedback_bug_reports ORDER BY report_id ASC");
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>使用者回報紀錄</title>
    <style>
        body {
            font-family: Arial;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #2ecc71;
            color: white;
        }
        .content {
            text-align: left;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>使用者回報紀錄</h2>
    <table>
        <thead>
            <tr>
                <th>回報 ID</th>
                <th>使用者 ID</th>
                <th>使用者名稱</th>
                <th>回報類型</th>
                <th>標題</th>
                <th>內容</th>
                <th>建立時間</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="7">目前沒有任何回報紀錄。</td></tr>
            <?php else: ?>
                <?php foreach ($reports as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['report_id']) ?></td>
                        <td><?= htmlspecialchars($row['user_id']) ?></td>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td><?= htmlspecialchars($row['report_type']) ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td class="content"><?= htmlspecialchars($row['content']) ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
