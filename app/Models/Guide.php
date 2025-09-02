<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Guide extends Model
{
    protected $fillable = [
        'title',
        'audience',
        'description',
        'video_path',
        'video_mime',
        'screenshots',
        'created_by',
    ];

    protected $casts = [
        'screenshots' => 'array',
    ];

    // append virtual attribute if you want direct url in model arrays
    protected $appends = ['video_url', 'screenshot_urls'];

    public function getVideoUrlAttribute()
    {
        if (! $this->video_path) {
            return null;
        }
        // Storage::url uses the default disk; for 'public' disk it will generate /storage/...
        return Storage::url($this->video_path);
    }

    public function getScreenshotUrlsAttribute()
    {
        if (! $this->screenshots || ! is_array($this->screenshots)) {
            return [];
        }
        
        return array_map(function ($path) {
            return Storage::url($path);
        }, $this->screenshots);
    }

    // optional: relationship to user if you store created_by
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // relationship to steps
    public function steps()
    {
        return $this->hasMany(\App\Models\Step::class);
    }
}