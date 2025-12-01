<?php

declare(strict_types=1);

namespace PfinalChess\Game;

use PfinalClub\AsyncioGamekit\Room;
use PfinalClub\AsyncioGamekit\Player;
use PfinalChess\Card\Card;
use PfinalChess\Card\Deck;
use PfinalChess\Card\CardUtils;
use function PfinalClub\Asyncio\sleep;

/**
 * 斗地主游戏房间
 */
class DouDiZhuRoom extends Room
{
    // 游戏阶段
    private const PHASE_WAITING = 'waiting';       // 等待玩家
    private const PHASE_BIDDING = 'bidding';       // 叫地主
    private const PHASE_PLAYING = 'playing';       // 出牌
    private const PHASE_FINISHED = 'finished';     // 结束

    private string $phase = self::PHASE_WAITING;
    
    /** @var Card[] 底牌 */
    private array $landlordCards = [];
    
    /** @var string|null 地主玩家ID */
    private ?string $landlordId = null;
    
    /** @var string|null 当前回合玩家ID */
    private ?string $currentPlayerId = null;
    
    /** @var int 当前叫分 */
    private int $currentBid = 0;
    
    /** @var string|null 当前最高叫分玩家 */
    private ?string $highestBidder = null;
    
    /** @var int 叫地主轮次 */
    private int $bidRound = 0;
    
    /** @var Card[] 上一手牌 */
    private array $lastPlayedCards = [];
    
    /** @var string|null 上一手牌玩家ID */
    private ?string $lastPlayerId = null;
    
    /** @var int 连续pass次数 */
    private int $passCount = 0;
    
    /** @var int 倍率 */
    private int $multiplier = 1;
    
    /** @var array 玩家顺序 */
    private array $playerOrder = [];

    protected function getDefaultConfig(): array
    {
        return [
            'max_players' => 3,
            'min_players' => 3,
            'auto_start' => true,
            'bid_timeout' => 15,     // 叫地主超时时间
            'play_timeout' => 30,    // 出牌超时时间
            'base_score' => 100,     // 底分
        ];
    }

    /**
     * 房间创建
     */
    protected function onCreate(): mixed
    {
        $this->broadcast('room:created', [
            'room_id' => $this->getId(),
            'config' => [
                'max_players' => $this->config['max_players'],
                'min_players' => $this->config['min_players'],
                'base_score' => $this->config['base_score'],
            ]
        ]);
        return null;
    }

    /**
     * 玩家加入
     */
    protected function onPlayerJoin(Player $player): void
    {
        echo "[斗地主] 玩家 {$player->getName()} 加入房间\n";
        
        // 初始化玩家数据
        $player->set('cards', []);
        $player->set('is_landlord', false);
        $player->set('has_bid', false);
        $player->set('bid_score', 0);
        
        // 广播玩家加入
        $this->broadcast('player:join', [
            'player' => $player->toArray(),
            'player_count' => $this->getPlayerCount(),
        ]);
    }

    /**
     * 玩家离开
     */
    protected function onPlayerLeave(Player $player): void
    {
        echo "[斗地主] 玩家 {$player->getName()} 离开房间\n";
        
        $this->broadcast('player:leave', [
            'player_id' => $player->getId(),
            'player_count' => $this->getPlayerCount(),
        ]);
        
        // 游戏中玩家离开，游戏结束
        if ($this->phase === self::PHASE_PLAYING || $this->phase === self::PHASE_BIDDING) {
            $this->endGameWithError('有玩家离开，游戏结束');
        }
    }

    /**
     * 游戏开始准备
     */
    protected function onStart(): mixed
    {
        echo "[斗地主] 游戏开始！\n";
        
        $this->broadcast('game:starting', [
            'message' => '游戏即将开始',
            'countdown' => 3,
        ]);
        
        sleep(3);
        
        // 初始化玩家顺序
        $this->playerOrder = array_keys($this->players);
        shuffle($this->playerOrder);
        
        // 发牌
        $this->dealCards();
        
        return null;
    }

