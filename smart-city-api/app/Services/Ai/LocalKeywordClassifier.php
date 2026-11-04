<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Ai;

use App\Enums\ReportPriority;
use App\Models\Category;
use App\Support\ArabicText;
use Illuminate\Support\Collection;

/**
 * مصنّف احتياطي يعمل داخل Laravel نفسه عندما تكون خدمة Python متوقفة.
 * يعتمد على الكلمات المفتاحية المخزّنة لكل تصنيف في قاعدة البيانات
 * (قابلة للتعديل من لوحة الإدارة) — فلا يتوقف توجيه البلاغات أبداً.
 *
 * v2:
 *  - تجذيع خفيف للعربية والعامية السورية (ال، بال، وال، عال… / ات، ين، ون، ة…)
 *    => "الكهرباء/كهربا/بالكهربا" و"شرارة/شرار" تتطابق.
 *  - العبارة متعددة الكلمات تُطابَق حتى لو لم تكن كلماتها متجاورة ("سلك كهربا مقطوع" ⇐ "سلك مقطوع").
 *  - وزن IDF: الكلمة المشتركة بين تصنيفات كثيرة (مثل "كهربا") وزنها أقل من الكلمة المميزة ("شرار").
 *  - الكلمات الواردة في العنوان وزنها أعلى.
 */
class LocalKeywordClassifier
{
    public const MODEL_VERSION = 'local-keywords-v2';

    // مخلفات الحرب (ألغام/قذائف) خطر حقيقي على الأرواح في سوريا => حرجة دائماً
    public const CRITICAL_WORDS = ['حريق', 'انفجار', 'انهيار', 'اصابات', 'مصاب', 'جرحي', 'اختناق', 'صعق', 'صعقه', 'غرق', 'ماس كهربايي', 'تسرب غاز', 'وفاه', 'لغم', 'قذيفه', 'مخلفات حرب', 'جسم مشبوه'];

    // ملاحظة: الكلمات مكتوبة بالصيغة الموحّدة (normalized): ة→ه، ى/ئ→ي، أ/إ/آ→ا
    public const HIGH_WORDS = ['خطر', 'خطير', 'عاجل', 'اطفال', 'مدرسه', 'مستشفي', 'مشفي', 'متكرر', 'طواري', 'حادث', 'حوادث', 'مكشوف', 'منذ ايام', 'منذ اسبوع', 'شرار'];

    private const STOP = ['في', 'من', 'على', 'علي', 'الى', 'الي', 'عن', 'مع', 'هذا', 'هذه', 'هناك', 'يوجد', 'تم', 'او', 'و', 'ان', 'كان',
        'التي', 'الذي', 'عند', 'جدا', 'كل', 'ما', 'لا', 'عم', 'في', 'بس', 'شي', 'صار', 'كتير', 'كثير', 'اكتر', 'اكثر', 'من', 'يلي', 'اللي', 'هاد', 'هي', 'هو'];

    private const PREFIXES = ['وبال', 'وال', 'بال', 'كال', 'فال', 'عال', 'لل', 'ال', 'و', 'ب', 'ل', 'ف'];

    private const SUFFIXES = ['يات', 'ات', 'ون', 'ين', 'ها', 'هم', 'كم', 'نا', 'ه', 'ي', 'ء', 'ا'];

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{category: Category, confidence: float, priority: ReportPriority, keywords: list<string>, summary: string, alternatives: list<array{slug: string, score: float}>}|null
     */
    public function classify(string $title, string $description, Collection $categories): ?array
    {
        $titleNorm = ' '.ArabicText::normalize($title).' ';
        $fullNorm = ' '.ArabicText::normalize($title.' '.$description).' ';
        $titleStems = $this->stems($titleNorm);
        $fullStems = $this->stems($fullNorm);

        $index = $this->buildIndex($categories);
        $scores = [];
        $matched = [];

        foreach ($index['categories'] as $id => $terms) {
            $score = 0.0;
            $hits = [];
            foreach ($terms as $term) {
                $m = $this->matchTerm($term, $fullNorm, $fullStems);
                if ($m <= 0) {
                    continue;
                }
                $weight = 0.5 + 0.5 * ($term['idf'] / $index['max_idf']);
                $inTitle = $this->matchTerm($term, $titleNorm, $titleStems) > 0;
                $score += $m * $weight * ($inTitle ? 1.35 : 1.0);
                $hits[] = $term['raw'];
            }
            if ($score > 0) {
                $scores[$id] = round($score, 4);
                $matched[$id] = $hits;
            }
        }

        if (! $scores) {
            return null;
        }

        arsort($scores);
        $ids = array_keys($scores);
        $best = $scores[$ids[0]];
        $second = $scores[$ids[1] ?? -1] ?? 0.0;
        // الثقة: هامش التفوق على التصنيف الثاني × قوة الدليل
        $confidence = min(0.97, ($best / ($best + $second + 0.6)) * min(1.0, 0.45 + 0.25 * $best));

        /** @var Category $category */
        $category = $categories->firstWhere('id', $ids[0]);

        return [
            'category' => $category,
            'confidence' => round($confidence, 4),
            'priority' => ReportPriority::max($category->basePriority(), $this->detectPriority($fullNorm)),
            'keywords' => array_values(array_unique($matched[$ids[0]])),
            'summary' => $this->summarize($title, $description),
            'alternatives' => collect($ids)->slice(1, 3)->map(fn ($id) => [
                'slug' => $categories->firstWhere('id', $id)->slug,
                'score' => round($scores[$id], 2),
            ])->values()->all(),
        ];
    }

