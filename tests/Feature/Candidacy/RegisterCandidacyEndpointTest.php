<?php

namespace Tests\Feature\Candidacy;

use App\Infrastructure\Persistence\Eloquent\ActivityLogModel;
use App\Infrastructure\Persistence\Eloquent\CandidacyModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterCandidacyEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_valid_candidacy_and_logs_the_event(): void
    {
        $response = $this->postJson('/api/v1/candidacies', [
            'full_name' => 'Jane Candidate',
            'email' => 'jane.candidate@example.com',
            'years_of_experience' => 4,
            'cv_text' => str_repeat('Experienced backend engineer. ', 5),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.full_name', 'Jane Candidate');
        $response->assertJsonPath('data.status', 'received');
        $response->assertJsonPath('data.evaluator_id', null);

        $id = $response->json('data.id');

        $this->assertDatabaseHas(CandidacyModel::class, [
            'id' => $id,
            'email' => 'jane.candidate@example.com',
            'status' => 'received',
        ]);

        $this->assertDatabaseHas(ActivityLogModel::class, [
            'candidacy_id' => $id,
            'action' => 'candidacy_registered',
        ]);
    }

    public function test_it_rejects_missing_required_fields_with_http_level_validation(): void
    {
        $response = $this->postJson('/api/v1/candidacies', [
            'email' => 'jane.candidate@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['full_name', 'years_of_experience', 'cv_text']);
    }

    public function test_it_rejects_input_that_fails_domain_validation_rules(): void
    {
        $response = $this->postJson('/api/v1/candidacies', [
            'full_name' => 'Jane Candidate',
            'email' => 'jane.candidate@mailinator.com',
            'years_of_experience' => 0,
            'cv_text' => 'Too short.',
        ]);

        $response->assertStatus(422);
        $this->assertNotEmpty($response->json('errors.candidacy'));

        $this->assertDatabaseCount(CandidacyModel::class, 0);
    }
}
