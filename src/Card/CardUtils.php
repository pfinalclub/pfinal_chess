<?php

declare(strict_types=1);

namespace PfinalChess\Card;

/**
 * 扑克牌工具类 - 斗地主规则
 */
class CardUtils
{
    // 牌型常量
    public const TYPE_INVALID = 'invalid';           // 无效
    public const TYPE_SINGLE = 'single';             // 单牌
    public const TYPE_PAIR = 'pair';                 // 对子
    public const TYPE_TRIPLE = 'triple';             // 三不带
    public const TYPE_TRIPLE_ONE = 'triple_one';     // 三带一
    public const TYPE_TRIPLE_PAIR = 'triple_pair';   // 三带二
    public const TYPE_STRAIGHT = 'straight';         // 顺子
    public const TYPE_STRAIGHT_PAIR = 'straight_pair'; // 连对
    public const TYPE_PLANE = 'plane';               // 飞机
    public const TYPE_PLANE_SINGLE = 'plane_single'; // 飞机带单
    public const TYPE_PLANE_PAIR = 'plane_pair';     // 飞机带对
    public const TYPE_FOUR_TWO = 'four_two';         // 四带二
    public const TYPE_BOMB = 'bomb';                 // 炸弹
    public const TYPE_ROCKET = 'rocket';             // 王炸

    /**
     * 对牌按权重排序
     * 
     * @param Card[] $cards
     * @return Card[]
     */
    public static function sortCards(array $cards): array
    {
        usort($cards, fn(Card $a, Card $b) => $a->getWeight() - $b->getWeight());
        return $cards;
    }

    /**
     * 按权重分组统计
     * 
     * @param Card[] $cards
     * @return array [weight => count]
     */
    public static function groupByWeight(array $cards): array
    {
        $groups = [];
        foreach ($cards as $card) {
            $weight = $card->getWeight();
            $groups[$weight] = ($groups[$weight] ?? 0) + 1;
        }
        ksort($groups);
        return $groups;
    }

    /**
     * 分析牌型
     * 
     * @param Card[] $cards
     * @return array ['type' => string, 'weight' => int]
     */
    public static function analyzeType(array $cards): array
    {
        $count = count($cards);
        
        if ($count === 0) {
            return ['type' => self::TYPE_INVALID, 'weight' => 0];
        }

        $cards = self::sortCards($cards);
        $groups = self::groupByWeight($cards);
        $weights = array_keys($groups);
        $counts = array_values($groups);

        // 王炸
        if ($count === 2 && self::isRocket($cards)) {
            return ['type' => self::TYPE_ROCKET, 'weight' => 100];
        }

        // 炸弹
        if ($count === 4 && count($groups) === 1 && reset($counts) === 4) {
            return ['type' => self::TYPE_BOMB, 'weight' => reset($weights)];
        }

        // 单牌
        if ($count === 1) {
            return ['type' => self::TYPE_SINGLE, 'weight' => $cards[0]->getWeight()];
        }

        // 对子
        if ($count === 2 && count($groups) === 1) {
            return ['type' => self::TYPE_PAIR, 'weight' => reset($weights)];
        }

        // 三不带
        if ($count === 3 && count($groups) === 1 && reset($counts) === 3) {
            return ['type' => self::TYPE_TRIPLE, 'weight' => reset($weights)];
        }

        // 三带一
        if ($count === 4) {
            $tripleWeight = self::findTripleWeight($groups);
            if ($tripleWeight > 0) {
                return ['type' => self::TYPE_TRIPLE_ONE, 'weight' => $tripleWeight];
            }
        }

        // 三带二
        if ($count === 5) {
            $tripleWeight = self::findTripleWeight($groups);
            $pairCount = count(array_filter($counts, fn($c) => $c === 2));
            if ($tripleWeight > 0 && $pairCount === 1) {
                return ['type' => self::TYPE_TRIPLE_PAIR, 'weight' => $tripleWeight];
            }
        }

        // 顺子（至少5张，最大到A）
        if ($count >= 5 && self::isStraight($weights) && max($weights) <= 14) {
            return ['type' => self::TYPE_STRAIGHT, 'weight' => min($weights), 'length' => $count];
        }

        // 连对（至少3对，最大到A）
        if ($count >= 6 && $count % 2 === 0) {
            $allPairs = array_filter($counts, fn($c) => $c === 2);
            if (count($allPairs) === count($groups) && self::isStraight($weights) && max($weights) <= 14) {
                return ['type' => self::TYPE_STRAIGHT_PAIR, 'weight' => min($weights), 'length' => $count / 2];
            }
        }

        // 飞机不带
        if ($count >= 6 && $count % 3 === 0) {
            $triples = array_keys(array_filter($groups, fn($c) => $c === 3));
            if (count($triples) === $count / 3 && self::isStraight($triples) && max($triples) <= 14) {
                return ['type' => self::TYPE_PLANE, 'weight' => min($triples), 'length' => count($triples)];
            }
        }

        // 四带二（可以带两单或两对）
        if ($count === 6 || $count === 8) {
            $fourWeight = self::findFourWeight($groups);
            if ($fourWeight > 0) {
                return ['type' => self::TYPE_FOUR_TWO, 'weight' => $fourWeight];
            }
        }

        return ['type' => self::TYPE_INVALID, 'weight' => 0];
    }