    public function detectPriority(string $normalizedText): ReportPriority
    {
        foreach (self::CRITICAL_WORDS as $w) {
            if (str_contains($normalizedText, $w)) {
                return ReportPriority::Critical;
            }
        }
        foreach (self::HIGH_WORDS as $w) {
            if (str_contains($normalizedText, $w)) {
                return ReportPriority::High;
            }
        }

        return ReportPriority::Low;
    }

    public function summarize(string $title, string $description): string
    {
        $first = trim(preg_split('/[.!؟?\n،]/u', $description)[0] ?? $description);
        if (mb_strlen($first) > 140) {
            $first = mb_substr($first, 0, 140).'…';
        }

        return trim($title).' — '.$first;
    }

    // ------------------------------------------------------------------

    /** جذع خفيف: حذف سابقة واحدة ولاحقة واحدة مع إبقاء 3 أحرف على الأقل */
    public function stem(string $word): string
    {
        foreach (self::PREFIXES as $p) {
            if (str_starts_with($word, $p) && mb_strlen($word) - mb_strlen($p) >= 3) {
                $word = mb_substr($word, mb_strlen($p));
                break;
            }
        }
        foreach (self::SUFFIXES as $s) {
            if (str_ends_with($word, $s) && mb_strlen($word) - mb_strlen($s) >= 3) {
                $word = mb_substr($word, 0, mb_strlen($word) - mb_strlen($s));
                break;
            }
        }

        return $word;
    }

    /** @return list<string> */
    private function stems(string $normalized): array
    {
        $out = [];
        foreach (explode(' ', trim($normalized)) as $w) {
            if (mb_strlen($w) < 2 || in_array($w, self::STOP, true)) {
                continue;
            }
            $out[] = $this->stem($w);
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{categories: array<int, list<array{raw: string, norm: string, stems: list<string>, idf: float}>>, max_idf: float}
     */
    private function buildIndex(Collection $categories): array
    {
        $perCategory = [];
        $df = [];
        foreach ($categories as $category) {
            $terms = [];
            $catStems = [];
            foreach (array_unique(array_merge($category->keywords ?? [], [$category->name])) as $raw) {
                $norm = ArabicText::normalize((string) $raw);
                if ($norm === '') {
                    continue;
                }
                $stems = $this->stems($norm);
                if ($stems === []) {
                    continue;
                }
                $terms[] = ['raw' => (string) $raw, 'norm' => $norm, 'stems' => $stems];
                foreach ($stems as $s) {
                    $catStems[$s] = true;
                }
            }
            foreach (array_keys($catStems) as $s) {
                $df[$s] = ($df[$s] ?? 0) + 1;
            }
            $perCategory[$category->id] = $terms;
        }

        $n = max(1, $categories->count());
        $maxIdf = log(1 + $n);
        foreach ($perCategory as $id => $terms) {
            foreach ($terms as $i => $t) {
                $idfs = array_map(fn ($s) => log(1 + $n / max(1, $df[$s] ?? 1)), $t['stems']);
                $perCategory[$id][$i]['idf'] = array_sum($idfs) / count($idfs);
            }
        }

        return ['categories' => $perCategory, 'max_idf' => $maxIdf];
    }

    /** درجة تطابق مصطلح مع النص (0 = لا تطابق) */
    private function matchTerm(array $term, string $normText, array $textStems): float
    {
        $words = count($term['stems']);
        $full = 1 + 0.75 * ($words - 1);

        // تطابق حرفي للعبارة كاملة (الكلمات القصيرة ككلمة كاملة فقط)
        if (mb_strlen($term['norm']) <= 3) {
            if (preg_match('/(^|\s)(ال|و|ب|بال)?'.preg_quote($term['norm'], '/').'(\s|$)/u', $normText)) {
                return $full;
            }
        } elseif (str_contains($normText, $term['norm'])) {
            return $full;
        }

        // تطابق على مستوى الجذور (الكلمات غير متجاورة)
        $found = 0.0;
        foreach ($term['stems'] as $s) {
            $found += $this->stemMatch($s, $textStems);
        }
        if ($words === 1) {
            return $found;
        }
        $ratio = $found / $words;
        if ($ratio >= 0.99) {
            return 0.8 * $full;
        }

        return $ratio >= 0.5 ? 0.25 * $ratio * $full : 0.0;
    }

    private function stemMatch(string $stem, array $textStems): float
    {
        if (in_array($stem, $textStems, true)) {
            return 1.0;
        }
        if (mb_strlen($stem) < 4) {
            return 0.0;
        }
        foreach ($textStems as $t) {
            if (mb_strlen($t) >= 4 && (str_starts_with($t, $stem) || str_starts_with($stem, $t))) {
                return 0.8;
            }
        }

        return 0.0;
    }
}
