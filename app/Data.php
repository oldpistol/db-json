<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Data extends Model
{
    protected $table = 'mainmerge';

    protected $dates = [
        'm_date',
        'created_at',
        'updated_at',
        'childdob',
        'childdob2',
        'childdob3'
    ];
}
