<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'pos_system';
    private $username = 'root';
    private $password = '';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname};charset=utf8", 
                                $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch(PDOException $e) {
            $this->createDatabase();
        }
    }

    private function createDatabase() {
        try {
            $pdo = new PDO("mysql:host={$this->host};charset=utf8", $this->username, $this->password);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS {$this->dbname}");
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname};charset=utf8", 
                                $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch(PDOException $e) {
            echo "データベース接続エラー: " . $e->getMessage();
        }
    }

    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(20) NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            tax_amount DECIMAL(10,2) NOT NULL,
            status ENUM('completed', 'refunded', 'voided') DEFAULT 'completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS sale_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(20) NOT NULL,
            item_name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            quantity INT NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            status ENUM('active', 'refunded', 'voided') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            category VARCHAR(50),
            stock_quantity INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(20) NOT NULL,
            action_type ENUM('edit', 'refund', 'void', 'restore') NOT NULL,
            old_values JSON,
            new_values JSON,
            admin_user VARCHAR(50) NOT NULL,
            reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        ";
        $this->pdo->exec($sql);
    }

    public function saveSale($transactionId, $items, $totalAmount, $taxAmount) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("INSERT INTO sales (transaction_id, total_amount, tax_amount) VALUES (?, ?, ?)");
            $stmt->execute([$transactionId, $totalAmount, $taxAmount]);
            
            $stmt = $this->pdo->prepare("INSERT INTO sale_items (transaction_id, item_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt->execute([$transactionId, $item['name'], $item['price'], $item['quantity'], $item['subtotal']]);
            }
            
            $this->pdo->commit();
            return true;
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    public function getSalesData($date = null) {
        $sql = "SELECT * FROM sales";
        if ($date) {
            $sql .= " WHERE DATE(created_at) = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$date]);
        } else {
            $stmt = $this->pdo->query($sql);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getDailySummary($date = null) {
        $sql = "SELECT COUNT(*) as transaction_count, SUM(total_amount) as total_sales, SUM(tax_amount) as total_tax FROM sales";
        if ($date) {
            $sql .= " WHERE DATE(created_at) = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$date]);
        } else {
            $sql .= " WHERE DATE(created_at) = CURDATE()";
            $stmt = $this->pdo->query($sql);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTotalProducts() {
        $sql = "SELECT COUNT(*) as total FROM products";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public function getLowStockItems() {
        $sql = "SELECT COUNT(*) as low_stock FROM products WHERE stock_quantity <= 5";
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['low_stock'] ?? 0;
    }

    public function getSalesHistory($filters = []) {
        $sql = "SELECT s.*, 
                       COUNT(si.id) as item_count,
                       GROUP_CONCAT(CONCAT(si.item_name, ' (', si.quantity, ')') SEPARATOR ', ') as items_summary
                FROM sales s 
                LEFT JOIN sale_items si ON s.transaction_id = si.transaction_id 
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(s.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(s.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['transaction_id'])) {
            $sql .= " AND s.transaction_id LIKE ?";
            $params[] = '%' . $filters['transaction_id'] . '%';
        }
        
        $sql .= " GROUP BY s.id";
        
        if (!empty($filters['sort_by'])) {
            $sortBy = $filters['sort_by'];
            $sortOrder = $filters['sort_order'] ?? 'DESC';
            $sql .= " ORDER BY s.{$sortBy} {$sortOrder}";
        } else {
            $sql .= " ORDER BY s.created_at DESC";
        }
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSaleDetails($transactionId) {
        $sql = "SELECT s.*, si.* FROM sales s 
                LEFT JOIN sale_items si ON s.transaction_id = si.transaction_id 
                WHERE s.transaction_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$transactionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateSaleStatus($transactionId, $status, $adminUser, $reason = '') {
        try {
            $this->pdo->beginTransaction();
            
            // 現在のデータを取得
            $stmt = $this->pdo->prepare("SELECT * FROM sales WHERE transaction_id = ?");
            $stmt->execute([$transactionId]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 売上ステータスを更新
            $stmt = $this->pdo->prepare("UPDATE sales SET status = ?, updated_at = NOW() WHERE transaction_id = ?");
            $stmt->execute([$status, $transactionId]);
            
            // 売上アイテムのステータスも更新
            $stmt = $this->pdo->prepare("UPDATE sale_items SET status = ? WHERE transaction_id = ?");
            $stmt->execute([$status === 'completed' ? 'active' : $status, $transactionId]);
            
            // 監査ログに記録
            $this->addAuditLog($transactionId, $status, $oldData, ['status' => $status], $adminUser, $reason);
            
            $this->pdo->commit();
            return true;
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    public function updateSaleItem($itemId, $newData, $adminUser, $reason = '') {
        try {
            $this->pdo->beginTransaction();
            
            // 現在のデータを取得
            $stmt = $this->pdo->prepare("SELECT * FROM sale_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // アイテムを更新
            $stmt = $this->pdo->prepare("UPDATE sale_items SET item_name = ?, price = ?, quantity = ?, subtotal = ? WHERE id = ?");
            $stmt->execute([
                $newData['item_name'],
                $newData['price'],
                $newData['quantity'],
                $newData['subtotal'],
                $itemId
            ]);
            
            // 売上合計を再計算
            $this->recalculateSaleTotal($oldData['transaction_id']);
            
            // 監査ログに記録
            $this->addAuditLog($oldData['transaction_id'], 'edit', $oldData, $newData, $adminUser, $reason);
            
            $this->pdo->commit();
            return true;
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    private function recalculateSaleTotal($transactionId) {
        $stmt = $this->pdo->prepare("SELECT SUM(subtotal) as total FROM sale_items WHERE transaction_id = ? AND status = 'active'");
        $stmt->execute([$transactionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $subtotal = $result['total'] ?? 0;
        $taxAmount = round($subtotal * 0.10);
        $totalAmount = $subtotal + $taxAmount;
        
        $stmt = $this->pdo->prepare("UPDATE sales SET total_amount = ?, tax_amount = ? WHERE transaction_id = ?");
        $stmt->execute([$totalAmount, $taxAmount, $transactionId]);
    }
    
    private function addAuditLog($transactionId, $actionType, $oldValues, $newValues, $adminUser, $reason) {
        $stmt = $this->pdo->prepare("INSERT INTO audit_log (transaction_id, action_type, old_values, new_values, admin_user, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $transactionId,
            $actionType,
            json_encode($oldValues),
            json_encode($newValues),
            $adminUser,
            $reason
        ]);
    }
    
    public function getAuditLog($transactionId) {
        $stmt = $this->pdo->prepare("SELECT * FROM audit_log WHERE transaction_id = ? ORDER BY created_at DESC");
        $stmt->execute([$transactionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteSaleItem($itemId, $adminUser, $reason = '') {
        try {
            $this->pdo->beginTransaction();
            
            // 現在のデータを取得
            $stmt = $this->pdo->prepare("SELECT * FROM sale_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldData) {
                throw new Exception('商品が見つかりません。');
            }
            
            // 商品を削除（ステータスをvoidedに変更）
            $stmt = $this->pdo->prepare("UPDATE sale_items SET status = 'voided' WHERE id = ?");
            $stmt->execute([$itemId]);
            
            // 売上合計を再計算
            $this->recalculateSaleTotal($oldData['transaction_id']);
            
            // 監査ログに記録
            $this->addAuditLog($oldData['transaction_id'], 'void', $oldData, ['status' => 'voided'], $adminUser, $reason);
            
            $this->pdo->commit();
            return true;
        } catch(Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
?>
