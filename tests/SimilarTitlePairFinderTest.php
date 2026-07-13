<?php

/**
 * @file plugins/generic/similarTitlesReport/tests/SimilarTitlePairFinderTest.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 */

namespace APP\plugins\generic\similarTitlesReport\tests;

use APP\plugins\generic\similarTitlesReport\classes\SimilarTitlePairFinder;
use APP\plugins\generic\similarTitlesReport\classes\TitleSimilarityCache;
use PHPUnit\Framework\TestCase;

class SimilarTitlePairFinderTest extends TestCase
{
    public function testShouldFindPairsWithAtLeastSeventyPercentTitleSimilarity(): void
    {
        $pairs = (new SimilarTitlePairFinder())->findPairs([
            $this->submission(1, 'Análise de dados em repositórios digitais'),
            $this->submission(2, 'Analise de dados em repositórios digitais'),
            $this->submission(3, 'Políticas de preservação em acervos físicos'),
        ]);

        $this->assertCount(1, $pairs);
        $this->assertSame(1, $pairs[0]['first']['submissionId']);
        $this->assertSame(2, $pairs[0]['second']['submissionId']);
        $this->assertGreaterThanOrEqual(70.0, $pairs[0]['similarity']);
    }

    public function testShouldNormalizeCaseTagsEntitiesAndWhitespaceBeforeComparing(): void
    {
        $pairs = (new SimilarTitlePairFinder())->findPairs([
            $this->submission(1, '<strong>Gestão&nbsp;da informação</strong> em arquivos'),
            $this->submission(2, 'gestão da informação em arquivos'),
        ]);

        $this->assertCount(1, $pairs);
        $this->assertSame(100.0, $pairs[0]['similarity']);
    }

    public function testShouldNotDiscardCandidateTitlesThatOnlyDifferByAccents(): void
    {
        $pairs = (new SimilarTitlePairFinder())->findPairs([
            $this->submission(1, 'pediátrica cardiológica'),
            $this->submission(2, 'pediatrica cardiologica'),
        ]);

        $this->assertCount(1, $pairs);
        $this->assertGreaterThanOrEqual(70.0, $pairs[0]['similarity']);
    }

    public function testShouldNotDiscardCandidateTitlesWithSmallTyposInAllTerms(): void
    {
        $pairs = (new SimilarTitlePairFinder())->findPairs([
            $this->submission(1, 'pediatrica cardiologica'),
            $this->submission(2, 'pediatricca cardiologca'),
        ]);

        $this->assertCount(1, $pairs);
        $this->assertGreaterThanOrEqual(70.0, $pairs[0]['similarity']);
    }

    public function testShouldIgnoreBlankTitles(): void
    {
        $pairs = (new SimilarTitlePairFinder())->findPairs([
            $this->submission(1, ''),
            $this->submission(2, 'Título válido'),
        ]);

        $this->assertSame([], $pairs);
    }

    public function testShouldReuseCachedSimilarityForSameTitleHashes(): void
    {
        $cache = new InMemoryTitleSimilarityCache();
        $finder = new SimilarTitlePairFinder(cache: $cache);
        $submissions = [
            $this->submission(1, 'Análise de dados em repositórios digitais'),
            $this->submission(2, 'Analise de dados em repositórios digitais'),
        ];

        $finder->findPairs($submissions);
        $finder->findPairs($submissions);

        $this->assertSame(1, $cache->stores);
        $this->assertSame(2, $cache->gets);
    }

    public function testShouldSkipClearlyDifferentTitlesBeforeUsingCacheOrSimilarText(): void
    {
        $cache = new InMemoryTitleSimilarityCache();
        $submissions = [];

        for ($index = 1; $index <= 200; $index++) {
            $submissions[] = $this->submission(
                $index,
                sprintf('Termo%03d Unico%03d Especifico%03d', $index, $index, $index)
            );
        }

        $pairs = (new SimilarTitlePairFinder(cache: $cache))->findPairs($submissions);

        $this->assertSame([], $pairs);
        $this->assertSame(0, $cache->gets);
        $this->assertSame(0, $cache->stores);
    }

    public function testShouldStillCompareCandidateTitlesAfterPrefiltering(): void
    {
        $cache = new InMemoryTitleSimilarityCache();
        $pairs = (new SimilarTitlePairFinder(cache: $cache))->findPairs([
            $this->submission(1, 'Preservação digital em repositórios institucionais'),
            $this->submission(2, 'Preservacao digital em repositórios institucionais'),
        ]);

        $this->assertCount(1, $pairs);
        $this->assertSame(1, $cache->gets);
        $this->assertSame(1, $cache->stores);
    }

    /**
     * @return array{submissionId:int,title:string,authors:string,submissionUrl:string}
     */
    private function submission(int $submissionId, string $title): array
    {
        return [
            'submissionId' => $submissionId,
            'title' => $title,
            'authors' => 'Autor de Teste',
            'submissionUrl' => 'https://example.test/submission/' . $submissionId,
        ];
    }
}

class InMemoryTitleSimilarityCache implements TitleSimilarityCache
{
    /** @var array<string,float> */
    private array $items = [];

    public int $gets = 0;

    public int $stores = 0;

    public function get(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash
    ): ?float {
        $this->gets++;
        return $this->items[$this->getKey(
            $firstSubmissionId,
            $firstTitleHash,
            $secondSubmissionId,
            $secondTitleHash
        )] ?? null;
    }

    public function store(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash,
        float $similarity
    ): void {
        $this->stores++;
        $this->items[$this->getKey($firstSubmissionId, $firstTitleHash, $secondSubmissionId, $secondTitleHash)]
            = $similarity;
    }

    private function getKey(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash
    ): string {
        if ($firstSubmissionId > $secondSubmissionId) {
            [$firstSubmissionId, $secondSubmissionId] = [$secondSubmissionId, $firstSubmissionId];
            [$firstTitleHash, $secondTitleHash] = [$secondTitleHash, $firstTitleHash];
        }

        return $firstSubmissionId . ':' . $firstTitleHash . ':' . $secondSubmissionId . ':' . $secondTitleHash;
    }
}
