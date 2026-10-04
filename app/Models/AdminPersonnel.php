<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AdminPersonnel extends Authenticatable
{
    public $timestamps = false;

    protected $table = 'admin_personnel';

    protected $fillable = [
        'personnel_id', 'firstname', 'lastname', 'email', 'password', 'office', 'role',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    // admin_personnel table doesn't have remember_token or updated_at
    public function getRememberTokenName()
    {
        return null;
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
        // no remember token
    }

    public function hasRememberToken()
    {
        return false;
    }

    public static array $validRoles = [
        'program_head_bsit' => 'Program Head — BSIT',
        'program_head_bsed' => 'Program Head — BSED',
        'program_head_beed' => 'Program Head — BEED',
        'program_head_bsba' => 'Program Head — BSBA',
        'program_head_bshm' => 'Program Head — BSHM',
        'education_department_head' => 'College of Education Department Head',
        'property_custodian' => 'Property Custodian',
        'scc_adviser' => 'SSC Adviser',
        'sas_director' => 'SAS Director',
        'guidance' => 'Guidance Office',
        'library' => 'Library',
    ];
}
