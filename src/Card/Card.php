<?php

declare(strict_types=1);

namespace PfinalChess\Card;

/**
 * 扑克牌类
 */
class Card
{
    // 花色常量
    public const SUIT_SPADE = 'spade';     // ♠ 黑桃
    public const SUIT_HEART = 'heart';     // ♥ 红桃
    public const SUIT_CLUB = 'club';       // ♣ 梅花
    public const SUIT_DIAMOND = 'diamond'; // ♦ 方块
    public const SUIT_JOKER = 'joker';     // 王

    // 花色显示
    private const SUIT_SYMBOLS = [
        self::SUIT_SPADE => '♠',
        self::SUIT_HEART => '♥',
        self::SUIT_CLUB => '♣',
        self::SUIT_DIAMOND => '♦',
        self::SUIT_JOKER => '🃏',
    ];

    // 牌值权重（斗地主规则）
    private const VALUE_WEIGHTS = [
        '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7,
        '8' => 8, '9' => 9, '10' => 10, 'J' => 11, 'Q' => 12,
        'K' => 13, 'A' => 14, '2' => 15, 'S' => 16, 'B' => 17,
    ];

    public function __construct(
        private string $suit,
        private string $value
    ) {}

    public function getSuit(): string
    {
        return $this->suit;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * 获取牌的权重（用于比较大小）
     */
    public function getWeight(): int
    {
        return self::VALUE_WEIGHTS[$this->value] ?? 0;
    }

    /**
     * 获取花色符号
     */
    public function getSuitSymbol(): string
    {
        return self::SUIT_SYMBOLS[$this->suit] ?? '';
    }

    /**
     * 是否为大小王
     */
    public function isJoker(): bool
    {
        return $this->suit === self::SUIT_JOKER;
    }

    /**
     * 是否为大王
     */
    public function isBigJoker(): bool
    {
        return $this->suit === self::SUIT_JOKER && $this->value === 'B';
    }

    /**
     * 是否为小王
     */
    public function isSmallJoker(): bool
    {
        return $this->suit === self::SUIT_JOKER && $this->value === 'S';
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'suit' => $this->suit,
            'value' => $this->value,
            'symbol' => $this->getSuitSymbol(),
            'weight' => $this->getWeight(),
            'display' => $this->getDisplayName(),
        ];
    }

    /**
     * 获取显示名称
     */
    public function getDisplayName(): string
    {
        if ($this->isJoker()) {
            return $this->isBigJoker() ? '大王' : '小王';
        }
        return $this->getSuitSymbol() . $this->value;
    }

    /**
     * 生成唯一ID
     */
    public function getId(): string
    {
        return $this->suit . '_' . $this->value;
    }

    /**
     * 从ID还原
     */
    public static function fromId(string $id): self
    {
        [$suit, $value] = explode('_', $id);
        return new self($suit, $value);
    }
}

