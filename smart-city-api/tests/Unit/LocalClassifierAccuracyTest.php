<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Unit;

use App\Models\Category;
use App\Services\Ai\LocalKeywordClassifier;
use Tests\TestCase;

/**
 * مجموعة اختبار مستقلة (Held-out) لم تُستخدم في ضبط الكلمات المفتاحية:
 * بلاغات بصياغات مختلفة (فصحى + عامية سورية) لقياس دقة المصنّف المحلي بصدق.
 */
class LocalClassifierAccuracyTest extends TestCase
{
    public const SAMPLES = [
        ['road-damage', 'الطريق كله جور', 'من بعد الشتوية الشارع صار كله جور وحفر والسيارات عم تتعطل'],
        ['road-damage', 'تخريب الإسفلت', 'الإسفلت متشقق ومخرب عند دوار الحي ويحتاج إعادة تزفيت'],
        ['sidewalk-damage', 'الرصيف مخلوع', 'حجار الرصيف مخلوعة قدام الجامع والناس عم توقع'],
        ['sidewalk-damage', 'أرصفة مهترئة', 'الأرصفة مهترئة ومكسرة ولا تصلح لمشي كبار السن'],
        ['garbage', 'الزبالة مالية الحارة', 'سيارة القمامة ما إجت من أسبوع والزبالة مكومة'],
        ['garbage', 'حاويات ممتلئة', 'جميع الحاويات في الشارع ممتلئة والنفايات على الأرض'],
        ['blocked-drains', 'المطر غرّق الشارع', 'كل ما تشتي الشارع بيتحول لبحيرة لأن المجارير مسدودة'],
        ['blocked-drains', 'بالوعة مسدودة', 'البالوعة على زاوية الشارع مسدودة بالتراب ومياه الأمطار متجمعة'],
        ['power-outage', 'ما في كهربا', 'الكهربا قاطعة عنا من مبارح الظهر وما رجعت أبداً'],
        ['power-outage', 'انقطاع التيار الكهربائي', 'انقطاع متواصل للتيار الكهربائي عن الحي منذ يومين'],
        ['street-light-failure', 'الضو مطفي بالشارع', 'ضو الشارع مطفي من شهر وصار الحي معتم بالليل'],
        ['street-light-failure', 'مصابيح الإنارة العامة', 'مصابيح الإنارة العامة على الأوتوستراد لا تعمل'],
        ['dangerous-electric-pole', 'عمود كهربا عم يميل', 'عمود الكهربا قدام البناية مايل وراح يوقع عالسيارات'],
        ['dangerous-electric-pole', 'عمود مكسور', 'عمود كهربائي مكسور من القاعدة بعد اصطدام سيارة'],
        ['exposed-wires', 'أسلاك عالأرض', 'في أسلاك كهربا مقطوعة ومرمية عالرصيف وعم تطلع شرار'],
        ['exposed-wires', 'كابلات مكشوفة', 'كابلات كهرباء مكشوفة بدون عازل قرب حديقة الأطفال'],
        ['street-water-leak', 'المي عم تطلع من الأرض', 'في مي عم تطلع من تحت الزفت من يومين وعم تمشي بالشارع'],
        ['street-water-leak', 'انكسار أنبوب مياه', 'انكسار أنبوب مياه رئيسي وهدر كبير للمياه'],
        ['drinking-water-outage', 'ما في مي عنا', 'المي ما إجت من أربع أيام وصرنا نشتري مي'],
        ['drinking-water-outage', 'انقطاع مياه الشفة', 'انقطاع مياه الشرب عن الحي بالكامل منذ ثلاثة أيام'],
        ['sewage-overflow', 'الصرف الصحي طافح', 'مياه الصرف الصحي طافحة بالشارع وريحتها كريهة كتير'],
        ['sewage-overflow', 'تسرب مياه آسنة', 'مياه آسنة عم تطلع من المنهل وتدخل للبيوت'],
        ['traffic-signal-failure', 'الإشارة الضوئية ما عم تشتغل', 'إشارة المرور عند الساحة واقفة والسيارات عم تتصادم'],
        ['traffic-signal-failure', 'عطل إشارة مرور', 'عطل في إشارات المرور عند التقاطع الرئيسي'],
        ['traffic-congestion', 'زحمة سير خانقة', 'كل يوم الساعة ٨ الصبح زحمة ما بتنحمل عند المدخل'],
        ['traffic-congestion', 'اختناق مروري', 'اختناق مروري يومي بسبب السيارات المتوقفة على الطرفين'],
        ['damaged-road-sign', 'شاخصة واقعة', 'الشاخصة المرورية واقعة عالأرض وما حدا رفعها'],
        ['damaged-road-sign', 'لوحة طرقية مكسورة', 'لوحة الإرشاد الطرقية مكسورة وغير مقروءة'],
        ['environmental-violation', 'معمل عم يلوث', 'معمل بالمنطقة عم يطلع دخان أسود وروائح طول الليل'],
        ['environmental-violation', 'قطع أشجار', 'في حدا عم يقطع الأشجار بالحديقة العامة'],
        ['waste-burning', 'حرق زبالة', 'جيراننا عم يحرقوا الزبالة بالأرض الفاضية والدخان خانقنا'],
        ['waste-burning', 'حرق إطارات', 'شباب عم يحرقوا كوشوك بالليل والريحة سامة'],
        ['water-pollution', 'النهر ملوث', 'مياه النهر صارت سودا من المخلفات اللي عم ترمى فيه'],
        ['water-pollution', 'تلوث بردى', 'رمي نفايات ومخلفات في مجرى نهر بردى'],
        ['collapsing-building', 'بيت رح يوقع', 'البيت القديم جنبنا صار فيه تصدعات كبيرة وخايفين يوقع'],
        ['collapsing-building', 'جدار متصدع', 'جدار البناية متصدع والسقف عم ينزل منه تراب'],
        ['fire', 'في حريق', 'في نار عم تشتعل بمحل بالسوق والدخان كثيف'],
        ['fire', 'حريق حراج', 'حريق كبير بالأحراج القريبة من البيوت'],
        ['public-safety-hazard', 'ريغار بلا غطا', 'غطا الريغار مسروق والفتحة مفتوحة بنص الطريق'],
        ['public-safety-hazard', 'جسم غريب', 'لقينا جسم غريب يشبه قذيفة بالأرض الزراعية'],
        ['internet-outage', 'النت مقطوع', 'النت مقطوع عن كل الحي والشركة ما عم ترد'],
        ['internet-outage', 'انقطاع خدمة الإنترنت', 'انقطاع خدمة الإنترنت ADSL منذ أسبوع'],
        ['telecom-network-failure', 'ما في شبكة', 'ما في إرسال للموبايل بالمنطقة أبداً'],
        ['telecom-network-failure', 'الهاتف الأرضي معطل', 'الخط الأرضي مقطوع ما في حرارة من أسبوع'],
        ['health-violation', 'محل لحمة مخالف', 'محل اللحمة عم يبيع لحمة ريحتها فاسدة'],
        ['health-violation', 'فرن غير نظيف', 'الفرن بالحي غير نظيف وفيه صراصير وحشرات'],
        ['contaminated-water', 'المي ريحتها غريبة', 'مي الحنفية ريحتها مثل المجارير ولونها عكر'],
        ['contaminated-water', 'تلوث مياه الشرب', 'ظهور حالات تسمم بعد شرب مياه الشبكة'],
    ];

