<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained()->cascadeOnDelete();
            $table->string('employer', 120);
            $table->string('role', 120);
            $table->string('location', 120)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->jsonb('highlights')->default('[]');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['cv_id', 'position']);
        });

        Schema::create('education_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained()->cascadeOnDelete();
            $table->string('institution', 120);
            $table->string('qualification', 160);
            $table->string('field_of_study', 120)->nullable();
            $table->string('location', 120)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['cv_id', 'position']);
        });

        Schema::create('skill_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['cv_id', 'position']);
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['skill_group_id', 'position']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('role', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->jsonb('highlights')->default('[]');
            $table->jsonb('technologies')->default('[]');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['cv_id', 'position']);
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('issuer', 120);
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('credential_id', 120)->nullable();
            $table->string('credential_url', 2048)->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['cv_id', 'position']);
        });

        Schema::create('cv_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('label', 60)->nullable();
            $table->string('url', 2048);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['cv_id', 'position']);
        });

        $this->addConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cv_links');
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('skill_groups');
        Schema::dropIfExists('education_entries');
        Schema::dropIfExists('work_experiences');
    }

    private function addConstraints(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE work_experiences
            ADD CONSTRAINT work_experiences_position_check CHECK (position >= 0),
            ADD CONSTRAINT work_experiences_month_check CHECK (
                EXTRACT(DAY FROM start_date) = 1
                AND (end_date IS NULL OR EXTRACT(DAY FROM end_date) = 1)
            ),
            ADD CONSTRAINT work_experiences_range_check CHECK (
                end_date IS NULL OR end_date >= start_date
            ),
            ADD CONSTRAINT work_experiences_current_check CHECK (
                (is_current AND end_date IS NULL)
                OR (NOT is_current AND end_date IS NOT NULL)
            ),
            ADD CONSTRAINT work_experiences_highlights_check CHECK (
                jsonb_typeof(highlights) = 'array'
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE education_entries
            ADD CONSTRAINT education_entries_position_check CHECK (position >= 0),
            ADD CONSTRAINT education_entries_month_check CHECK (
                EXTRACT(DAY FROM start_date) = 1
                AND (end_date IS NULL OR EXTRACT(DAY FROM end_date) = 1)
            ),
            ADD CONSTRAINT education_entries_range_check CHECK (
                end_date IS NULL OR end_date >= start_date
            ),
            ADD CONSTRAINT education_entries_current_check CHECK (
                (is_current AND end_date IS NULL)
                OR (NOT is_current AND end_date IS NOT NULL)
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE skill_groups
            ADD CONSTRAINT skill_groups_position_check CHECK (position >= 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE skills
            ADD CONSTRAINT skills_position_check CHECK (position >= 0)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE projects
            ADD CONSTRAINT projects_position_check CHECK (position >= 0),
            ADD CONSTRAINT projects_month_check CHECK (
                (start_date IS NULL OR EXTRACT(DAY FROM start_date) = 1)
                AND (end_date IS NULL OR EXTRACT(DAY FROM end_date) = 1)
            ),
            ADD CONSTRAINT projects_start_check CHECK (
                end_date IS NULL OR start_date IS NOT NULL
            ),
            ADD CONSTRAINT projects_range_check CHECK (
                end_date IS NULL OR end_date >= start_date
            ),
            ADD CONSTRAINT projects_current_check CHECK (
                NOT is_current OR (start_date IS NOT NULL AND end_date IS NULL)
            ),
            ADD CONSTRAINT projects_highlights_check CHECK (
                jsonb_typeof(highlights) = 'array'
            ),
            ADD CONSTRAINT projects_technologies_check CHECK (
                jsonb_typeof(technologies) = 'array'
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE certifications
            ADD CONSTRAINT certifications_position_check CHECK (position >= 0),
            ADD CONSTRAINT certifications_month_check CHECK (
                (issued_on IS NULL OR EXTRACT(DAY FROM issued_on) = 1)
                AND (expires_on IS NULL OR EXTRACT(DAY FROM expires_on) = 1)
            ),
            ADD CONSTRAINT certifications_issued_check CHECK (
                expires_on IS NULL OR issued_on IS NOT NULL
            ),
            ADD CONSTRAINT certifications_range_check CHECK (
                expires_on IS NULL OR expires_on >= issued_on
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE cv_links
            ADD CONSTRAINT cv_links_position_check CHECK (position >= 0),
            ADD CONSTRAINT cv_links_type_check CHECK (
                type IN ('linkedin', 'github', 'portfolio', 'other')
            ),
            ADD CONSTRAINT cv_links_other_label_check CHECK (
                type <> 'other' OR (label IS NOT NULL AND BTRIM(label) <> '')
            )
            SQL);
    }
};
