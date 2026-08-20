<?php

/**
 * @file plugins/generic/similarTitlesReport/tests/PluginIntegrationTest.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 */

namespace APP\plugins\generic\similarTitlesReport\tests;

use APP\plugins\generic\similarTitlesReport\pages\similarTitlesReport\SimilarTitlesReportHandler;
use APP\plugins\generic\similarTitlesReport\SimilarTitlesReportPlugin;
use PHPUnit\Framework\TestCase;

class PluginIntegrationTest extends TestCase
{
    public function testShouldInstallReportHandlerForItsPublicPage(): void
    {
        $page = 'similarTitlesReport';
        $handler = null;
        $params = [&$page, null, null, &$handler];

        $handled = (new SimilarTitlesReportPlugin())->setupSimilarTitlesReportHandler('LoadHandler', $params);

        $this->assertTrue($handled);
        $this->assertInstanceOf(SimilarTitlesReportHandler::class, $handler);
    }

    public function testShouldIgnoreOtherPagesWithoutChangingTheirHandler(): void
    {
        $page = 'dashboard';
        $handler = new \stdClass();
        $originalHandler = $handler;
        $params = [&$page, null, null, &$handler];

        $handled = (new SimilarTitlesReportPlugin())->setupSimilarTitlesReportHandler('LoadHandler', $params);

        $this->assertFalse($handled);
        $this->assertSame($originalHandler, $handler);
    }
}
