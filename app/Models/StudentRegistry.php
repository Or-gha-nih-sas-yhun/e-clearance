<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per Microsoft account the college has cleared to self-register.
 *
 * `status` is the whole point of the table: 'inactive' means "may register",
 * 'active' means "already has a student_account". Registration flips it, and
 * deleting the student account flips it back (see App\Support\RecordPurge).
 */
class StudentRegistry extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'student_registry';

    protected $fillable = ['student_id', 'ms_account', 'status', 'registered_at'];
}
