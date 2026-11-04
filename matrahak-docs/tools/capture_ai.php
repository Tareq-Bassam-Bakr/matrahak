<?php
// يسجّل الطلبات الحقيقية التي يرسلها Laravel لخدمة الذكاء الاصطناعي وردودها، لتوثيق الـ API.
// شغّله من مجلد Laravel والخدمة تعمل على :8001:
//   php ..\matrahak-docs\tools\capture_ai.php ..\matrahak-docs\api-examples
// ⚠️ يشغّل أوامر التنبؤ والرؤى والخطورة فيكتب نتائجها في قاعدة البيانات — قاعدة تطوير فقط.
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\Report;
use App\Services\Ai\AiServiceClient;
use App\Services\Ai\ReportAiProcessor;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

$out = $argv[1];
@mkdir($out, 0777, true);
$log = [];
Event::listen(ResponseReceived::class, function (ResponseReceived $e) use (&$log) {
    $req = $e->request;
    $body = $req->isMultipart() ? ['multipart' => array_map(fn ($p) => ['name' => $p['name'], 'filename' => $p['filename'] ?? null], $req->data())] : $req->data();
    $log[] = [
        'method' => $req->method(),
        'url' => preg_replace('#^https?://[^/]+#', '', $req->url()),
        'headers' => ['X-Internal-Key' => $req->hasHeader('X-Internal-Key') ? '***' : null],
        'request' => $body,
        'status' => $e->response->status(),
        'response' => $e->response->json(),
    ];
});

$client = app(AiServiceClient::class);
$save = function (string $name) use (&$log, $out) {
    $last = array_pop($log);
    if ($last) {
        file_put_contents("$out/ai_$name.json", json_encode($last, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "saved ai_$name ({$last['status']} {$last['url']})\n";
    } else {
        echo "NO CALL for $name\n";
    }
    $log = [];
};

// 1) /health
$client->health();
$save('health');

// 2) /v1/classify — نفس الحمولة التي يبنيها ReportAiProcessor::classify
$categories = Category::query()->active()->with('department')->get();
$client->classify('تسرب مياه من أنبوب رئيسي', 'في تسريب مي قوي من أنبوب مكسور عم يغرق الشارع قدام البناية', $categories->map(fn (Category $c) => [
    'slug' => $c->slug,
    'name' => $c->name,
    'name_en' => $c->name_en,
    'keywords' => $c->keywords ?? [],
    'department_code' => $c->department?->code,
    'priority_weight' => $c->priority_weight,
])->values()->all());
$save('classify');

// 3) /v1/duplicates — بلاغ جديد مقابل بلاغات مفتوحة قريبة (نفس حمولة detectDuplicates)
$base = Report::query()->open()->whereNotNull('latitude')->latest('id')->first();
$cands = Report::query()->open()->where('id', '!=', $base->id)->nearby($base->latitude, $base->longitude, 1)->limit(5)->get();
$client->duplicates(
    ['id' => 'new', 'text' => $base->title.' — نفس المشكلة '.$base->description, 'lat' => $base->latitude, 'lng' => $base->longitude],
    collect([$base])->merge($cands)->map(fn (Report $c) => ['id' => (string) $c->id, 'text' => $c->title.' '.$c->description, 'lat' => $c->latitude, 'lng' => $c->longitude])->values()->all(),
    300,
);
$save('duplicates');

// 4) /v1/analyze-image — صورة مرفقة حقيقية
$att = App\Models\ReportAttachment::query()->where('file_type', 'image')->latest('id')->first();
if ($att && Storage::disk('public')->exists($att->file_path)) {
    $client->analyzeImage(Storage::disk('public')->path($att->file_path));
    $save('analyze_image');
}

// 5) /v1/model
Http::withHeaders(['X-Internal-Key' => config('services.ai.key')])->get(rtrim(config('services.ai.url'), '/').'/v1/model');
$save('model');

// 6) التنبؤ والرؤى والخطورة — عبر نفس الأوامر المجدولة
foreach (['predict' => 'ai:predictions', 'insights' => 'ai:insights', 'risk' => 'ai:risk-scores'] as $name => $cmd) {
    Artisan::call($cmd);
    echo trim(Artisan::output())."\n";
    $save($name);
}
