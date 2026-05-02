<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'update_status') {
    $order_id = $data['order_id'] ?? null;
    $status = $data['status'] ?? '';
    
    if (!$order_id || !in_array($status, ['pending', 'completed', 'cancelled'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

if ($action === 'update_product') {
    $id = $data['id'] ?? null;
    $name = $data['name'] ?? '';
    $price = $data['price'] ?? 0;
    $stock = $data['stock'] ?? 0;
    $is_even_only = $data['is_even_only'] ?? 0;
    
    if (!$id || empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Invalid product data']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, stock = ?, is_even_only = ? WHERE id = ?");
        $stmt->execute([$name, $price, $stock, $is_even_only, $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

if ($action === 'add_product') {
    $name = $data['name'] ?? '';
    $price = $data['price'] ?? 0;
    $stock = $data['stock'] ?? 0;
    $description = $data['description'] ?? '';
    $is_even_only = $data['is_even_only'] ?? 0;
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Product name is required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO products (name, price, stock, description, is_even_only) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $stock, $description, $is_even_only]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

if ($action === 'delete_product') {
    $id = $data['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        exit;
    }
    
    try {
        // First delete associated orders to prevent foreign key issues
        $stmt = $pdo->prepare("DELETE FROM orders WHERE product_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

if ($action === 'delete_order') {
    $id = $data['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

if ($action === 'add_order') {
    $product_id = $data['product_id'] ?? null;
    $customer_name = $data['customer_name'] ?? '';
    $quantity = $data['quantity'] ?? 0;
    
    if (!$product_id || empty($customer_name) || $quantity <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing order details']);
        exit;
    }
    
    try {
        // Fetch product for price and stock check
        $stmt = $pdo->prepare("SELECT name, price, stock, is_even_only FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            exit;
        }
        
        if ($product['is_even_only'] && $quantity % 2 !== 0) {
            echo json_encode(['success' => false, 'error' => 'Quantity must be an even number for this product']);
            exit;
        }
        
        if ($product['stock'] < $quantity) {
            echo json_encode(['success' => false, 'error' => 'Insufficient stock']);
            exit;
        }
        
        $total_price = $product['price'] * $quantity;
        
        // Record Order
        $stmt = $pdo->prepare("INSERT INTO orders (product_id, customer_name, quantity, total_price, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$product_id, $customer_name, $quantity, $total_price]);
        
        // Deduct Stock
        $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$quantity, $product_id]);
        
        // Notify Admin via Telegram (Optional but recommended)
        $stmt = $pdo->query("SELECT admin_telegram_id, telegram_bot_token, store_name FROM settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings && $settings['admin_telegram_id'] && $settings['telegram_bot_token']) {
            $admin_msg = "🛍️ <b>NEW WEB ORDER</b>\n━━━━━━━━━━━━━━━━━━━━\n" .
                         "<b>Item:</b> " . $product['name'] . "\n" .
                         "<b>Qty:</b> " . $quantity . "\n" .
                         "<b>Total:</b> ₱" . $total_price . "\n" .
                         "<b>Customer:</b> " . $customer_name . "\n" .
                         "<b>Store:</b> " . ($settings['store_name'] ?: 'Official Store');
            
            $url = "https://api.telegram.org/bot" . $settings['telegram_bot_token'] . "/sendMessage?chat_id=" . $settings['admin_telegram_id'] . "&text=" . urlencode($admin_msg) . "&parse_mode=HTML";
            @file_get_contents($url);
        }
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
