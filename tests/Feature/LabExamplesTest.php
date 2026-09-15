<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\RabbitRsExamples\Jobs\DelayedReportJob;
use Modules\RabbitRsExamples\Jobs\FlakyJob;
use Modules\RabbitRsExamples\Jobs\ProcessOrderJob;
use Tests\TestCase;

class LabExamplesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/lab/examples')->assertRedirect(route('login'));
    }

    public function test_examples_page_renders_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/lab/examples')
            ->assertOk()
            // Module pages resolve via the JS-side glob (module prefix stripped),
            // so the PHP-side file-existence check does not apply.
            ->assertInertia(fn ($page) => $page->component('FrontLab/Examples', false));
    }

    public function test_rabbit_rs_endpoint_dispatches_the_selected_example(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/lab/examples/rabbit-rs', ['example' => 'dispatch', 'count' => 3])
            ->assertRedirect();

        Queue::assertPushed(ProcessOrderJob::class, 9); // count 3 × 3 queues — per-queue semantics, as in examples:rabbit-rs:dispatch

        $this->actingAs($user)->post('/lab/examples/rabbit-rs', ['example' => 'delay', 'count' => 1]);
        Queue::assertPushed(DelayedReportJob::class, 1);

        $this->actingAs($user)->post('/lab/examples/rabbit-rs', ['example' => 'fail', 'count' => 1]);
        Queue::assertPushed(FlakyJob::class, 1);
    }

    public function test_rabbit_rs_endpoint_validates_the_example_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/lab/examples/rabbit-rs', ['example' => 'nope'])
            ->assertSessionHasErrors('example');
    }

    public function test_sentinel_endpoint_returns_the_snapshot_json(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/lab/examples/sentinel');

        $response->assertOk()->assertJsonStructure(['service', 'master', 'role', 'connection', 'ok']);
    }
}
