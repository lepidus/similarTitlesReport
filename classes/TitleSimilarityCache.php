<?php

/**
 * @file plugins/generic/similarTitlesReport/classes/TitleSimilarityCache.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class TitleSimilarityCache
 *
 * @brief Cache contract for title similarity calculations.
 */

namespace APP\plugins\generic\similarTitlesReport\classes;

interface TitleSimilarityCache
{
    public function get(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash
    ): ?float;

    public function store(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash,
        float $similarity
    ): void;
}
