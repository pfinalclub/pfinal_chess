<?php
/**
 * 斗地主游戏服务器
 * 
 * 启动方式：php server.php start
 * 调试模式：php server.php start -d
 */

require_once __DIR__ . '/vendor/autoload.php';

use PfinalClub\AsyncioGamekit\GameServer;
use PfinalClub\AsyncioGamekit\Security\InputValidator;
use PfinalChess\Game\DouDiZhuRoom;

// 注册自动加载
spl_autoload_register(function ($class) {
    $prefix = 'PfinalChess\\';
    $baseDir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// ==================== 注册自定义游戏事件 ====================

// 添加斗地主游戏的自定义事件到白名单
InputValidator::addAllowedEvent('bid');       // 叫地主
InputValidator::addAllowedEvent('play');      // 出牌
InputValidator::addAllowedEvent('pass');      // 不出
InputValidator::addAllowedEvent('get_state'); // 获取游戏状态

// ==================== 启动服务器 ====================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    🃏 斗地主游戏服务器 🃏                     ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  监听地址: ws://0.0.0.0:2345                                 ║\n";
echo "║  游戏房间: DouDiZhuRoom (斗地主)                             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

$server = new GameServer('0.0.0.0', 2345, [
    'name' => 'DouDiZhuGameServer',
    'count' => 1,  // 单进程用于测试
    // 配置允许的房间类（使用完整类名）
    'allowed_room_classes' => [
        \PfinalChess\Game\DouDiZhuRoom::class,
    ],
]);

echo "服务器启动中...\n";
echo "\n";
echo "📖 使用说明:\n";
echo "   1. 打开浏览器访问 client.html\n";
echo "   2. 连接服务器: ws://localhost:2345\n";
echo "   3. 设置玩家名称\n";
echo "   4. 快速匹配开始游戏\n";
echo "\n";
echo "🎮 客户端命令:\n";
echo "   • set_name      - 设置玩家名称\n";
echo "   • quick_match   - 快速匹配 (room_class: PfinalChess\\\\Game\\\\DouDiZhuRoom)\n";
echo "   • create_room   - 创建房间\n";
echo "   • join_room     - 加入房间\n";
echo "   • leave_room    - 离开房间\n";
echo "   • get_rooms     - 获取房间列表\n";
echo "\n";
echo "🃏 游戏命令:\n";
echo "   • bid           - 叫地主 (score: 1/2/3 或 0不叫)\n";
echo "   • play          - 出牌 (cards: [card_id, ...])\n";
echo "   • pass          - 不出\n";
echo "   • get_state     - 获取游戏状态\n";
echo "\n";

$server->run();

