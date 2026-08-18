<?php

namespace Tests\Feature\Cvs;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CvManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_use_cv_management_routes(): void
    {
        $cv = Cv::factory()->create();

        $this->get(route('cvs.index'))->assertRedirect('/login');
        $this->post(route('cvs.store'), ['title' => 'CV privado'])->assertRedirect('/login');
        $this->get(route('cvs.edit', $cv))->assertRedirect('/login');
        $this->post(route('cvs.duplicate', $cv))->assertRedirect('/login');
        $this->delete(route('cvs.destroy', $cv))->assertRedirect('/login');
    }

    public function test_index_only_lists_the_authenticated_users_cvs_in_update_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $olderCv = Cv::factory()->for($user)->create([
            'title' => 'CV antiguo',
            'updated_at' => now()->subDay(),
        ]);
        $newerCv = Cv::factory()->for($user)->create([
            'title' => 'CV reciente',
            'updated_at' => now(),
        ]);
        Cv::factory()->for($otherUser)->create(['title' => 'CV ajeno secreto']);

        $this->actingAs($user)
            ->get(route('cvs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cvs/Index')
                ->has('cvs', 2)
                ->where('cvs.0.id', $newerCv->id)
                ->where('cvs.0.title', 'CV reciente')
                ->where('cvs.1.id', $olderCv->id)
                ->where('cvs.1.title', 'CV antiguo'));
    }

    public function test_index_supports_an_empty_cv_collection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cvs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cvs/Index')
                ->has('cvs', 0));
    }

    public function test_a_user_can_create_an_empty_cv_with_the_controlled_template(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('cvs.store'), [
            'title' => '  CV para backend  ',
            'template_key' => 'untrusted-template',
        ]);

        $cv = $user->cvs()->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertRedirect(route('cvs.edit', $cv));
        $this->assertSame('CV para backend', $cv->title);
        $this->assertSame('jakes-resume', $cv->template_key);
        $this->assertNull($cv->full_name);
        $this->assertNull($cv->contact_email);
    }

    public function test_cv_creation_validates_the_internal_title(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('cvs.index'))
            ->post(route('cvs.store'), ['title' => '   '])
            ->assertSessionHasErrors('title')
            ->assertRedirect(route('cvs.index'));

        $this->actingAs($user)
            ->from(route('cvs.index'))
            ->post(route('cvs.store'), ['title' => str_repeat('a', 101)])
            ->assertSessionHasErrors('title')
            ->assertRedirect(route('cvs.index'));

        $this->assertDatabaseCount('cvs', 0);
    }

    public function test_an_owner_can_open_their_cv(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->get(route('cvs.edit', $cv))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cvs/Edit')
                ->where('cv.id', $cv->id)
                ->where('cv.title', $cv->title)
                ->where('cv.template_key', 'jakes-resume'));
    }

    public function test_an_owner_can_duplicate_a_complete_cv_as_an_independent_copy(): void
    {
        $owner = User::factory()->create();
        $original = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'CV principal',
        ])->load([
            'workExperiences',
            'educationEntries',
            'skillGroups.skills',
            'projects',
            'certifications',
            'links',
        ]);

        $response = $this->actingAs($owner)->post(route('cvs.duplicate', $original));

        $copy = $owner->cvs()->whereKeyNot($original->id)->sole()->load([
            'workExperiences',
            'educationEntries',
            'skillGroups.skills',
            'projects',
            'certifications',
            'links',
        ]);

        $response
            ->assertSessionHas('success')
            ->assertRedirect(route('cvs.edit', $copy));
        $this->assertSame('CV principal (copia)', $copy->title);
        $this->assertSame($original->template_key, $copy->template_key);

        foreach (['workExperiences', 'educationEntries', 'skillGroups', 'projects', 'certifications', 'links'] as $relation) {
            $this->assertCount($original->{$relation}->count(), $copy->{$relation});
            $this->assertNotSame(
                $original->{$relation}->pluck('id')->all(),
                $copy->{$relation}->pluck('id')->all(),
                "The duplicated [{$relation}] rows must have independent IDs.",
            );
        }

        $originalSkills = $original->skillGroups->sole()->skills;
        $copiedSkills = $copy->skillGroups->sole()->skills;
        $this->assertCount($originalSkills->count(), $copiedSkills);
        $this->assertNotSame($originalSkills->pluck('id')->all(), $copiedSkills->pluck('id')->all());
        $this->assertSame($original->projects->sole()->technologies, $copy->projects->sole()->technologies);

        $copy->projects->sole()->update(['name' => 'Proyecto modificado en copia']);

        $this->assertNotSame($original->projects->sole()->name, $copy->projects->sole()->fresh()->name);
    }

    public function test_a_duplicated_title_respects_the_database_limit(): void
    {
        $owner = User::factory()->create();
        $original = Cv::factory()->for($owner)->create([
            'title' => str_repeat('á', 100),
        ]);

        $this->actingAs($owner)
            ->post(route('cvs.duplicate', $original))
            ->assertSessionHasNoErrors();

        $copy = $owner->cvs()->whereKeyNot($original->id)->sole();

        $this->assertLessThanOrEqual(100, mb_strlen($copy->title));
        $this->assertStringEndsWith(' (copia)', $copy->title);
    }

    public function test_an_owner_can_permanently_delete_a_cv_and_its_content(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create();

        $this->actingAs($owner)
            ->delete(route('cvs.destroy', $cv))
            ->assertSessionHas('success')
            ->assertRedirect(route('cvs.index'));

        $this->assertDatabaseMissing('cvs', ['id' => $cv->id]);
        foreach (['work_experiences', 'education_entries', 'skill_groups', 'skills', 'projects', 'certifications', 'cv_links'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_foreign_cvs_cannot_be_opened_duplicated_or_deleted(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'CV privado del propietario',
        ]);

        $this->actingAs($otherUser)
            ->get(route('cvs.edit', $cv))
            ->assertNotFound()
            ->assertDontSee($cv->title);
        $this->actingAs($otherUser)
            ->post(route('cvs.duplicate', $cv))
            ->assertNotFound()
            ->assertDontSee($cv->title);
        $this->actingAs($otherUser)
            ->delete(route('cvs.destroy', $cv))
            ->assertNotFound()
            ->assertDontSee($cv->title);

        $this->assertDatabaseHas('cvs', ['id' => $cv->id]);
        $this->assertSame(1, $owner->cvs()->count());
        $this->assertSame(0, $otherUser->cvs()->count());
    }
}
