# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""تطابق معالجة النص العربي مع Laravel (App\\Support\\ArabicText + LocalKeywordClassifier)."""
import json

from app.text.arabic import normalize, stems
from app.text.keywords import KeywordScorer, detect_urgency
from tests.conftest import FIXTURES


def test_normalize_stems_and_urgency_match_php():
    rows = json.loads((FIXTURES / "arabic_parity.json").read_text(encoding="utf-8"))
    assert len(rows) >= 30
    for r in rows:
        n = normalize(r["text"])
        assert n == r["normalized"], r["text"]
        assert stems(f" {n} ") == r["stems"], r["text"]
        assert detect_urgency(f" {n} ") == r["priority"], r["text"]


def test_keyword_scorer_matches_laravel_fallback(categories):
    """المصنّف بالكلمات المفتاحية = البديل المحلي في Laravel حرفياً (نفس التصنيف ونفس درجة الثقة)."""
    rows = json.loads((FIXTURES / "keyword_parity.json").read_text(encoding="utf-8"))
    scorer = KeywordScorer(categories)
    for r in rows:
        scores, _ = scorer.scores(r["title"], r["description"])
        if not scores:
            assert r["php_slug"] is None
            continue
        ordered = sorted(scores.values(), reverse=True)
        assert max(scores, key=scores.get) == r["php_slug"], r["title"]
        conf = KeywordScorer.confidence(ordered[0], ordered[1] if len(ordered) > 1 else 0.0)
        assert abs(round(conf, 4) - r["php_confidence"]) < 1e-4, r["title"]


def test_normalization_rules():
    assert normalize("أإآ ة ى ـــ") == "ااا ه ي"
    assert normalize("حُفرةٌ") == "حفره"
    assert normalize("حريق!! في   المحل؟") == normalize("حريق في المحل")
