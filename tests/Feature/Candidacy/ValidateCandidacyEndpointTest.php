<?php

namespace Tests\Feature\Candidacy;

use App\Infrastructure\Persistence\CandidacyMapper;
use App\Infrastructure\Persistence\Eloquent\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use App\Infrastructure\Persistence\EloquentCandidacyRepository;
use Candidacy\Domain\Candidacy;
use Candidacy\Domain\CvText;
use Candidacy\Domain\Email;
use Candidacy\Domain\YearsOfExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidateCandidacyEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_a_candidacy_that_passes_the_chain(): void
    {
        $candidacyId = $this->createReceivedCandidacy(
            yearsOfExperience: 4,
            cvText: str_repeat('Experienced backend engineer. ', 5),
            email: 'jane.candidate@example.com',
        );

        $response = $this->postJson("/api/v1/candidacies/{$candidacyId}/validate");

        $response->assertOk();
        $response->assertJsonPath('isValid', true);
        $response->assertJsonPath('failed', []);
        $this->assertNotEmpty($response->json('passed'));

        $this->assertDatabaseHas(CandidacyModel::class, [
            'id' => $candidacyId,
            'status' => 'validated',
        ]);

        $this->assertDatabaseHas(ActivityLogModel::class, [
            'candidacy_id' => $candidacyId,
            'action' => 'candidacy_validated',
        ]);

        $entry = ActivityLogModel::query()->where('candidacy_id', $candidacyId)
            ->where('action', 'candidacy_validated')
            ->firstOrFail();

        $this->assertSame('validated', $entry->payload['outcome']);
        $this->assertSame([], $entry->payload['reasons']);
    }

    public function test_it_rejects_a_candidacy_that_fails_the_chain(): void
    {
        $candidacyId = $this->createReceivedCandidacy(
            yearsOfExperience: 0,
            cvText: 'N/A',
            email: 'jane.candidate@example.com',
        );

        $response = $this->postJson("/api/v1/candidacies/{$candidacyId}/validate");

        $response->assertOk();
        $response->assertJsonPath('isValid', false);
        $this->assertNotEmpty($response->json('failed'));

        $this->assertDatabaseHas(CandidacyModel::class, [
            'id' => $candidacyId,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas(ActivityLogModel::class, [
            'candidacy_id' => $candidacyId,
            'action' => 'candidacy_rejected',
        ]);

        $entry = ActivityLogModel::query()->where('candidacy_id', $candidacyId)
            ->where('action', 'candidacy_rejected')
            ->firstOrFail();

        $this->assertSame('rejected', $entry->payload['outcome']);
        $this->assertNotEmpty($entry->payload['reasons']);
    }

    public function test_it_returns_409_when_validating_a_candidacy_a_second_time(): void
    {
        $candidacyId = $this->createReceivedCandidacy(
            yearsOfExperience: 4,
            cvText: str_repeat('Experienced backend engineer. ', 5),
            email: 'jane.candidate@example.com',
        );

        $this->postJson("/api/v1/candidacies/{$candidacyId}/validate")->assertOk();

        $response = $this->postJson("/api/v1/candidacies/{$candidacyId}/validate");

        $response->assertStatus(409);
    }

    public function test_it_returns_404_for_an_unknown_candidacy(): void
    {
        $response = $this->postJson('/api/v1/candidacies/missing-id/validate');

        $response->assertStatus(404);
    }

    private function createReceivedCandidacy(int $yearsOfExperience, string $cvText, string $email): string
    {
        $repository = new EloquentCandidacyRepository(new CandidacyMapper());

        $candidacy = Candidacy::register(
            $repository->nextIdentity(),
            'Jane Candidate',
            new Email($email),
            new YearsOfExperience($yearsOfExperience),
            new CvText($cvText),
        );

        $repository->save($candidacy);

        return $candidacy->id();
    }
}
