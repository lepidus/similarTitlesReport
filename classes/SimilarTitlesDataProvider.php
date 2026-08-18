<?php

/**
 * @file plugins/generic/similarTitlesReport/classes/SimilarTitlesDataProvider.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class SimilarTitlesDataProvider
 *
 * @brief Builds similar-title submission records from OJS submissions and authors.
 */

namespace APP\plugins\generic\similarTitlesReport\classes;

use APP\facades\Repo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\submission\PKPSubmission;

class SimilarTitlesDataProvider
{
    private SimilarTitlePairFinder $pairFinder;

    private PublicationTitleFormatter $titleFormatter;

    private bool $submissionsTruncated = false;

    /**
     * @param ?int[] $allowedSubmissionIds Null means unrestricted inside the current context.
     */
    public function __construct(
        private Context $context,
        private ?array $allowedSubmissionIds,
        ?SimilarTitlePairFinder $pairFinder = null
    ) {
        $this->pairFinder = $pairFinder ?? new SimilarTitlePairFinder(
            cache: new LaravelTitleSimilarityCache()
        );
        $this->titleFormatter = new PublicationTitleFormatter();
    }

    /**
     * @return array<int,array{
     *     similarity:float,
     *     first:array{submissionId:int,title:string,authors:string,submissionUrl:string},
     *     second:array{submissionId:int,title:string,authors:string,submissionUrl:string}
     * }>
     */
    public function getPairs(): array
    {
        $submissions = $this->getSubmissions();
        return $this->pairFinder->findPairs($submissions);
    }

    public function wereSubmissionsTruncated(): bool
    {
        return $this->submissionsTruncated || $this->pairFinder->wereSubmissionsTruncated();
    }

    public function werePairsTruncated(): bool
    {
        return $this->pairFinder->werePairsTruncated();
    }

    /**
     * @return array<int,array{submissionId:int,title:string,authors:string,submissionUrl:string}>
     */
    private function getSubmissions(): array
    {
        $this->submissionsTruncated = false;
        $contextId = (int) $this->context->getId();
        $rows = $this->getBaseRows($contextId);
        if ($rows->isEmpty()) {
            return [];
        }

        $localePrecedence = $this->getLocalePrecedence($contextId);
        $publicationIds = $rows->pluck('publication_id')->unique()->all();
        $authorRows = $this->getAuthorRows($publicationIds);
        $publicationTitles = $this->getSettingsByOwner(
            'publication_settings',
            'publication_id',
            $publicationIds,
            ['prefix', 'subtitle', 'title'],
            $localePrecedence
        );
        $authorSettings = $this->getSettingsByOwner(
            'author_settings',
            'author_id',
            $authorRows->pluck('author_id')->unique()->all(),
            ['givenName', 'familyName', 'preferredPublicName'],
            $localePrecedence
        );
        $authorsByPublication = $this->getAuthorsByPublication($authorRows, $authorSettings);
        $submissions = [];

        foreach ($rows as $row) {
            $publicationId = (int) $row->publication_id;
            $submissionId = (int) $row->submission_id;
            $titleSettings = $publicationTitles[$publicationId] ?? [];
            $title = $this->titleFormatter->getFullTitle(
                $titleSettings['title'] ?? '',
                $titleSettings['subtitle'] ?? '',
                $titleSettings['prefix'] ?? ''
            );

            if ($title === '') {
                continue;
            }

            $submissions[] = [
                'submissionId' => $submissionId,
                'title' => $title,
                'authors' => $authorsByPublication[$publicationId] ?? '',
                'submissionUrl' => Repo::submission()->getUrlEditorialWorkflow($this->context, $submissionId),
            ];
        }

        return $submissions;
    }

