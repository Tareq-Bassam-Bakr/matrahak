<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Database\Seeders;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\District;
use App\Models\Report;
use App\Models\User;
use App\Services\Ai\InsightService;
use App\Services\Ai\PredictionService;
use App\Services\Ai\RiskScoringService;
use App\Services\Analytics\AnalyticsService;
use App\Services\Geo\DistrictLocator;
use App\Services\Map\MapService;
use App\Services\Reports\EmployeeStatsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * بيانات عرض واقعية لمشروع التخرج (اختيارية — لا تُشغَّل مع db:seed العادي):
 *     php artisan db:seed --class=DemoReportsSeeder
 *
 * - أحياء ومناطق حقيقية في المحافظات الـ 14 بإحداثيات فعلية تقريبية.
 * - ~800 بلاغ على مدى 90 يوماً بدورة حياة كاملة (إسناد، معالجة، حل، تقييم، رفض، تأخير).
 * - أنماط مقصودة ليكتشفها الذكاء الاصطناعي: موجة انقطاع كهرباء في حلب (آخر 3 أيام)،
 *   واتجاه متصاعد لانقطاع المياه في ريف دمشق (آخر أسبوعين).
 *
 * المحافظة تُحدَّد لكل بلاغ من إحداثياته عبر حدود OCHA (كما في التطبيق الحقيقي).
 * آمن للتشغيل أكثر من مرة: يحذف بيانات العرض السابقة فقط (حسابات demo.*).
 */
class DemoReportsSeeder extends Seeder
{
    /** [المحافظة => [[الحي, lat, lng], ...]] */
    private const PLACES = [
        'دمشق' => [
            ['المزة', 33.5000, 36.2450], ['المالكي', 33.5190, 36.2780], ['باب توما', 33.5130, 36.3170],
            ['الميدان', 33.4950, 36.3000], ['ركن الدين', 33.5350, 36.2950], ['كفرسوسة', 33.4980, 36.2670],
            ['البرامكة', 33.5080, 36.2880], ['الشعلان', 33.5160, 36.2850], ['القصاع', 33.5180, 36.3150],
            ['مساكن برزة', 33.5530, 36.3130], ['الصالحية', 33.5230, 36.2900], ['المهاجرين', 33.5280, 36.2780],
        ],
        'ريف دمشق' => [
            ['جرمانا', 33.4870, 36.3450], ['صحنايا', 33.4280, 36.2300], ['قدسيا', 33.5450, 36.2350],
            ['دوما', 33.5720, 36.4030], ['التل', 33.6100, 36.3100], ['الكسوة', 33.3580, 36.2380],
            ['يبرود', 33.9670, 36.6570], ['الزبداني', 33.7250, 36.1000], ['قطنا', 33.4390, 36.0790],
        ],
        'حلب' => [
            ['العزيزية', 36.2080, 37.1430], ['الجميلية', 36.2050, 37.1460], ['حلب الجديدة', 36.2120, 37.1100],
            ['الشهباء', 36.2280, 37.1220], ['السليمانية', 36.2100, 37.1580], ['الفرقان', 36.2190, 37.1170],
            ['الحمدانية', 36.1790, 37.1070], ['صلاح الدين', 36.1870, 37.1210], ['السفيرة', 36.0700, 37.3720],
        ],
        'حمص' => [
            ['الإنشاءات', 34.7210, 36.6980], ['الحمراء', 34.7310, 36.7050], ['الوعر', 34.7370, 36.6630],
            ['عكرمة', 34.7180, 36.7180], ['الزهراء', 34.7280, 36.7350], ['الغوطة', 34.7260, 36.7000],
            ['تلكلخ', 34.6700, 36.2600],
        ],
        'حماة' => [
            ['الحاضر', 35.1380, 36.7570], ['البعث', 35.1300, 36.7400], ['القصور', 35.1430, 36.7480],
            ['طريق حلب', 35.1550, 36.7550], ['السلمية', 35.0110, 37.0530], ['مصياف', 35.0650, 36.3420],
        ],
        'اللاذقية' => [
            ['الزراعة', 35.5320, 35.7920], ['الشيخ ضاهر', 35.5200, 35.7850], ['الرمل الجنوبي', 35.5050, 35.7850],
            ['مشروع الصليبة', 35.5250, 35.7830], ['جبلة', 35.3610, 35.9250], ['الأزهري', 35.5350, 35.8000],
        ],
        'طرطوس' => [
            ['الكورنيش البحري', 34.8900, 35.8800], ['المشبكة', 34.8850, 35.8900], ['بانياس', 35.1820, 35.9490],
            ['صافيتا', 34.8210, 36.1170], ['الدريكيش', 34.8960, 36.1370],
        ],
        'إدلب' => [
            ['إدلب المدينة', 35.9300, 36.6330], ['أريحا', 35.8120, 36.6100], ['سراقب', 35.8640, 36.8000],
            ['معرة النعمان', 35.6480, 36.6720], ['جسر الشغور', 35.8130, 36.3180],
        ],
        'الحسكة' => [
            ['الحسكة المدينة', 36.5000, 40.7470], ['القامشلي', 37.0520, 41.2310], ['رأس العين', 36.8500, 40.0700],
            ['المالكية', 37.1720, 42.1340], ['عامودا', 37.1040, 40.9300],
        ],
        'دير الزور' => [
            ['دير الزور المدينة', 35.3360, 40.1410], ['الميادين', 35.0190, 40.4530], ['البوكمال', 34.4530, 40.9180],
        ],
        'الرقة' => [
            ['الرقة المدينة', 35.9530, 39.0090], ['الطبقة', 35.8360, 38.5480], ['تل أبيض', 36.6980, 38.9570],
        ],
        'درعا' => [
            ['درعا المحطة', 32.6250, 36.1060], ['إزرع', 32.8670, 36.2510], ['بصرى الشام', 32.5180, 36.4810],
            ['نوى', 32.8910, 36.0430], ['الصنمين', 33.0720, 36.1840],
        ],
        'السويداء' => [
            ['السويداء المدينة', 32.7090, 36.5660], ['شهبا', 32.8550, 36.6290], ['صلخد', 32.4920, 36.7110],
        ],
        'القنيطرة' => [
            ['خان أرنبة', 33.1750, 35.8920], ['مدينة البعث', 33.1500, 35.8680],
        ],
    ];

