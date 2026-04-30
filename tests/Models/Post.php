<?php

namespace Searchkit\Searchable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Searchkit\Searchable\Sift;

class Post extends Model
{
    use Sift;

    protected $fillable = ['user_id', 'title', 'content'];

    protected static $searchable = ['title'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
