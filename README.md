# 🃏 PFinal Chess - 斗地主游戏

基于 `pfinalclub/asyncio-gamekit` 框架开发的实时多人斗地主游戏。

![](https://raw.githubusercontent.com/pfinal-nc/iGallery/master/blog/202511181423338.png)

## ✨ 特性

- 🎮 完整的斗地主游戏规则
- 🌐 WebSocket 实时通信
- 👥 支持 3 人同时游戏
- 🎯 智能牌型识别（单牌、对子、三带、顺子、炸弹、王炸等）
- 📱 现代化 Web 界面
- ⚡ 基于 PHP Fiber 的高性能异步架构

## 📦 安装

```bash
# 安装依赖
composer install

# 更新自动加载
composer dump-autoload
```

## 🚀 快速开始

### 1. 启动游戏服务器

```bash
php server.php start
```

或使用 composer 脚本：

```bash
composer start
```

### 2. 打开游戏客户端

在浏览器中打开 `public/client.html` 文件。

### 3. 开始游戏

1. 点击「连接服务器」
2. 设置玩家名称
3. 点击「快速匹配」
4. 等待其他玩家加入（需要 3 人）
5. 开始游戏！

## 🎮 游戏规则

### 基本规则

- 一副牌 54 张（包含大小王）
- 3 名玩家，1 名地主，2 名农民
- 地主有 20 张牌，农民各 17 张牌
- 地主先出牌，逆时针轮流

### 牌型

| 牌型 | 说明 |
|------|------|
| 单牌 | 任意一张单牌 |
| 对子 | 两张相同点数的牌 |
| 三不带 | 三张相同点数的牌 |
| 三带一 | 三张相同点数 + 一张单牌 |
| 三带二 | 三张相同点数 + 一对 |
| 顺子 | 五张或更多连续的单牌（不含 2 和王） |
| 连对 | 三对或更多连续的对子（不含 2 和王） |
| 飞机 | 两个或更多连续的三张（不含 2 和王） |
| 炸弹 | 四张相同点数的牌 |
| 王炸 | 大王 + 小王（最大） |

### 计分规则

- 底分：100 分
- 叫地主分数 = 倍率
- 炸弹/王炸：倍率 x2
- 地主获胜：地主 +底分×倍率×2，农民各 -底分×倍率
- 农民获胜：地主 -底分×倍率×2，农民各 +底分×倍率

## 📁 项目结构

```
pfinal_chess/
├── src/
│   ├── Card/
│   │   ├── Card.php          # 扑克牌类
│   │   ├── Deck.php          # 牌组类
│   │   └── CardUtils.php     # 牌型工具类
│   └── Game/
│       └── DouDiZhuRoom.php  # 斗地主游戏房间
├── public/
│   └── client.html           # Web 游戏客户端
├── server.php                # 游戏服务器入口
├── composer.json
└── README.md
```

## 🔧 配置

游戏服务器默认监听 `ws://0.0.0.0:2345`

可在 `server.php` 中修改：

```php
$server = new GameServer('0.0.0.0', 2345, [
    'name' => 'DouDiZhuGameServer',
    'count' => 1,  // Worker 进程数
]);
```

游戏房间配置在 `DouDiZhuRoom.php`：

```php
protected function getDefaultConfig(): array
{
    return [
        'max_players' => 3,
        'min_players' => 3,
        'auto_start' => true,
        'bid_timeout' => 15,     // 叫地主超时时间（秒）
        'play_timeout' => 30,    // 出牌超时时间（秒）
        'base_score' => 100,     // 底分
    ];
}
```

## 📡 WebSocket 协议

### 客户端 → 服务器

```javascript
// 设置名称
{ event: 'set_name', data: { name: '玩家名' } }

// 快速匹配
{ event: 'quick_match', data: { room_class: 'DouDiZhuRoom' } }

// 叫地主
{ event: 'bid', data: { score: 1 } }  // 0=不叫, 1/2/3=叫分

// 出牌
{ event: 'play', data: { cards: ['spade_A', 'heart_A'] } }

// 不出
{ event: 'pass', data: {} }
```

### 服务器 → 客户端

```javascript
// 发牌
{ event: 'game:cards_dealt', data: { cards: [...], position: 0 } }

// 叫地主回合
{ event: 'game:bid_turn', data: { player_id: '...', current_bid: 0 } }

// 设置地主
{ event: 'game:landlord_set', data: { landlord_id: '...', landlord_cards: [...] } }

// 出牌回合
{ event: 'game:play_turn', data: { player_id: '...', must_play: true } }

// 有人出牌
{ event: 'game:cards_played', data: { player_id: '...', cards: [...] } }

// 游戏结束
{ event: 'game:end', data: { winner_id: '...', results: [...] } }
```

## 🛠️ 技术栈

- **后端框架**: pfinalclub/asyncio-gamekit
- **底层框架**: Workerman + pfinal-asyncio
- **协程支持**: PHP 8.1+ Fiber
- **通信协议**: WebSocket
- **前端**: 原生 HTML/CSS/JavaScript

## 📄 许可证

MIT License

## 🔗 相关链接

- [pfinal-asyncio-gamekit](https://github.com/pfinalclub/pfinal-asyncio-gamekit)
- [pfinal-asyncio](https://github.com/pfinalclub/pfinal-asyncio)
- [Workerman](https://www.workerman.net/)

---

Made with ❤️ by PFinal Club