    /** وزن كل محافظة في توزيع البلاغات (تقريباً حسب السكان) */
    private const DISTRICT_WEIGHTS = [
        'دمشق' => 16, 'ريف دمشق' => 15, 'حلب' => 18, 'حمص' => 9, 'حماة' => 8, 'اللاذقية' => 7, 'طرطوس' => 5,
        'إدلب' => 5, 'الحسكة' => 5, 'دير الزور' => 4, 'الرقة' => 3, 'درعا' => 4, 'السويداء' => 3, 'القنيطرة' => 1,
    ];

    /** slug => [وزن التكرار, [عناوين], [أوصاف]] — {p} = اسم الحي */
    private const TEMPLATES = [
        'garbage' => [12, ['تراكم القمامة في {p}', 'حاوية الزبالة ممتلئة من أيام', 'نفايات مرمية جنب المدرسة'],
            ['الزبالة متراكمة جنب الحاوية بـ{p} من أكثر من ٣ أيام والريحة صارت لا تحتمل، والقطط والكلاب عم تفرّقها بالشارع.',
                'ما حدا عم يرحّل القمامة من الحاويات بالحي، صار في أكياس مرمية عالرصيف وعم تسد الطريق.']],
        'road-damage' => [10, ['حفرة كبيرة بالطريق الرئيسي', 'جورة خطيرة قرب الإشارة', 'تشققات بالزفت بعد الأمطار'],
            ['في جورة كبيرة بنص الشارع بـ{p} عم تخرّب السيارات، وبالليل ما بتنشاف أبداً وصار في أكتر من حادث.',
                'الطريق مكسّر وبحاجة تزفيت، الحفر عم تكبر كل يوم وخاصة بعد المطر.']],
        'power-outage' => [14, ['انقطاع الكهرباء عن الحي', 'الكهربا مقطوعة من الصبح', 'عطل بالمحولة'],
            ['الكهربا مقطوعة عن {p} من أكتر من ١٢ ساعة بغير أوقات التقنين، وفي مرضى بالبيوت بحاجة أجهزة.',
                'المحولة بالحي عم تطلع صوت وانطفت الكهربا عن كل البناية والبنايات اللي حواليها.']],
        'street-light-failure' => [8, ['إنارة الشارع معطلة', 'الشارع معتم بالليل', 'أعمدة إنارة لا تعمل'],
            ['أعمدة الإنارة بالشارع الرئيسي بـ{p} مطفية من أسبوعين، والشارع صاير معتم وخطر عالمشاة.',
                'لمبات الإنارة محروقة وما حدا بدّلها، الأولاد عم يرجعوا من الدروس بالعتمة.']],
        'drinking-water-outage' => [9, ['انقطاع مياه الشرب', 'المي مقطوعة عن الحي', 'ضعف ضخ المياه'],
            ['المي مقطوعة عن {p} من ٣ أيام وما في أي إعلان، الناس صارت تشتري صهاريج بأسعار عالية.',
                'ضخ المياه ضعيف كتير وما عم توصل للطوابق العليا أبداً.']],
        'street-water-leak' => [6, ['تسرب مياه في الشارع', 'كسر بخط المياه الرئيسي', 'مي عم تهدر بالطريق'],
            ['في كسر بخط المي الرئيسي بـ{p} والمي عم تهدر بالشارع من يومين وعم تعمل برك كبيرة.',
                'تسريب مي من تحت الإسفلت، الطريق عم يهبط والمي ضايعة.']],
        'sewage-overflow' => [6, ['طفح الصرف الصحي', 'ريغار طافح بالشارع', 'روائح مجارير قوية'],
            ['الريغار طافح بـ{p} والمياه الآسنة عم تمشي بالشارع جنب البيوت، الريحة كتير قوية وخطر على صحة الأطفال.',
                'انسداد بشبكة الصرف الصحي والمي عم ترجع عالبيوت بالطابق الأرضي.']],
        'blocked-drains' => [5, ['مجرور مطر مسدود', 'تجمع مياه الأمطار', 'فتحات التصريف مسدودة'],
            ['فتحات تصريف مياه الأمطار مسدودة بـ{p} وكل ما تشتي بتصير الطريق بحيرة.',
                'المجرور مسدود بالأتربة والأوراق وبحاجة تنظيف قبل الشتوية.']],
        'traffic-congestion' => [6, ['ازدحام خانق على المفرق', 'عجقة سير يومية', 'اختناق مروري وقت الذروة'],
            ['في عجقة كبيرة كل يوم الصبح عند مفرق {p}، السيارات واقفة ساعة كاملة وبحاجة شرطي مرور أو تنظيم.',
                'ازدحام خانق بسبب الوقوف المزدوج والبسطات على جانبي الطريق.']],
        'traffic-signal-failure' => [4, ['إشارة المرور معطلة', 'الإشارة الضوئية مطفية', 'إشارة تعطي أخضر للاتجاهين'],
            ['إشارة المرور عند تقاطع {p} معطلة من يومين وصار في فوضى وخطر حوادث.',
                'الإشارة الضوئية عم تعطي إشارات غلط وفي سيارات كادت تتصادم.']],
        'sidewalk-damage' => [4, ['رصيف مكسور', 'بلاط الرصيف مقلوع', 'رصيف خطر على المشاة'],
            ['بلاط الرصيف مقلوع بـ{p} وكبار السن عم يتعثروا، وفي حفرة بنص الرصيف.',
                'الرصيف مكسر بالكامل والناس عم تمشي بنص الطريق.']],
        'damaged-road-sign' => [2, ['لافتة طرقية ساقطة', 'شاخصة مرورية مكسورة'],
            ['لافتة الاتجاهات ساقطة عالأرض بـ{p} وما عاد في دلالة للطريق.']],
        'exposed-wires' => [3, ['أسلاك كهربائية مكشوفة', 'شريط كهربا مقطوع عالأرض', 'كبل مكشوف جنب المدرسة'],
            ['في شريط كهربا مقطوع ومرمي عالأرض بـ{p} وعم يطلع شرار، خطر جداً عالأطفال، لازم تدخل فوري.',
                'أسلاك كهربائية مكشوفة بعلبة التوزيع والباب مخلوع.']],
        'dangerous-electric-pole' => [2, ['عمود كهرباء مائل', 'عمود كهربائي آيل للسقوط'],
            ['عمود الكهربا بـ{p} مايل كتير بعد العاصفة وممكن يوقع على السيارات والمارة.']],
        'waste-burning' => [3, ['حرق نفايات قرب البيوت', 'دخان حرق زبالة'],
            ['في ناس عم تحرق الزبالة كل مسا بالأرض الفاضية بـ{p} والدخان مالي البيوت وفي أطفال عندهن ربو.']],
        'environmental-violation' => [2, ['ورشة ترمي زيوت بالشارع', 'مخالفة بيئية من معمل'],
            ['ورشة تصليح سيارات بـ{p} عم ترمي الزيوت المستعملة بالمجرور والشارع.']],
        'water-pollution' => [1, ['تلوث مياه النهر', 'رمي مخلفات بالساقية'],
            ['المي بالساقية قرب {p} لونها متغير وريحتها كريهة، في مخلفات عم ترمى فيها.']],
        'collapsing-building' => [2, ['بناء آيل للسقوط', 'تشققات خطيرة بجدران بناية'],
            ['في بناية قديمة بـ{p} صار فيها تشققات كبيرة بعد المطر وعم يوقع منها حجار عالرصيف، خطر على الساكنين والمارة.']],
        'fire' => [1, ['حريق بأرض زراعية', 'حريق بمحل تجاري'],
            ['في حريق عم يمتد بالأعشاب اليابسة جنب البيوت بـ{p}، بحاجة إطفاء بسرعة.']],
        'public-safety-hazard' => [2, ['فتحة ريغار مكشوفة', 'حفرة بدون حماية'],
            ['غطا الريغار مسروق والفتحة مكشوفة بنص الطريق بـ{p}، ممكن يوقع فيها طفل أو موتور.']],
        'internet-outage' => [5, ['انقطاع الإنترنت', 'الإنترنت ضعيف جداً', 'خط الهاتف والإنترنت مقطوع'],
            ['الإنترنت مقطوع عن {p} من يومين، الطلاب والموظفين اللي بيشتغلوا أونلاين متضررين.',
                'سرعة الإنترنت ضعيفة كتير وبتقطع كل شوي من بعد عطل بالمقسم.']],
        'telecom-network-failure' => [2, ['ضعف تغطية الشبكة الخلوية', 'عطل بكبل الهاتف الأرضي'],
            ['تغطية الموبايل ضعيفة كتير بـ{p} وما فينا نتصل حتى بالطوارئ.']],
        'health-violation' => [2, ['مطعم مخالف للشروط الصحية', 'بيع مواد غذائية منتهية الصلاحية'],
            ['مطعم بـ{p} عم يشتغل بشروط صحية سيئة وفي حشرات بالمطبخ.']],
        'contaminated-water' => [1, ['مياه الشرب ملوثة', 'لون المي متغير'],
            ['مي الشرب عم تجي لونها أصفر وريحتها غريبة بـ{p}، وفي أطفال مرضوا.']],
    ];

