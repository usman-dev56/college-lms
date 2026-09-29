<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\AcademicSession;
use App\Models\ClassModel;
use App\Models\Period;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base class for every test in the suite.
 *
 * The helpers below build the academic structure - sessions, streams,
 * subjects, classes and periods - with the minimum valid payload each table
 * requires. A test only has to state the parts it actually cares about, which
 * keeps the shape of a valid record in one place instead of repeating it in
 * every file.
 *
 * Each helper takes an $attrs array merged over the defaults, so a test can
 * override any column without losing the rest.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Create an administrator.
     */
    protected function createAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);
    }

    /**
     * Create an active teacher.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function createTeacher(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Teacher,
            'is_active' => true,
        ], $attrs));
    }

    /**
     * Create an active student.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function createStudent(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Student,
            'is_active' => true,
        ], $attrs));
    }

    /**
     * Create an academic session and leave it as the only active one.
     *
     * The session is written inactive and then activated, because activate()
     * is what enforces the "exactly one active session" rule.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function createActiveSession(array $attrs = []): AcademicSession
    {
        $session = AcademicSession::create(array_merge([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_active' => false,
        ], $attrs));

        $session->activate();

        return $session->fresh();
    }

    /**
     * Create a stream.
     */
    protected function createStream(string $name = 'Pre-Medical', string $code = 'PM'): Stream
    {
        return Stream::create([
            'name' => $name,
            'code' => $code,
            'description' => null,
            'is_active' => true,
        ]);
    }

    /**
     * Create a subject.
     *
     * The default is a compulsory grade 11 subject - the simplest row the
     * table accepts - so a test only sets a stream when it is testing an
     * elective.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function createSubject(array $attrs = []): Subject
    {
        return Subject::create(array_merge([
            'name' => 'English',
            'code' => 'ENG',
            'grade_level' => 11,
            'stream_id' => null,
            'has_practical' => false,
            'is_active' => true,
        ], $attrs));
    }

    /**
     * Create a class in a session and stream.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function createClass(AcademicSession $session, Stream $stream, array $attrs = []): ClassModel
    {
        return ClassModel::create(array_merge([
            'academic_session_id' => $session->id,
            'stream_id' => $stream->id,
            'grade_level' => 11,
            'section' => 'A',
            'capacity' => 50,
            'room' => null,
            'is_active' => true,
        ], $attrs));
    }

    /**
     * Create one row of a session's daily grid.
     *
     * The period number is unique per session, so a test that needs several
     * periods must pass a distinct number for each one.
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function createPeriod(AcademicSession $session, array $attrs = []): Period
    {
        return Period::create(array_merge([
            'academic_session_id' => $session->id,
            'number' => 1,
            'label' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:45',
            'is_break' => false,
        ], $attrs));
    }
}
