<?php

/**
 * @file plugins/generic/similarTitlesReport/tests/SimilarTitlesDataProviderTest.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 */

namespace APP\plugins\generic\similarTitlesReport\tests;

use APP\journal\Journal;
use APP\plugins\generic\similarTitlesReport\classes\SimilarTitlesDataProvider;
use PHPUnit\Framework\TestCase;

class SimilarTitlesDataProviderTest extends TestCase
{
    public function testShouldAcceptOjsJournalContextFromRequest(): void
    {
        $provider = new SimilarTitlesDataProvider(new Journal(), null);

        $this->assertInstanceOf(SimilarTitlesDataProvider::class, $provider);
    }
}
