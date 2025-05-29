<?php
session_start();

require_once 'db.php';

// レジシステムクラス
class POSSystem {
    private $db;
    private $taxRate = 0.10;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function calculateTax($amount) {
        return round($amount * $this->taxRate);
    }
    
    public function calculateTotalWithTax($amount) {
        return $amount + $this->calculateTax($amount);
    }
    
    public function generateTransactionId() {
        return 'TXN' . date('YmdHis') . rand(100, 999);
    }
    
    public function processSale($items) {
        $transactionId = $this->generateTransactionId();
        $subtotal = 0;
        
        foreach ($items as &$item) {
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $subtotal += $item['subtotal'];
        }
        
        $taxAmount = $this->calculateTax($subtotal);
        $totalAmount = $subtotal + $taxAmount;
        
        $success = $this->db->saveSale($transactionId, $items, $totalAmount, $taxAmount);
        
        return [
            'success' => $success,
            'transaction_id' => $transactionId,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount
        ];
    }
    
    public function getSalesReport($date = null) {
        return $this->db->getSalesData($date);
    }
    
    public function getDailySummary($date = null) {
        return $this->db->getDailySummary($date);
    }
}

$pos = new POSSystem();

// AJAX処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'process_sale':
            $items = json_decode($_POST['items'], true);
            $result = $pos->processSale($items);
            echo json_encode($result);
            exit;
            
        case 'get_daily_summary':
            $date = $_POST['date'] ?? null;
            $summary = $pos->getDailySummary($date);
            echo json_encode($summary);
            exit;
            
        case 'get_sales_report':
            $date = $_POST['date'] ?? null;
            $report = $pos->getSalesReport($date);
            echo json_encode($report);
            exit;
    }
}

