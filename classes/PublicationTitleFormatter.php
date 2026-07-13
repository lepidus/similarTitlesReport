<?php

/**
 * @file plugins/generic/similarTitlesReport/classes/PublicationTitleFormatter.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class PublicationTitleFormatter
 *
 * @brief Formats publication titles following OJS presentation rules.
 */

namespace APP\plugins\generic\similarTitlesReport\classes;

class PublicationTitleFormatter
{
    /**
     * Mirrors the text format used by PKPPublication::getLocalizedFullTitle().
     */
    public function getFullTitle(string $title, string $subtitle = '', string $prefix = ''): string
    {
        $title = $this->normalizeText($title);
        $subtitle = $this->normalizeText($subtitle);
        $prefix = $this->normalizeText($prefix);

        if ($prefix !== '') {
            $title = trim($prefix . ' ' . $title);
        }

        if ($title === '') {
            return $subtitle;
        }

        if ($subtitle === '') {
            return $title;
        }

        return $this->concatTitleFields([$title, $subtitle]);
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    }

    /**
     * Mirrors PKPString::concatTitleFields().
     *
     * @param string[] $fields
     */
    private function concatTitleFields(array $fields): string
    {
        $avoidColonChars = ['?', '!', '/', '&'];

        if (in_array(substr($fields[0], -1, 1), $avoidColonChars)) {
            return join(' ', $fields);
        }

        return join(': ', $fields);
    }
}
