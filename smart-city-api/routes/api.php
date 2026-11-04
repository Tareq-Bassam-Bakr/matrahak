<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

use App\Http\Controllers\Api\V1\Auth\CitizenAuthController;
use App\Http\Controllers\Api\V1\Auth\EmployeeAuthController;
use App\Http\Controllers\Api\V1\Citizen\MapController as CitizenMapController;
use App\Http\Controllers\Api\V1\Citizen\ReportController as CitizenReportController;
use App\Http\Controllers\Api\V1\Employee\AnalyticsController as EmployeeAnalyticsController;
use App\Http\Controllers\Api\V1\Employee\ReportController as EmployeeReportController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\Shared\LookupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — كل المسارات تبدأ بـ /api/v1
|--------------------------------------------------------------------------
| citizen.*  => تطبيق المواطن   (Sanctum + citizen.only)
| employee.* => تطبيق الموظف    (Sanctum + employee.only)
| notifications => مشتركة للتطبيقين
| shared => بيانات مرجعية بدون تسجيل دخول
*/

Route::prefix('v1')->group(function () {

    Route::get('health', HealthController::class);

    Route::prefix('shared')->group(function () {
        Route::get('categories', [LookupController::class, 'categories']);
        Route::get('districts', [LookupController::class, 'districts']);
        Route::get('departments', [LookupController::class, 'departments']);
    });

    // ===================== تطبيق المواطن =====================
    Route::prefix('citizen')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('auth/register', [CitizenAuthController::class, 'register']);
            Route::post('auth/login', [CitizenAuthController::class, 'login']);
        });

        Route::middleware(['auth:sanctum', 'citizen.only'])->group(function () {
            Route::post('auth/logout', [CitizenAuthController::class, 'logout']);
            Route::get('profile', [CitizenAuthController::class, 'profile']);
            Route::match(['post', 'put'], 'profile', [CitizenAuthController::class, 'updateProfile']);

            Route::get('reports', [CitizenReportController::class, 'index']);
            Route::get('reports/stats', [CitizenReportController::class, 'stats']);
            Route::post('reports', [CitizenReportController::class, 'store']);
            Route::get('reports/{uuid}', [CitizenReportController::class, 'show'])->whereUuid('uuid');
            Route::delete('reports/{uuid}', [CitizenReportController::class, 'destroy'])->whereUuid('uuid');
            Route::post('reports/{uuid}/rate', [CitizenReportController::class, 'rate'])->whereUuid('uuid');
            Route::post('reports/{uuid}/upvote', [CitizenReportController::class, 'upvote'])->whereUuid('uuid');

            Route::get('map/nearby', [CitizenMapController::class, 'nearby']);
            Route::get('map/heatmap', [CitizenMapController::class, 'heatmap']);
            Route::get('map/layers', [CitizenMapController::class, 'layers']);
            Route::get('map/districts/{district}/stats', [CitizenMapController::class, 'districtStats'])->whereNumber('district');
        });
    });

    // ===================== تطبيق الموظف =====================
    Route::prefix('employee')->group(function () {
        Route::post('auth/login', [EmployeeAuthController::class, 'login'])->middleware('throttle:auth');

        Route::middleware(['auth:sanctum', 'employee.only'])->group(function () {
            Route::post('auth/logout', [EmployeeAuthController::class, 'logout']);
            Route::get('profile', [EmployeeAuthController::class, 'profile']);
            Route::match(['post', 'put'], 'profile', [EmployeeAuthController::class, 'updateProfile']);

            Route::get('reports/assigned', [EmployeeReportController::class, 'assigned']);
            Route::get('reports/pool', [EmployeeReportController::class, 'pool']);
            Route::get('reports/history', [EmployeeReportController::class, 'history']);
            Route::get('reports/{uuid}', [EmployeeReportController::class, 'show'])->whereUuid('uuid');
            Route::post('reports/{uuid}/claim', [EmployeeReportController::class, 'claim'])->whereUuid('uuid');
            Route::post('reports/{uuid}/status', [EmployeeReportController::class, 'changeStatus'])->whereUuid('uuid');
            Route::post('reports/{uuid}/notes', [EmployeeReportController::class, 'addNote'])->whereUuid('uuid');
            Route::post('reports/{uuid}/reply', [EmployeeReportController::class, 'reply'])->whereUuid('uuid');
            Route::post('reports/{uuid}/transfer', [EmployeeReportController::class, 'transfer'])->whereUuid('uuid');
            Route::post('reports/{uuid}/attachments', [EmployeeReportController::class, 'uploadAttachments'])->whereUuid('uuid');

            Route::get('map/department-issues', [EmployeeReportController::class, 'map']);

            Route::get('analytics/my-stats', [EmployeeAnalyticsController::class, 'myStats']);
            Route::get('analytics/department-stats', [EmployeeAnalyticsController::class, 'departmentStats']);
            Route::get('analytics/workload', [EmployeeAnalyticsController::class, 'workload']);
        });
    });

    // ===================== الإشعارات (مشتركة) =====================
    Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('device-token', [NotificationController::class, 'deviceToken']);
        Route::post('{id}/read', [NotificationController::class, 'markAsRead'])->whereUuid('id');
    });
});
