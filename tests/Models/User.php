<?php

namespace Model\Searchable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Model\Searchable\Sift;

class User extends Model
{
    use Sift;

    protected $fillable = ['name', 'email', 'data'];

    protected static $searchable = ['name', 'email'];

    protected static $relation_searchable = [
        'posts' => ['title']
    ];

    protected static $json_searchable = [
        'data' => '*'
    ];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
