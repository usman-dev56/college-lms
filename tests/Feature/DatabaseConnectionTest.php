<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the environment the suite runs in.
 *
 * The academic structure relies on PostgreSQL features that SQLite does not
 * implement: partial unique indexes (WHERE deleted_at IS NULL) on every
 * soft-deletable table, NULLS NOT DISTINCT on the subjects index, and CHECK
 * constraints on grade level, day of week and period times. Those
 * constraints are the thing most of the other tests are proving, so if the
 * suite ever silently falls back to SQLite the rest of the suite would pass
 * while testing nothing.
 *
 * These assertions are deliberately cheap and read-only.
 */
class DatabaseConnectionTest extends TestCase
{
    public function test_the_suite_runs_on_postgresql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_the_suite_runs_against_the_dedicated_test_database(): void
    {
        $this->assertSame(
            'college_lms_test',
            config('database.connections.pgsql.database'),
            'Tests must never run against the development or production database.'
        );

        // Proves the credentials in .env.testing actually work, rather than
        // only that the configuration looks right.
        $this->assertSame(1, (int) DB::selectOne('select 1 as alive')->alive);
    }

    public function test_the_application_key_is_configured(): void
    {
        // Without a key the password hashing and session handling in every
        // feature test would fail with an encryption error, so this catches a
        // missing or blank APP_KEY in .env.testing early.
        $this->assertNotEmpty(config('app.key'));
    }
}
