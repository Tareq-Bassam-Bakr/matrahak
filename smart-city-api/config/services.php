<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | خدمة الذكاء الاصطناعي (Python FastAPI) — خدمة حسابية مستقلة:
    | Laravel يرسل لها كل البيانات المطلوبة، وهي لا تتصل بـ Laravel أبداً.
    */
    'ai' => [
        'url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'key' => env('AI_SERVICE_KEY', 'change-this-internal-key'),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 8),
    ],

    /*
    | Firebase Cloud Messaging (HTTP v1) — اختياري.
    | إذا لم يُضبط، تبقى الإشعارات تعمل داخل التطبيق (محفوظة بقاعدة البيانات)
    | ويُعطَّل الـ Push فقط. ضع مسار ملف Service Account كمسار نسبي من جذر المشروع.
    */
    'fcm' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

];
