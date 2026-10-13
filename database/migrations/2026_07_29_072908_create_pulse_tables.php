<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Support\PulseMigration;

return new class extends PulseMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        Schema::create('pulse_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');
            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash'),
                'sqlite' => $table->string('key_hash'),
            };
            $table->mediumText('value');

            $table->index('timestamp'); // For trimming...
            $table->index('type'); // For fast lookups and purging...
            $table->unique(['type', 'key_hash']); // For data integrity and upserts...
        });

        Schema::create('pulse_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');
            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash'),
                'sqlite' => $table->string('key_hash'),
            };
            $table->bigInteger('value')->nullable();

            $table->index('timestamp'); // For trimming...
            $table->index('type'); // For purging...
            $table->index('key_hash'); // For mapping...
            $table->index(['timestamp', 'type', 'key_hash', 'value']); // For aggregate queries...
        });

        Schema::create('pulse_aggregates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('bucket');
            $table->unsignedMediumInteger('period');
            $table->string('type');
            $table->mediumText('key');
            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash'),
                'sqlite' => $table->string('key_hash'),
            };
            $table->string('aggregate');
            $table->decimal('value', 20, 2);
            $table->unsignedInteger('count')->nullable();

            $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']); // Force "on duplicate update"...
            $table->index(['period', 'bucket']); // For trimming...
            $table->index('type'); // For purging...
            $table->index(['period', 'type', 'aggregate', 'bucket']); // For aggregate queries...
        });

        if ($this->driver() === 'pgsql') {
            Schema::getConnection()->statement('CREATE OR REPLACE FUNCTION populate_pulse_key_hash() RETURNS TRIGGER AS $$ BEGIN NEW.key_hash := md5(NEW.key)::uuid; RETURN NEW; END; $$ LANGUAGE plpgsql;');
            Schema::getConnection()->statement('CREATE TRIGGER trigger_pulse_values_key_hash BEFORE INSERT OR UPDATE ON pulse_values FOR EACH ROW EXECUTE PROCEDURE populate_pulse_key_hash();');
            Schema::getConnection()->statement('CREATE TRIGGER trigger_pulse_entries_key_hash BEFORE INSERT OR UPDATE ON pulse_entries FOR EACH ROW EXECUTE PROCEDURE populate_pulse_key_hash();');
            Schema::getConnection()->statement('CREATE TRIGGER trigger_pulse_aggregates_key_hash BEFORE INSERT OR UPDATE ON pulse_aggregates FOR EACH ROW EXECUTE PROCEDURE populate_pulse_key_hash();');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->driver() === 'pgsql') {
            Schema::getConnection()->statement('DROP TRIGGER IF EXISTS trigger_pulse_values_key_hash ON pulse_values;');
            Schema::getConnection()->statement('DROP TRIGGER IF EXISTS trigger_pulse_entries_key_hash ON pulse_entries;');
            Schema::getConnection()->statement('DROP TRIGGER IF EXISTS trigger_pulse_aggregates_key_hash ON pulse_aggregates;');
            Schema::getConnection()->statement('DROP FUNCTION IF EXISTS populate_pulse_key_hash();');
        }

        Schema::dropIfExists('pulse_values');
        Schema::dropIfExists('pulse_entries');
        Schema::dropIfExists('pulse_aggregates');
    }
};