    /**
     * 发牌
     */
    private function dealCards(): void
    {
        $deck = new Deck(true);
        $deck->shuffle();
        
        // 每人17张牌
        $playerIds = $this->playerOrder;
        foreach ($playerIds as $index => $playerId) {
            $player = $this->getPlayer($playerId);
            if ($player) {
                $cards = $deck->deal(17);
                $cards = CardUtils::sortCards($cards);
                $player->set('cards', $cards);
                
                // 只发给对应玩家
                $player->send('game:cards_dealt', [
                    'cards' => CardUtils::cardsToArray($cards),
                    'position' => $index,
                ]);
            }
        }
        
        // 留3张底牌
        $this->landlordCards = $deck->deal(3);
        
        $this->broadcast('game:deal_complete', [
            'message' => '发牌完成',
            'landlord_cards_count' => 3,
        ]);
    }

    /**
     * 游戏主循环
     */
    protected function run(): mixed
    {
        // 叫地主阶段
        $this->startBidding();
        
        // 如果没人叫地主，重新发牌
        if ($this->landlordId === null) {
            $this->broadcast('game:rebid', ['message' => '无人叫地主，重新发牌']);
            sleep(2);
            
            // 重置玩家叫地主状态
            foreach ($this->players as $player) {
                $player->set('has_bid', false);
                $player->set('bid_score', 0);
            }
            
            $this->dealCards();
            $this->startBidding();
        }
        
        // 如果仍然没人叫，随机选地主
        if ($this->landlordId === null) {
            $this->landlordId = $this->playerOrder[array_rand($this->playerOrder)];
            $this->currentBid = 1;
            
            $randomLandlord = $this->getPlayer($this->landlordId);
            $this->broadcast('game:random_landlord', [
                'message' => '两轮无人叫地主，随机指定地主',
                'landlord_id' => $this->landlordId,
                'landlord_name' => $randomLandlord?->getName(),
            ]);
        }
        
        // 设置地主
        $this->setLandlord();
        
        // 出牌阶段
        $this->startPlaying();
        
        return null;
    }

    /**
     * 叫地主阶段
     */
    private function startBidding(): void
    {
        $this->phase = self::PHASE_BIDDING;
        $this->currentBid = 0;
        $this->highestBidder = null;
        $this->bidRound = 0;
        
        // 随机选择第一个叫地主的玩家
        $firstBidderIndex = array_rand($this->playerOrder);
        
        $this->broadcast('game:bidding_start', [
            'message' => '叫地主阶段开始',
            'first_bidder' => $this->playerOrder[$firstBidderIndex],
        ]);
        
        // 每人一轮叫地主机会
        for ($round = 0; $round < 3; $round++) {
            $bidderIndex = ($firstBidderIndex + $round) % 3;
            $bidderId = $this->playerOrder[$bidderIndex];
            $bidder = $this->getPlayer($bidderId);
            
            if (!$bidder) continue;
            
            // 如果已经有人叫3分，结束叫地主
            if ($this->currentBid >= 3) {
                break;
            }
            
            // 通知当前玩家叫地主
            $this->currentPlayerId = $bidderId;
            $bidder->set('can_bid', true);
            
            $this->broadcast('game:bid_turn', [
                'player_id' => $bidderId,
                'player_name' => $bidder->getName(),
                'current_bid' => $this->currentBid,
                'timeout' => $this->config['bid_timeout'],
            ]);
            
            // 等待玩家叫地主
            $timeout = $this->config['bid_timeout'];
            $startTime = time();
            
            while (!$bidder->get('has_bid', false) && (time() - $startTime) < $timeout) {
                sleep(0.5);
            }
            
            // 超时不叫
            if (!$bidder->get('has_bid', false)) {
                $bidder->set('bid_score', 0);
                $bidder->set('has_bid', true);
                $this->broadcast('game:bid_timeout', [
                    'player_id' => $bidderId,
                    'message' => "{$bidder->getName()} 超时，自动不叫",
                ]);
            }
            
            $bidder->set('can_bid', false);
            $this->bidRound++;
        }
    }

