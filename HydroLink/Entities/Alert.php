<?php

declare(strict_types=1);

namespace Modules\HydroLink\Entities;

use Illuminate\Database\Eloquent\Model;

/** State of one problem key (service, account:<id>, binary, api...), used to notify once per change of state. */
class Alert extends Model
{
    protected $table = 'hydrolink_alerts';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean'];

    protected $dates = ['since', 'notified_at'];
}
