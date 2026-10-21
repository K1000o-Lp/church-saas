<?php

namespace Tests\Feature\Segments;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\CustomFieldDefinition;
use App\Models\Person;
use App\Models\Segment;
use App\Models\SegmentMember;
use App\Models\User;
use App\Support\Segments\SegmentQuery;
use App\Support\Segments\SegmentRuleValidator;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SegmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_segments_screens_render_with_fields_and_types(): void
    {
        $church = $this->createChurch('iglesia-segmentos-ui');
        $admin = $this->createMember($church, 'admin');

        $this->createDefinition($church, 'zona', 'select', [
            ['value' => 'este', 'label' => 'Este'],
            ['value' => 'oeste', 'label' => 'Oeste'],
        ]);

        $this->actingAs($admin)
            ->get(route('segments.index', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('segments/index')
                ->where('canManage', true)
                ->has('segments', 0)
                ->has('types', 2));

        $this->actingAs($admin)
            ->get(route('segments.create', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('segments/create')
                ->has('fields', 7)
                ->where('fields.6.key', 'zona')
                ->where('fields.6.source', 'custom'));
    }

    public function test_an_admin_can_create_a_dynamic_segment_and_preview_it(): void
    {
        $church = $this->createChurch('iglesia-segmento-dinamico');
        $admin = $this->createMember($church, 'admin');

        $this->createDefinition($church, 'zona', 'select', [
            ['value' => 'este', 'label' => 'Este'],
            ['value' => 'oeste', 'label' => 'Oeste'],
        ]);

        $ana = $this->createPerson($church, [
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'custom_fields' => ['zona' => 'este'],
        ]);
        $this->createPerson($church, [
            'first_name' => 'Beto',
            'last_name' => 'Rojas',
            'custom_fields' => ['zona' => 'oeste'],
        ]);
        $this->createPerson($church, [
            'first_name' => 'Carla',
            'last_name' => 'Díaz',
            'status' => 'archived',
            'custom_fields' => ['zona' => 'este'],
        ]);

        $rules = [
            'version' => 1,
            'match' => 'all',
            'conditions' => [
                ['source' => 'custom', 'field' => 'zona', 'operator' => 'is', 'value' => 'este'],
            ],
        ];

        $this->actingAs($admin)
            ->post(route('segments.store', $church), [
                'name' => 'Zona este',
                'description' => 'Personas de la zona este.',
                'type' => 'dynamic',
                'rules' => $rules,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $segment = Segment::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame(Segment::TYPE_DYNAMIC, $segment->type);
        $this->assertEquals($rules, $segment->rules);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'segment.created',
            'subject_id' => $segment->getKey(),
        ]);

        $this->actingAs($admin)
            ->get(route('segments.show', ['church' => $church, 'segment' => $segment->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('segments/show')
                ->where('segment.memberCount', 1)
                ->where('segment.rulesSummary', 'Zona es Este')
                ->has('people', 1)
                ->where('people.0.id', $ana->getKey()));

        $this->actingAs($admin)
            ->get(route('segments.preview', $church).'?rules='.urlencode(json_encode($rules)))
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    public function test_dynamic_segments_support_each_field_type(): void
    {
        $church = $this->createChurch('iglesia-segmentos-tipos');

        $this->createDefinition($church, 'zona', 'select', [
            ['value' => 'este', 'label' => 'Este'],
            ['value' => 'oeste', 'label' => 'Oeste'],
        ]);
        $this->createDefinition($church, 'grupo', 'multiselect', [
            ['value' => 'joven', 'label' => 'Joven'],
            ['value' => 'adulto', 'label' => 'Adulto'],
        ]);
        $this->createDefinition($church, 'edad', 'number');
        $this->createDefinition($church, 'fecha', 'date');
        $this->createDefinition($church, 'activo', 'boolean');

        $this->createPerson($church, [
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'custom_fields' => [
                'zona' => 'este',
                'grupo' => ['joven', 'adulto'],
                'edad' => 25,
                'fecha' => '2026-01-10',
                'activo' => true,
            ],
        ]);
        $this->createPerson($church, [
            'first_name' => 'Beto',
            'last_name' => 'Rojas',
            'status' => 'inactive',
            'custom_fields' => [
                'zona' => 'oeste',
                'grupo' => ['adulto'],
                'edad' => 40,
                'fecha' => '2025-01-10',
                'activo' => false,
            ],
        ]);
        $this->createPerson($church, [
            'first_name' => 'Carla',
            'last_name' => 'Díaz',
            'status' => 'archived',
            'custom_fields' => [
                'zona' => 'este',
                'grupo' => ['joven'],
                'edad' => 30,
                'fecha' => '2026-02-10',
                'activo' => true,
            ],
        ]);

        $this->withChurchContext($church, function () use ($church): void {
            $this->assertCountForRule($church, 'person', 'first_name', 'contains', 'an', 1);
            $this->assertCountForRule($church, 'custom', 'edad', 'greater_than', 30, 1);
            $this->assertCountForRule($church, 'custom', 'fecha', 'after', '2025-06-01', 1);
            $this->assertCountForRule($church, 'custom', 'activo', 'is_true', null, 1);
            $this->assertCountForRule($church, 'custom', 'zona', 'is', 'este', 1);
            $this->assertCountForRule($church, 'custom', 'grupo', 'contains_any', ['joven'], 1);
            $this->assertCountForRule($church, 'custom', 'grupo', 'contains_all', ['joven', 'adulto'], 1);
            $this->assertCountForRule($church, 'person', 'status', 'is', 'inactive', 1);
            $this->assertCountForRule($church, 'person', 'status', 'is_not', 'active', 1);
            $this->assertCountForRule($church, 'person', 'first_name', 'not_equals', 'Ana', 1);
            $this->assertCountForRule($church, 'person', 'first_name', 'not_contains', 'an', 1);
            $this->assertCountForRule($church, 'custom', 'zona', 'is_not', 'este', 1);
            $this->assertCountForRule($church, 'custom', 'edad', 'between', [20, 30], 1);
            $this->assertCountForRule($church, 'custom', 'fecha', 'between', ['2026-01-01', '2026-12-31'], 1);
            $this->assertCountForRule($church, 'person', 'created_at', 'before', now()->addDay()->toDateString(), 2);

            $rules = SegmentRuleValidator::validate([
                'version' => 1,
                'match' => 'any',
                'conditions' => [
                    ['source' => 'person', 'field' => 'first_name', 'operator' => 'contains', 'value' => 'an'],
                    ['source' => 'person', 'field' => 'status', 'operator' => 'is', 'value' => 'inactive'],
                ],
            ], $church);

            $this->assertSame(2, SegmentQuery::count($church, $rules));
        });
    }

    public function test_invalid_rules_and_static_rules_are_rejected(): void
    {
        $church = $this->createChurch('iglesia-segmentos-invalidos');
        $admin = $this->createMember($church, 'admin');

        $this->createDefinition($church, 'edad', 'number');

        $this->actingAs($admin)
            ->post(route('segments.store', $church), [
                'name' => 'Campo desconocido',
                'type' => 'dynamic',
                'rules' => [
                    'version' => 1,
                    'match' => 'all',
                    'conditions' => [
                        ['source' => 'custom', 'field' => 'no_existe', 'operator' => 'is', 'value' => 'x'],
                    ],
                ],
            ])
            ->assertSessionHasErrors('rules');

        $this->actingAs($admin)
            ->post(route('segments.store', $church), [
                'name' => 'Operador inválido',
                'type' => 'dynamic',
                'rules' => [
                    'version' => 1,
                    'match' => 'all',
                    'conditions' => [
                        ['source' => 'custom', 'field' => 'edad', 'operator' => 'contains', 'value' => '20'],
                    ],
                ],
            ])
            ->assertSessionHasErrors('rules');

        $this->actingAs($admin)
            ->post(route('segments.store', $church), [
                'name' => 'Sin condiciones',
                'type' => 'dynamic',
                'rules' => ['version' => 1, 'match' => 'all', 'conditions' => []],
            ])
            ->assertSessionHasErrors('rules');

        $this->actingAs($admin)
            ->post(route('segments.store', $church), [
                'name' => 'Estático con reglas',
                'type' => 'static',
                'rules' => [
                    'version' => 1,
                    'match' => 'all',
                    'conditions' => [
                        ['source' => 'person', 'field' => 'status', 'operator' => 'is', 'value' => 'active'],
                    ],
                ],
            ])
            ->assertSessionHasErrors('rules');

        $this->actingAs($admin)
            ->post(route('segments.store', $church), [
                'name' => 'Duplicado',
                'type' => 'static',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('segments.store', $church), [
                'name' => 'Duplicado',
                'type' => 'static',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_an_admin_can_manage_a_static_segment(): void
    {
        $church = $this->createChurch('iglesia-segmento-estatico');
        $admin = $this->createMember($church, 'admin');

        $ana = $this->createPerson($church, ['first_name' => 'Ana', 'last_name' => 'Pérez']);
        $beto = $this->createPerson($church, ['first_name' => 'Beto', 'last_name' => 'Rojas']);
        $carla = $this->createPerson($church, [
            'first_name' => 'Carla',
            'last_name' => 'Díaz',
            'status' => 'archived',
        ]);

        $this->actingAs($admin)
            ->post(route('segments.store', $church), [
                'name' => 'Grupo manual',
                'type' => 'static',
            ])
            ->assertSessionHasNoErrors();

        $segment = Segment::withoutGlobalScope('church')->firstOrFail();

        foreach ([$ana, $beto, $carla] as $person) {
            $this->actingAs($admin)
                ->post(route('segments.members.store', [
                    'church' => $church,
                    'segment' => $segment->getKey(),
                ]), ['person_id' => $person->getKey()])
                ->assertSessionHasNoErrors();
        }

        // Re-adding the same person is idempotent.
        $this->actingAs($admin)
            ->post(route('segments.members.store', [
                'church' => $church,
                'segment' => $segment->getKey(),
            ]), ['person_id' => $ana->getKey()])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('segment_members', 3);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'segment.members_changed',
        ]);

        $this->actingAs($admin)
            ->get(route('segments.show', ['church' => $church, 'segment' => $segment->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('segments/show')
                ->where('segment.memberCount', 3)
                ->has('people', 3)
                ->has('candidates', 0)
                ->where('segment.type', 'static'));

        $this->actingAs($admin)
            ->delete(route('segments.members.destroy', [
                'church' => $church,
                'segment' => $segment->getKey(),
                'person' => $ana->getKey(),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('segment_members', 2);
        $this->assertDatabaseMissing('segment_members', [
            'segment_id' => $segment->getKey(),
            'person_id' => $ana->getKey(),
        ]);
    }

    public function test_segment_permissions_and_tenancy_are_enforced(): void
    {
        $churchA = $this->createChurch('iglesia-segmentos-a');
        $churchB = $this->createChurch('iglesia-segmentos-b');
        $adminA = $this->createMember($churchA, 'admin');
        $leaderA = $this->createMember($churchA, 'leader');
        $memberA = $this->createMember($churchA, 'member');
        $adminB = $this->createMember($churchB, 'admin');

        $segmentA = $this->withChurchContext($churchA, fn (): Segment => Segment::create([
            'name' => 'Solo A',
            'type' => 'static',
            'is_active' => true,
        ]));

        $personB = $this->createPerson($churchB, ['first_name' => 'Beto', 'last_name' => 'B']);

        $this->actingAs($leaderA)
            ->get(route('segments.index', $churchA))
            ->assertOk();

        $this->actingAs($leaderA)
            ->post(route('segments.store', $churchA), ['name' => 'No permitido', 'type' => 'static'])
            ->assertForbidden();

        $this->actingAs($memberA)
            ->get(route('segments.index', $churchA))
            ->assertForbidden();

        $this->actingAs($adminB)
            ->get(route('segments.show', ['church' => $churchB, 'segment' => $segmentA->getKey()]))
            ->assertNotFound();

        $this->actingAs($adminB)
            ->get(route('segments.index', $churchB))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('segments/index')
                ->has('segments', 0));

        $segmentB = $this->withChurchContext($churchB, fn (): Segment => Segment::create([
            'name' => 'Solo B',
            'type' => 'static',
            'is_active' => true,
        ]));

        $this->actingAs($adminB)
            ->post(route('segments.members.store', [
                'church' => $churchB,
                'segment' => $segmentB->getKey(),
            ]), ['person_id' => $personB->getKey()])
            ->assertSessionHasNoErrors();

        $personA = $this->createPerson($churchA, ['first_name' => 'Ana', 'last_name' => 'A']);

        $this->actingAs($adminB)
            ->post(route('segments.members.store', [
                'church' => $churchB,
                'segment' => $segmentB->getKey(),
            ]), ['person_id' => $personA->getKey()])
            ->assertSessionHasErrors('person_id');
    }

    public function test_dynamic_segments_exclude_archived_people(): void
    {
        $church = $this->createChurch('iglesia-segmentos-archivadas');

        $this->createPerson($church, ['first_name' => 'Ana', 'last_name' => 'Activa']);
        $this->createPerson($church, [
            'first_name' => 'Carla',
            'last_name' => 'Archivada',
            'status' => 'archived',
        ]);

        $this->withChurchContext($church, function () use ($church): void {
            $rules = SegmentRuleValidator::validate([
                'version' => 1,
                'match' => 'all',
                'conditions' => [
                    ['source' => 'person', 'field' => 'status', 'operator' => 'is', 'value' => 'active'],
                ],
            ], $church);

            $this->assertSame(1, SegmentQuery::count($church, $rules));

            $inactiveRules = SegmentRuleValidator::validate([
                'version' => 1,
                'match' => 'all',
                'conditions' => [
                    ['source' => 'person', 'field' => 'status', 'operator' => 'is', 'value' => 'inactive'],
                ],
            ], $church);

            $this->assertSame(0, SegmentQuery::count($church, $inactiveRules));
        });
    }

    public function test_a_segment_can_be_toggled_and_deleted(): void
    {
        $church = $this->createChurch('iglesia-segmentos-toggle');
        $admin = $this->createMember($church, 'admin');

        $segment = $this->withChurchContext($church, fn (): Segment => Segment::create([
            'name' => 'Temporal',
            'type' => 'static',
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->patch(route('segments.toggle', ['church' => $church, 'segment' => $segment->getKey()]))
            ->assertSessionHasNoErrors();

        $fresh = Segment::withoutGlobalScope('church')->findOrFail($segment->getKey());
        $this->assertFalse($fresh->is_active);

        $this->actingAs($admin)
            ->delete(route('segments.destroy', ['church' => $church, 'segment' => $segment->getKey()]))
            ->assertRedirect(route('segments.index', $church));

        $this->assertDatabaseMissing('segments', ['id' => $segment->getKey()]);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'segment.deleted',
        ]);
    }

    public function test_preview_rejects_invalid_rules(): void
    {
        $church = $this->createChurch('iglesia-segmentos-preview');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->get(route('segments.preview', $church).'?rules=no-es-json')
            ->assertStatus(422)
            ->assertJson(['message' => 'Las reglas no son válidas.']);

        $this->actingAs($admin)
            ->get(route('segments.preview', $church).'?rules='.urlencode(json_encode([
                'version' => 1,
                'match' => 'all',
                'conditions' => [
                    ['source' => 'custom', 'field' => 'no_existe', 'operator' => 'is', 'value' => 'x'],
                ],
            ])))
            ->assertStatus(422);
    }

    public function test_the_people_directory_can_be_filtered_by_static_and_dynamic_segments(): void
    {
        $church = $this->createChurch('iglesia-filtro-segmentos');
        $admin = $this->createMember($church, 'admin');

        $this->createDefinition($church, 'zona', 'select', [
            ['value' => 'este', 'label' => 'Este'],
            ['value' => 'oeste', 'label' => 'Oeste'],
        ]);

        $ana = $this->createPerson($church, [
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'custom_fields' => ['zona' => 'este'],
        ]);
        $beto = $this->createPerson($church, [
            'first_name' => 'Beto',
            'last_name' => 'Rojas',
            'custom_fields' => ['zona' => 'oeste'],
        ]);
        $carla = $this->createPerson($church, [
            'first_name' => 'Carla',
            'last_name' => 'Díaz',
            'status' => 'archived',
            'custom_fields' => ['zona' => 'este'],
        ]);

        $static = $this->withChurchContext($church, function () use ($ana, $beto, $carla): Segment {
            $segment = Segment::create([
                'name' => 'Grupo manual',
                'type' => Segment::TYPE_STATIC,
                'is_active' => true,
            ]);

            foreach ([$ana, $beto, $carla] as $person) {
                SegmentMember::create([
                    'segment_id' => $segment->getKey(),
                    'person_id' => $person->getKey(),
                ]);
            }

            return $segment;
        });

        $this->actingAs($admin)
            ->get(route('people.index', $church).'?segment='.$static->getKey())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/index')
                ->where('filters.segment', (string) $static->getKey())
                ->has('segments', 1)
                ->has('people', 3)
                ->where('people.0.fullName', 'Carla Díaz')
                ->where('people.0.status', 'archived'));

        $dynamic = $this->withChurchContext($church, fn (): Segment => Segment::create([
            'name' => 'Zona este',
            'type' => Segment::TYPE_DYNAMIC,
            'rules' => [
                'version' => 1,
                'match' => 'all',
                'conditions' => [
                    ['source' => 'custom', 'field' => 'zona', 'operator' => 'is', 'value' => 'este'],
                ],
            ],
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->get(route('people.index', $church).'?segment='.$dynamic->getKey())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/index')
                ->where('filters.segment', (string) $dynamic->getKey())
                ->has('segments', 2)
                ->has('people', 1)
                ->where('people.0.id', $ana->getKey()));

        $otherChurch = $this->createChurch('iglesia-filtro-otra');
        $otherSegment = $this->withChurchContext($otherChurch, fn (): Segment => Segment::create([
            'name' => 'Ajeno',
            'type' => Segment::TYPE_STATIC,
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->get(route('people.index', $church).'?segment='.$otherSegment->getKey())
            ->assertNotFound();
    }

    private function assertCountForRule(
        Church $church,
        string $source,
        string $field,
        string $operator,
        mixed $value,
        int $expected,
    ): void {
        $rules = SegmentRuleValidator::validate([
            'version' => 1,
            'match' => 'all',
            'conditions' => [
                ['source' => $source, 'field' => $field, 'operator' => $operator, 'value' => $value],
            ],
        ], $church);

        $this->assertSame($expected, SegmentQuery::count($church, $rules), "Rule {$field} {$operator}");
    }

    private function createDefinition(
        Church $church,
        string $key,
        string $type,
        array $options = [],
    ): CustomFieldDefinition {
        return $this->withChurchContext($church, fn (): CustomFieldDefinition => CustomFieldDefinition::create([
            'key' => $key,
            'label' => ucfirst($key),
            'type' => $type,
            'options' => $options === [] ? null : $options,
            'is_required' => false,
            'is_active' => true,
        ]));
    }

    private function createPerson(Church $church, array $attributes = []): Person
    {
        return $this->withChurchContext($church, fn (): Person => Person::create([
            'first_name' => $attributes['first_name'] ?? 'Ana',
            'last_name' => $attributes['last_name'] ?? 'Pérez',
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'custom_fields' => $attributes['custom_fields'] ?? (object) [],
        ]));
    }

    private function createChurch(string $slug): Church
    {
        return Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'active',
            'country_code' => 'VE',
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
            'locale' => 'es',
        ]);
    }

    private function createMember(Church $church, string $role): User
    {
        $user = User::factory()->create();

        ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    private function withChurchContext(Church $church, callable $callback): mixed
    {
        return app(CurrentChurch::class)->run($church, fn () => $callback());
    }
}
