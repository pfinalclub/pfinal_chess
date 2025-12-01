<?php

declare(strict_types=1);

namespace PfinalChess\Card;

/**
 * 扑克牌组类
 */
class Deck
{
    /** @var Card[] */
    private array $cards = [];

    public function __construct(bool $withJokers = true)
    {
        $this->init($withJokers);
    }

    /**
     * 初始化牌组
     */
    private function init(bool $withJokers = true): void
    {
        $this->cards = [];
        
        $suits = [Card::SUIT_SPADE, Card::SUIT_HEART, Card::SUIT_CLUB, Card::SUIT_DIAMOND];
        $values = ['3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A', '2'];

        foreach ($suits as $suit) {
            foreach ($values as $value) {
                $this->cards[] = new Card($suit, $value);
            }
        }

        if ($withJokers) {
            $this->cards[] = new Card(Card::SUIT_JOKER, 'S'); // 小王
            $this->cards[] = new Card(Card::SUIT_JOKER, 'B'); // 大王
        }
    }

    /**
     * 洗牌
     */
    public function shuffle(): self
    {
        shuffle($this->cards);
        return $this;
    }

    /**
     * 发牌（取出指定数量的牌）
     * 
     * @return Card[]
     */
    public function deal(int $count): array
    {
        return array_splice($this->cards, 0, $count);
    }

    /**
     * 获取剩余牌数
     */
    public function remaining(): int
    {
        return count($this->cards);
    }

    /**
     * 获取所有剩余的牌
     * 
     * @return Card[]
     */
    public function getCards(): array
    {
        return $this->cards;
    }

    /**
     * 重置牌组
     */
    public function reset(bool $withJokers = true): self
    {
        $this->init($withJokers);
        return $this;
    }

    /**
     * 牌组转数组
     */
    public function toArray(): array
    {
        return array_map(fn(Card $card) => $card->toArray(), $this->cards);
    }
}