// 管理画面へのリダイレクト処理
if (isset($_GET['page']) && $_GET['page'] === 'admin') {
    include 'admin.php';
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riora POS System - 高機能レジシステム</title>
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
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            color: #333;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .container {
            display: flex;
            max-width: 1400px;
            margin: 30px auto;
            gap: 30px;
            padding: 0 20px;
        }
        
        .calculator-section {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            height: fit-content;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .calculator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .calculator-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .admin-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .admin-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .admin-icon {
            width: 16px;
            height: 16px;
        }
        
        .display {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            border-radius: 15px;
            padding: 25px;
            text-align: right;
            font-size: 36px;
            font-weight: 300;
            margin-bottom: 25px;
            min-height: 90px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            box-shadow: inset 0 4px 15px rgba(0,0,0,0.2);
            font-family: 'Helvetica', monospace;
            position: relative;
            overflow: hidden;
        }
        
        .display::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .calculator-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .calc-btn {
            padding: 20px;
            font-size: 18px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .calc-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }
        
        .calc-btn:active::before {
            width: 100px;
            height: 100px;
        }
        
        .calc-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .calc-btn.number {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #495057;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .calc-btn.number:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
        }
        
        .calc-btn.clear {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
        }
        
        .calc-btn.clear:hover {
            background: linear-gradient(135deg, #ff5252, #e53935);
        }
        
        .calc-btn.add {
            background: linear-gradient(135deg, #4ecdc4, #44a08d);
            color: white;
        }
        
        .calc-btn.add:hover {
            background: linear-gradient(135deg, #26d0ce, #2bb3aa);
        }
        
        .transaction-section {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            height: fit-content;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .transaction-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .item-list {
            margin-bottom: 25px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 12px;
            margin-bottom: 12px;
            border-left: 4px solid #4ecdc4;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        
        .item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .item-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .quantity-btn:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: scale(1.1);
        }
        
        .delete-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .delete-btn:hover {
            background: linear-gradient(135deg, #ff5252, #e53935);
            transform: scale(1.05);
        }
        
        .confirm-section {
            border-top: 2px solid #e9ecef;
            padding-top: 25px;
        }
        
        .total-display {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 2px solid #4ecdc4;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(78, 205, 196, 0.2);
        }
        
        .total-amount {
            font-size: 32px;
            font-weight: 700;
            color: #2e7d32;
            margin-bottom: 8px;
        }
        
        .tax-info {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            flex: 1;
            padding: 18px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s, height 0.3s;
        }
        
        .btn:active::before {
            width: 200px;
            height: 200px;
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, #4ecdc4, #44a08d);
            color: white;
        }
        
        .btn-confirm:hover {
            background: linear-gradient(135deg, #26d0ce, #2bb3aa);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(78, 205, 196, 0.4);
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .btn-cancel:hover {
            background: linear-gradient(135deg, #5a6268, #495057);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        .reports-section {
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .report-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
        }
        
        .date-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .date-input {
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .date-input:focus {
            outline: none;
            border-color: #4ecdc4;
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        }
        
        .update-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .update-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 35px rgba(102, 126, 234, 0.4);
        }
        
        .summary-card h3 {
            font-size: 14px;
            margin-bottom: 12px;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .summary-card .value {
            font-size: 28px;
            font-weight: 700;
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
            margin: 10% auto;
            padding: 40px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            text-align: center;
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
        
        .modal h2 {
            color: #4ecdc4;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .modal .transaction-id {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-family: 'Helvetica', monospace;
            font-weight: bold;
            color: #495057;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                padding: 0 15px;
            }
            
            .calculator-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .report-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .date-controls {
                justify-content: center;
            }
            
            .calculator-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Riora POS System - 高機能レジシステム</h1>
    </div>
    
    <div class="container">
        <div class="calculator-section">
            <div class="calculator-header">
                <h2 class="calculator-title">価格入力</h2>
                <a href="?page=admin" class="admin-link">
                    <svg class="admin-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"></path>
                    </svg>
                    管理画面
                </a>
            </div>
            
            <div class="display" id="display">0</div>
            
            <div class="calculator-grid">
                <button class="calc-btn clear" onclick="clearDisplay()">AC</button>
                <button class="calc-btn number" onclick="inputNumber('00')">00</button>
                <button class="calc-btn number" onclick="inputNumber('000')">000</button>
                <button class="calc-btn clear" onclick="deleteLastDigit()">⌫</button>
                
                <button class="calc-btn number" onclick="inputNumber('7')">7</button>
                <button class="calc-btn number" onclick="inputNumber('8')">8</button>
                <button class="calc-btn number" onclick="inputNumber('9')">9</button>
                <button class="calc-btn add" onclick="addItem()">追加</button>
                
                <button class="calc-btn number" onclick="inputNumber('4')">4</button>
                <button class="calc-btn number" onclick="inputNumber('5')">5</button>
                <button class="calc-btn number" onclick="inputNumber('6')">6</button>
                <button class="calc-btn number" onclick="inputNumber('0')" style="grid-row: span 2;">0</button>
                
                <button class="calc-btn number" onclick="inputNumber('1')">1</button>
                <button class="calc-btn number" onclick="inputNumber('2')">2</button>
                <button class="calc-btn number" onclick="inputNumber('3')">3</button>
            </div>
        </div>
        
        <div class="transaction-section">
            <h2 class="transaction-title">取引内容</h2>
            
            <div class="item-list" id="itemList">
                <div style="text-align: center; color: #666; padding: 40px;">
                    商品を追加してください
                </div>
            </div>
            
            <div class="confirm-section">
                <div class="total-display">
                    <div class="total-amount" id="totalAmount">¥0 (税込)</div>
                    <div class="tax-info">小計: <span id="subtotal">¥0</span> | 消費税: <span id="taxAmount">¥0</span></div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-confirm" onclick="processSale()">売上計上</button>
                    <button class="btn btn-cancel" onclick="cancelTransaction()">取引キャンセル</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="reports-section">
        <div class="report-header">
            <h2 class="report-title">売上レポート</h2>
            <div class="date-controls">
                <input type="date" id="reportDate" class="date-input" onchange="loadDailySummary()">
                <button class="update-btn" onclick="loadDailySummary()">更新</button>
            </div>
        </div>
        
        <div class="summary-cards" id="summaryCards">
            <!-- サマリーカードがここに表示されます -->
        </div>
    </div>
    
    <!-- 成功モーダル -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <h2>✅ 売上が正常に記録されました</h2>
            <div class="transaction-id" id="transactionId"></div>
            <p>取引が完了しました。ありがとうございました。</p>
            <button class="btn btn-confirm" style="margin-top: 25px;" onclick="closeModal()">OK</button>
        </div>
    </div>
    
    <script>
        let currentDisplay = '0';
        let items = [];
        
        function inputNumber(num) {
            if (currentDisplay === '0') {
                currentDisplay = num;
            } else {
                currentDisplay += num;
            }
            updateDisplay();
        }
        
        function clearDisplay() {
            currentDisplay = '0';
            updateDisplay();
        }
        
        function deleteLastDigit() {
            if (currentDisplay.length > 1) {
                currentDisplay = currentDisplay.slice(0, -1);
            } else {
                currentDisplay = '0';
            }
            updateDisplay();
        }
        
        function updateDisplay() {
            const displayElement = document.getElementById('display');
            const formattedNumber = parseInt(currentDisplay).toLocaleString();
            displayElement.textContent = formattedNumber;
        }
        
        function addItem() {
            const price = parseInt(currentDisplay);
            if (price > 0) {
                const itemName = `商品 ¥${price.toLocaleString()}`;
                const existingItemIndex = items.findIndex(item => item.price === price);
                
                if (existingItemIndex !== -1) {
                    items[existingItemIndex].quantity += 1;
                } else {
                    items.push({
                        name: itemName,
                        price: price,
                        quantity: 1
                    });
                }
                
                updateItemList();
                updateTotal();
                clearDisplay();
            }
        }
        
        function updateItemList() {
            const listElement = document.getElementById('itemList');
            
            if (items.length === 0) {
                listElement.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;">商品を追加してください</div>';
                return;
            }
            
            listElement.innerHTML = '';
            
            items.forEach((item, index) => {
                const itemElement = document.createElement('div');
                itemElement.className = 'item';
                itemElement.innerHTML = `
                    <div>
                        <strong>${item.name}</strong><br>
                        <small>小計: ¥${(item.price * item.quantity).toLocaleString()}</small>
                    </div>
                    <div class="item-controls">
                        <button class="quantity-btn" onclick="changeQuantity(${index}, -1)">-</button>
                        <span style="margin: 0 12px; font-weight: bold; min-width: 20px; text-align: center;">${item.quantity}</span>
                        <button class="quantity-btn" onclick="changeQuantity(${index}, 1)">+</button>
                        <button class="delete-btn" onclick="removeItem(${index})">削除</button>
                    </div>
                `;
                listElement.appendChild(itemElement);
            });
        }
        
        function changeQuantity(index, change) {
            items[index].quantity += change;
            if (items[index].quantity <= 0) {
                items.splice(index, 1);
            }
            updateItemList();
            updateTotal();
        }
        
        function removeItem(index) {
            items.splice(index, 1);
            updateItemList();
            updateTotal();
        }
        
        function updateTotal() {
            let subtotal = 0;
            items.forEach(item => {
                subtotal += item.price * item.quantity;
            });
            
            const taxAmount = Math.round(subtotal * 0.10);
            const total = subtotal + taxAmount;
            
            document.getElementById('subtotal').textContent = `¥${subtotal.toLocaleString()}`;
            document.getElementById('taxAmount').textContent = `¥${taxAmount.toLocaleString()}`;
            document.getElementById('totalAmount').textContent = `¥${total.toLocaleString()} (税込)`;
        }
        
        function processSale() {
            if (items.length === 0) {
                alert('商品を追加してください。');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'process_sale');
            formData.append('items', JSON.stringify(items));
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('transactionId').textContent = `取引ID: ${data.transaction_id}`;
                    document.getElementById('successModal').style.display = 'block';
                    cancelTransaction();
                    loadDailySummary();
                } else {
                    alert('売上の記録に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('エラーが発生しました。');
            });
        }
        
        function cancelTransaction() {
            items = [];
            updateItemList();
            updateTotal();
            clearDisplay();
        }
        
        function closeModal() {
            document.getElementById('successModal').style.display = 'none';
        }
        
        function loadDailySummary() {
            const date = document.getElementById('reportDate').value;
            const formData = new FormData();
            formData.append('action', 'get_daily_summary');
            if (date) formData.append('date', date);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const summaryCards = document.getElementById('summaryCards');
                summaryCards.innerHTML = `
                    <div class="summary-card">
                        <h3>取引件数</h3>
                        <div class="value">${data.transaction_count || 0}件</div>
                    </div>
                    <div class="summary-card">
                        <h3>売上合計</h3>
                        <div class="value">¥${(data.total_sales || 0).toLocaleString()}</div>
                    </div>
                    <div class="summary-card">
                        <h3>消費税合計</h3>
                        <div class="value">¥${(data.total_tax || 0).toLocaleString()}</div>
                    </div>
                `;
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        // キーボードサポート
        document.addEventListener('keydown', function(event) {
            const key = event.key;
            
            if (key >= '0' && key <= '9') {
                inputNumber(key);
            } else if (key === 'Enter') {
                addItem();
            } else if (key === 'Escape') {
                clearDisplay();
            } else if (key === 'Backspace') {
                deleteLastDigit();
            }
        });
        
        // ページ読み込み時に今日のサマリーを表示
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('reportDate').value = new Date().toISOString().split('T')[0];
            loadDailySummary();
        });
        
        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            const modal = document.getElementById('successModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>