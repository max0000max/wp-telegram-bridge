<?php
/**
 * Тестовый скрипт для AJAX endpoint'ов
 * 
 * Запуск: php test-ajax.php [start|send|get]
 * 
 * Настрой BASE_URL и NONCE перед запуском!
 */

define('BASE_URL', 'https://your-site.com'); // ЗАМЕНИТЬ
define('NONCE', 'your-nonce-here');           // ЗАМЕНИТЬ

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'start':
        testStartSession();
        break;
    case 'send':
        $sessionKey = $argv[2] ?? readline("Session key: ");
        $message = $argv[3] ?? readline("Message: ");
        testSendMessage($sessionKey, $message);
        break;
    case 'get':
        $sessionKey = $argv[2] ?? readline("Session key: ");
        testGetMessages($sessionKey);
        break;
    default:
        echo "Использование:\n";
        echo "  php test-ajax.php start                    - Создать сессию\n";
        echo "  php test-ajax.php send <key> <message>     - Отправить сообщение\n";
        echo "  php test-ajax.php get <key>                - Получить сообщения\n";
}

function makeRequest($action, $data) {
    $url = BASE_URL . '/wp-admin/admin-ajax.php';
    
    $data['action'] = $action;
    $data['nonce'] = NONCE;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Для локальных тестов
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response:\n";
    
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo $response;
    }
    echo "\n";
    
    return $decoded;
}

function testStartSession() {
    echo "=== Тест: Создание сессии ===\n";
    
    $result = makeRequest('wtb_start_session', [
        'name' => 'Тест Пользователь',
        'email' => 'test@example.com'
    ]);
    
    if ($result && $result['success']) {
        echo "\n✅ Сессия создана!\n";
        echo "Session key: " . $result['data']['session_key'] . "\n";
        echo "Session ID: " . $result['data']['session_id'] . "\n";
        echo "\nСохрани session_key для следующих запросов.\n";
    } else {
        echo "\n❌ Ошибка создания сессии\n";
    }
}

function testSendMessage($sessionKey, $message) {
    echo "=== Тест: Отправка сообщения ===\n";
    
    $result = makeRequest('wtb_send_message', [
        'session_key' => $sessionKey,
        'content' => $message
    ]);
    
    if ($result && $result['success']) {
        echo "\n✅ Сообщение отправлено\n";
    } else {
        echo "\n❌ Ошибка: " . ($result['data']['message'] ?? 'Unknown error') . "\n";
    }
}

function testGetMessages($sessionKey) {
    echo "=== Тест: Получение сообщений ===\n";
    
    $result = makeRequest('wtb_get_messages', [
        'session_key' => $sessionKey
    ]);
    
    if ($result && $result['success']) {
        $count = count($result['data']['messages'] ?? []);
        echo "\n✅ Получено сообщений: $count\n";
        
        foreach ($result['data']['messages'] as $msg) {
            $direction = $msg['direction'] === 'to_tg' ? '→' : '←';
            echo "  $direction {$msg['content']}\n";
        }
    } else {
        echo "\n❌ Ошибка получения сообщений\n";
    }
}
