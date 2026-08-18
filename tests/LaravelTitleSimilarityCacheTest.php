<?php

/**
 * @file plugins/generic/similarTitlesReport/tests/LaravelTitleSimilarityCacheTest.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 */

namespace APP\plugins\generic\similarTitlesReport\tests;

use APP\plugins\generic\similarTitlesReport\classes\LaravelTitleSimilarityCache;
use Illuminate\Support\Facades\Cache;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class LaravelTitleSimilarityCacheTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private object $cacheManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheManager = app('cache');
    }

    protected function tearDown(): void
    {
        app()->instance('cache', $this->cacheManager);
        Cache::clearResolvedInstance('cache');
        parent::tearDown();
    }

    public function testShouldStoreExactSimilarityUnderVersionedKey(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->with(
                'similarTitlesReport:titleSimilarity:v2:10:first-hash:20:second-hash',
                90.004,
                2592000
            );

        (new LaravelTitleSimilarityCache())->store(
            20,
            'second-hash',
            10,
            'first-hash',
            90.004
        );

        $this->addToAssertionCount(1);
    }
}
