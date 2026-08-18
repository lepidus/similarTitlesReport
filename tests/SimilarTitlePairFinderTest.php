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

    public function testShouldNotDiscardGloballySimilarTitlesWhenNoIndividualTermReachesEightyFivePercent(): void
    {
        $pairs = (new SimilarTitlePairFinder())->findPairs([
            $this->submission(1, 'abcde fghij klmno pqrst'),
            $this->submission(2, 'abcdx fghix klmnx pqrsx'),
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

    public function testShouldEvaluateEqualLengthTitlesWithoutLossyTermPrefilter(): void
    {
        $cache = new CountingTitleSimilarityCache();
        $submissions = [];

        for ($index = 1; $index <= 20; $index++) {
            $submissions[] = $this->submission(
                $index,
                sprintf('Termo%03d Unico%03d Especifico%03d', $index, $index, $index)
            );
        }

        $pairs = (new SimilarTitlePairFinder(cache: $cache))->findPairs($submissions);

        $this->assertCount(190, $pairs);
        $this->assertSame(190, $cache->gets);
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

    public function testShouldBoundInputComparisonsAndReturnedPairs(): void
    {
        $cache = new CountingTitleSimilarityCache();
        $submissions = [];

        for ($submissionId = 1; $submissionId <= 250; $submissionId++) {
            $submissions[] = $this->submission($submissionId, 'Identical title for bounded comparison');
        }

        $finder = new SimilarTitlePairFinder(cache: $cache);
        $pairs = $finder->findPairs($submissions);

        $this->assertCount(SimilarTitlePairFinder::MAX_PAIRS, $pairs);
        $this->assertLessThanOrEqual(19900, $cache->gets);
        $this->assertTrue($finder->wereSubmissionsTruncated());
        $this->assertTrue($finder->werePairsTruncated());
    }

    public function testShouldKeepTheMostSimilarPairsWhenTheResultBufferIsTrimmed(): void
    {
        $submissions = [];
        for ($submissionId = 1; $submissionId <= 63; $submissionId++) {
            $submissions[] = $this->submission($submissionId, sprintf('Distinct title number %03d', $submissionId));
        }
        $submissions[] = $this->submission(64, 'Most similar title');
        $submissions[] = $this->submission(65, 'Most similar title');

        $finder = new SimilarTitlePairFinder(threshold: 0.0);
        $pairs = $finder->findPairs($submissions);

        $this->assertCount(SimilarTitlePairFinder::MAX_PAIRS, $pairs);
        $this->assertSame(100.0, $pairs[0]['similarity']);
        $this->assertSame(64, $pairs[0]['first']['submissionId']);
        $this->assertSame(65, $pairs[0]['second']['submissionId']);
        $this->assertTrue($finder->werePairsTruncated());
    }

    public function testShouldRankPairsByExactSimilarityBeforeRounding(): void
    {
        $submissions = [];
        for ($submissionId = 1; $submissionId <= 46; $submissionId++) {
            $submissions[] = $this->submission($submissionId, sprintf('Candidate title %03d', $submissionId));
        }

        $finder = new SimilarTitlePairFinder(
            threshold: 0.0,
            cache: new ExactRankingTitleSimilarityCache()
        );
        $pairs = $finder->findPairs($submissions);

        $this->assertCount(SimilarTitlePairFinder::MAX_PAIRS, $pairs);
        $this->assertSame(45, $pairs[0]['first']['submissionId']);
        $this->assertSame(46, $pairs[0]['second']['submissionId']);
        $this->assertSame(90.0, $pairs[0]['similarity']);
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

class CountingTitleSimilarityCache implements TitleSimilarityCache
{
    public int $gets = 0;

    public function get(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash
    ): ?float {
        $this->gets++;
        return null;
    }

    public function store(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash,
        float $similarity
    ): void {
    }
}

class ExactRankingTitleSimilarityCache implements TitleSimilarityCache
{
    public function get(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash
    ): ?float {
        return $firstSubmissionId === 45 && $secondSubmissionId === 46 ? 90.004 : 90.001;
    }

    public function store(
        int $firstSubmissionId,
        string $firstTitleHash,
        int $secondSubmissionId,
        string $secondTitleHash,
        float $similarity
    ): void {
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