    /**
     * 设置地主
     */
    private function setLandlord(): void
    {
        $landlord = $this->getPlayer($this->landlordId);
        if (!$landlord) return;
        
        $landlord->set('is_landlord', true);
        
        // 把底牌给地主
        $cards = $landlord->get('cards', []);
        $cards = array_merge($cards, $this->landlordCards);
        $cards = CardUtils::sortCards($cards);
        $landlord->set('cards', $cards);
        
        // 倍率
        $this->multiplier = $this->currentBid;
        
        // 广播地主信息和底牌
        $this->broadcast('game:landlord_set', [
            'landlord_id' => $this->landlordId,
            'landlord_name' => $landlord->getName(),
            'landlord_cards' => CardUtils::cardsToArray($this->landlordCards),
            'bid_score' => $this->currentBid,
            'multiplier' => $this->multiplier,
        ]);
        
        // 发送更新后的手牌给地主
        $landlord->send('game:cards_update', [
            'cards' => CardUtils::cardsToArray($cards),
        ]);
        
        sleep(2);
    }

    /**
     * 出牌阶段
     */
    private function startPlaying(): void
    {
        $this->phase = self::PHASE_PLAYING;
        $this->lastPlayedCards = [];
        $this->lastPlayerId = null;
        $this->passCount = 0;
        
        // 地主先出牌
        $landlordIndex = array_search($this->landlordId, $this->playerOrder);
        $currentIndex = $landlordIndex;
        
        $this->broadcast('game:playing_start', [
            'message' => '出牌阶段开始',
            'first_player' => $this->landlordId,
        ]);
        
        while ($this->phase === self::PHASE_PLAYING) {
            $currentPlayerId = $this->playerOrder[$currentIndex];
            $currentPlayer = $this->getPlayer($currentPlayerId);
            
            if (!$currentPlayer) {
                $currentIndex = ($currentIndex + 1) % 3;
                continue;
            }
            
            $this->currentPlayerId = $currentPlayerId;
            
            // 检查是否需要重置（连续两人pass，轮到最后出牌的人）
            if ($this->passCount >= 2 && $currentPlayerId === $this->lastPlayerId) {
                $this->lastPlayedCards = [];
                $this->lastPlayerId = null;
                $this->passCount = 0;
            }
            
            // 通知当前玩家出牌
            $mustPlay = empty($this->lastPlayedCards) || $this->lastPlayerId === $currentPlayerId;
            
            $this->broadcast('game:play_turn', [
                'player_id' => $currentPlayerId,
                'player_name' => $currentPlayer->getName(),
                'must_play' => $mustPlay,
                'last_cards' => CardUtils::cardsToArray($this->lastPlayedCards),
                'last_player_id' => $this->lastPlayerId,
                'timeout' => $this->config['play_timeout'],
            ]);
            
            $currentPlayer->set('action_taken', false);
            
            // 等待出牌
            $timeout = $this->config['play_timeout'];
            $startTime = time();
            
            while (!$currentPlayer->get('action_taken', false) && (time() - $startTime) < $timeout) {
                sleep(0.5);
            }
            
            // 超时处理
            if (!$currentPlayer->get('action_taken', false)) {
                if ($mustPlay) {
                    // 必须出牌时，自动出最小的牌
                    $this->autoPlaySmallest($currentPlayer);
                } else {
                    // 可以选择不出时，自动pass
                    $this->handlePass($currentPlayer);
                }
            }
            
            // 检查是否有人出完牌
            $cards = $currentPlayer->get('cards', []);
            if (empty($cards)) {
                $this->endGame($currentPlayerId);
                break;
            }
            
            // 下一个玩家
            $currentIndex = ($currentIndex + 1) % 3;
        }
    }

    /**
     * 自动出最小的牌
     */
    private function autoPlaySmallest(Player $player): void
    {
        $cards = $player->get('cards', []);
        if (empty($cards)) return;
        
        // 出最小的单牌
        $smallestCard = $cards[0];
        $this->playCards($player, [$smallestCard]);
    }