    public function test_held_out_accuracy_is_at_least_80_percent(): void
    {
        $categories = Category::query()->active()->with('department')->get();
        $clf = app(LocalKeywordClassifier::class);

        $correct = 0;
        $failures = [];
        foreach (self::SAMPLES as [$expected, $title, $description]) {
            $result = $clf->classify($title, $description, $categories);
            $got = $result['category']->slug ?? 'none';
            if ($got === $expected) {
                $correct++;
            } else {
                $failures[] = "{$expected} => {$got} | {$title}";
            }
        }

        $accuracy = $correct / count(self::SAMPLES);
        fwrite(STDERR, sprintf("\n[local-keywords-v2] held-out accuracy: %.1f%% (%d/%d)\n", $accuracy * 100, $correct, count(self::SAMPLES)));
        foreach ($failures as $f) {
            fwrite(STDERR, "  ✗ {$f}\n");
        }

        $this->assertGreaterThanOrEqual(0.80, $accuracy, implode("\n", $failures));
    }

    public function test_stemmer_handles_syrian_dialect_prefixes(): void
    {
        $clf = app(LocalKeywordClassifier::class);
        $this->assertSame($clf->stem('كهربا'), $clf->stem('بالكهربا'));
        $this->assertSame('شارع', $clf->stem('عالشارع'));
        $this->assertSame('شرار', $clf->stem('شراره'));
    }
}
