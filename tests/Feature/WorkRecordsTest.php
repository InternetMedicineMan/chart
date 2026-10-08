<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\LocalDate;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()]);
        config(['chart.owner_id' => $this->owner->id]);
        app(WorkSetup::class)->initialize($this->owner);
        $this->actingAs($this->owner);
    }

    private function domain(string $slug = 'writing'): Domain
    {
        return Domain::forUser($this->owner)->where('slug', $slug)->firstOrFail();
    }

    private function project(array $attributes = []): Project
    {
        return Project::create($attributes + ['user_id' => $this->owner->id, 'domain_id' => $this->domain()->id, 'name' => 'Publish essay', 'slug' => fake()->uuid(), 'type' => 'target_date', 'lifecycle' => 'active']);
    }

    private function task(array $attributes = []): Task
    {
        return Task::create($attributes + ['user_id' => $this->owner->id, 'domain_id' => $this->domain()->id, 'title' => 'Draft introduction']);
    }

    public function test_setup_is_repeatable_and_preserves_edits_and_removed_domains(): void
    {
        $this->domain()->update(['name' => 'Essays', 'cadence_days' => 10]);
        $this->domain('family')->delete();
        $this->artisan('chart:setup')->assertSuccessful();
        $this->assertSame(7, Domain::withTrashed()->forUser($this->owner)->count());
        $this->assertSame('Essays', $this->domain()->name);
        $this->assertNotNull(Domain::withTrashed()->where('slug', 'family')->first()->deleted_at);
        $this->assertSame('America/Chicago', app(LocalDate::class)->timezone($this->owner));
        $this->assertSame('personal', $this->domain('ministry-church')->sphere->value);
    }

    public function test_task_defaults_to_inbox_and_client_cannot_supply_owner_or_completion(): void
    {
        $this->post('/tasks', ['title' => 'Call plumber', 'user_id' => 999, 'completed_at' => now(), 'source' => 'email'])->assertSessionHasNoErrors();
        $task = Task::firstOrFail();
        $this->assertSame($this->domain('inbox')->id, $task->domain_id);
        $this->assertSame($this->owner->id, $task->user_id);
        $this->assertSame(4, $task->priority);
        $this->assertSame('manual', $task->source->value);
        $this->assertNull($task->completed_at);
    }

    public function test_project_determines_task_domain_and_moving_project_moves_all_its_tasks(): void
    {
        $project = $this->project();
        $this->post('/tasks', ['title' => 'Outline', 'project_id' => $project->id, 'domain_id' => $this->domain('inbox')->id])->assertSessionHasNoErrors();
        $task = Task::firstOrFail();
        $this->assertSame($project->domain_id, $task->domain_id);
        $deleted = $this->task(['project_id' => $project->id]);
        $deleted->delete();
        $destination = $this->domain('ministry-church');
        $this->put('/projects/'.$project->id, ['name' => $project->name, 'domain_id' => $destination->id, 'type' => 'target_date', 'lifecycle' => 'active', 'quiet_enabled' => true])->assertSessionHasNoErrors();
        $this->assertSame($destination->id, $task->fresh()->domain_id);
        $this->assertSame($destination->id, $deleted->fresh()->domain_id);
        $this->post('/tasks/'.$deleted->id.'/restore')->assertSessionHasNoErrors();
        $this->assertSame($destination->id, $deleted->fresh()->domain_id);
    }

    public function test_task_can_move_out_of_a_project_complete_reopen_and_recover(): void
    {
        $project = $this->project();
        $task = $this->task(['project_id' => $project->id]);
        $this->put('/tasks/'.$task->id, ['title' => 'Revised task', 'project_id' => null, 'domain_id' => $this->domain('family')->id])->assertSessionHasNoErrors();
        $this->assertNull($task->fresh()->project_id);
        $this->assertSame($this->domain('family')->id, $task->fresh()->domain_id);
        $this->patch('/tasks/'.$task->id.'/completion', ['completed' => true])->assertSessionHasNoErrors();
        $completedAt = $task->fresh()->completed_at;
        $touchedAt = $this->domain('family')->last_touched_at;
        $this->travel(1)->hours();
        $this->patch('/tasks/'.$task->id.'/completion', ['completed' => true]);
        $this->assertTrue($completedAt->equalTo($task->fresh()->completed_at));
        $this->assertTrue($touchedAt->equalTo($this->domain('family')->last_touched_at));
        $this->patch('/tasks/'.$task->id.'/completion', ['completed' => false]);
        $this->assertNull($task->fresh()->completed_at);
        $this->delete('/tasks/'.$task->id);
        $this->assertSoftDeleted($task);
        $this->post('/tasks/'.$task->id.'/restore');
        $this->assertNotSoftDeleted($task);
    }

    public function test_task_validation_preserves_dates_and_rejects_invalid_values(): void
    {
        $this->post('/tasks', ['title' => 'Appointment', 'due_time' => '15:30'])->assertSessionHasErrors('due_date');
        $this->post('/tasks', ['title' => 'Appointment', 'priority' => 5, 'due_date' => '2026-02-30'])->assertSessionHasErrors(['priority', 'due_date']);
        $this->assertDatabaseCount('tasks', 0);
        $this->post('/tasks', ['title' => 'Appointment', 'due_date' => '2026-10-08', 'due_time' => '15:30'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-08', Task::first()->toArray()['due_date']);
        $this->get('/bench')->assertOk();
    }

    public function test_inbox_cannot_be_deleted_or_reconfigured_and_occupied_domain_cannot_be_deleted(): void
    {
        $inbox = $this->domain('inbox');
        $this->delete('/domains/'.$inbox->id)->assertSessionHasErrors('domain');
        $this->put('/domains/'.$inbox->id, ['name' => 'No inbox', 'sphere' => 'work', 'quiet_enabled' => false, 'parked' => true])->assertUnprocessable();
        $this->assertTrue($inbox->fresh()->is_inbox);
        $this->assertFalse($inbox->fresh()->parked);
        $task = $this->task();
        $task->delete();
        $this->delete('/domains/'.$this->domain()->id)->assertSessionHasErrors('domain');
        $this->assertNotSoftDeleted($this->domain());
    }

    public function test_owner_cannot_read_or_modify_another_users_work_or_assign_foreign_parents(): void
    {
        $other = User::factory()->create();
        app(WorkSetup::class)->initialize($other);
        $domain = Domain::forUser($other)->where('slug', 'writing')->first();
        $project = $this->project(['user_id' => $other->id, 'domain_id' => $domain->id, 'name' => 'Private project']);
        $task = $this->task(['user_id' => $other->id, 'domain_id' => $domain->id, 'project_id' => $project->id]);
        $idea = Note::create(['user_id' => $other->id, 'body' => 'Private thought']);
        $this->get('/projects/'.$project->id)->assertNotFound();
        $this->put('/tasks/'.$task->id, ['title' => 'Changed'])->assertNotFound();
        $this->patch('/tasks/'.$task->id.'/completion', ['completed' => true])->assertNotFound();
        $this->delete('/tasks/'.$task->id)->assertNotFound();
        $this->delete('/domains/'.$domain->id)->assertNotFound();
        $this->put('/ideas/'.$idea->id, ['body' => 'Changed'])->assertNotFound();
        $this->patch('/ideas/'.$idea->id.'/review')->assertNotFound();
        $this->post('/tasks', ['title' => 'Foreign domain', 'domain_id' => $domain->id])->assertSessionHasErrors('domain_id');
        $this->post('/tasks', ['title' => 'Foreign project', 'project_id' => $project->id])->assertSessionHasErrors('project_id');
        $this->get('/bench')->assertInertia(fn (Assert $page) => $page->has('tasks.data', 0)->has('projects.data', 0)->has('options.domains', 7)->has('options.projects', 0));
        $this->get('/ideas')->assertInertia(fn (Assert $page) => $page->has('ideas.data', 0));
    }

    public function test_new_pages_and_writes_require_owner_and_two_factor(): void
    {
        $this->owner->forceFill(['two_factor_confirmed_at' => null])->save();
        foreach (['/bench', '/intake', '/ideas', '/settings/work', '/more'] as $path) {
            $this->get($path)->assertRedirect('/user/profile');
        }
        $this->post('/tasks', ['title' => 'Blocked'])->assertRedirect('/user/profile');
        $this->assertDatabaseCount('tasks', 0);
        $this->post('/logout');
        $this->get('/bench')->assertRedirect('/login');
        $this->post('/ideas', ['body' => 'Blocked'])->assertRedirect('/login');
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_intake_only_shows_open_inbox_tasks_and_lists_are_paginated(): void
    {
        $this->task();
        $this->task(['domain_id' => $this->domain('inbox')->id, 'completed_at' => now()]);
        for ($index = 0; $index < 23; $index++) {
            $this->task(['domain_id' => $this->domain('inbox')->id]);
        }
        $this->get('/intake')->assertInertia(fn (Assert $page) => $page->where('tasks.total', 23)->has('tasks.data', 20));
        $this->get('/intake?page=2')->assertInertia(fn (Assert $page) => $page->has('tasks.data', 3));
        $this->get('/bench?status=completed')->assertInertia(fn (Assert $page) => $page->has('tasks.data', 1));
    }

    public function test_dashboard_caps_due_work_and_excludes_someday_done_and_parked_work(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 9)->setTime(2, 0));
        $this->task(['due_date' => '2026-10-09']);
        foreach (['someday', 'parked', 'done', 'dropped'] as $lifecycle) {
            $project = $this->project(['lifecycle' => $lifecycle]);
            $this->task(['project_id' => $project->id, 'due_date' => '2026-10-01']);
        }
        $this->domain('family')->update(['parked' => true]);
        $this->task(['domain_id' => $this->domain('family')->id, 'due_date' => '2026-10-01']);
        for ($i = 0; $i < 9; $i++) {
            $this->task(['due_date' => '2026-10-08']);
        }
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('options.today', '2026-10-08')->where('dueCount', 9)->has('dueTasks', 7));
    }

    public function test_ideas_stay_separate_from_tasks_and_can_be_edited_and_reviewed(): void
    {
        $this->post('/ideas', ['body' => 'A memory verse game', 'user_id' => 999])->assertSessionHasNoErrors();
        $idea = Note::firstOrFail();
        $this->assertSame($this->owner->id, $idea->user_id);
        $this->assertDatabaseCount('tasks', 0);
        $this->put('/ideas/'.$idea->id, ['body' => 'A card game for memory verses']);
        $this->patch('/ideas/'.$idea->id.'/review');
        $this->assertNotNull($idea->fresh()->reviewed_at);
        $this->assertSame('A card game for memory verses', $idea->fresh()->body);
        $this->project(['lifecycle' => 'someday']);
        $this->get('/ideas')->assertInertia(fn (Assert $page) => $page->has('someday.data', 1));
        $this->get('/bench')->assertInertia(fn (Assert $page) => $page->has('projects.data', 0));
    }

    public function test_local_dates_handle_evenings_and_both_daylight_saving_transitions(): void
    {
        $dates = app(LocalDate::class);
        $this->assertSame('2026-10-07', $dates->date('2026-10-08 02:00:00', 'America/Chicago'));
        $this->travelTo(CarbonImmutable::parse('2026-03-09 05:30:00', 'UTC'));
        $this->assertSame(1, $dates->daysSince('2026-03-08 06:30:00', 'America/Chicago'));
        $this->travelTo(CarbonImmutable::parse('2026-11-02 06:30:00', 'UTC'));
        $this->assertSame(1, $dates->daysSince('2026-11-01 05:30:00', 'America/Chicago'));
        $this->put('/settings/timezone', ['timezone' => 'Not/AZone'])->assertSessionHasErrors('timezone');
        $this->put('/settings/timezone', ['timezone' => 'America/New_York'])->assertSessionHasNoErrors();
        $this->assertSame('America/New_York', $dates->timezone($this->owner));
        $this->assertSame(1, AppSetting::forUser($this->owner)->where('key', 'timezone')->count());
    }
}
