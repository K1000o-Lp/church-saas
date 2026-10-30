<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_custom_fields_has_a_gin_index(): void
    {
        $index = DB::selectOne(
            "SELECT indexdef FROM pg_indexes WHERE schemaname = 'public' AND indexname = 'people_custom_fields_gin'"
        );

        $this->assertNotNull($index);
        $this->assertStringContainsString('USING gin', $index->indexdef);
    }

    public function test_contribution_amount_must_be_non_negative(): void
    {
        $churchId = $this->createChurch('amount-check');
        $this->createCurrency($churchId);

        $this->expectException(QueryException::class);

        $this->insertContribution($churchId, -1);
    }

    public function test_contribution_currency_must_belong_to_the_church(): void
    {
        $churchId = $this->createChurch('currency-check');

        $this->expectException(QueryException::class);

        $this->insertContribution($churchId, 100, 'EUR');
    }

    public function test_a_church_can_have_only_one_default_currency(): void
    {
        $churchId = $this->createChurch('default-currency');
        $this->createCurrency($churchId, 'USD', true);

        $this->expectException(QueryException::class);

        $this->createCurrency($churchId, 'EUR', true);
    }

    public function test_event_registration_guest_count_must_be_non_negative(): void
    {
        $churchId = $this->createChurch('guest-count');
        $eventId = $this->createEvent($churchId);

        $this->expectException(QueryException::class);

        DB::table('event_registrations')->insert([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'guest_name' => 'Guest',
            'guest_count' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_event_registration_requires_a_person_or_guest_name(): void
    {
        $churchId = $this->createChurch('registration-identity');
        $eventId = $this->createEvent($churchId);

        $this->expectException(QueryException::class);

        DB::table('event_registrations')->insert([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_attendance_requires_a_person_or_registration(): void
    {
        $churchId = $this->createChurch('attendance-identity');
        $eventId = $this->createEvent($churchId);

        $this->expectException(QueryException::class);

        DB::table('attendance_records')->insert([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_attendance_person_must_match_the_registration_person(): void
    {
        $churchId = $this->createChurch('attendance-mismatch');
        $eventId = $this->createEvent($churchId);
        $juan = $this->createPerson($churchId, 'Juan');
        $maria = $this->createPerson($churchId, 'Maria');

        $registrationId = DB::table('event_registrations')->insertGetId([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'person_id' => $juan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('attendance_records')->insert([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'event_registration_id' => $registrationId,
            'person_id' => $maria,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_attendance_can_be_recorded_without_a_registration(): void
    {
        $churchId = $this->createChurch('attendance');
        $eventId = $this->createEvent($churchId);
        $personId = $this->createPerson($churchId, 'Ana');

        $attendanceId = DB::table('attendance_records')->insertGetId([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'event_registration_id' => null,
            'person_id' => $personId,
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceId,
            'event_registration_id' => null,
        ]);
    }

    public function test_attendance_is_unique_per_person_and_event(): void
    {
        $churchId = $this->createChurch('attendance-duplicate');
        $eventId = $this->createEvent($churchId);
        $personId = $this->createPerson($churchId, 'Ana');

        DB::table('attendance_records')->insert([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'person_id' => $personId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('attendance_records')->insert([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'person_id' => $personId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_event_time_range_cannot_be_inverted(): void
    {
        $churchId = $this->createChurch('event-range');

        $this->expectException(QueryException::class);

        DB::table('events')->insert([
            'church_id' => $churchId,
            'title' => 'Inverted',
            'starts_at' => now(),
            'ends_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_service_plan_time_range_cannot_be_inverted(): void
    {
        $churchId = $this->createChurch('service-range');

        $this->expectException(QueryException::class);

        DB::table('service_plans')->insert([
            'church_id' => $churchId,
            'title' => 'Inverted',
            'starts_at' => now(),
            'ends_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_pipeline_stage_position_must_be_non_negative(): void
    {
        $churchId = $this->createChurch('stage-position');

        $this->expectException(QueryException::class);

        DB::table('pipeline_stages')->insert([
            'church_id' => $churchId,
            'name' => 'Stage',
            'position' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_service_item_position_must_be_non_negative(): void
    {
        $churchId = $this->createChurch('item-position');
        $planId = $this->createServicePlan($churchId);

        $this->expectException(QueryException::class);

        DB::table('service_items')->insert([
            'church_id' => $churchId,
            'service_plan_id' => $planId,
            'title' => 'Item',
            'position' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_workflow_version_must_be_positive(): void
    {
        [$churchId, $workflowId] = $this->createWorkflow('version-zero');

        $this->expectException(QueryException::class);

        DB::table('workflow_versions')->insert([
            'church_id' => $churchId,
            'workflow_id' => $workflowId,
            'version' => 0,
            'definition' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_workflow_node_attempts_must_be_non_negative(): void
    {
        [$churchId, $workflowId] = $this->createWorkflow('attempts-negative');
        $versionId = $this->createWorkflowVersion($churchId, $workflowId, 1);
        $runId = $this->createWorkflowRun($churchId, $workflowId, $versionId);

        $this->expectException(QueryException::class);

        DB::table('workflow_node_executions')->insert([
            'church_id' => $churchId,
            'workflow_run_id' => $runId,
            'node_id' => 'start',
            'attempts' => -1,
            'idempotency_key' => 'node-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_workflow_versions_cannot_be_updated(): void
    {
        [$churchId, $workflowId] = $this->createWorkflow('immutable-update');
        $versionId = $this->createWorkflowVersion($churchId, $workflowId, 1);

        $this->expectException(QueryException::class);

        DB::table('workflow_versions')->where('id', $versionId)->update(['version' => 2]);
    }

    public function test_workflow_versions_are_removed_when_the_church_is_deleted(): void
    {
        [$churchId] = $this->createWorkflow('immutable-cascade');

        DB::table('churches')->where('id', $churchId)->delete();

        $this->assertSame(0, DB::table('workflow_versions')->where('church_id', $churchId)->count());
        $this->assertSame(0, DB::table('workflows')->where('church_id', $churchId)->count());
    }

    public function test_event_segment_cannot_pair_records_from_different_churches(): void
    {
        $churchA = $this->createChurch('pair-a');
        $churchB = $this->createChurch('pair-b');
        $eventId = $this->createEvent($churchA);

        $segmentId = DB::table('segments')->insertGetId([
            'church_id' => $churchB,
            'name' => 'Segment B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('event_segments')->insert([
            'church_id' => $churchB,
            'event_id' => $eventId,
            'segment_id' => $segmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_follow_up_cannot_be_assigned_to_a_non_member(): void
    {
        $churchId = $this->createChurch('followup-assignee');
        $userId = $this->createUser('outsider@example.com');

        $this->expectException(QueryException::class);

        DB::table('pastoral_follow_ups')->insert([
            'church_id' => $churchId,
            'assigned_to' => $userId,
            'title' => 'Visit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_church_deletion_cascades_assignments_and_memberships(): void
    {
        $churchId = $this->createChurch('member-cascade');
        $userId = $this->createUser('member@example.com');
        $this->createMembership($churchId, $userId);

        DB::table('pastoral_follow_ups')->insert([
            'church_id' => $churchId,
            'assigned_to' => $userId,
            'title' => 'Visit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('churches')->where('id', $churchId)->delete();

        $this->assertSame(0, DB::table('pastoral_follow_ups')->where('church_id', $churchId)->count());
        $this->assertSame(0, DB::table('church_memberships')->where('church_id', $churchId)->count());
    }

    public function test_fund_names_are_unique_per_church(): void
    {
        $churchId = $this->createChurch('fund-names');

        DB::table('funds')->insert([
            'church_id' => $churchId,
            'name' => 'Missions',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('funds')->insert([
            'church_id' => $churchId,
            'name' => 'Missions',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_invalid_event_status_is_rejected(): void
    {
        $churchId = $this->createChurch('event-status');

        $this->expectException(QueryException::class);

        DB::table('events')->insert([
            'church_id' => $churchId,
            'title' => 'Invalid',
            'status' => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_church_settings_must_be_a_json_object(): void
    {
        $this->expectException(QueryException::class);

        DB::table('churches')->insert([
            'name' => 'Invalid settings',
            'slug' => 'invalid-settings',
            'settings' => DB::raw("'[]'::jsonb"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_workflow_definition_must_be_a_json_object(): void
    {
        [$churchId, $workflowId] = $this->createWorkflow('definition-array');

        $this->expectException(QueryException::class);

        DB::table('workflow_versions')->insert([
            'church_id' => $churchId,
            'workflow_id' => $workflowId,
            'version' => 1,
            'definition' => DB::raw("'[]'::jsonb"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_segment_type_must_be_valid(): void
    {
        $churchId = $this->createChurch('segment-type-check');

        $this->expectException(QueryException::class);

        DB::table('segments')->insert([
            'church_id' => $churchId,
            'name' => 'Invalid type',
            'type' => 'other',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_dynamic_segments_require_rules_and_static_segments_cannot_have_them(): void
    {
        $churchId = $this->createChurch('segment-rules-check');

        $this->expectException(QueryException::class);

        DB::table('segments')->insert([
            'church_id' => $churchId,
            'name' => 'Dynamic without rules',
            'type' => 'dynamic',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_static_segments_cannot_have_rules(): void
    {
        $churchId = $this->createChurch('segment-static-rules-check');

        $this->expectException(QueryException::class);

        DB::table('segments')->insert([
            'church_id' => $churchId,
            'name' => 'Static with rules',
            'type' => 'static',
            'rules' => DB::raw("'{}'::jsonb"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_segment_members_cannot_pair_records_from_different_churches(): void
    {
        $churchA = $this->createChurch('segment-member-a');
        $churchB = $this->createChurch('segment-member-b');
        $personA = $this->createPerson($churchA, 'Ana');
        $segmentB = $this->createSegment($churchB, 'Segment B');

        $this->expectException(QueryException::class);

        DB::table('segment_members')->insert([
            'church_id' => $churchB,
            'segment_id' => $segmentB,
            'person_id' => $personA,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_segment_members_and_event_segments_cascade_when_a_segment_is_deleted(): void
    {
        $churchId = $this->createChurch('segment-cascade');
        $personId = $this->createPerson($churchId, 'Ana');
        $eventId = $this->createEvent($churchId);
        $segmentId = $this->createSegment($churchId, 'Cascade segment');

        DB::table('segment_members')->insert([
            'church_id' => $churchId,
            'segment_id' => $segmentId,
            'person_id' => $personId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('event_segments')->insert([
            'church_id' => $churchId,
            'event_id' => $eventId,
            'segment_id' => $segmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('segments')->where('id', $segmentId)->delete();

        $this->assertSame(0, DB::table('segment_members')->where('segment_id', $segmentId)->count());
        $this->assertSame(0, DB::table('event_segments')->where('segment_id', $segmentId)->count());
    }

    private function createSegment(int $churchId, string $name, string $type = 'static'): int
    {
        return DB::table('segments')->insertGetId([
            'church_id' => $churchId,
            'name' => $name,
            'type' => $type,
            'rules' => $type === 'dynamic' ? DB::raw("'{}'::jsonb") : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createChurch(string $slug): int
    {
        return DB::table('churches')->insertGetId([
            'name' => $slug,
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCurrency(int $churchId, string $code = 'USD', bool $isDefault = true): void
    {
        DB::table('church_currencies')->insert([
            'church_id' => $churchId,
            'currency_code' => $code,
            'is_default' => $isDefault,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createEvent(int $churchId, string $title = 'Event'): int
    {
        return DB::table('events')->insertGetId([
            'church_id' => $churchId,
            'title' => $title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPerson(int $churchId, string $firstName): int
    {
        return DB::table('people')->insertGetId([
            'church_id' => $churchId,
            'first_name' => $firstName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createServicePlan(int $churchId, string $title = 'Service'): int
    {
        return DB::table('service_plans')->insertGetId([
            'church_id' => $churchId,
            'title' => $title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(string $email): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Member',
            'email' => $email,
            'password' => 'secret',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMembership(int $churchId, int $userId): void
    {
        DB::table('church_memberships')->insert([
            'church_id' => $churchId,
            'user_id' => $userId,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWorkflow(string $slug): array
    {
        $churchId = $this->createChurch($slug);

        $workflowId = DB::table('workflows')->insertGetId([
            'church_id' => $churchId,
            'name' => 'Workflow',
            'trigger_type' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$churchId, $workflowId];
    }

    private function createWorkflowVersion(int $churchId, int $workflowId, int $version): int
    {
        return DB::table('workflow_versions')->insertGetId([
            'church_id' => $churchId,
            'workflow_id' => $workflowId,
            'version' => $version,
            'definition' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWorkflowRun(int $churchId, int $workflowId, int $versionId): int
    {
        return DB::table('workflow_runs')->insertGetId([
            'church_id' => $churchId,
            'workflow_id' => $workflowId,
            'workflow_version_id' => $versionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertContribution(int $churchId, int $amount, string $currency = 'USD'): void
    {
        DB::table('contributions')->insert([
            'church_id' => $churchId,
            'amount' => $amount,
            'currency_code' => $currency,
            'method' => 'cash',
            'contributed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
