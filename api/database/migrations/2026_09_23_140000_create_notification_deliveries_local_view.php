<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Storage stays UTC (notification_deliveries.sent_at/created_at) — this view exists purely for
 * admins running ad-hoc SQL who want KL/Seoul times without retyping `AT TIME ZONE 'UTC' AT TIME
 * ZONE '...'` each time. Both zones are exposed as separate columns (see AdminTimezone) rather
 * than picking one dynamically, since a plain view can't read the querying admin's Filament
 * preference — pick whichever *_kl/*_seoul column matches what you need.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW notification_deliveries_local AS
            SELECT
                nd.*,
                nd.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Kuala_Lumpur' AS created_at_kl,
                nd.created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Seoul' AS created_at_seoul,
                nd.sent_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Kuala_Lumpur' AS sent_at_kl,
                nd.sent_at AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Seoul' AS sent_at_seoul
            FROM notification_deliveries nd
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS notification_deliveries_local');
    }
};
