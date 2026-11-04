# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
التصنيف الهجين (Hybrid):
    P(تصنيف) = (1 - w) · P_model + w · P_keywords        (w مختار عبر Grouped CV على بيانات التدريب)

- P_model: نموذج تعلم آلي مدرّب (TF-IDF + LinearSVC/LogReg) — يتعلم من البيانات.
- P_keywords: الكلمات المفتاحية لكل تصنيف كما يحددها المدير في لوحة الإدارة (معرفة الخبير) —
  تسمح بدعم أي تصنيف جديد فوراً قبل إعادة تدريب النموذج.
- التصنيفات المرسلة من Laravel فقط هي المسموحة (تصنيف معطّل لا يُقترح أبداً).
- w يُختار عبر Grouped CV على بيانات التدريب فقط (python -m app.ml.train) ويُحفظ مع النموذج، ويمكن تجاوزه
  بالمتغير KEYWORD_WEIGHT — ولا يُضبط أبداً على مجموعات الاختبار.
"""
from __future__ import annotations

import re
import time

from app.ml.model import MODEL_VERSION, classifier
from app.text.arabic import normalize
from app.text.keywords import KeywordScorer, detect_urgency

PRIORITY_RANK = {"low": 1, "medium": 2, "high": 3, "critical": 4}


def base_priority(weight: int | None) -> str:
    """نفس Category::basePriority() في Laravel."""
    w = int(weight or 5)
    return "high" if w >= 9 else ("medium" if w >= 5 else "low")


def max_priority(a: str, b: str) -> str:
    return a if PRIORITY_RANK.get(a, 1) >= PRIORITY_RANK.get(b, 1) else b


def summarize(title: str, description: str) -> str:
    first = re.split(r"[.!؟?\n،]", description or "")[0].strip() or (description or "").strip()
    if len(first) > 140:
        first = first[:140] + "…"
    return f"{(title or '').strip()} — {first}"


def combine(p_model: dict[str, float], keyword_scores: dict[str, float], allowed: list[str],
            weight: float) -> list[tuple[str, float]]:
    """يدمج احتمالات النموذج مع درجات الكلمات المفتاحية (بعد تطبيعهما) ويعيد التصنيفات مرتبة."""
    pm = {s: p for s, p in p_model.items() if s in allowed}
    z = sum(pm.values())
    pm = {s: p / z for s, p in pm.items()} if z > 0 else {}
    kz = sum(keyword_scores.values())
    pk = {s: v / kz for s, v in keyword_scores.items() if s in allowed} if kz > 0 else {}
    if not pm and not pk:
        return []
    w = weight if pm else 1.0
    final = {s: (1 - w) * pm.get(s, 0.0) + w * pk.get(s, 0.0) for s in allowed}
    return sorted(final.items(), key=lambda kv: kv[1], reverse=True)


def classify(title: str, description: str, categories: list[dict]) -> dict:
    started = time.perf_counter()
    active = {c["slug"]: c for c in categories}
    text = f"{title} — {description}"

    p_model = classifier.predict_proba(text)
    scorer = KeywordScorer(categories)
    kscores, hits = scorer.scores(title, description)

    ranked = combine(p_model, kscores, list(active), classifier.keyword_weight)
    if not ranked or ranked[0][1] <= 0:
        return {"category_slug": None, "confidence": 0.0, "backend": "none", "model_version": MODEL_VERSION,
                "processing_ms": int((time.perf_counter() - started) * 1000)}

    model_used = any(s in active for s in p_model)
    best, confidence = ranked[0]
    if not model_used:  # النموذج غير جاهز => نفس معادلة الثقة في Laravel للكلمات المفتاحية
        ordered = sorted(kscores.values(), reverse=True)
        confidence = KeywordScorer.confidence(ordered[0], ordered[1] if len(ordered) > 1 else 0.0)

    urgency = detect_urgency(normalize(f"{title} {description}"))
    priority = max_priority(base_priority(active[best].get("priority_weight")), urgency)

    return {
        "category_slug": best,
        "department_code": active[best].get("department_code"),
        "confidence": round(float(confidence), 4),
        "priority": priority,
        # مؤشر الخطورة من النص وحده (حريق، ماس كهربائي...) — يُعتمد حتى لو كانت الثقة بالتصنيف منخفضة
        "urgency_signal": urgency,
        "summary": summarize(title, description),
        # الكلمات المفتاحية (من لوحة الإدارة) التي وردت فعلاً في النص للتصنيف المختار
        "keywords_matched": hits.get(best, []),
        # تفسير قرار النموذج: الجذور الأكثر مساهمة في اختيار هذا التصنيف
        "explanation": classifier.explain(text, best) if model_used else [],
        "alternatives": [{"slug": s, "score": round(float(p), 4)} for s, p in ranked[1:4] if p > 0],
        "model_version": MODEL_VERSION if model_used else "keywords-v2",
        "backend": "ml-hybrid" if model_used else "keywords",
        "processing_ms": int((time.perf_counter() - started) * 1000),
    }
