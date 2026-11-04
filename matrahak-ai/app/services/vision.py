# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
تحليل الصور (heuristic-v1) — بدون نموذج رؤية مدرّب، لذلك نُعلن حدوده بوضوح:

1) فحص الجودة: إضاءة ضعيفة، صورة غير واضحة (تباين Laplacian منخفض)، دقة منخفضة
   => مفيد عملياً: التطبيق يطلب من المواطن إعادة التصوير.
2) مؤشرات لونية كلاسيكية (Color-rule based detection):
   - احتمال حريق/لهب: قاعدة RGB الشائعة في الأدبيات (R > 190، R > G > B، R - B > 70)
   - غطاء نباتي (أخضر) / مسطحات مائية (أزرق)
الوسوم مؤشرات مساعدة للموظف وليست تشخيصاً؛ يمكن لاحقاً استبدال هذا الملف بنموذج رؤية مدرّب (مثل YOLO)
دون تغيير الـ API.
"""
from __future__ import annotations

import io

import numpy as np
from PIL import Image, ImageOps

METHOD = "heuristic-color-quality-v1"


def analyze(data: bytes) -> dict:
    try:
        img = Image.open(io.BytesIO(data))
        img = ImageOps.exif_transpose(img).convert("RGB")
    except Exception:
        # الخدمة تعمل لكن الملف تالف/غير مدعوم: نُرجع وسماً يراه الموظف بدل التوقف عن تحليل بقية الصور
        return {"available": True, "method": METHOD, "tags": ["تعذّرت قراءة الصورة"], "severity": None,
                "quality_ok": False, "error": "unreadable_image"}

    w, h = img.size
    img.thumbnail((512, 512))
    rgb = np.asarray(img, dtype=np.float32)
    r, g, b = rgb[..., 0], rgb[..., 1], rgb[..., 2]
    gray = 0.299 * r + 0.587 * g + 0.114 * b

    brightness = float(gray.mean())
    lap = (-4 * gray[1:-1, 1:-1] + gray[:-2, 1:-1] + gray[2:, 1:-1] + gray[1:-1, :-2] + gray[1:-1, 2:])
    sharpness = float(lap.var())

    fire = float(((r > 190) & (r > g) & (g > b) & ((r - b) > 70)).mean())
    green = float(((g > r + 15) & (g > b + 15)).mean())
    blue = float(((b > r + 20) & (b > g + 5) & (b > 90)).mean())

    tags: list[str] = []
    if brightness < 45:
        tags.append("إضاءة ضعيفة")
    if sharpness < 60:
        tags.append("صورة غير واضحة")
    if min(w, h) < 320:
        tags.append("دقة منخفضة")
    if fire > 0.12:
        tags.append("احتمال حريق أو لهب")
    if green > 0.35:
        tags.append("غطاء نباتي")
    if blue > 0.30:
        tags.append("مياه/سماء")

    quality_ok = brightness >= 45 and sharpness >= 60 and min(w, h) >= 320
    return {
        "available": True,
        "method": METHOD,
        "tags": tags,
        "severity": 8 if fire > 0.12 else None,
        "quality_ok": quality_ok,
        "metrics": {
            "width": w, "height": h,
            "brightness": round(brightness, 1),
            "sharpness": round(sharpness, 1),
            "fire_ratio": round(fire, 4),
            "green_ratio": round(green, 4),
            "blue_ratio": round(blue, 4),
        },
    }
