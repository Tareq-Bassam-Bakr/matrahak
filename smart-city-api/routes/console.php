<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| المهام المجدولة — شغّل أثناء التطوير:  php artisan schedule:work
|--------------------------------------------------------------------------
| (على السيرفر: Cron كل دقيقة => php artisan schedule:run)
*/

Schedule::command('reports:auto-assign')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reports:check-overdue')->hourly()->withoutOverlapping();
Schedule::command('reports:auto-close')->dailyAt('00:30');

Schedule::command('analytics:snapshot daily')->dailyAt('00:10');
Schedule::command('analytics:snapshot weekly')->weeklyOn(1, '00:20');
Schedule::command('analytics:snapshot monthly')->monthlyOn(1, '00:40');

Schedule::command('ai:risk-scores')->dailyAt('01:00');
Schedule::command('ai:predictions')->dailyAt('02:00');
Schedule::command('ai:insights')->dailyAt('03:00');