    /**
     * 检查是否王炸
     * 
     * @param Card[] $cards
     */
    private static function isRocket(array $cards): bool
    {
        if (count($cards) !== 2) {
            return false;
        }
        $hasBig = false;
        $hasSmall = false;
        foreach ($cards as $card) {
            if ($card->isBigJoker()) $hasBig = true;
            if ($card->isSmallJoker()) $hasSmall = true;
        }
        return $hasBig && $hasSmall;
    }

    /**
     * 检查是否连续
     */
    private static function isStraight(array $weights): bool
    {
        if (count($weights) < 2) {
            return true;
        }
        sort($weights);
        for ($i = 1; $i < count($weights); $i++) {
            if ($weights[$i] - $weights[$i - 1] !== 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * 查找三张的权重
     */
    private static function findTripleWeight(array $groups): int
    {
        foreach ($groups as $weight => $count) {
            if ($count === 3) {
                return $weight;
            }
        }
        return 0;
    }

    /**
     * 查找四张的权重
     */
    private static function findFourWeight(array $groups): int
    {
        foreach ($groups as $weight => $count) {
            if ($count === 4) {
                return $weight;
            }
        }
        return 0;
    }

    /**
     * 比较两手牌的大小
     * 
     * @param Card[] $cards1 出的牌
     * @param Card[] $cards2 要压的牌
     * @return bool cards1 是否能压过 cards2
     */
    public static function canBeat(array $cards1, array $cards2): bool
    {
        $type1 = self::analyzeType($cards1);
        $type2 = self::analyzeType($cards2);

        // 无效牌型不能出
        if ($type1['type'] === self::TYPE_INVALID) {
            return false;
        }

        // 王炸最大
        if ($type1['type'] === self::TYPE_ROCKET) {
            return true;
        }
        if ($type2['type'] === self::TYPE_ROCKET) {
            return false;
        }

        // 炸弹可以压非炸弹
        if ($type1['type'] === self::TYPE_BOMB) {
            if ($type2['type'] !== self::TYPE_BOMB) {
                return true;
            }
            // 炸弹比大小
            return $type1['weight'] > $type2['weight'];
        }

        // 同类型比较
        if ($type1['type'] !== $type2['type']) {
            return false;
        }

        // 顺子/连对需要相同长度
        if (in_array($type1['type'], [self::TYPE_STRAIGHT, self::TYPE_STRAIGHT_PAIR, self::TYPE_PLANE])) {
            if (($type1['length'] ?? 0) !== ($type2['length'] ?? 0)) {
                return false;
            }
        }

        // 牌数相同，比较权重
        if (count($cards1) !== count($cards2)) {
            return false;
        }

        return $type1['weight'] > $type2['weight'];
    }

    /**
     * 将牌数组转为数组格式
     * 
     * @param Card[] $cards
     */
    public static function cardsToArray(array $cards): array
    {
        return array_map(fn(Card $card) => $card->toArray(), $cards);
    }

    /**
     * 从ID数组还原牌
     * 
     * @param string[] $ids
     * @return Card[]
     */
    public static function cardsFromIds(array $ids): array
    {
        return array_map(fn(string $id) => Card::fromId($id), $ids);
    }
}

