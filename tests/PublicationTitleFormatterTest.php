<?php

/**
 * @file plugins/generic/similarTitlesReport/tests/PublicationTitleFormatterTest.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 */

namespace APP\plugins\generic\similarTitlesReport\tests;

use APP\plugins\generic\similarTitlesReport\classes\PublicationTitleFormatter;
use PHPUnit\Framework\TestCase;

class PublicationTitleFormatterTest extends TestCase
{
    public function testShouldIncludeSubtitleWithOjsTitleSeparator(): void
    {
        $title = (new PublicationTitleFormatter())->getFullTitle(
            'Gestão da informação',
            'Práticas em repositórios digitais'
        );

        $this->assertSame('Gestão da informação: Práticas em repositórios digitais', $title);
    }

    public function testShouldUseSpaceBeforeSubtitleWhenOjsAvoidsColon(): void
    {
        $title = (new PublicationTitleFormatter())->getFullTitle(
            'Gestão da informação?',
            'Práticas em repositórios digitais'
        );

        $this->assertSame('Gestão da informação? Práticas em repositórios digitais', $title);
    }

    public function testShouldIncludePrefixAndStripHtmlLikeOjsTextTitles(): void
    {
        $title = (new PublicationTitleFormatter())->getFullTitle(
            '<strong>Gestão&nbsp;da informação</strong>',
            '<em>Práticas&nbsp;em repositórios</em>',
            'Dossiê'
        );

        $this->assertSame('Dossiê Gestão da informação: Práticas em repositórios', $title);
    }
}
