# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
مؤشر خطورة المحافظة (risk-v2) — نفس معادلة RiskScoringService::localScore في Laravel.
كل المكونات متزايدة رتيبة (Monotonic): زيادة البلاغات الحرجة أو المتأخرة لا تُخفض المؤشر أبداً.
"""
from __future__ import annotations

import math

from app.services.phpcompat import php_round

VERSION = "risk-v2"


def score(f: dict, load_scale: float = 1.0, overdue_scale: float = 0.5) -> float:
    per100k = 100000 / max(1, int(f.get("population") or 0))
    open_total = f["open_critical"] + f["open_high"] + f["open_medium"] + f["open_low"]
    load = (f["open_critical"] * 4 + f["open_high"] * 3 + f["open_medium"] * 2 + f["open_low"]) * per100k
    overdue = f["overdue"] * per100k
    growth = (f["last30"] - f["prev30"]) / max(1, f["prev30"])
    unresolved = 1 - f["resolved"] / f["total"] if f["total"] else 0
    _ = open_total
    value = 100 * (
        0.50 * (1 - math.exp(-load / max(0.01, load_scale)))
        + 0.20 * (1 - math.exp(-overdue / max(0.01, overdue_scale)))
        + 0.15 * ((1 / (1 + math.exp(-2 * growth)) - 0.5) * 2 if growth > 0 else 0)
        + 0.15 * unresolved
    )
    return php_round(max(0.0, min(100.0, value)), 2)


def scores(districts: list[dict], scales: dict | None = None) -> list[dict]:
    scales = scales or {}
    load_scale = float(scales.get("load", 1.0))
    overdue_scale = float(scales.get("overdue", 0.5))
    return [{"district_id": d["district_id"], "score": score(d, load_scale, overdue_scale)} for d in districts]
