<?php

namespace App\Modules\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class OtpChallenge extends Model
{
    protected $primaryKey = 'mobile';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['mobile', 'code_hash', 'challenge_id'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}
