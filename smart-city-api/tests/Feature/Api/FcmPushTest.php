<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ReportEventNotification;
use App\Services\Notifications\FcmService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Push عبر FCM HTTP v1 بدون مكتبة kreait: توقيع JWT حقيقي بمفتاح RSA مولَّد للاختبار
 * + محاكاة خوادم Google بـ Http::fake.
 */
class FcmPushTest extends TestCase
{
    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->credentialsPath = storage_path('framework/testing/firebase-test.json');
        @mkdir(dirname($this->credentialsPath), 0777, true);
        file_put_contents($this->credentialsPath, json_encode([
            'type' => 'service_account',
            'project_id' => 'smart-city-test',
            'private_key' => $pem,
            'client_email' => 'push@smart-city-test.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $this->credentialsPath, 'services.fcm.project_id' => null]);
        $this->app->forgetInstance(FcmService::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsPath);
        parent::tearDown();
    }

    public function test_push_is_sent_with_signed_jwt_and_android_channel(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/smart-city-test/messages/1']),
        ]);

        $citizen = $this->citizen();
        $citizen->forceFill(['citizen_fcm_token' => 'device-abc'])->save();

        $citizen->notify(new ReportEventNotification('status_change', 'تحديث البلاغ', 'بدأ العمل على بلاغك'));

        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'oauth2.googleapis.com')) {
                return false;
            }
            [$h, $c, $sig] = explode('.', $r['assertion']);
            $claims = json_decode(base64_decode(strtr($c, '-_', '+/')), true);

            return $r['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && $claims['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
                && $claims['iss'] === 'push@smart-city-test.iam.gserviceaccount.com'
                && $sig !== '';
        });

        Http::assertSent(fn (Request $r) => $r->url() === 'https://fcm.googleapis.com/v1/projects/smart-city-test/messages:send'
            && $r->hasHeader('Authorization', 'Bearer ya29.test')
            && $r['message']['token'] === 'device-abc'
            && $r['message']['notification']['title'] === 'تحديث البلاغ'
            && $r['message']['android']['notification']['channel_id'] === 'smart_city_channel'
            && ((array) $r['message']['data'])['type'] === 'status_change');

        // الإشعار محفوظ أيضاً داخل التطبيق
        $this->assertSame(1, $citizen->notifications()->count());
    }

    public function test_unregistered_token_is_removed(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND']], 404),
        ]);

        $employee = $this->employee();
        $employee->forceFill(['employee_fcm_token' => 'stale-token'])->save();
        $employee->notify(new ReportEventNotification('assignment', 'بلاغ جديد', 'نص'));

        $this->assertNull($employee->refresh()->employee_fcm_token);
    }

    public function test_without_firebase_nothing_is_sent_but_notification_is_saved(): void
    {
        config(['services.fcm.credentials' => null]);
        $this->app->forgetInstance(FcmService::class);
        Http::fake();

        $citizen = $this->citizen();
        $citizen->forceFill(['citizen_fcm_token' => 'device-abc'])->save();
        $citizen->notify(new ReportEventNotification('status_change', 'تحديث', 'نص'));

        Http::assertNothingSent();
        $this->assertSame(1, $citizen->notifications()->count());
        $this->assertFalse(app(FcmService::class)->isConfigured());
        $this->assertInstanceOf(User::class, $citizen);
    }
}
