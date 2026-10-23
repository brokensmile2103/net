<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nội dung 3 trang tĩnh (Giới thiệu/Điều khoản/Chính sách), sửa được qua Super Admin. */
class Page extends Model
{
    protected $fillable = ['slug', 'title', 'content', 'meta_description'];
}
