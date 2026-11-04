<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Pages;

use App\Services\Ai\AiServiceClient;
use App\Services\Ai\TrainingDataService;
use App\Services\Notifications\FcmService;
use App\Services\Settings\SettingsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

/**
 * إعدادات النظام القابلة للتعديل بدون تعديل الكود أو ملف .env
 * + حالة خدمة الذكاء الاصطناعي و Firebase.
 *
 * @property Form $form
 */
class SystemSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'الإدارة';

    protected static ?string $navigationLabel = 'إعدادات النظام';

    protected static ?string $title = 'إعدادات النظام';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.system-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('settings.manage');
    }

    public function mount(): void
    {
        $this->form->fill(Arr::undot(app(SettingsService::class)->all()));
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('الذكاء الاصطناعي')
                    ->icon('heroicon-o-sparkles')
                    ->columns(2)
                    ->schema([
                        Toggle::make('ai.enabled')->label('تفعيل خدمة الذكاء الاصطناعي (Python)')
                            ->helperText('عند التعطيل أو توقف الخدمة يعمل المصنّف المحلي في Laravel تلقائياً.'),
                        Toggle::make('ai.image_analysis_enabled')->label('تحليل الصور المرفقة'),
                        TextInput::make('ai.auto_apply_threshold')
                            ->label('حد الثقة لتطبيق التصنيف تلقائياً')
                            ->numeric()->minValue(0.3)->maxValue(0.95)->step(0.05)->required()
                            ->helperText('يُطبَّق الاقتراح تلقائياً فقط إذا لم يختر المواطن تصنيفاً وكانت الثقة أعلى من هذا الحد.'),
                        TextInput::make('ai.duplicate_radius_m')
                            ->label('نطاق كشف التكرار (متر)')
                            ->numeric()->integer()->minValue(50)->maxValue(5000)->required(),
                        TextInput::make('ai.duplicate_lookback_days')
                            ->label('مدة البحث عن المكرر (يوم)')
                            ->numeric()->integer()->minValue(1)->maxValue(180)->required(),
                        Placeholder::make('ai_status')
                            ->label('حالة الخدمة')
                            ->content(fn () => $this->aiStatus()),
                    ]),
                Section::make('البلاغات والإسناد')
                    ->icon('heroicon-o-inbox-stack')
                    ->columns(2)
                    ->schema([
                        Toggle::make('assignment.auto_enabled')->label('الإسناد التلقائي للموظف الأقل عبئاً'),
                        Toggle::make('reports.allow_anonymous')->label('السماح بالبلاغات المجهولة للعامة'),
                        TextInput::make('reports.auto_close_days')
                            ->label('إغلاق البلاغ المحلول تلقائياً بعد (يوم)')
                            ->numeric()->integer()->minValue(1)->maxValue(60)->required()
                            ->helperText('إذا لم يقيّم المواطن البلاغ خلال هذه المدة يُغلق تلقائياً.'),
                    ]),
                Section::make('الإشعارات')
                    ->icon('heroicon-o-bell')
                    ->columns(2)
                    ->schema([
                        Toggle::make('notifications.notify_managers_on_critical')->label('إشعار الإدارة فور وصول بلاغ حرج'),
                        Placeholder::make('fcm_status')
                            ->label('Firebase Cloud Messaging')
                            ->content(fn () => $this->fcmStatus()),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = Arr::dot($this->form->getState());
        $values = [];
        foreach (array_keys(SettingsService::DEFAULTS) as $key) {
            if (! array_key_exists($key, $state)) {
                continue;
            }
            $default = SettingsService::DEFAULTS[$key];
            $values[$key] = match (true) {
                is_bool($default) => (bool) $state[$key],
                is_int($default) => (int) $state[$key],
                is_float($default) => (float) $state[$key],
                default => $state[$key],
            };
        }

        app(SettingsService::class)->setMany($values);

        Notification::make()->title('تم حفظ الإعدادات')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('حفظ الإعدادات')->submit('save'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkAi')
                ->label('فحص خدمة AI')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function () {
                    Cache::forget('settings.ai_health');
                    $health = app(AiServiceClient::class)->health();
                    $health
                        ? Notification::make()->title('خدمة الذكاء الاصطناعي تعمل')
                            ->body('الإصدار: '.($health['version'] ?? '—').' — المحرك: '.($health['classifier'] ?? '—')
                                .(($facts = self::modelFacts($health)) ? ' — '.collect($facts)->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ') : ''))
                            ->success()->send()
                        : Notification::make()->title('خدمة الذكاء الاصطناعي غير متاحة')
                            ->body('تأكد من تشغيلها (uvicorn) ومن AI_SERVICE_URL و AI_SERVICE_KEY في .env — النظام يعمل حالياً بالبديل المحلي.')
                            ->warning()->send();
                }),
            Action::make('retrainAi')
                ->label('إعادة تدريب النموذج')
                ->icon('heroicon-o-academic-cap')
                ->visible(fn () => app(AiServiceClient::class)->enabled())
                ->modalHeading('إعادة تدريب نموذج تصنيف البلاغات')
                ->modalIcon('heroicon-o-academic-cap')
                ->modalDescription(fn () => 'سيُرسل إلى خدمة الذكاء الاصطناعي '.number_format(app(TrainingDataService::class)->count())
                    .' من البلاغات الحقيقية موثّقة التصنيف (عولجت وأُغلقت، أو راجعت الإدارة تصنيفها — بيانات العرض التجريبية مستبعدة). '
                    .'تُدرَّب نسخة مرشّحة وتُقارن بالنموذج الحالي على بيانات اختبار لم يرها، ولا تُعتمد إلا إذا لم تكن '
                    .'أضعف بدلالة إحصائية (اختبار McNemar). تُحذف أرقام الهواتف والبريد من النصوص قبل التدريب.')
                ->modalSubmitActionLabel('بدء التدريب')
                ->form([
                    Toggle::make('include_synthetic')
                        ->label('دمج بيانات التدريب الأولية (المولّدة)')
                        ->helperText('يُنصح بإبقائه مفعّلاً حتى تتوفر بلاغات حقيقية كافية لكل التصنيفات.')
                        ->default(true),
                ])
                ->action(fn (array $data) => $this->retrain((bool) ($data['include_synthetic'] ?? true))),
            Action::make('checkFcm')
                ->label('فحص Firebase')
                ->icon('heroicon-o-bell-alert')
                ->color('gray')
                ->action(function () {
                    $fcm = app(FcmService::class);
                    if (! $fcm->isConfigured()) {
                        Notification::make()->title('Firebase غير مضبوط')
                            ->body('ضع ملف Service Account في storage/app/firebase/ واضبط FIREBASE_CREDENTIALS في .env. الإشعارات داخل التطبيق تعمل بدونه.')
                            ->warning()->send();

                        return;
                    }
                    try {
                        Cache::forget('fcm.access_token.'.md5((string) ($fcm->credentials()['client_email'] ?? '')));
                        $fcm->accessToken();
                        Notification::make()->title('الاتصال بـ Firebase ناجح')->body('المشروع: '.$fcm->projectId())->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('فشل الاتصال بـ Google')
                            ->body($e->getMessage().' — إن كانت خدمات Google محجوبة على الخادم تبقى الإشعارات داخل التطبيق فقط.')
                            ->danger()->send();
                    }
                }),
        ];
    }

    protected function retrain(bool $includeSynthetic = true): void
    {
        $samples = app(TrainingDataService::class)->samples();
        if ($samples === []) {
            Notification::make()->title('لا توجد بلاغات موثّقة بعد')
                ->body('يُدرَّب النموذج على بلاغات عولجت وأُغلقت أو راجعت الإدارة تصنيفها. عالج بعض البلاغات ثم أعد المحاولة.')
                ->warning()->send();

            return;
        }

        $result = app(AiServiceClient::class)->train($samples, $includeSynthetic);
        Cache::forget('settings.ai_health');
        $body = $result['body'] ?? [];

        if ($result['ok']) {
            $promoted = (bool) ($body['promoted'] ?? false);
            Notification::make()
                ->title($promoted ? 'تم اعتماد النموذج الجديد' : 'لم يُعتمد النموذج الجديد — بقي النموذج الحالي')
                ->body($body['message'] ?? null)
                ->status($promoted ? 'success' : 'warning')
                ->persistent()
                ->send();

            return;
        }

        $detail = $body['detail'] ?? null;
        Notification::make()->title('تعذّرت إعادة التدريب')
            ->body(match (true) {
                is_array($detail) && isset($detail['message']) => $detail['message'],
                $result['status'] === null => 'خدمة الذكاء الاصطناعي غير متاحة — تأكد من تشغيلها ومن AI_SERVICE_URL و AI_SERVICE_KEY.',
                default => 'ردّت الخدمة بالرمز '.$result['status'].'.',
            })
            ->danger()->persistent()->send();
    }

    private function aiStatus(): HtmlString
    {
        $client = app(AiServiceClient::class);
        if (! $client->enabled()) {
            return new HtmlString('<span style="color:#ca8a04">معطّلة أو AI_SERVICE_URL فارغ — يعمل المصنّف المحلي</span>');
        }
        $health = Cache::remember('settings.ai_health', 30, fn () => $client->health() ?? false);

        if (! $health) {
            return new HtmlString('<span style="color:#dc2626">● غير متاحة</span> — '.e((string) config('services.ai.url')).' (يعمل البديل المحلي)');
        }

        // <bdi dir="ltr"> يعزل النصوص الإنجليزية والأرقام حتى لا يختلط ترتيبها داخل السطر العربي
        $ltr = fn ($v) => '<bdi dir="ltr">'.e((string) $v).'</bdi>';
        $facts = collect(self::modelFacts($health))->map(fn ($v, $k) => e($k).': '.$ltr($v))->implode(' · ');

        return new HtmlString('<span style="color:#16a34a">● تعمل</span> — '.$ltr(config('services.ai.url')).' — '.$ltr($health['classifier'] ?? '')
            .($facts ? '<br><span style="font-size:.85em;opacity:.75">'.$facts.'</span>' : ''));
    }

    /**
     * ملخص النموذج من /health: آخر تدريب، عدد العينات، وأهم أرقام التقييم.
     *
     * @return array<string, string>
     */
    protected static function modelFacts(array $health): array
    {
        $model = $health['model'] ?? null;
        if (! is_array($model) || empty($model['ready'])) {
            return [];
        }
        $sources = (array) ($model['sources'] ?? []);
        $real = array_sum(array_diff_key($sources, ['synthetic' => 0]));
        $metrics = (array) ($model['metrics'] ?? []);
        $pct = fn ($v) => is_numeric($v) ? round($v * 100, 1).'%' : null;

        return array_filter([
            'آخر تدريب' => isset($model['trained_at'])
                ? Carbon::parse($model['trained_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') : null,
            'عينات التدريب' => number_format((int) ($model['n_samples'] ?? 0)),
            'منها بلاغات حقيقية' => number_format($real),
            'دقة البلاغات الحقيقية' => $pct($metrics['real_test_accuracy'] ?? null),
            'دقة اختبار الإجهاد' => $pct($metrics['challenge_accuracy'] ?? null),
            'Grouped CV' => $pct($metrics['cv_accuracy_mean'] ?? null),
        ], fn ($v) => $v !== null);
    }

    private function fcmStatus(): HtmlString
    {
        $fcm = app(FcmService::class);

        return $fcm->isConfigured()
            ? new HtmlString('<span style="color:#16a34a">● مضبوط</span> — المشروع: '.e((string) $fcm->projectId()))
            : new HtmlString('<span style="color:#ca8a04">● غير مضبوط</span> — الإشعارات تُحفظ وتظهر داخل التطبيقين فقط (بدون Push)');
    }
}
