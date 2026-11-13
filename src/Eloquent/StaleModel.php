<?php

namespace EduKSX\Core\Eloquent;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** Datensatz wurde inzwischen von jemand anderem geändert (optimistisches Sperren); über die API als 409 «conflict». */
final class StaleModel extends RuntimeException
{
    public function __construct(public readonly Model $model)
    {
        parent::__construct('Der Eintrag wurde inzwischen von jemand anderem geändert. Bitte neu laden und erneut speichern.');
    }
}
