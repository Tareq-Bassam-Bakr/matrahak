# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""تحميل بيانات التدريب والاختبار ومعلومات التصنيفات + حماية مجموعات الاختبار من التسرّب."""
from __future__ import annotations

import csv
import hashlib
import json
import random
from pathlib import Path

from sklearn.feature_extraction.text import TfidfVectorizer

from app.config import settings
from app.text.arabic import normalize

# عينة تدريب يتجاوز تشابهها (cosine على مقاطع الحروف) هذا الحد مع أي عينة اختبار تُعدّ «نسخة» منها وتُستبعد
NEAR_DUPLICATE_THRESHOLD = 0.8


def _read_csv(path: Path, default_source: str) -> list[dict]:
    rows = []
    with path.open(encoding="utf-8-sig", newline="") as fh:
        for i, r in enumerate(csv.DictReader(fh)):
            text = (r.get("text") or "").strip()
            label = (r.get("label") or "").strip()
            if text and label:
                rows.append({
                    "text": text,
                    "label": label,
                    "source": (r.get("source") or default_source).strip(),
                    # المجموعة لـ Grouped CV: قالب الجملة للمولّدة، وكل بلاغ حقيقي مجموعة مستقلة
                    "group": (r.get("group") or f"{path.stem}:{i}").strip(),
                })
    return rows


def text_key(text: str) -> str:
    """بصمة النص بعد التوحيد — لمعرفة هل تدرّب نموذج ما على عينة معينة (مقارنة عادلة عند إعادة التدريب)."""
    return hashlib.sha1(normalize(text).encode("utf-8")).hexdigest()[:16]


def synthetic() -> list[dict]:
    p = settings.data_dir / "synthetic_train.csv"
    return _read_csv(p, "synthetic") if p.exists() else []


def real_raw() -> list[dict]:
    """بلاغات حقيقية مؤكَّدة: ملفات CSV المصدّرة من Laravel في data/real/ (بعد إزالة التكرار)."""
    rows: list[dict] = []
    folder = settings.data_dir / "real"
    if folder.exists():
        for f in sorted(folder.glob("*.csv")):
            rows.extend(_read_csv(f, "real"))
    seen, out = set(), []
    for r in rows:
        k = text_key(r["text"])
        if k not in seen:
            seen.add(k)
            out.append(r)
    return out


def real() -> list[dict]:
    """البلاغات الحقيقية بعد استبعاد ما يطابق مجموعات الاختبار."""
    return decontaminate(real_raw())[0]


def heldout() -> list[dict]:
    p = settings.data_dir / "heldout_test.csv"
    return _read_csv(p, "heldout") if p.exists() else []


def challenge() -> list[dict]:
    """اختبار إجهاد: نصوص قصيرة، أخطاء إملائية، ومرادفات عامية غير موجودة في التدريب."""
    p = settings.data_dir / "challenge_test.csv"
    return _read_csv(p, "challenge") if p.exists() else []


def categories() -> list[dict]:
    p = settings.data_dir / "categories.json"
    return json.loads(p.read_text(encoding="utf-8")) if p.exists() else []


def decontaminate(rows: list[dict], threshold: float = NEAR_DUPLICATE_THRESHOLD) -> tuple[list[dict], int]:
    """
    Decontamination: يستبعد من بيانات التدريب كل عينة شبه مطابقة لعينة في مجموعتي الاختبار
    (heldout + challenge) — حتى لا «يحفظ» النموذج أسئلة الامتحان فتتضخم الدقة.
    مثال: البلاغات التجريبية (DemoReportsSeeder) مبنية على نفس قوالب heldout مع اسم حي مختلف => تُستبعد.
    """
    tests = [normalize(r["text"]) for r in heldout() + challenge()]
    if not rows or not tests:
        return rows, 0
    texts = [normalize(r["text"]) for r in rows]
    vec = TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), sublinear_tf=True).fit(texts + tests)
    # المتجهات مطبّعة (L2) => حاصل الضرب = cosine similarity
    best = (vec.transform(texts) @ vec.transform(tests).T).max(axis=1).toarray().ravel()
    kept = [r for r, s in zip(rows, best) if s < threshold]
    return kept, len(rows) - len(kept)


def stratified_split(rows: list[dict], ratio: float, seed: int = 42, min_per_label: int = 3) -> tuple[list[dict], list[dict]]:
    """تقسيم طبقي بسيط: من كل تصنيف لديه min_per_label عينة على الأقل تُفصل نسبة ratio للاختبار."""
    by_label: dict[str, list[dict]] = {}
    for r in rows:
        by_label.setdefault(r["label"], []).append(r)
    rng = random.Random(seed)
    train_rows, test_rows = [], []
    for label in sorted(by_label):
        items = by_label[label][:]
        rng.shuffle(items)
        k = max(1, int(round(len(items) * ratio))) if len(items) >= min_per_label else 0
        test_rows += items[:k]
        train_rows += items[k:]
    return train_rows, test_rows