    /**
     * 处理pass
     */
    private function handlePass(Player $player): void
    {
        $this->passCount++;
        $player->set('action_taken', true);
        
        $this->broadcast('game:player_pass', [
            'player_id' => $player->getId(),
            'player_name' => $player->getName(),
        ]);
    }

    /**
     * 出牌
     * 
     * @param Card[] $cards
     */
    private function playCards(Player $player, array $cards): bool
    {
        // 验证牌型
        $type = CardUtils::analyzeType($cards);
        if ($type['type'] === CardUtils::TYPE_INVALID) {
            $player->send('game:play_error', ['message' => '无效的牌型']);
            return false;
        }
        
        // 如果有上家出的牌，需要比较大小
        if (!empty($this->lastPlayedCards)) {
            if (!CardUtils::canBeat($cards, $this->lastPlayedCards)) {
                $player->send('game:play_error', ['message' => '出的牌不够大']);
                return false;
            }
        }
        
        // 验证玩家手中是否有这些牌
        $playerCards = $player->get('cards', []);
        $playerCardIds = array_map(fn(Card $c) => $c->getId(), $playerCards);
        $playCardIds = array_map(fn(Card $c) => $c->getId(), $cards);
        
        foreach ($playCardIds as $cardId) {
            if (!in_array($cardId, $playerCardIds)) {
                $player->send('game:play_error', ['message' => '你没有这些牌']);
                return false;
            }
        }
        
        // 从手牌中移除出的牌
        $remainingCards = array_filter($playerCards, fn(Card $c) => !in_array($c->getId(), $playCardIds));
        $remainingCards = array_values($remainingCards);
        $player->set('cards', $remainingCards);
        
        // 更新游戏状态
        $this->lastPlayedCards = $cards;
        $this->lastPlayerId = $player->getId();
        $this->passCount = 0;
        $player->set('action_taken', true);
        
        // 炸弹/王炸翻倍
        if ($type['type'] === CardUtils::TYPE_BOMB || $type['type'] === CardUtils::TYPE_ROCKET) {
            $this->multiplier *= 2;
        }
        
        // 广播出牌信息
        $this->broadcast('game:cards_played', [
            'player_id' => $player->getId(),
            'player_name' => $player->getName(),
            'cards' => CardUtils::cardsToArray($cards),
            'card_type' => $type['type'],
            'remaining_count' => count($remainingCards),
            'multiplier' => $this->multiplier,
        ]);
        
        // 更新玩家手牌
        $player->send('game:cards_update', [
            'cards' => CardUtils::cardsToArray($remainingCards),
        ]);
        
        return true;
    }

    /**
     * 游戏结束
     */
    private function endGame(string $winnerId): void
    {
        $this->phase = self::PHASE_FINISHED;
        
        $winner = $this->getPlayer($winnerId);
        $isLandlordWin = $winnerId === $this->landlordId;
        
        // 计算分数
        $baseScore = $this->config['base_score'];
        $finalScore = $baseScore * $this->multiplier;
        
        $results = [];
        foreach ($this->players as $player) {
            $isLandlord = $player->getId() === $this->landlordId;
            
            if ($isLandlordWin) {
                // 地主赢
                $score = $isLandlord ? $finalScore * 2 : -$finalScore;
            } else {
                // 农民赢
                $score = $isLandlord ? -$finalScore * 2 : $finalScore;
            }
            
            $results[] = [
                'player_id' => $player->getId(),
                'player_name' => $player->getName(),
                'is_landlord' => $isLandlord,
                'score' => $score,
                'is_winner' => ($isLandlordWin && $isLandlord) || (!$isLandlordWin && !$isLandlord),
            ];
        }
        
        $this->broadcast('game:end', [
            'winner_id' => $winnerId,
            'winner_name' => $winner?->getName(),
            'is_landlord_win' => $isLandlordWin,
            'multiplier' => $this->multiplier,
            'results' => $results,
        ]);
        
        echo "[斗地主] 游戏结束，" . ($isLandlordWin ? '地主' : '农民') . "获胜\n";
        
        sleep(5);
        $this->destroy();
    }

