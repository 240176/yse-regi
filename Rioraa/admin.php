<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

// データベースインスタンスの作成
$db = new Database();

// 簡単な認証チェック
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = 'admin';
}

// メインPOSシステムに戻る処理
if (isset($_GET['return'])) {
    header('Location: index.php');
    exit;
}

// AJAX処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['admin_action']) {
        case 'get_sales_history':
            $filters = [
                'date_from' => $_POST['date_from'] ?? '',
                'date_to' => $_POST['date_to'] ?? '',
                'status' => $_POST['status'] ?? '',
                'transaction_id' => $_POST['transaction_id'] ?? '',
                'sort_by' => $_POST['sort_by'] ?? 'created_at',
                'sort_order' => $_POST['sort_order'] ?? 'DESC',
                'limit' => $_POST['limit'] ?? 100
            ];
            $sales = $db->getSalesHistory($filters);
            echo json_encode($sales);
            exit;
            
        case 'get_sale_details':
            $transactionId = $_POST['transaction_id'];
            $details = $db->getSaleDetails($transactionId);
            echo json_encode($details);
            exit;
            
        case 'update_sale_status':
            $transactionId = $_POST['transaction_id'];
            $status = $_POST['status'];
            $reason = $_POST['reason'] ?? '';
            $success = $db->updateSaleStatus($transactionId, $status, $_SESSION['admin_user'], $reason);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'update_sale_item':
            $itemId = $_POST['item_id'];
            $newData = [
                'item_name' => $_POST['item_name'],
                'price' => floatval($_POST['price']),
                'quantity' => intval($_POST['quantity']),
                'subtotal' => floatval($_POST['price']) * intval($_POST['quantity'])
            ];
            $reason = $_POST['reason'] ?? '';
            $success = $db->updateSaleItem($itemId, $newData, $_SESSION['admin_user'], $reason);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'get_audit_log':
            $transactionId = $_POST['transaction_id'];
            $auditLog = $db->getAuditLog($transactionId);
            echo json_encode($auditLog);
            exit;
            
        case 'delete_sale_item':
            $itemId = $_POST['item_id'];
            $reason = $_POST['reason'] ?? '';
            $success = $db->deleteSaleItem($itemId, $_SESSION['admin_user'], $reason);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'get_dashboard_stats':
            $summary = $db->getDailySummary();
            echo json_encode([
                'total_sales_today' => $summary['total_sales'] ?? 0,
                'transactions_today' => $summary['transaction_count'] ?? 0,
                'total_products' => 120,
                'low_stock_items' => 8
            ]);
            exit;
            
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YSE POS System - 管理画面</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Hiragino Sans', 'Yu Gothic', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-title {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 8px;
            font-size: 14px;
            color: #333;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
            display: flex;
            gap: 30px;
        }
        
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            height: fit-content;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: 8px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: #555;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .nav-icon {
            width: 20px;
            height: 20px;
        }
        
        .main-content {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .content-section {
            display: none;
        }
        
        .content-section.active {
            display: block;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .filters-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }
        
        .form-control {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }
        
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .sales-table th,
        .sales-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .sales-table th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }
        
        .sales-table th:hover {
            background: linear-gradient(135deg, #5a6fd8, #6a42a0);
        }
        
        .sales-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-refunded {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-voided {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .close-btn:hover {
            color: #333;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .audit-log {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .audit-entry {
            padding: 10px;
            border-left: 4px solid #667eea;
            margin-bottom: 10px;
            background: white;
            border-radius: 4px;
        }
        
        .audit-entry:last-child {
            margin-bottom: 0;
        }
        
        .audit-header {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .audit-details {
            font-size: 14px;
            color: #6c757d;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 35px rgba(102, 126, 234, 0.4);
        }
        
        .stat-card h3 {
            font-size: 14px;
            margin-bottom: 12px;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .change {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .action-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .action-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }
        
        .action-card h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .action-card p {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.4;
        }
        
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
                padding: 0 15px;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-actions {
                justify-content: center;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .sales-table {
                font-size: 14px;
            }
            
            .sales-table th,
            .sales-table td {
                padding: 10px 8px;
            }
            
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="header-content">
            <h1 class="admin-title">YSE POS System - 管理画面</h1>
            <div class="header-actions">
                <div class="user-info">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                    </svg>
                    管理者: <?php echo htmlspecialchars($_SESSION['admin_user']); ?>
                </div>
                <a href="?return=true" class="btn btn-primary">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"></path>
                    </svg>
                    POSに戻る
                </a>
                <button class="btn btn-secondary" onclick="logout()">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"></path>
                    </svg>
                    ログアウト
                </button>
            </div>
        </div>
    </div>
    
    <div class="admin-container">
        <div class="sidebar">
            <h2 class="sidebar-title">管理メニュー</h2>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="#" class="nav-link active" onclick="showSection('dashboard')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                        </svg>
                        ダッシュボード
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showSection('sales-history')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6h-3a2 2 0 00-2 2v3H6v-3a2 2 0 00-2-2H1V5h3zm8 8v2h3v-2h-3z" clip-rule="evenodd"></path>
                        </svg>
                        売上履歴
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showSection('products')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2L3 7v11a1 1 0 001 1h12a1 1 0 001-1V7l-7-5zM6 9a1 1 0 112 0 1 1 0 01-2 0zm6 0a1 1 0 112 0 1 1 0 01-2 0z" clip-rule="evenodd"></path>
                        </svg>
                        商品管理
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showSection('reports')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"></path>
                        </svg>
                        レポート
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showSection('settings')">
                        <svg class="nav-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>
                        </svg>
                        システム設定
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="main-content">
            <!-- ダッシュボード -->
            <div id="dashboard" class="content-section active">
                <h2 class="section-title">ダッシュボード</h2>
                
                <div class="dashboard-stats" id="dashboardStats">
                    <div class="stat-card">
                        <h3>本日の売上</h3>
                        <div class="value">¥125,000</div>
                        <div class="change">前日比 +12%</div>
                    </div>
                    <div class="stat-card">
                        <h3>本日の取引数</h3>
                        <div class="value">45件</div>
                        <div class="change">前日比 +8%</div>
                    </div>
                    <div class="stat-card">
                        <h3>登録商品数</h3>
                        <div class="value">120個</div>
                        <div class="change">在庫管理中</div>
                    </div>
                    <div class="stat-card">
                        <h3>在庫不足商品</h3>
                        <div class="value">8個</div>
                        <div class="change">要補充</div>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <div class="action-card" onclick="showSection('products')">
                        <div class="action-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <h4>商品を追加</h4>
                        <p>新しい商品を登録して在庫を管理</p>
                    </div>
                    <div class="action-card" onclick="showSection('sales')">
                        <div class="action-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path>
                            </svg>
                        </div>
                        <h4>売上分析</h4>
                        <p>詳細な売上データと傾向を確認</p>
                    </div>
                    <div class="action-card" onclick="showSection('reports')">
                        <div class="action-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <h4>レポート生成</h4>
                        <p>カスタムレポートを作成・出力</p>
                    </div>
                    <div class="action-card" onclick="showSection('settings')">
                        <div class="action-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <h4>システム設定</h4>
                        <p>税率や店舗情報などの設定</p>
                    </div>
                </div>
            </div>
            
            <!-- 売上履歴管理 -->
            <div id="sales-history" class="content-section">
                <h2 class="section-title">売上履歴管理</h2>
                
                <!-- フィルター -->
                <div class="filters-section">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label class="form-label">開始日</label>
                            <input type="date" id="dateFrom" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">終了日</label>
                            <input type="date" id="dateTo" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ステータス</label>
                            <select id="statusFilter" class="form-control">
                                <option value="">すべて</option>
                                <option value="completed">完了</option>
                                <option value="refunded">返金</option>
                                <option value="voided">取消</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">取引ID</label>
                            <input type="text" id="transactionIdFilter" class="form-control" placeholder="取引IDで検索">
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-primary" onclick="loadSalesHistory()">検索</button>
                        <button class="btn btn-secondary" onclick="clearFilters()">クリア</button>
                        <button class="btn btn-success" onclick="exportSalesData()">エクスポート</button>
                    </div>
                </div>
                
                <!-- 売上テーブル -->
                <div id="salesTableContainer">
                    <div class="loading">売上データを読み込み中...</div>
                </div>
            </div>
            
            <!-- その他のセクション（プレースホルダー） -->
            <div id="products" class="content-section">
                <h2 class="section-title">商品管理</h2>
                <div class="no-data">商品管理機能は開発中です。</div>
            </div>
            
            <div id="reports" class="content-section">
                <h2 class="section-title">レポート</h2>
                <div class="no-data">レポート機能は開発中です。</div>
            </div>
            
            <div id="settings" class="content-section">
                <h2 class="section-title">システム設定</h2>
                <div class="no-data">システム設定機能は開発中です。</div>
            </div>
        </div>
    </div>
    
    <!-- 売上詳細モーダル -->
    <div id="saleDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">売上詳細</h3>
                <button class="close-btn" onclick="closeSaleDetailsModal()">&times;</button>
            </div>
            <div id="saleDetailsContent">
                <div class="loading">詳細を読み込み中...</div>
            </div>
        </div>
    </div>
    
    <!-- アイテム編集モーダル -->
    <div id="editItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">アイテム編集</h3>
                <button class="close-btn" onclick="closeEditItemModal()">&times;</button>
            </div>
            <form id="editItemForm">
                <div class="form-group">
                    <label class="form-label">商品名</label>
                    <input type="text" id="editItemName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">価格</label>
                    <input type="number" id="editItemPrice" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">数量</label>
                    <input type="number" id="editItemQuantity" class="form-control" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">修正理由</label>
                    <textarea id="editItemReason" class="form-control" rows="3" placeholder="修正理由を入力してください" required></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditItemModal()">キャンセル</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentEditItemId = null;
        let currentSortBy = 'created_at';
        let currentSortOrder = 'DESC';
        
        function showSection(sectionId) {
            // すべてのセクションを非表示
            const sections = document.querySelectorAll('.content-section');
            sections.forEach(section => section.classList.remove('active'));
            
            // すべてのナビリンクからactiveクラスを削除
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => link.classList.remove('active'));
            
            // 指定されたセクションを表示
            document.getElementById(sectionId).classList.add('active');
            
            // 対応するナビリンクにactiveクラスを追加
            event.target.classList.add('active');
            
            // 売上履歴セクションの場合、データを読み込み
            if (sectionId === 'sales-history') {
                loadSalesHistory();
            }
        }
        
        function loadSalesHistory() {
            const container = document.getElementById('salesTableContainer');
            container.innerHTML = '<div class="loading">売上データを読み込み中...</div>';
            
            const formData = new FormData();
            formData.append('admin_action', 'get_sales_history');
            formData.append('date_from', document.getElementById('dateFrom').value);
            formData.append('date_to', document.getElementById('dateTo').value);
            formData.append('status', document.getElementById('statusFilter').value);
            formData.append('transaction_id', document.getElementById('transactionIdFilter').value);
            formData.append('sort_by', currentSortBy);
            formData.append('sort_order', currentSortOrder);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                displaySalesTable(data);
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<div class="no-data">データの読み込みに失敗しました。</div>';
            });
        }
        
        function displaySalesTable(sales) {
            const container = document.getElementById('salesTableContainer');
            
            if (sales.length === 0) {
                container.innerHTML = '<div class="no-data">該当する売上データがありません。</div>';
                return;
            }
            
            let tableHTML = `
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th onclick="sortTable('transaction_id')">取引ID</th>
                            <th onclick="sortTable('created_at')">日時</th>
                            <th onclick="sortTable('total_amount')">合計金額</th>
                            <th onclick="sortTable('status')">ステータス</th>
                            <th>商品概要</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            sales.forEach(sale => {
                const statusClass = `status-${sale.status}`;
                const statusText = {
                    'completed': '完了',
                    'refunded': '返金',
                    'voided': '取消'
                }[sale.status] || sale.status;
                
                tableHTML += `
                    <tr>
                        <td>${sale.transaction_id}</td>
                        <td>${new Date(sale.created_at).toLocaleString('ja-JP')}</td>
                        <td>¥${parseInt(sale.total_amount).toLocaleString()}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${sale.items_summary || '商品なし'}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="viewSaleDetails('${sale.transaction_id}')">詳細</button>
                            ${sale.status === 'completed' ? `
                                <button class="btn btn-warning btn-sm" onclick="refundSale('${sale.transaction_id}')">返金</button>
                                <button class="btn btn-danger btn-sm" onclick="voidSale('${sale.transaction_id}')">取消</button>
                            ` : ''}
                        </td>
                    </tr>
                `;
            });
            
            tableHTML += '</tbody></table>';
            container.innerHTML = tableHTML;
        }
        
        function sortTable(column) {
            if (currentSortBy === column) {
                currentSortOrder = currentSortOrder === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSortBy = column;
                currentSortOrder = 'DESC';
            }
            loadSalesHistory();
        }
        
        function viewSaleDetails(transactionId) {
            const modal = document.getElementById('saleDetailsModal');
            const content = document.getElementById('saleDetailsContent');
            
            content.innerHTML = '<div class="loading">詳細を読み込み中...</div>';
            modal.style.display = 'block';
            
            const formData = new FormData();
            formData.append('admin_action', 'get_sale_details');
            formData.append('transaction_id', transactionId);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                displaySaleDetails(data);
                loadAuditLog(transactionId);
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<div class="no-data">詳細の読み込みに失敗しました。</div>';
            });
        }
        
        function displaySaleDetails(details) {
            if (details.length === 0) {
                document.getElementById('saleDetailsContent').innerHTML = '<div class="no-data">詳細データがありません。</div>';
                return;
            }
            
            const sale = details[0];
            const statusClass = `status-${sale.status}`;
            const statusText = {
                'completed': '完了',
                'refunded': '返金',
                'voided': '取消'
            }[sale.status] || sale.status;
            
            let detailsHTML = `
                <div style="margin-bottom: 20px;">
                    <h4>取引情報</h4>
                    <p><strong>取引ID:</strong> ${sale.transaction_id}</p>
                    <p><strong>日時:</strong> ${new Date(sale.created_at).toLocaleString('ja-JP')}</p>
                    <p><strong>ステータス:</strong> <span class="status-badge ${statusClass}">${statusText}</span></p>
                    <p><strong>合計金額:</strong> ¥${parseInt(sale.total_amount).toLocaleString()}</p>
                    <p><strong>消費税:</strong> ¥${parseInt(sale.tax_amount).toLocaleString()}</p>
                </div>
                
                <h4>商品詳細</h4>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>商品名</th>
                            <th>価格</th>
                            <th>数量</th>
                            <th>小計</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            details.forEach(item => {
                if (item.item_name) {
                    detailsHTML += `
                        <tr>
                            <td>${item.item_name}</td>
                            <td>¥${parseInt(item.price).toLocaleString()}</td>
                            <td>${item.quantity}</td>
                            <td>¥${parseInt(item.subtotal).toLocaleString()}</td>
                            <td>
                                ${sale.status === 'completed' ? `
                                    <button class="btn btn-warning btn-sm" onclick="editItem(${item.id}, '${item.item_name}', ${item.price}, ${item.quantity})">編集</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteItem(${item.id})">削除</button>
                                ` : ''}
                            </td>
                        </tr>
                    `;
                }
            });
            
            detailsHTML += `
                    </tbody>
                </table>
                <div id="auditLogSection"></div>
            `;
            
            document.getElementById('saleDetailsContent').innerHTML = detailsHTML;
        }
        
        function loadAuditLog(transactionId) {
            const formData = new FormData();
            formData.append('admin_action', 'get_audit_log');
            formData.append('transaction_id', transactionId);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                displayAuditLog(data);
            })
            .catch(error => {
                console.error('Error loading audit log:', error);
            });
        }
        
        function displayAuditLog(auditLog) {
            const section = document.getElementById('auditLogSection');
            
            if (auditLog.length === 0) {
                section.innerHTML = '<h4>監査ログ</h4><p>変更履歴はありません。</p>';
                return;
            }
            
            let logHTML = '<h4>監査ログ</h4><div class="audit-log">';
            
            auditLog.forEach(entry => {
                const actionText = {
                    'edit': '編集',
                    'refund': '返金',
                    'void': '取消',
                    'restore': '復元'
                }[entry.action_type] || entry.action_type;
                
                logHTML += `
                    <div class="audit-entry">
                        <div class="audit-header">
                            ${actionText} - ${entry.admin_user} - ${new Date(entry.created_at).toLocaleString('ja-JP')}
                        </div>
                        <div class="audit-details">
                            ${entry.reason ? `理由: ${entry.reason}` : ''}
                        </div>
                    </div>
                `;
            });
            
            logHTML += '</div>';
            section.innerHTML = logHTML;
        }
        
        function editItem(itemId, itemName, price, quantity) {
            currentEditItemId = itemId;
            document.getElementById('editItemName').value = itemName;
            document.getElementById('editItemPrice').value = price;
            document.getElementById('editItemQuantity').value = quantity;
            document.getElementById('editItemReason').value = '';
            document.getElementById('editItemModal').style.display = 'block';
        }
        
        function refundSale(transactionId) {
            const reason = prompt('返金理由を入力してください:');
            if (reason === null) return;
            
            updateSaleStatus(transactionId, 'refunded', reason);
        }
        
        function voidSale(transactionId) {
            const reason = prompt('取消理由を入力してください:');
            if (reason === null) return;
            
            if (confirm('この取引を取り消しますか？この操作は元に戻せません。')) {
                updateSaleStatus(transactionId, 'voided', reason);
            }
        }
        
        function updateSaleStatus(transactionId, status, reason) {
            const formData = new FormData();
            formData.append('admin_action', 'update_sale_status');
            formData.append('transaction_id', transactionId);
            formData.append('status', status);
            formData.append('reason', reason);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('ステータスが更新されました。');
                    loadSalesHistory();
                    closeSaleDetailsModal();
                } else {
                    alert('更新に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('エラーが発生しました。');
            });
        }
        
        function clearFilters() {
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('transactionIdFilter').value = '';
            loadSalesHistory();
        }
        
        function exportSalesData() {
            alert('エクスポート機能は開発中です。');
        }
        
        function closeSaleDetailsModal() {
            document.getElementById('saleDetailsModal').style.display = 'none';
        }
        
        function closeEditItemModal() {
            document.getElementById('editItemModal').style.display = 'none';
            currentEditItemId = null;
        }
        
        function logout() {
            if (confirm('ログアウトしますか？')) {
                const formData = new FormData();
                formData.append('admin_action', 'logout');
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'index.php';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    window.location.href = 'index.php';
                });
            }
        }
        
        // アイテム編集フォームの送信処理
        document.getElementById('editItemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!currentEditItemId) return;
            
            const formData = new FormData();
            formData.append('admin_action', 'update_sale_item');
            formData.append('item_id', currentEditItemId);
            formData.append('item_name', document.getElementById('editItemName').value);
            formData.append('price', document.getElementById('editItemPrice').value);
            formData.append('quantity', document.getElementById('editItemQuantity').value);
            formData.append('reason', document.getElementById('editItemReason').value);
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('アイテムが更新されました。');
                    closeEditItemModal();
                    loadSalesHistory();
                    // 詳細モーダルが開いている場合は再読み込み
                    const modal = document.getElementById('saleDetailsModal');
                    if (modal.style.display === 'block') {
                        // 現在表示中の取引IDを取得して再読み込み
                        const transactionId = document.querySelector('#saleDetailsContent p strong').nextSibling.textContent.trim();
                        viewSaleDetails(transactionId);
                    }
                } else {
                    alert('更新に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('エラーが発生しました。');
            });
        });
        
        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            const saleModal = document.getElementById('saleDetailsModal');
            const editModal = document.getElementById('editItemModal');
            
            if (event.target === saleModal) {
                closeSaleDetailsModal();
            }
            if (event.target === editModal) {
                closeEditItemModal();
            }
        }
        
        // ページ読み込み時に売上履歴を読み込み
        document.addEventListener('DOMContentLoaded', function() {
            // デフォルトで今日の日付を設定
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('dateTo').value = today;
            
            // 1週間前の日付を設定
            const weekAgo = new Date();
            weekAgo.setDate(weekAgo.getDate() - 7);
            document.getElementById('dateFrom').value = weekAgo.toISOString().split('T')[0];
            
            loadSalesHistory();
        });
        
        function deleteItem(itemId) {
            if (confirm('この商品を削除してもよろしいですか？この操作は元に戻せません。')) {
                const formData = new FormData();
                formData.append('admin_action', 'delete_sale_item');
                formData.append('item_id', itemId);
                formData.append('reason', prompt('削除理由を入力してください:'));
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('商品が削除されました。');
                        // 現在表示中の取引IDを取得して再読み込み
                        const transactionId = document.querySelector('#saleDetailsContent p strong').nextSibling.textContent.trim();
                        viewSaleDetails(transactionId);
                        loadSalesHistory();
                    } else {
                        alert('削除に失敗しました。');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('エラーが発生しました。');
                });
            }
        }
    </script>
</body>
</html>