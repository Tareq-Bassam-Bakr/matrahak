# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
المصنّف المعتمد على الكلمات المفتاحية (معرفة الخبراء) — منفذ Python لـ
App\\Services\\Ai\\LocalKeywordClassifier (local-keywords-v2) في Laravel.

يُستخدم في الخدمة كجزء من النموذج الهجين:
  - يدعم أي تصنيف جديد يضيفه المدير من لوحة الإدارة (بكلماته المفتاحية) قبل إعادة تدريب النموذج.
  - ويرفع دقة الحالات التي تحتوي مصطلحات حاسمة.
"""
from __future__ import annotations

import math
from dataclasses import dataclass

from .arabic import normalize, stems as stems_of

CRITICAL_WORDS = ("حريق", "انفجار", "انهيار", "اصابات", "مصاب", "جرحي", "اختناق", "صعق", "صعقه", "غرق", "ماس كهربايي",
                  "تسرب غاز", "وفاه", "لغم", "قذيفه", "مخلفات حرب", "جسم مشبوه")
HIGH_WORDS = ("خطر", "خطير", "عاجل", "اطفال", "مدرسه", "مستشفي", "مشفي", "متكرر", "طواري", "حادث", "حوادث", "مكشوف",
              "منذ ايام", "منذ اسبوع", "شرار")


@dataclass
class Term:
    raw: str
    norm: str
    stems: list[str]
    idf: float = 0.0


def detect_urgency(normalized_text: str) -> str:
    """low | high | critical — من كلمات الخطورة في النص."""
    if any(w in normalized_text for w in CRITICAL_WORDS):
        return "critical"
    if any(w in normalized_text for w in HIGH_WORDS):
        return "high"
    return "low"


def _stem_match(stem: str, text_stems: list[str]) -> float:
    if stem in text_stems:
        return 1.0
    if len(stem) < 4:
        return 0.0
    for t in text_stems:
        if len(t) >= 4 and (t.startswith(stem) or stem.startswith(t)):
            return 0.8
    return 0.0


def _match_term(term: Term, norm_text: str, text_stems: list[str]) -> float:
    words = len(term.stems)
    full = 1 + 0.75 * (words - 1)
    if len(term.norm) <= 3:
        padded = f" {norm_text} "
        if any(f" {p}{term.norm} " in padded for p in ("", "ال", "و", "ب", "بال")):
            return full
    elif term.norm in norm_text:
        return full

    found = sum(_stem_match(s, text_stems) for s in term.stems)
    if words == 1:
        return found
    ratio = found / words
    if ratio >= 0.99:
        return 0.8 * full
    return 0.25 * ratio * full if ratio >= 0.5 else 0.0


class KeywordScorer:
    """يُبنى من قائمة التصنيفات المرسلة من Laravel: [{slug, name, keywords, ...}]"""

    def __init__(self, categories: list[dict]):
        self.terms: dict[str, list[Term]] = {}
        df: dict[str, int] = {}
        for c in categories:
            raws = list(dict.fromkeys([*(c.get("keywords") or []), c.get("name") or ""]))
            terms: list[Term] = []
            cat_stems: set[str] = set()
            for raw in raws:
                norm = normalize(str(raw))
                if not norm:
                    continue
                st = stems_of(norm)
                if not st:
                    continue
                terms.append(Term(str(raw), norm, st))
                cat_stems.update(st)
            for s in cat_stems:
                df[s] = df.get(s, 0) + 1
            self.terms[c["slug"]] = terms

        n = max(1, len(categories))
        self.max_idf = math.log(1 + n)
        for terms in self.terms.values():
            for t in terms:
                t.idf = sum(math.log(1 + n / max(1, df.get(s, 1))) for s in t.stems) / len(t.stems)

    def scores(self, title: str, description: str) -> tuple[dict[str, float], dict[str, list[str]]]:
        title_norm = normalize(title)
        full_norm = normalize(f"{title} {description}")
        title_stems = stems_of(title_norm)
        full_stems = stems_of(full_norm)

        scores: dict[str, float] = {}
        hits: dict[str, list[str]] = {}
        for slug, terms in self.terms.items():
            score = 0.0
            matched = []
            for t in terms:
                m = _match_term(t, full_norm, full_stems)
                if m <= 0:
                    continue
                weight = 0.5 + 0.5 * (t.idf / self.max_idf)
                in_title = _match_term(t, title_norm, title_stems) > 0
                score += m * weight * (1.35 if in_title else 1.0)
                matched.append(t.raw)
            if score > 0:
                scores[slug] = round(score, 4)
                hits[slug] = list(dict.fromkeys(matched))
        return scores, hits

    @staticmethod
    def confidence(best: float, second: float) -> float:
        return min(0.97, (best / (best + second + 0.6)) * min(1.0, 0.45 + 0.25 * best))