    /**
     * @return Collection<int,object>
     */
    private function getBaseRows(int $contextId): Collection
    {
        $rows = DB::table('submissions as s')
            ->join('publications as p', 's.current_publication_id', '=', 'p.publication_id')
            ->where('s.context_id', '=', $contextId)
            ->where('s.status', '=', PKPSubmission::STATUS_QUEUED)
            ->where('s.submission_progress', '=', '')
            ->when(
                $this->allowedSubmissionIds !== null,
                fn ($query) => $query->whereIn('s.submission_id', $this->allowedSubmissionIds)
            )
            ->orderByDesc('s.submission_id')
            ->limit(SimilarTitlePairFinder::MAX_SUBMISSIONS + 1)
            ->get(['s.submission_id', 'p.publication_id']);

        if ($rows->count() > SimilarTitlePairFinder::MAX_SUBMISSIONS) {
            $this->submissionsTruncated = true;
            $rows = $rows->take(SimilarTitlePairFinder::MAX_SUBMISSIONS);
        }

        return $rows->sortBy('submission_id')->values();
    }

    /**
     * @param int[] $publicationIds
     *
     * @return Collection<int,object>
     */
    private function getAuthorRows(array $publicationIds): Collection
    {
        if (empty($publicationIds)) {
            return collect();
        }

        return DB::table('authors')
            ->whereIn('publication_id', array_values(array_unique(array_map('intval', $publicationIds))))
            ->orderBy('publication_id')
            ->orderBy('seq')
            ->get(['publication_id', 'author_id']);
    }

    /**
     * @param Collection<int,object> $authorRows
     * @param array<int,array<string,string>> $authorSettings
     *
     * @return array<int,string>
     */
    private function getAuthorsByPublication(Collection $authorRows, array $authorSettings): array
    {
        $authorsByPublication = [];
        foreach ($authorRows as $row) {
            $publicationId = (int) $row->publication_id;
            $authorId = (int) $row->author_id;
            $authorName = $this->getAuthorName($authorSettings[$authorId] ?? []);

            if ($authorName === '') {
                continue;
            }

            $authorsByPublication[$publicationId] ??= [];
            $authorsByPublication[$publicationId][] = $authorName;
        }

        return array_map(fn (array $authors) => implode('; ', $authors), $authorsByPublication);
    }

    /**
     * @param int[] $ownerIds
     * @param string[] $settingNames
     * @param string[] $localePrecedence
     *
     * @return array<int,array<string,string>>
     */
    private function getSettingsByOwner(
        string $table,
        string $ownerColumn,
        array $ownerIds,
        array $settingNames,
        array $localePrecedence
    ): array {
        if (empty($ownerIds)) {
            return [];
        }

        $rows = DB::table($table)
            ->whereIn($ownerColumn, array_values(array_unique(array_map('intval', $ownerIds))))
            ->whereIn('setting_name', $settingNames)
            ->get([$ownerColumn, 'locale', 'setting_name', 'setting_value']);

        $settings = [];
        foreach ($rows as $row) {
            $ownerId = (int) $row->{$ownerColumn};
            $settings[$ownerId][$row->setting_name][(string) $row->locale] = (string) $row->setting_value;
        }

        foreach ($settings as $ownerId => $ownerSettings) {
            foreach ($ownerSettings as $settingName => $localizedValues) {
                $settings[$ownerId][$settingName] = $this->selectLocalizedValue(
                    $localizedValues,
                    $localePrecedence
                );
            }
        }

        return $settings;
    }

    /**
     * @return string[]
     */
    private function getLocalePrecedence(int $contextId): array
    {
        $primaryLocale = (string) DB::table('journals')
            ->where('journal_id', '=', $contextId)
            ->value('primary_locale');

        return array_values(array_unique(array_filter([
            Locale::getLocale(),
            $primaryLocale,
            '',
            'en',
            'pt_BR',
        ])));
    }

    /**
     * @param array<string,string> $localizedValues
     * @param string[] $localePrecedence
     */
    private function selectLocalizedValue(array $localizedValues, array $localePrecedence): string
    {
        foreach ($localePrecedence as $locale) {
            if (isset($localizedValues[$locale]) && trim($localizedValues[$locale]) !== '') {
                return $localizedValues[$locale];
            }
        }

        foreach ($localizedValues as $value) {
            if (trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string,string> $settings
     */
    private function getAuthorName(array $settings): string
    {
        if (!empty($settings['preferredPublicName'])) {
            return $this->normalizeText($settings['preferredPublicName']);
        }

        return $this->normalizeText(trim(($settings['givenName'] ?? '') . ' ' . ($settings['familyName'] ?? '')));
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    }
}
