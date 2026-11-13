<?php

namespace EduKSX\Core\Tests\Fixtures;

use EduKSX\Core\Data\RecordsDataChanges;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User;

/**
 * Beispielkonto (angemeldete Person) mit Historie.
 *
 * @property string $id
 * @property string $email
 */
final class Account extends User
{
    use HasUuids, RecordsDataChanges;

    protected $table = 'accounts';

    protected $guarded = [];
}
