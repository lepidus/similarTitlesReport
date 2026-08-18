<?php

/**
 * @file plugins/generic/similarTitlesReport/classes/SimilarTitlePairFinder.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class SimilarTitlePairFinder
 *
 * @brief Finds submission pairs whose titles reach a minimum similarity percentage.
 */

namespace APP\plugins\generic\similarTitlesReport\classes;

class SimilarTitlePairFinder
{
    public const DEFAULT_THRESHOLD = 70.0;

    public const MAX_SUBMISSIONS = 200;

    public const MAX_PAIRS = 1000;

    private const MAX_COMPARISON_TITLE_LENGTH = 500;

    private bool $submissionsTruncated = false;

    private bool $pairsTruncated = false;

    public function __construct(
        private float $threshold = self::DEFAULT_THRESHOLD,
        private ?TitleSimilarityCache $cache = null
    ) {
    }

    /**
     * @param array<int,array{submissionId:int,title:string,authors:string,submissionUrl:string}> $submissions
     *
     * @return array<int,array{
     *     similarity:float,
     *     first:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     second:array{submissionId:int,title:string,authors:string,submissionUrl:string}
     * }>
     */
    public function findPairs(array $submissions): array
    {
        $this->submissionsTruncated = false;
        $this->pairsTruncated = false;
        $pairs = [];
        $preparedSubmissions = $this->prepareSubmissions($submissions);
        $submissionCount = count($preparedSubmissions);

        for ($firstIndex = 0; $firstIndex < $submissionCount; $firstIndex++) {
            for ($secondIndex = $firstIndex + 1; $secondIndex < $submissionCount; $secondIndex++) {
                if (!$this->canReachThreshold($preparedSubmissions[$firstIndex], $preparedSubmissions[$secondIndex])) {
                    continue;
                }

                $similarity = $this->getSimilarity($preparedSubmissions[$firstIndex], $preparedSubmissions[$secondIndex]);

                if ($similarity < $this->threshold) {
                    continue;
                }

                $this->addPairToTopResults($pairs, [
                    'similarity' => $similarity,
                    'first' => $preparedSubmissions[$firstIndex]['submission'],
                    'second' => $preparedSubmissions[$secondIndex]['submission'],
                ]);
            }
        }

        foreach ($pairs as &$pair) {
            $pair['similarity'] = round($pair['similarity'], 2);
        }
        unset($pair);

        return $pairs;
    }

    public function wereSubmissionsTruncated(): bool
    {
        return $this->submissionsTruncated;
    }

    public function werePairsTruncated(): bool
    {
        return $this->pairsTruncated;
    }

    /**
     * @param array<int,array{submissionId:int,title:string,authors:string,submissionUrl:string}> $submissions
     *
     * @return array<int,array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int
     * }>
     */
    private function prepareSubmissions(array $submissions): array
    {
        $preparedSubmissions = [];

        foreach ($submissions as $submission) {
            $normalizedTitle = $this->normalizeTitle($submission['title']);
            if ($normalizedTitle === '') {
                continue;
            }

            if (count($preparedSubmissions) >= self::MAX_SUBMISSIONS) {
                $this->submissionsTruncated = true;
                break;
            }

            $preparedSubmissions[] = [
                'submission' => $submission,
                'normalizedTitle' => $normalizedTitle,
                'titleHash' => hash('sha256', $normalizedTitle),
                // similar_text() compares bytes, so the conservative length
                // bound must use the same unit.
                'titleLength' => strlen($normalizedTitle),
            ];
        }

        return $preparedSubmissions;
    }

    /**
     * @param array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int
     * } $firstSubmission
     * @param array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int
     * } $secondSubmission
     */
    private function canReachThreshold(array $firstSubmission, array $secondSubmission): bool
    {
        return $this->canReachThresholdByLength(
            $firstSubmission['titleLength'],
            $secondSubmission['titleLength']
        );
    }

    private function canReachThresholdByLength(int $firstLength, int $secondLength): bool
    {
        $totalLength = $firstLength + $secondLength;
        if ($totalLength === 0) {
            return false;
        }

        return (min($firstLength, $secondLength) * 200 / $totalLength) >= $this->threshold;
    }

    /**
     * @param array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int
     * } $firstSubmission
     * @param array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int
     * } $secondSubmission
     */
    private function getSimilarity(array $firstSubmission, array $secondSubmission): float
    {
        $cachedSimilarity = $this->cache?->get(
            $firstSubmission['submission']['submissionId'],
            $firstSubmission['titleHash'],
            $secondSubmission['submission']['submissionId'],
            $secondSubmission['titleHash']
        );

        if ($cachedSimilarity !== null) {
            return $cachedSimilarity;
        }

        similar_text($firstSubmission['normalizedTitle'], $secondSubmission['normalizedTitle'], $similarity);
        $this->cache?->store(
            $firstSubmission['submission']['submissionId'],
            $firstSubmission['titleHash'],
            $secondSubmission['submission']['submissionId'],
            $secondSubmission['titleHash'],
            $similarity
        );

        return $similarity;
    }

    private function normalizeTitle(string $title): string
    {
        $title = html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = mb_strtolower($title, 'UTF-8');
        $title = preg_replace('/\s+/u', ' ', trim($title)) ?? '';
        return mb_substr($title, 0, self::MAX_COMPARISON_TITLE_LENGTH, 'UTF-8');
    }

    /**
     * @param array<int,array{
     *     similarity:float,
     *     first:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     second:array{submissionId:int,title:string,authors:string,submissionUrl:string}
     * }> $pairs
     */
    private function addPairToTopResults(array &$pairs, array $pair): void
    {
        if (count($pairs) >= self::MAX_PAIRS) {
            $this->pairsTruncated = true;
            if ($this->comparePairs($pair, $pairs[self::MAX_PAIRS - 1]) >= 0) {
                return;
            }

            array_pop($pairs);
        }

        $minimumIndex = 0;
        $maximumIndex = count($pairs);
        while ($minimumIndex < $maximumIndex) {
            $middleIndex = intdiv($minimumIndex + $maximumIndex, 2);
            if ($this->comparePairs($pair, $pairs[$middleIndex]) < 0) {
                $maximumIndex = $middleIndex;
            } else {
                $minimumIndex = $middleIndex + 1;
            }
        }

        array_splice($pairs, $minimumIndex, 0, [$pair]);
    }

    /**
     * Compare pairs in report order: exact similarity descending, then IDs ascending.
     */
    private function comparePairs(array $firstPair, array $secondPair): int
    {
        return [$secondPair['similarity'], $firstPair['first']['submissionId'], $firstPair['second']['submissionId']]
            <=> [$firstPair['similarity'], $secondPair['first']['submissionId'], $secondPair['second']['submissionId']];
    }
}
