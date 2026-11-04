<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Repositories\Contracts;

use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Repository Pattern: كل استعلامات البلاغات المعقدة في مكان واحد،
 * والخدمات (Services) والـ Controllers لا تكتب استعلامات مباشرة.
 */
interface ReportRepositoryInterface
{
    public function forCitizen(User $citizen, array $filters, int $perPage): LengthAwarePaginator;

    public function citizenStats(User $citizen): array;

    public function findVisibleByUuid(string $uuid): ?Report;

    public function nearby(float $lat, float $lng, float $km, array $filters, int $limit = 200): Collection;

    public function assignedTo(User $employee, array $filters, int $perPage): LengthAwarePaginator;

    public function departmentPool(User $employee, int $perPage): LengthAwarePaginator;

    public function historyFor(User $employee, array $filters, int $perPage): LengthAwarePaginator;

    public function findForEmployee(string $uuid, User $employee): ?Report;

    public function departmentOpenPoints(int $departmentId): Collection;

    /** @return Collection<int, Report> */
    public function duplicateCandidates(Report $report, int $radiusMeters, int $lookbackDays): Collection;
}
