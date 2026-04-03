<?php
// includes/telegram.php - Helper to send Telegram notifications
require_once __DIR__ . '/db.php';

function send_telegram_notification($message) {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT telegram_bot_token, telegram_group_id FROM settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $token = $settings['telegram_bot_token'] ?? '';
        $chat_id = $settings['telegram_group_id'] ?? '';
        
        if (empty($token) || empty($chat_id)) {
            return false; // Not configured
        }
        
        // Save to offline queue
        $stmt = $pdo->prepare("INSERT INTO telegram_queue (message) VALUES (?)");
        $stmt->execute([$message]);
        
        return true;
    } catch (Exception $e) {
        error_log("Telegram Queue Error: " . $e->getMessage());
        return false;
    }
}
?>
