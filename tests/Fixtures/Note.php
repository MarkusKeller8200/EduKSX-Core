<?php

namespace EduKSX\Core\Tests\Fixtures;

use EduKSX\Core\Eloquent\OptimisticLocking;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property int $lock_version
 */
final class Note extends Model
{
    use OptimisticLocking;

    protected $table = 'notes';

    protected $guarded = [];
}