    private const FIRST = ['محمد', 'أحمد', 'علي', 'خالد', 'عمر', 'يوسف', 'حسن', 'سامر', 'رامي', 'باسل', 'فادي', 'مازن', 'وسيم', 'طارق', 'نزار',
        'فاطمة', 'رهف', 'لين', 'سارة', 'هبة', 'ريم', 'نور', 'دانة', 'غنى', 'ميس', 'رنا', 'لمى', 'سلمى', 'جود', 'مريم'];

    private const LAST = ['الأحمد', 'الحسن', 'الخطيب', 'السيد', 'العلي', 'الشامي', 'الحلبي', 'الحمصي', 'الدمشقي', 'الإدلبي', 'النجار',
        'الحداد', 'الصباغ', 'القاسم', 'الحموي', 'الزعبي', 'المصري', 'الكردي', 'العمر', 'الجاسم'];

    private array $locator = [];

    public function run(): void
    {
        mt_srand(2026);
        AuditLog::$disabled = true;

        $this->command?->info('حذف بيانات العرض السابقة...');
        $this->cleanup();

        // أسماء المحافظات في قاعدة البيانات بصيغة «محافظة دمشق» => نفهرسها بالاسم المختصر
        $districts = District::query()->get()->keyBy(fn (District $d) => trim(str_replace('محافظة', '', $d->name)));
        $categories = Category::query()->with('department')->get()->keyBy('slug');
        $locator = app(DistrictLocator::class);

        $citizens = $this->makeCitizens($districts);
        $employees = $this->makeEmployees();

        // 1) قائمة البلاغات المخطط لها (يوم + محافظة + تصنيف)
        $plan = [];
        for ($age = 89; $age >= 0; $age--) {
            $day = today()->subDays($age);
            $base = 6 + (in_array($day->dayOfWeek, [Carbon::SUNDAY, Carbon::MONDAY], true) ? 3 : 0) - ($day->dayOfWeek === Carbon::FRIDAY ? 3 : 0);
            $count = max(2, $base + mt_rand(-2, 3));
            for ($i = 0; $i < $count; $i++) {
                $plan[] = [$age, $this->weighted(self::DISTRICT_WEIGHTS), $this->weighted(array_map(fn ($t) => $t[0], self::TEMPLATES))];
            }
            // اتجاه متصاعد: انقطاع المياه في ريف دمشق خلال آخر 14 يوماً
            if ($age < 14) {
                for ($i = 0; $i < intdiv(14 - $age, 3) + 1; $i++) {
                    $plan[] = [$age, 'ريف دمشق', 'drinking-water-outage'];
                }
            }
            // شذوذ: موجة انقطاع كهرباء في حلب آخر 3 أيام
            if ($age < 3) {
                for ($i = 0; $i < 6; $i++) {
                    $plan[] = [$age, 'حلب', 'power-outage'];
                }
            }
        }

        $this->command?->info('إنشاء '.count($plan).' بلاغ...');
        $created = 0;
        $recentByCell = [];

        DB::transaction(function () use ($plan, $districts, $categories, $locator, $citizens, $employees, &$created, &$recentByCell) {
            foreach ($plan as [$age, $districtName, $slug]) {
                $category = $categories[$slug] ?? null;
                if (! $category || ! isset(self::PLACES[$districtName])) {
                    continue;
                }
                [$place, $lat, $lng] = self::PLACES[$districtName][array_rand(self::PLACES[$districtName])];
                // انتشار داخل الحي (~0-600 م)
                $lat += mt_rand(-550, 550) / 100000;
                $lng += mt_rand(-550, 550) / 100000;

                $district = $locator->locate($lat, $lng) ?? $districts[$districtName] ?? null;
                $createdAt = today()->subDays($age)->setTime(mt_rand(7, 22), mt_rand(0, 59), mt_rand(0, 59));
                if ($createdAt->isFuture()) {
                    $createdAt = now()->subMinutes(mt_rand(5, 120));
                }

                $report = $this->makeReport($category, $district, $place, $lat, $lng, $createdAt, $citizens, $employees);
                $created++;

                // تكرار محتمل: نفس التصنيف في نفس الحي خلال يومين
                $cell = $slug.'|'.$place;
                if (isset($recentByCell[$cell]) && $recentByCell[$cell]['at']->diffInHours($createdAt) < 48 && mt_rand(1, 100) <= 35) {
                    $report->forceFill([
                        'ai_is_duplicate' => true,
                        'ai_duplicate_of' => $recentByCell[$cell]['id'],
                        'ai_duplicate_score' => mt_rand(62, 91) / 100,
                    ])->saveQuietly();
                }
                $recentByCell[$cell] = ['id' => $report->id, 'at' => $createdAt];
            }
        });

        $this->command?->info("تم إنشاء {$created} بلاغ — تحديث الإحصائيات والذكاء الاصطناعي...");

        $stats = app(EmployeeStatsService::class);
        User::query()->where('user_type', 'employee')->each(fn (User $u) => $stats->refresh($u));

        app(RiskScoringService::class)->recalculate();
        $map = app(MapService::class);
        for ($i = 29; $i >= 0; $i--) {
            $map->snapshotDay(today()->subDays($i));
        }
        app(AnalyticsService::class)->snapshot('daily', today());
        $p = app(PredictionService::class)->generate();
        $i = app(InsightService::class)->generate();

        AuditLog::$disabled = false;

        $this->command?->info('✓ '.$p['message']);
        $this->command?->info('✓ '.$i['message']);
        $this->command?->info('✓ مواطنون تجريبيون: demo.citizen1@matrahak.sy … كلمة المرور Citizen@1234');
    }

