<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class WebsiteContactMessage extends Model
{
    protected $fillable = ['submission_hash', 'sender_name', 'phone', 'message'];

    protected $hidden = ['submission_hash', 'sender_name', 'phone', 'message'];

    protected function casts(): array
    {
        return ['sender_name' => 'encrypted', 'phone' => 'encrypted', 'message' => 'encrypted', 'read_at' => 'immutable_datetime'];
    }
}