    /**
     * 游戏异常结束
     */
    private function endGameWithError(string $reason): void
    {
        $this->phase = self::PHASE_FINISHED;
        
        $this->broadcast('game:error_end', [
            'reason' => $reason,
        ]);
        
        echo "[斗地主] 游戏异常结束：{$reason}\n";
        
        sleep(2);
        $this->destroy();
    }

    /**
     * 处理玩家消息
     */
    public function onPlayerMessage(Player $player, string $event, mixed $data): mixed
    {
        switch ($event) {
            case 'bid':
                $this->handleBid($player, $data);
                break;
                
            case 'play':
                $this->handlePlay($player, $data);
                break;
                
            case 'pass':
                if ($this->currentPlayerId === $player->getId() && !empty($this->lastPlayedCards)) {
                    $this->handlePass($player);
                }
                break;
                
            case 'get_state':
                $this->sendGameState($player);
                break;
        }
        
        return null;
    }

    /**
     * 处理叫地主
     */
    private function handleBid(Player $player, mixed $data): void
    {
        if ($this->phase !== self::PHASE_BIDDING) {
            return;
        }
        
        if ($this->currentPlayerId !== $player->getId()) {
            $player->send('game:bid_error', ['message' => '还没轮到你叫地主']);
            return;
        }
        
        if ($player->get('has_bid', false)) {
            return;
        }
        
        $score = (int)($data['score'] ?? 0);
        
        // 叫分必须大于当前叫分，且在1-3之间
        if ($score > 0 && ($score <= $this->currentBid || $score > 3)) {
            $player->send('game:bid_error', ['message' => '叫分无效']);
            return;
        }
        
        $player->set('has_bid', true);
        $player->set('bid_score', $score);
        
        if ($score > $this->currentBid) {
            $this->currentBid = $score;
            $this->highestBidder = $player->getId();
            $this->landlordId = $player->getId();
        }
        
        $action = $score > 0 ? "叫 {$score} 分" : '不叫';
        
        $this->broadcast('game:bid_result', [
            'player_id' => $player->getId(),
            'player_name' => $player->getName(),
            'score' => $score,
            'action' => $action,
            'current_bid' => $this->currentBid,
            'highest_bidder' => $this->highestBidder,
        ]);
    }

    /**
     * 处理出牌
     */
    private function handlePlay(Player $player, mixed $data): void
    {
        if ($this->phase !== self::PHASE_PLAYING) {
            return;
        }
        
        if ($this->currentPlayerId !== $player->getId()) {
            $player->send('game:play_error', ['message' => '还没轮到你出牌']);
            return;
        }
        
        if ($player->get('action_taken', false)) {
            return;
        }
        
        $cardIds = $data['cards'] ?? [];
        if (empty($cardIds)) {
            $player->send('game:play_error', ['message' => '请选择要出的牌']);
            return;
        }
        
        // 还原牌对象
        $cards = CardUtils::cardsFromIds($cardIds);
        
        $this->playCards($player, $cards);
    }

    /**
     * 发送游戏状态
     */
    private function sendGameState(Player $player): void
    {
        $otherPlayers = [];
        foreach ($this->players as $p) {
            if ($p->getId() !== $player->getId()) {
                $otherPlayers[] = [
                    'id' => $p->getId(),
                    'name' => $p->getName(),
                    'card_count' => count($p->get('cards', [])),
                    'is_landlord' => $p->get('is_landlord', false),
                ];
            }
        }
        
        $player->send('game:state', [
            'phase' => $this->phase,
            'your_cards' => CardUtils::cardsToArray($player->get('cards', [])),
            'is_landlord' => $player->get('is_landlord', false),
            'landlord_id' => $this->landlordId,
            'current_player_id' => $this->currentPlayerId,
            'last_cards' => CardUtils::cardsToArray($this->lastPlayedCards),
            'last_player_id' => $this->lastPlayerId,
            'multiplier' => $this->multiplier,
            'other_players' => $otherPlayers,
        ]);
    }

    /**
     * 房间销毁
     */
    protected function onDestroy(): mixed
    {
        echo "[斗地主] 房间 {$this->getId()} 已销毁\n";
        return null;
    }
}

