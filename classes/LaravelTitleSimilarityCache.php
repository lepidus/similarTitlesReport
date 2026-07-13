<?php

/**
 * @file plugins/generic/similarTitlesReport/classes/LaravelTitleSimilarityCache.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class LaravelTitleSimilarityCache
 *
 * @brief Stores title similarity calculations in the OJS application cache.
 */

namespace APP\plugins\generic\similarTitlesReport\classes;

use Illuminate\Support\Facades\Cache;

class LaravelTitleSimilarityCache implements TitleSimilarityCache
{
    private const TTL_SECONDS = 2592000;

    public function get(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash
    ): ?float {
        $cachedSimilarity = Cache::get($this->getCacheKey(
            $firstSubmissionId,
            $firstTitleHash,
            $secondSubmissionId,
            $secondTitleHash
        ));

        return is_numeric($cachedSimilarity) ? (float) $cachedSimilarity : null;
    }

    public function store(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash,
        float $similarity
    ): void {
        Cache::put(
            $this->getCacheKey($firstSubmissionId, $firstTitleHash, $secondSubmissionId, $secondTitleHash),
            round($similarity, 2),
            self::TTL_SECONDS
        );
    }

    private function getCacheKey(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash
    ): string {
        if ($firstSubmissionId > $secondSubmissionId) {
            [$firstSubmissionId, $secondSubmissionId] = [$secondSubmissionId, $firstSubmissionId];
            [$firstTitleHash, $secondTitleHash] = [$secondTitleHash, $firstTitleHash];
        }

        return sprintf(
            'similarTitlesReport:titleSimilarity:%d:%s:%d:%s',
            $firstSubmissionId,
            $firstTitleHash,
            $secondSubmissionId,
            $secondTitleHash
        );
    }
}
