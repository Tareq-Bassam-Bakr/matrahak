# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
التنبؤ والرؤى — مطابق لـ App\\Services\\Ai\\LocalForecaster في Laravel (نفس المعادلات والنصوص).

predict():  نموذج Poisson بمعدل يومي موزون أُسّياً (EWMA, half-life = 14 يوم) + عامل الاتجاه،
            P(حدث واحد على الأقل خلال الأفق) = 1 - e^(-λ·H·trend)
insights(): كشف الشذوذ (z-score لمتوسط آخر 3 أيام مقابل خط الأساس) + الاتجاه الأسبوعي على مستوى البلد.
"""
from __future__ import annotations

import math
from datetime import date, timedelta

from app.services.phpcompat import num, php_int_round, php_round

PREDICT_VERSION = "poisson-ewma-v1"
INSIGHTS_VERSION = "stats-v1"
HALF_LIFE = 14.0
TYPE_AR = {"electricity_failure": "انقطاع كهرباء", "water_problem": "مشكلة مياه", "traffic_congestion": "ازدحام مروري"}


def _age(day: str, today: date) -> int:
    return (today - date.fromisoformat(day[:10])).days


def _stats(ages: dict[int, int], weight_sum: float) -> dict:
    weighted = 0.0
    recent = previous = total = 0
    for age, count in ages.items():
        weighted += (0.5 ** (age / HALF_LIFE)) * count
        total += count
        if age < 14:
            recent += count
        elif age < 28:
            previous += count
    return {
        "rate": weighted / weight_sum if weight_sum > 0 else 0.0,
        "recent": recent,
        "previous": previous,
        "growth": (recent - previous) / max(1, previous),
        "total": total,
    }


def _severity(p: float, expected: float, horizon: int) -> str:
    if p >= 0.95 and expected >= horizon:
        return "critical"
    if p >= 0.8:
        return "high"
    if p >= 0.6:
        return "medium"
    return "low"


def predict(series: list[dict], type_map: dict[str, str], districts: list[dict], horizon: int, lookback: int,
            today: str | None = None, category_names: dict[str, str] | None = None) -> list[dict]:
    today_d = date.fromisoformat(today) if today else date.today()
    horizon = max(1, int(horizon))
    lookback = max(28, int(lookback))
    names = {int(d["id"]): d["name"] for d in districts}
    category_names = category_names or {}

    by_type: dict[str, dict[int, int]] = {}
    by_cat: dict[str, dict[int, int]] = {}
    for row in series:
        age = _age(row["date"], today_d)
        if age < 0 or age >= lookback or int(row["district_id"]) not in names:
            continue
        count = int(row["count"])
        t = type_map.get(row["category_slug"])
        if t:
            k = f"{row['district_id']}|{t}"
            by_type.setdefault(k, {})
            by_type[k][age] = by_type[k].get(age, 0) + count
        k = f"{row['district_id']}|{row['category_slug']}"
        by_cat.setdefault(k, {})
        by_cat[k][age] = by_cat[k].get(age, 0) + count

    weight_sum = sum(0.5 ** (a / HALF_LIFE) for a in range(lookback))
    start = (today_d + timedelta(days=1)).isoformat()
    end = (today_d + timedelta(days=horizon)).isoformat()
    out: list[dict] = []

    for key, ages in by_type.items():
        district_s, ptype = key.split("|", 1)
        district_id = int(district_s)
        s = _stats(ages, weight_sum)
        if s["total"] < 3:
            continue
        trend = 1 + 0.5 * max(0.0, min(1.0, s["growth"])) if s["recent"] >= 3 else 1.0
        expected = s["rate"] * horizon * trend
        p = 1 - math.exp(-expected)
        if p < 0.35:
            continue
        out.append({
            "prediction_type": ptype,
            "district_id": district_id,
            "category_slug": None,
            "predicted_date": start,
            "window_end": end,
            "probability": php_round(p, 4),
            "expected_count": php_round(expected, 2),
            "severity": _severity(p, expected, horizon),
            "description": "متوقع حدوث %s في %s خلال %d أيام — الاحتمال %d%%، والعدد المتوقع ≈ %s بلاغ" % (
                TYPE_AR.get(ptype, ptype), names[district_id], horizon, php_int_round(p * 100), num(expected)),
            "supporting_data": {
                "daily_rate": php_round(s["rate"], 4),
                "recent_14": s["recent"],
                "previous_14": s["previous"],
                "growth": php_round(s["growth"], 3),
                "lookback_total": s["total"],
            },
        })

    for key, ages in by_cat.items():
        district_s, slug = key.split("|", 1)
        district_id = int(district_s)
        s = _stats(ages, weight_sum)
        if s["recent"] < 5 or s["growth"] < 0.5:
            continue
        p = min(0.95, 0.5 + s["growth"] / 4)
        expected = s["recent"] / 14 * horizon
        previous_txt = (f"{s['previous']} في الـ 14 يوماً السابقة (+{php_int_round(s['growth'] * 100)}%)"
                        if s["previous"] > 0 else "لا شيء في الـ 14 يوماً السابقة")
        out.append({
            "prediction_type": "seasonal_trend",
            "district_id": district_id,
            "category_slug": slug,
            "predicted_date": start,
            "window_end": end,
            "probability": php_round(p, 4),
            "expected_count": php_round(expected, 2),
            "severity": "high" if s["growth"] >= 1 else "medium",
            "description": "اتجاه متصاعد لـ«%s» في %s: %d بلاغ خلال آخر 14 يوماً مقابل %s" % (
                category_names.get(slug, slug), names[district_id], s["recent"], previous_txt),
            "supporting_data": {"recent_14": s["recent"], "previous_14": s["previous"], "growth": php_round(s["growth"], 3)},
        })

    out.sort(key=lambda r: r["probability"], reverse=True)
    return out[:150]


def insights(series: list[dict], districts: dict, categories: dict[str, str], lookback: int,
             today: str | None = None) -> list[dict]:
    today_d = date.fromisoformat(today) if today else date.today()
    lookback = max(21, int(lookback))
    districts = {int(k): v for k, v in (districts or {}).items()}
    cells: dict[str, dict[int, int]] = {}
    city: dict[str, dict[int, int]] = {}

    for row in series:
        age = _age(row["date"], today_d)
        if age < 0 or age >= lookback:
            continue
        k = f"{row['district_id']}|{row['category_slug']}"
        cells.setdefault(k, {})
        cells[k][age] = cells[k].get(age, 0) + int(row["count"])
        slug = row["category_slug"]
        city.setdefault(slug, {})
        city[slug][age] = city[slug].get(age, 0) + int(row["count"])

    out: list[dict] = []
    for key, ages in cells.items():
        district_s, slug = key.split("|", 1)
        recent_sum = ages.get(0, 0) + ages.get(1, 0) + ages.get(2, 0)
        if recent_sum < 4:
            continue
        baseline = [ages.get(a, 0) for a in range(3, lookback)]
        mean = sum(baseline) / len(baseline)
        var = sum((x - mean) ** 2 for x in baseline) / len(baseline)
        std = max(math.sqrt(var), 0.5)
        z = (recent_sum / 3 - mean) / std
        if z < 3:
            continue
        district = districts.get(int(district_s), "محافظة غير معروفة")
        category = categories.get(slug, slug)
        out.append({
            "type": "anomaly",
            "severity": "critical" if z >= 5 else "warning",
            "title": f"ارتفاع غير معتاد في «{category}» — {district}",
            "content": ("سُجّل %d بلاغاً خلال آخر 3 أيام، بينما المعدل اليومي المعتاد %s بلاغ. "
                        "قد يدل ذلك على عطل واسع أو حادث يحتاج تدخلاً سريعاً.") % (recent_sum, num(mean)),
            "district_id": int(district_s),
            "category_slug": slug,
            "data": {"recent_3_days": recent_sum, "baseline_mean": php_round(mean, 3),
                     "baseline_std": php_round(std, 3), "z_score": php_round(z, 2)},
            "fingerprint": f"anomaly-{district_s}-{slug}-{today_d.isoformat()}",
        })

    iso = today_d.isocalendar()
    week = f"{iso[0]}{iso[1]:02d}"
    for slug, ages in city.items():
        last7 = sum(ages.get(a, 0) for a in range(7))
        prev7 = sum(ages.get(a + 7, 0) for a in range(7))
        if last7 < 5:
            continue
        growth = (last7 - prev7) / max(1, prev7)
        if growth < 0.5:
            continue
        category = categories.get(slug, slug)
        content = ("ارتفعت بلاغات «%s» بنسبة %d%% خلال الأسبوع الأخير (%d بلاغ مقابل %d في الأسبوع السابق)." % (
            category, php_int_round(growth * 100), last7, prev7) if prev7 > 0
            else "سُجّل %d بلاغ «%s» خلال الأسبوع الأخير بعد أسبوع بلا أي بلاغ من هذا النوع." % (last7, category))
        out.append({
            "type": "trend",
            "severity": "critical" if growth >= 1 else "warning",
            "title": f"اتجاه متصاعد: «{category}»",
            "content": content,
            "district_id": None,
            "category_slug": slug,
            "data": {"last_7": last7, "previous_7": prev7, "growth": php_round(growth, 3)},
            "fingerprint": f"trend-{slug}-{week}",
        })

    out.sort(key=lambda r: r["severity"] == "critical", reverse=True)
    return out[:50]