    private function cleanup(): void
    {
        $demoIds = User::withTrashed()->where('email', 'like', User::DEMO_EMAIL_PATTERN)->pluck('id');
        $reportIds = Report::withTrashed()->whereIn('citizen_id', $demoIds)->pluck('id');

        foreach ($reportIds->chunk(500) as $chunk) {
            DB::table('report_upvotes')->whereIn('report_id', $chunk)->delete();
            DB::table('report_ratings')->whereIn('report_id', $chunk)->delete();
            DB::table('report_updates')->whereIn('report_id', $chunk)->delete();
            DB::table('report_assignments')->whereIn('report_id', $chunk)->delete();
            DB::table('report_attachments')->whereIn('report_id', $chunk)->delete();
            DB::table('ai_classifications')->whereIn('report_id', $chunk)->delete();
            DB::table('reports')->whereIn('ai_duplicate_of', $chunk)->update(['ai_duplicate_of' => null]);
            DB::table('reports')->whereIn('id', $chunk)->delete();
        }
        DB::table('report_assignments')->whereIn('employee_id', $demoIds)->delete();
        DB::table('report_attachments')->whereIn('uploader_id', $demoIds)->delete();
        DB::table('employee_stats')->whereIn('employee_id', $demoIds)->delete();
        DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $demoIds)->delete();
        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $demoIds)->delete();
        DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $demoIds)->delete();
        DB::table('users')->whereIn('id', $demoIds)->delete();
        DB::table('ai_predictions')->delete();
        DB::table('ai_insights')->delete();
        DB::table('heatmap_snapshots')->delete();
    }

    private function makeCitizens($districts)
    {
        $out = collect();
        $password = Hash::make('Citizen@1234');
        for ($n = 1; $n <= 60; $n++) {
            $name = self::FIRST[($n * 7) % count(self::FIRST)].' '.self::LAST[($n * 3) % count(self::LAST)];
            $district = $districts->values()[$n % $districts->count()] ?? null;
            $out->push(User::query()->create([
                'name' => $name,
                'email' => "demo.citizen{$n}@matrahak.sy",
                'phone' => '09'.str_pad((string) (30000000 + $n * 7919), 8, '0', STR_PAD_LEFT),
                'user_type' => 'citizen',
                'district_id' => $district?->id,
                'is_active' => true,
                'is_verified' => true,
                'password' => $password,
                'email_verified_at' => now(),
            ]));
        }

        return $out;
    }

    /** موظفان إضافيان لكل مديرية + موظفو UserSeeder => توزيع عبء واقعي */
    private function makeEmployees(): array
    {
        $password = Hash::make('Employee@1234');
        $n = 0;
        foreach (Department::query()->get() as $d) {
            for ($i = 1; $i <= 2; $i++) {
                $n++;
                User::query()->create([
                    'name' => self::FIRST[($n * 5) % 15].' '.self::LAST[($n * 11) % count(self::LAST)],
                    'email' => 'demo.employee'.$i.'.'.strtolower($d->code).'@matrahak.sy',
                    'phone' => '09'.str_pad((string) (50000000 + $n * 104729), 8, '0', STR_PAD_LEFT),
                    'user_type' => 'employee',
                    'department_id' => $d->id,
                    'is_active' => true,
                    'is_verified' => true,
                    'password' => $password,
                    'email_verified_at' => now(),
                ]);
            }
        }

        return User::query()->where('user_type', 'employee')->where('is_active', true)->get()->groupBy('department_id')->all();
    }

    private function makeReport(Category $category, ?District $district, string $place, float $lat, float $lng, Carbon $createdAt, $citizens, array $employees): Report
    {
        [, $titles, $descs] = self::TEMPLATES[$category->slug];
        $title = str_replace('{p}', $place, $titles[array_rand($titles)]);
        if (! str_contains($title, $place)) {
            $title .= ' — '.$place;
        }
        $description = str_replace('{p}', $place, $descs[array_rand($descs)]);

        $priority = $category->basePriority();
        if (mt_rand(1, 100) <= 7) {
            $priority = ReportPriority::from(['medium', 'high', 'critical'][min(2, $priority->rank())]);
        }
        $slaHours = (int) ($category->sla_hours ?: $category->department?->avg_resolution_hours ?: 72);
        $ageHours = $createdAt->diffInHours(now());

        // الحالة النهائية حسب عمر البلاغ
        $roll = mt_rand(1, 100);
        $status = match (true) {
            $ageHours < 6 => $roll <= 55 ? ReportStatus::Pending : ReportStatus::Assigned,
            $ageHours < 48 => $roll <= 15 ? ReportStatus::UnderReview : ($roll <= 50 ? ReportStatus::Assigned : ($roll <= 85 ? ReportStatus::InProgress : ReportStatus::Resolved)),
            $ageHours < 24 * 7 => $roll <= 8 ? ReportStatus::Assigned : ($roll <= 28 ? ReportStatus::InProgress : ($roll <= 68 ? ReportStatus::Resolved : ($roll <= 92 ? ReportStatus::Closed : ReportStatus::Rejected))),
            $ageHours < 24 * 30 => $roll <= 2 ? ReportStatus::Assigned : ($roll <= 7 ? ReportStatus::InProgress : ($roll <= 25 ? ReportStatus::Resolved : ($roll <= 92 ? ReportStatus::Closed : ReportStatus::Rejected))),
            default => $roll <= 1 ? ReportStatus::InProgress : ($roll <= 6 ? ReportStatus::Resolved : ($roll <= 93 ? ReportStatus::Closed : ReportStatus::Rejected)),
        };

        $citizen = $citizens->random();
        $deptEmployees = $employees[$category->department_id] ?? collect();
        $employee = $deptEmployees instanceof \Illuminate\Support\Collection && $deptEmployees->isNotEmpty() ? $deptEmployees->random() : null;
        if (! $employee && ! in_array($status, [ReportStatus::Pending, ReportStatus::UnderReview, ReportStatus::Rejected], true)) {
            $status = ReportStatus::UnderReview;
        }

        $assignedAt = $createdAt->copy()->addMinutes(mt_rand(10, 360));
        $startedAt = $assignedAt->copy()->addMinutes(mt_rand(30, 600));
        // زمن الحل: معظمها ضمن المهلة وبعضها يتجاوزها (لقياس SLA)
        $resolveHours = $slaHours * (mt_rand(1, 100) <= 78 ? mt_rand(15, 95) / 100 : mt_rand(105, 220) / 100);
        $resolvedAt = $createdAt->copy()->addMinutes((int) ($resolveHours * 60));
        if ($resolvedAt->isFuture()) {
            $resolvedAt = now()->subMinutes(mt_rand(5, 60));
        }
        if ($startedAt->greaterThan($resolvedAt)) {
            $startedAt = $assignedAt->copy()->addMinutes(5);
        }
        $closedAt = $resolvedAt->copy()->addHours(mt_rand(2, 96));
        if ($closedAt->isFuture()) {
            $closedAt = now();
        }

        $isResolved = in_array($status, [ReportStatus::Resolved, ReportStatus::Closed], true);
        $aiCorrect = mt_rand(1, 100) <= 86;

        $report = Report::query()->create([
            'citizen_id' => $citizen->id,
            'category_id' => $category->id,
            'department_id' => $category->department_id,
            'district_id' => $district?->id,
            'title' => Str::limit($title, 190, ''),
            'description' => $description,
            'status' => $status,
            'priority' => $priority,
            'latitude' => round($lat, 7),
            'longitude' => round($lng, 7),
            'address' => $place.'، '.($district?->name ?? ''),
            'ai_category_id' => $aiCorrect ? $category->id : Category::query()->where('department_id', $category->department_id)->inRandomOrder()->value('id'),
            'ai_department_id' => $category->department_id,
            'ai_priority' => $priority,
            'ai_confidence' => mt_rand(58, 97) / 100,
            'ai_summary' => Str::limit($description, 110),
            'ai_processed_at' => $createdAt->copy()->addSeconds(mt_rand(2, 30)),
            'assigned_at' => $employee && $status !== ReportStatus::Pending && $status !== ReportStatus::UnderReview ? $assignedAt : null,
            'started_at' => in_array($status, [ReportStatus::InProgress, ReportStatus::Resolved, ReportStatus::Closed], true) ? $startedAt : null,
            'resolved_at' => $isResolved ? $resolvedAt : null,
            'closed_at' => $status === ReportStatus::Closed ? $closedAt : ($status === ReportStatus::Rejected ? $createdAt->copy()->addHours(mt_rand(1, 30)) : null),
            'expected_resolution' => $createdAt->copy()->addHours($slaHours),
            'overdue_notified_at' => null,
            'is_anonymous' => mt_rand(1, 100) <= 8,
            'upvotes_count' => 0,
            'views_count' => mt_rand(0, 40),
        ]);
        // created_at مضبوط على تاريخ البلاغ الحقيقي (create() يضع الوقت الحالي)
        $report->forceFill(['created_at' => $createdAt, 'updated_at' => $closedAt->min(now())])->saveQuietly();
        $report->forceFill(['ticket_number' => sprintf('%s-%s-%06d', config('smartcity.ticket_prefix', 'SC'), $createdAt->format('Y'), $report->id)])->saveQuietly();

        $updates = [[
            'type' => 'created', 'user_id' => $citizen->id, 'old_value' => null, 'new_value' => ReportStatus::Pending->value,
            'note' => 'تم استلام البلاغ', 'is_public' => true, 'created_at' => $createdAt,
        ], [
            'type' => 'ai_update', 'user_id' => null, 'old_value' => null, 'new_value' => $category->slug,
            'note' => 'اقتراح الذكاء الاصطناعي: '.$category->name.' — ثقة '.(int) round($report->ai_confidence * 100).'%',
            'is_public' => false, 'created_at' => $createdAt->copy()->addSeconds(20),
        ]];

        if ($report->assigned_at && $employee) {
            DB::table('report_assignments')->insert([
                'report_id' => $report->id,
                'employee_id' => $employee->id,
                'assigned_by' => null,
                'department_id' => $category->department_id,
                'method' => mt_rand(1, 100) <= 80 ? 'auto' : 'claim',
                'is_active' => true,
                'notes' => null,
                'assigned_at' => $assignedAt,
                'completed_at' => $isResolved ? $resolvedAt : null,
            ]);
            $updates[] = ['type' => 'assignment', 'user_id' => null, 'old_value' => null, 'new_value' => $employee->name,
                'note' => 'إسناد تلقائي إلى الموظف الأقل عبئاً في المديرية', 'is_public' => false, 'created_at' => $assignedAt];
            $updates[] = ['type' => 'status_change', 'user_id' => null, 'old_value' => 'pending', 'new_value' => 'assigned',
                'note' => 'تم إسناد بلاغك إلى فريق '.$category->department?->name, 'is_public' => true, 'created_at' => $assignedAt];
        }
        if ($report->started_at) {
            $updates[] = ['type' => 'status_change', 'user_id' => $employee?->id, 'old_value' => 'assigned', 'new_value' => 'in_progress',
                'note' => 'الفريق في الموقع وبدأ العمل', 'is_public' => true, 'created_at' => $startedAt];
        }
        if ($isResolved) {
            $updates[] = ['type' => 'status_change', 'user_id' => $employee?->id, 'old_value' => 'in_progress', 'new_value' => 'resolved',
                'note' => 'تمت معالجة المشكلة', 'is_public' => true, 'created_at' => $resolvedAt];
        }
        if ($status === ReportStatus::Rejected) {
            $updates[] = ['type' => 'status_change', 'user_id' => $employee?->id, 'old_value' => 'under_review', 'new_value' => 'rejected',
                'note' => mt_rand(0, 1) ? 'البلاغ مكرر — المشكلة نفسها قيد المعالجة ببلاغ سابق' : 'المشكلة خارج صلاحيات الجهة — تم التوجيه للجهة المعنية',
                'is_public' => true, 'created_at' => $report->closed_at];
        }

        // تقييم المواطن لنصف البلاغات المغلقة — أقل عند تجاوز المهلة
        if ($status === ReportStatus::Closed && mt_rand(1, 100) <= 55) {
            $late = $resolvedAt->greaterThan($report->expected_resolution);
            $rating = $late ? mt_rand(1, 4) : [3, 4, 4, 5, 5, 5][mt_rand(0, 5)];
            $ratedAt = $resolvedAt->copy()->addHours(mt_rand(1, 40))->min(now());
            DB::table('report_ratings')->insert([
                'report_id' => $report->id, 'citizen_id' => $citizen->id, 'employee_id' => $employee?->id,
                'rating' => $rating, 'feedback' => $rating >= 4 ? 'شكراً على سرعة الاستجابة' : ($rating <= 2 ? 'تأخرتوا كتير' : null),
                'rated_at' => $ratedAt,
            ]);
            $updates[] = ['type' => 'rating', 'user_id' => $citizen->id, 'old_value' => null, 'new_value' => (string) $rating,
                'note' => null, 'is_public' => true, 'created_at' => $ratedAt];
        }
        if ($status === ReportStatus::Closed) {
            $updates[] = ['type' => 'status_change', 'user_id' => null, 'old_value' => 'resolved', 'new_value' => 'closed',
                'note' => 'تم إغلاق البلاغ', 'is_public' => true, 'created_at' => $closedAt];
        }

        DB::table('report_updates')->insert(array_map(fn ($u) => $u + ['report_id' => $report->id], $updates));

        // تأييد من مواطنين آخرين
        $upvoters = $citizens->where('id', '!=', $citizen->id)->random(min(mt_rand(0, 6), 59));
        foreach ($upvoters as $u) {
            DB::table('report_upvotes')->insertOrIgnore(['report_id' => $report->id, 'user_id' => $u->id, 'created_at' => $createdAt->copy()->addHours(mt_rand(1, 48))->min(now())]);
        }
        if ($upvoters->count()) {
            $report->forceFill(['upvotes_count' => $upvoters->count()])->saveQuietly();
        }

        return $report;
    }

    /** @param array<string,int> $weights */
    private function weighted(array $weights): string
    {
        $total = array_sum($weights);
        $r = mt_rand(1, $total);
        foreach ($weights as $key => $w) {
            $r -= $w;
            if ($r <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
