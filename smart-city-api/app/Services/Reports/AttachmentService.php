<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

/**
 * تخزين مرفقات البلاغ + صور مصغّرة.
 *
 * ⚠️ الكود السابق استخدم Intervention\Image\Facades\Image::make (إصدار v2)
 * بينما composer يثبّت v3 => Class not found => Error لا يلتقطه catch(\Exception) => 500 عند رفع أي صورة.
 * هنا: واجهة v3 الصحيحة + catch(\Throwable) فالصورة المصغّرة لا تُفشل الرفع أبداً.
 */
class AttachmentService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, ReportAttachment>
     */
    public function storeMany(Report $report, User $uploader, array $files, string $stage = 'before'): Collection
    {
        $start = (int) $report->attachments()->max('sort_order') + ($report->attachments()->exists() ? 1 : 0);

        return collect(array_values($files))->map(
            fn (UploadedFile $file, int $i) => $this->store($report, $uploader, $file, $stage, $start + $i)
        );
    }

    public function store(Report $report, User $uploader, UploadedFile $file, string $stage, int $order = 0): ReportAttachment
    {
        $type = $this->detectType($file);
        $dir = 'reports/'.$report->uuid;
        $path = $file->store($dir, 'public');

        return ReportAttachment::create([
            'report_id' => $report->id,
            'uploader_id' => $uploader->id,
            'file_type' => $type,
            'stage' => $stage,
            'file_path' => $path,
            'file_name' => mb_substr($file->getClientOriginalName() ?: basename($path), 0, 250),
            'file_size' => (int) $file->getSize(),
            'mime_type' => (string) ($file->getMimeType() ?: $file->getClientMimeType()),
            'thumbnail' => $type === 'image' ? $this->thumbnail($path, $dir) : null,
            'sort_order' => $order,
        ]);
    }

    public function detectType(UploadedFile $file): string
    {
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            default => 'document',
        };
    }

    private function thumbnail(string $path, string $dir): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            $thumbPath = $dir.'/thumb_'.pathinfo($path, PATHINFO_FILENAME).'.jpg';
            $manager = new ImageManager(new GdDriver);
            $image = $manager->read($disk->path($path));
            $image->cover(400, 400);
            $disk->put($thumbPath, (string) $image->toJpeg(75));

            return $thumbPath;
        } catch (\Throwable $e) {
            Log::info('Thumbnail generation skipped: '.$e->getMessage());

            return null;
        }
    }
}
