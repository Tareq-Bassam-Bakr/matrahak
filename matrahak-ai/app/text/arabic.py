# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
معالجة النص العربي (فصحى + عامية سورية) — مطابقة حرفياً لـ Laravel:
  - App\\Support\\ArabicText::normalize
  - App\\Services\\Ai\\LocalKeywordClassifier::stem
حتى يعطي المصنّف المحلي في Laravel وخدمة Python نفس التوحيد والتجذيع.
"""
from __future__ import annotations

import re

_DIACRITICS = re.compile("[ً-ٰٟـ]")
_LETTERS = str.maketrans({
    "أ": "ا", "إ": "ا", "آ": "ا", "ٱ": "ا",
    "ة": "ه", "ى": "ي", "ؤ": "و", "ئ": "ي",
})
_NON_WORD = re.compile(r"[^\w\s]|_", re.UNICODE)
_SPACES = re.compile(r"\s+", re.UNICODE)

STOP_WORDS = frozenset([
    "في", "من", "على", "علي", "الى", "الي", "عن", "مع", "هذا", "هذه", "هناك", "يوجد", "تم", "او", "و", "ان", "كان",
    "التي", "الذي", "عند", "جدا", "كل", "ما", "لا", "عم", "بس", "شي", "صار", "كتير", "كثير", "اكتر", "اكثر",
    "يلي", "اللي", "هاد", "هي", "هو",
])

PREFIXES = ("وبال", "وال", "بال", "كال", "فال", "عال", "لل", "ال", "و", "ب", "ل", "ف")
SUFFIXES = ("يات", "ات", "ون", "ين", "ها", "هم", "كم", "نا", "ه", "ي", "ء", "ا")


def normalize(text: str) -> str:
    """إزالة التشكيل والتطويل، توحيد الألف والتاء المربوطة والياء، حذف الرموز."""
    text = (text or "").lower()
    text = _DIACRITICS.sub("", text)
    text = text.translate(_LETTERS)
    text = _NON_WORD.sub(" ", text)
    return _SPACES.sub(" ", text).strip()


def stem(word: str) -> str:
    """تجذيع خفيف: سابقة واحدة ولاحقة واحدة مع إبقاء 3 أحرف على الأقل."""
    for p in PREFIXES:
        if word.startswith(p) and len(word) - len(p) >= 3:
            word = word[len(p):]
            break
    for s in SUFFIXES:
        if word.endswith(s) and len(word) - len(s) >= 3:
            word = word[: len(word) - len(s)]
            break
    return word


def tokens(text: str, *, stemmed: bool = True, drop_stop_words: bool = True) -> list[str]:
    out = []
    for w in normalize(text).split(" "):
        if len(w) < 2 or (drop_stop_words and w in STOP_WORDS):
            continue
        out.append(stem(w) if stemmed else w)
    return out


def stems(normalized: str) -> list[str]:
    """جذور فريدة بترتيب الظهور (مطابقة لـ LocalKeywordClassifier::stems في PHP)."""
    seen: dict[str, None] = {}
    for w in normalized.strip().split(" "):
        if len(w) < 2 or w in STOP_WORDS:
            continue
        seen.setdefault(stem(w), None)
    return list(seen)


def preprocess_for_model(text: str) -> str:
    """النص الذي يراه نموذج TF-IDF للكلمات: جذور مفصولة بمسافات."""
    return " ".join(tokens(text))
