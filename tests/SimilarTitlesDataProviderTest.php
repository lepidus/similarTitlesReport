<?php

/**
 * @file plugins/generic/similarTitlesReport/tests/SimilarTitlesDataProviderTest.php
 *
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 */

namespace APP\plugins\generic\similarTitlesReport\tests;

use APP\core\Application;
use APP\journal\Journal;
use APP\plugins\generic\similarTitlesReport\classes\SimilarTitlePairFinder;
use APP\plugins\generic\similarTitlesReport\classes\SimilarTitlesDataProvider;
use Illuminate\Support\Facades\DB;
use PKP\submission\PKPSubmission;
use PKP\tests\DatabaseTestCase;

class SimilarTitlesDataProviderTest extends DatabaseTestCase
{
    /** @var int[] */
    private array $submissionIds = [];

    protected function tearDown(): void
    {
        if ($this->submissionIds !== []) {
            DB::table('submissions')
                ->whereIn('submission_id', $this->submissionIds)
                ->update(['current_publication_id' => null]);
            DB::table('submissions')->whereIn('submission_id', $this->submissionIds)->delete();
        }

        parent::tearDown();
    }

    public function testShouldAcceptOjsJournalContextFromRequest(): void
    {
        $provider = new SimilarTitlesDataProvider(new Journal(), null);

        $this->assertInstanceOf(SimilarTitlesDataProvider::class, $provider);
    }

    public function testShouldReturnCompletedPairsAndExcludeSubmissionsStillInWizard(): void
    {
        $completedTitle = 'Completed duplicate title for database integration';
        $incompleteTitle = 'Incomplete duplicate title excluded from report';
        $firstCompletedId = $this->createSubmission($completedTitle, '', 'Alice Example');
        $secondCompletedId = $this->createSubmission($completedTitle, '', 'Bob Example');
        $firstIncompleteId = $this->createSubmission($incompleteTitle, 'details', 'Carol Example');
        $secondIncompleteId = $this->createSubmission($incompleteTitle, 'files', 'Dan Example');

        $pairs = $this->provider([
            $firstCompletedId,
            $secondCompletedId,
            $firstIncompleteId,
            $secondIncompleteId,
        ])->getPairs();

        $this->assertCount(1, $pairs);
        $this->assertSame(100.0, $pairs[0]['similarity']);
        $this->assertSame($firstCompletedId, $pairs[0]['first']['submissionId']);
        $this->assertSame($secondCompletedId, $pairs[0]['second']['submissionId']);
        $this->assertSame($completedTitle, $pairs[0]['first']['title']);
        $this->assertSame('Alice Example', $pairs[0]['first']['authors']);
        $this->assertSame('Bob Example', $pairs[0]['second']['authors']);
        $this->assertStringContainsString((string) $firstCompletedId, $pairs[0]['first']['submissionUrl']);
        $this->assertStringContainsString((string) $secondCompletedId, $pairs[0]['second']['submissionUrl']);
        $this->assertNotContains($firstIncompleteId, $this->pairSubmissionIds($pairs));
        $this->assertNotContains($secondIncompleteId, $this->pairSubmissionIds($pairs));
    }

    public function testShouldApplyStatusAndAllowedSubmissionFiltersAgainstDatabase(): void
    {
        $title = 'Restricted duplicate title for database integration';
        $firstAllowedId = $this->createSubmission($title, '', 'First Allowed');
        $secondAllowedId = $this->createSubmission($title, '', 'Second Allowed');
        $declinedId = $this->createSubmission($title, '', 'Declined', PKPSubmission::STATUS_DECLINED);
        $notAllowedId = $this->createSubmission($title, '', 'Not Allowed');

        $pairs = $this->provider([$firstAllowedId, $secondAllowedId, $declinedId])->getPairs();

        $this->assertCount(1, $pairs);
        $this->assertSame([$firstAllowedId, $secondAllowedId], $this->pairSubmissionIds($pairs));
        $this->assertNotContains($declinedId, $this->pairSubmissionIds($pairs));
        $this->assertNotContains($notAllowedId, $this->pairSubmissionIds($pairs));
    }

    /**
     * @param ?int[] $allowedSubmissionIds
     */
    private function provider(?array $allowedSubmissionIds = null): SimilarTitlesDataProvider
    {
        $context = Application::getContextDAO()->getById(1);
        $this->assertInstanceOf(Journal::class, $context);

        return new SimilarTitlesDataProvider(
            $context,
            $allowedSubmissionIds,
            new SimilarTitlePairFinder()
        );
    }

    private function createSubmission(
        string $title,
        string $submissionProgress,
        string $authorName,
        int $status = PKPSubmission::STATUS_QUEUED
    ): int {
        $submissionId = (int) DB::table('submissions')->insertGetId([
            'context_id' => 1,
            'current_publication_id' => null,
            'stage_id' => 1,
            'locale' => 'en',
            'status' => $status,
            'submission_progress' => $submissionProgress,
        ], 'submission_id');
        $this->submissionIds[] = $submissionId;

        $publicationId = (int) DB::table('publications')->insertGetId([
            'submission_id' => $submissionId,
            'status' => $status,
        ], 'publication_id');
        DB::table('submissions')
            ->where('submission_id', $submissionId)
            ->update(['current_publication_id' => $publicationId]);
        DB::table('publication_settings')->insert([
            'publication_id' => $publicationId,
            'locale' => 'en',
            'setting_name' => 'title',
            'setting_value' => $title,
        ]);

        $authorId = (int) DB::table('authors')->insertGetId([
            'email' => sprintf('author-%d@example.test', $submissionId),
            'publication_id' => $publicationId,
            'seq' => 0,
        ], 'author_id');
        DB::table('author_settings')->insert([
            'author_id' => $authorId,
            'locale' => 'en',
            'setting_name' => 'preferredPublicName',
            'setting_value' => $authorName,
        ]);

        return $submissionId;
    }

    /**
     * @param array<int,array{first:array{submissionId:int},second:array{submissionId:int}}> $pairs
     *
     * @return int[]
     */
    private function pairSubmissionIds(array $pairs): array
    {
        $submissionIds = [];
        foreach ($pairs as $pair) {
            $submissionIds[] = $pair['first']['submissionId'];
            $submissionIds[] = $pair['second']['submissionId'];
        }

        sort($submissionIds);
        return $submissionIds;
    }
}
