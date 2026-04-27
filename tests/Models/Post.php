<?php

namespace Model\Searchable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Model\Searchable\Sift;

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
