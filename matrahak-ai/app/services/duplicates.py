# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
كشف البلاغات المكررة — ثلاث إشارات:

    semantic = cosine(احتمالات التصنيفات من النموذج المدرّب)   — هل يصف البلاغان نفس نوع المشكلة؟
    lexical  = cosine(TF-IDF مقاطع الحروف)                      — هل يستخدمان نفس الكلمات؟
    geo      = 1 - المسافة / نصف القطر                           — هل هما في نفس المكان؟

    score = 0.45·semantic + 0.35·lexical + 0.20·geo
    مكرر إذا: semantic ≥ 0.5 و score ≥ 0.55 والمسافة ضمن نصف القطر

لماذا الإشارة الدلالية؟ تشابه الكلمات وحده يخلط بين بلاغين مختلفين في نفس المكان
(«حفرة قدام المدرسة» و«ضو الشارع مطفي قدام المدرسة» يتشاركان «قدام المدرسة»)، ويفوّت بلاغين متطابقين
بصياغتين مختلفتين («المياه مقطوعة عن الحي» و«ما في مي بالحارة»). النموذج المدرّب يحل الحالتين.

ملاحظة صريحة: الأوزان والعتبات استدلالية (Heuristic) اختيرت على أمثلة توضيحية مكتوبة يدوياً
(tests/test_duplicates.py) وليست مُقيَّمة على بيانات حقيقية؛ والنتيجة اقتراح «للمراجعة» وليست دمجاً تلقائياً.
Laravel يرسل فقط البلاغات المفتوحة ضمن نصف القطر والمدة المحددين في «إعدادات النظام».
"""
from __future__ import annotations

import math

import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from app.ml.model import classifier
from app.text.arabic import normalize

W_SEMANTIC, W_LEXICAL, W_GEO = 0.45, 0.35, 0.20
SEMANTIC_MIN = 0.5
THRESHOLD = 0.55
# بدون نموذج جاهز: تشابه الكلمات + المكان فقط
FALLBACK_TEXT_MIN, FALLBACK_THRESHOLD = 0.45, 0.55


def haversine_m(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    r = 6371000.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dp, dl = math.radians(lat2 - lat1), math.radians(lng2 - lng1)
    a = math.sin(dp / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(math.sqrt(a))


def _lexical(texts: list[str]) -> np.ndarray:
    vectors = classifier.text_vectors(texts)
    if vectors is None:
        vectors = TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5)).fit_transform([normalize(t) for t in texts])
    return cosine_similarity(vectors[0], vectors[1:])[0]


def _semantic(texts: list[str]) -> np.ndarray | None:
    proba = classifier.proba_matrix(texts)
    if proba is None:
        return None
    return cosine_similarity(proba[0:1], proba[1:])[0]


def find(report: dict, candidates: list[dict], radius_m: float) -> dict:
    if not candidates:
        return {"is_duplicate": False, "duplicate_of": None, "similarity": 0.0, "method": "none"}

    texts = [report["text"]] + [c["text"] for c in candidates]
    lexical = _lexical(texts)
    semantic = _semantic(texts)
    radius = max(1.0, float(radius_m or 300))

    scored = []
    for i, c in enumerate(candidates):
        dist = None
        if None not in (report.get("lat"), report.get("lng"), c.get("lat"), c.get("lng")):
            dist = haversine_m(float(report["lat"]), float(report["lng"]), float(c["lat"]), float(c["lng"]))
        geo = max(0.0, 1 - dist / radius) if dist is not None else 0.5
        within = dist is None or dist <= radius
        lex = float(lexical[i])
        if semantic is not None:
            sem = float(semantic[i])
            score = W_SEMANTIC * sem + W_LEXICAL * lex + W_GEO * geo
            ok = within and sem >= SEMANTIC_MIN and score >= THRESHOLD
        else:
            sem = None
            score = 0.75 * lex + 0.25 * geo
            ok = within and lex >= FALLBACK_TEXT_MIN and score >= FALLBACK_THRESHOLD
        scored.append({"id": c["id"], "score": score, "lex": lex, "sem": sem, "distance": dist, "ok": ok})

    # الأفضل بين المرشحين المستوفين للشروط؛ وإن لم يوجد: الأعلى درجة (للعرض فقط)
    pool = [s for s in scored if s["ok"]] or scored
    best = max(pool, key=lambda s: s["score"])

    return {
        "is_duplicate": best["ok"],
        "duplicate_of": best["id"] if best["ok"] else None,
        "similarity": round(best["score"], 4),
        "text_similarity": round(best["lex"], 4),
        "semantic_similarity": round(best["sem"], 4) if best["sem"] is not None else None,
        "distance_m": round(best["distance"], 1) if best["distance"] is not None else None,
        "method": "model-semantic+tfidf-char+geo" if semantic is not None else "tfidf-char+geo",
    }
