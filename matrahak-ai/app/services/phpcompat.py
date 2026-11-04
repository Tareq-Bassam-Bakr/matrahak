# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""دوال تطابق سلوك PHP حرفياً (للحصول على نفس النتائج في Laravel والخدمة)."""
from __future__ import annotations

import math


def php_round(value: float, places: int = 0) -> float:
    """round() في PHP: تقريب نصف بعيداً عن الصفر مع تقريب مسبق لـ 15 رقماً معنوياً (وليس Banker's rounding)."""
    m = 10.0 ** places
    x = float(f"{value * m:.15g}")
    r = math.floor(abs(x) + 0.5)
    return math.copysign(r, x) / m if r else 0.0


def php_int_round(value: float) -> int:
    return int(php_round(value, 0))


def num(n: float) -> str:
    """rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.')"""
    s = f"{php_round(n, 1):.1f}"
    return s.rstrip("0").rstrip(".") if "." in s else s
