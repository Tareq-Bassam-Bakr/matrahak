<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Console\Commands;

use App\Services\Ai\TrainingDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * تصدير بيانات تدريب حقيقية لنموذج تصنيف البلاغات (خدمة Python):
 * بلاغات تصنيفها مؤكَّد بقرار بشري — عولجت وأُغلقت من المديرية المختصة، أو راجعت الإدارة اقتراح الـ AI.
 *
 * الملف: text,label,department,priority,source,report_id,created_at  (UTF-8 مع BOM ليفتح في Excel)
 * ضعه في matrahak-ai/data/real/ ثم شغّل: python -m app.ml.train  (أو استخدم زر «إعادة تدريب النموذج» مباشرة)
 */
class ExportTrainingData extends Command
{
    protected $signature = 'ai:export-training-data {--path= : مسار ملف CSV (افتراضياً storage/app/ai/)}';

    protected $description = 'تصدير البلاغات ذات التصنيف المؤكَّد كبيانات تدريب لنموذج الذكاء الاصطناعي';

    public function handle(TrainingDataService $training): int
    {
        $path = $this->option('path') ?: storage_path('app/ai/training-'.now()->format('Ymd-His').'.csv');
        File::ensureDirectoryExists(dirname($path));

        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['text', 'label', 'department', 'priority', 'source', 'report_id', 'created_at']);

        $count = $training->each(fn (array $row) => fputcsv($fh, [
            $row['text'], $row['label'], $row['department'], $row['priority'], $row['source'], $row['report_id'], $row['created_at'],
        ]));

        fclose($fh);
        $this->info("تم تصدير {$count} بلاغاً موثّق التصنيف إلى: {$path}");

        return self::SUCCESS;
    }
}
