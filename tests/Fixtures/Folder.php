<?php

namespace EduKSX\Core\Tests\Fixtures;

use EduKSX\Core\Data\RecordsDataChanges;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Beispiel mit Historie und Papierkorb.
 *
 * @property string $id
 * @property string $name
 * @property ?string $note
 * @property ?string $organization_id
 */
final class Folder extends Model
{
    use HasUuids, RecordsDataChanges, SoftDeletes;

    protected $table = 'folders';

    protected $guarded = [];
}
