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

    private const MINIMUM_TERM_OVERLAP = 0.5;

    private const MINIMUM_TERM_SIMILARITY = 85.0;

    private const MAX_COMPARISON_TITLE_LENGTH = 500;

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

                $pairs[] = [
                    'similarity' => round($similarity, 2),
                    'first' => $preparedSubmissions[$firstIndex]['submission'],
                    'second' => $preparedSubmissions[$secondIndex]['submission'],
                ];
            }
        }

        usort(
            $pairs,
            fn (array $a, array $b) => [$b['similarity'], $a['first']['submissionId'], $a['second']['submissionId']]
                <=> [$a['similarity'], $b['first']['submissionId'], $b['second']['submissionId']]
        );

        return $pairs;
    }

    /**
     * @param array<int,array{submissionId:int,title:string,authors:string,submissionUrl:string}> $submissions
     *
     * @return array<int,array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int,
     *     terms:array<int,string>
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

            $preparedSubmissions[] = [
                'submission' => $submission,
                'normalizedTitle' => $normalizedTitle,
                'titleHash' => hash('sha256', $normalizedTitle),
                'titleLength' => mb_strlen($normalizedTitle, 'UTF-8'),
                'terms' => $this->extractTerms($normalizedTitle),
            ];
        }

        return $preparedSubmissions;
    }

    /**
     * @param array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int,
     *     terms:array<int,string>
     * } $firstSubmission
     * @param array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int,
     *     terms:array<int,string>
     * } $secondSubmission
     */
    private function canReachThreshold(array $firstSubmission, array $secondSubmission): bool
    {
        if (!$this->canReachThresholdByLength($firstSubmission['titleLength'], $secondSubmission['titleLength'])) {
            return false;
        }

        return $this->hasEnoughTermOverlap($firstSubmission['terms'], $secondSubmission['terms']);
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
     * @param string[] $firstTerms
     * @param string[] $secondTerms
     */
    private function hasEnoughTermOverlap(array $firstTerms, array $secondTerms): bool
    {
        if (empty($firstTerms) || empty($secondTerms)) {
            return true;
        }

        $overlap = $this->countSimilarTerms($firstTerms, $secondTerms);
        return ($overlap / min(count($firstTerms), count($secondTerms))) >= self::MINIMUM_TERM_OVERLAP;
    }

    /**
     * @param string[] $firstTerms
     * @param string[] $secondTerms
     */
    private function countSimilarTerms(array $firstTerms, array $secondTerms): int
    {
        $matchedSecondTermIndexes = [];
        $overlap = 0;

        foreach ($firstTerms as $firstTerm) {
            foreach ($secondTerms as $secondTermIndex => $secondTerm) {
                if (isset($matchedSecondTermIndexes[$secondTermIndex])) {
                    continue;
                }

                if (!$this->termsAreSimilar($firstTerm, $secondTerm)) {
                    continue;
                }

                $matchedSecondTermIndexes[$secondTermIndex] = true;
                $overlap++;
                break;
            }
        }

        return $overlap;
    }

    private function termsAreSimilar(string $firstTerm, string $secondTerm): bool
    {
        if ($firstTerm === $secondTerm) {
            return true;
        }

        if (preg_match('/\d/', $firstTerm . $secondTerm)) {
            return false;
        }

        if (!$this->canReachThresholdByLength(strlen($firstTerm), strlen($secondTerm))) {
            return false;
        }

        similar_text($firstTerm, $secondTerm, $similarity);
        return $similarity >= self::MINIMUM_TERM_SIMILARITY;
    }

    /**
     * @param array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int,
     *     terms:array<int,string>
     * } $firstSubmission
     * @param array{
     *     submission:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     normalizedTitle:string,
     *     titleHash:string,
     *     titleLength:int,
     *     terms:array<int,string>
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
     * @return string[]
     */
    private function extractTerms(string $title): array
    {
        $title = $this->removeDiacritics($title);
        preg_match_all('/[\p{L}\p{N}]{4,}/u', $title, $matches);
        return array_values(array_unique($matches[0]));
    }

    private function removeDiacritics(string $text): string
    {
        $transliteratedText = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return is_string($transliteratedText) ? $transliteratedText : $text;
    }
}
