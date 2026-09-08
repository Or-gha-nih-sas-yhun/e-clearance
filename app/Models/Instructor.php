<?php

namespace App\Models;

use App\Support\InstructorDepartment;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property string $instructor_id
 * @property string $firstname
 * @property string|null $middlename
 * @property string $lastname
 * @property string|null $suffix
 * @property string $email
 * @property string $password
 * @property string $department
 * @property string|null $employment_status
 * @property string $full_name
 * @property string $department_label
 */
class Instructor extends Authenticatable
{
    /** Full-time faculty. */
    public const EMPLOYMENT_REGULAR = 'Regular';

    /** Faculty hired per term or per load. */
    public const EMPLOYMENT_PART_TIME = 'Part Timer';

    public const EMPLOYMENT_STATUSES = [self::EMPLOYMENT_REGULAR, self::EMPLOYMENT_PART_TIME];

    public $timestamps = false;

    protected $table = 'instructor_account';

    protected $fillable = [
        'instructor_id', 'firstname', 'middlename', 'lastname', 'suffix',
        'email', 'password', 'department', 'employment_status',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * Whether this database has the employment status column yet.
     *
     * Deploys never run `artisan migrate`, so a live database may still be
     * without it (`database/sql/instructor_employment_status.sql` adds it by
     * hand). Every read and write of the column is gated on this so the
     * instructor CRUD keeps working until the column exists.
     */
    public static function tracksEmploymentStatus(): bool
    {
        return Schema::hasTable('instructor_account')
            && Schema::hasColumn('instructor_account', 'employment_status');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    /** The stored department with the pre-merge education programs folded in. */
    public function getDepartmentLabelAttribute(): string
    {
        return InstructorDepartment::canonical($this->department);
    }
}
