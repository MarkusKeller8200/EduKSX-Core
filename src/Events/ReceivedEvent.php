<?php

namespace EduKSX\Core\Events;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Bereits verarbeitete S4-Ereignisse (Idempotenz über die Ereignis-ID). Aufbewahrung begrenzt
 * (eduksx.events.retention_days); Core plant das Aufräumen täglich ein.
 *
 * @property string $event_id
 * @property string $type
 * @property string $source
 * @property string $received_at
 */
final class ReceivedEvent extends Model
{
    use MassPrunable;

    protected $table = 'received_events';

    protected $primaryKey = 'event_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return self::query()->where('received_at', '<', now()->utc()->subDays((int) config('eduksx.events.retention_days', 30)));
    }
}
