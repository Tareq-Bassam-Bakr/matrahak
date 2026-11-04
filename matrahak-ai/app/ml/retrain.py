# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
إعادة تدريب النموذج من بلاغات حقيقية موثّقة يرسلها Laravel
(زر «إعادة تدريب النموذج» في إعدادات النظام  ←  POST /v1/train).

منهجية Champion / Challenger:
 1) تنظيف العينات: إزالة الفارغ والمكرر، وحذف أرقام الهواتف والبريد الإلكتروني (خصوصية المواطنين).
 2) Decontamination: استبعاد أي عينة شبه مطابقة لعينات الاختبار (heldout + challenge) — مثل البلاغات التجريبية.
 3) فصل 20% من العينات الحقيقية للاختبار (عند توفر 20 عينة على الأقل).
 4) تدريب نموذج مرشّح (Challenger) على المولّدة + 80% من الحقيقية ومقارنته بالنموذج الحالي (Champion)
    على نفس بيانات الاختبار. العينات التي تدرّب عليها النموذج الحالي سابقاً تُستبعد من المقارنة لتبقى عادلة.
 5) قرار الاعتماد باختبار McNemar (مقارنة نموذجين على نفس العينات): يُرفض المرشّح فقط إذا كان أسوأ
    من الحالي بفرق ذي دلالة إحصائية (p < 0.05) على أي مجموعة اختبار — فلا تُرفض تحديثات جيدة بسبب
    تذبذب عينة أو عينتين في مجموعات اختبار صغيرة. بعدها يُدرَّب النموذج النهائي على كل البيانات
    ويُستبدل فوراً دون إيقاف الخدمة.
 كل محاولة (معتمدة أو مرفوضة) تُسجَّل في models/training_history.jsonl
"""
from __future__ import annotations

import csv
import json
import math
import re
import threading
import time
from collections import Counter
from datetime import datetime, timezone

from sklearn.metrics import accuracy_score

from app.config import settings
from app.ml import dataset
from app.ml.model import classifier, selected_config, train

MIN_PER_LABEL = 3            # أقل عدد عينات لكل تصنيف (شرط المعايرة الاحتمالية بثلاث طيات)
SIGNIFICANCE = 0.05          # مستوى الدلالة لاختبار McNemar
REAL_TEST_RATIO = 0.2
REAL_TEST_MIN_SAMPLES = 20
HISTORY_FILE = "training_history.jsonl"
DATASET_FILE = "laravel-verified.csv"   # data/real/ — تستخدمه أيضاً python -m app.ml.train

SET_NAMES = {
    "real_test": "البلاغات الحقيقية المفصولة للاختبار",
    "heldout": "مجموعة الاختبار المستقلة",
    "challenge": "اختبار الإجهاد",
}

_PHONE = re.compile(r"[+\d][\d\s\-]{6,}\d")
_EMAIL = re.compile(r"\S+@\S+\.\S+")
_lock = threading.Lock()


class TrainingBusy(RuntimeError):
    """تدريب آخر قيد التنفيذ."""


class NotEnoughData(ValueError):
    """لا توجد عينات حقيقية صالحة — args[0] = إحصاءات العينات."""


def scrub(text: str) -> str:
    """حذف أرقام الهواتف والبريد الإلكتروني من نص البلاغ قبل استخدامه في التدريب."""
    return " ".join(_PHONE.sub(" ", _EMAIL.sub(" ", text)).split())


def _clean(samples: list[dict]) -> tuple[list[dict], int]:
    seen: set[str] = set()
    rows, invalid = [], 0
    for s in samples:
        text = scrub(str(s.get("text") or ""))
        label = str(s.get("label") or "").strip()
        key = dataset.text_key(text)
        if len(text) < 6 or not label or key in seen:
            invalid += 1
            continue
        seen.add(key)
        rows.append({"text": text, "label": label, "source": str(s.get("source") or "real"), "group": f"real:{key}"})
    return rows, invalid


def mcnemar_worse_p(b: int, c: int) -> float:
    """McNemar الدقيق (أحادي الاتجاه): احتمال ملاحظة b تراجعاً أو أكثر من أصل b+c اختلافاً لو كان النموذجان متكافئين.
    b = عينات صحيحة في الحالي وخاطئة في المرشّح، c = العكس."""
    n = b + c
    if n == 0:
        return 1.0
    return min(1.0, sum(math.comb(n, k) for k in range(b, n + 1)) / 2 ** n)


def _compare(current: dict | None, candidate: dict, rows: list[dict]) -> dict:
    out = {"n": len(rows), "current": None, "candidate": None, "regressions": 0, "improvements": 0, "p_value": None}
    if not rows:
        return out
    gold = [r["label"] for r in rows]
    texts = [r["text"] for r in rows]
    cand = list(candidate["pipeline"].predict(texts))
    out["candidate"] = round(float(accuracy_score(gold, cand)), 4)
    if current:
        cur = list(current["pipeline"].predict(texts))
        out["current"] = round(float(accuracy_score(gold, cur)), 4)
        out["regressions"] = sum(1 for g, a, b in zip(gold, cur, cand) if a == g and b != g)
        out["improvements"] = sum(1 for g, a, b in zip(gold, cur, cand) if a != g and b == g)
        out["p_value"] = round(mcnemar_worse_p(out["regressions"], out["improvements"]), 4)
    return out


def _pct(v: float | None) -> str:
    return "—" if v is None else f"{v * 100:.1f}%"


def _message(promoted: bool, forced: bool, worse: list[str], evaluation: dict) -> str:
    parts = [f"{SET_NAMES[k]}: {_pct(e['current'])} ← {_pct(e['candidate'])} (n={e['n']})"
             for k, e in evaluation.items() if e["n"]]
    summary = " — ".join(parts)
    worse_text = "، ".join(f"{SET_NAMES[w]} (McNemar p={evaluation[w]['p_value']})" for w in worse)
    if promoted and forced:
        return f"اعتُمد النموذج الجديد بطلب صريح رغم تراجعه على: {worse_text}. {summary}"
    if promoted:
        return f"تم اعتماد النموذج الجديد. الدقة (الحالي ← الجديد): {summary}"
    return (f"لم يُعتمد النموذج الجديد: أضعف من النموذج الحالي بفرق ذي دلالة إحصائية على: {worse_text}"
            f" — بقي النموذج الحالي. {summary}")


def not_enough_message(stats: dict) -> str:
    return (f"لا توجد عينات حقيقية صالحة للتدريب. العينات المستلمة: {stats['received']} — "
            f"غير صالحة أو مكررة: {stats['invalid_or_duplicate']} — "
            f"شبه مطابقة لبيانات الاختبار: {stats['excluded_test_overlap']} (تُستبعد حتى لا يتدرّب النموذج على أسئلة الاختبار) — "
            f"تصنيفات عيناتها أقل من {MIN_PER_LABEL}: {len(stats['skipped_labels'])}. "
            "عالج بلاغات حقيقية حتى الإغلاق أو راجع اقتراحات الذكاء الاصطناعي ثم أعد المحاولة.")


def retrain(samples: list[dict], *, include_synthetic: bool = True, force: bool = False) -> dict:
    if not _lock.acquire(blocking=False):
        raise TrainingBusy("training already in progress")
    try:
        return _retrain(samples, include_synthetic, force)
    finally:
        _lock.release()


def _retrain(samples: list[dict], include_synthetic: bool, force: bool) -> dict:
    started = time.time()
    real, invalid = _clean(samples)
    real, overlap = dataset.decontaminate(real)
    synthetic = dataset.decontaminate(dataset.synthetic())[0] if include_synthetic else []

    counts = Counter(r["label"] for r in synthetic + real)
    skipped = sorted(label for label, c in counts.items() if c < MIN_PER_LABEL)
    real = [r for r in real if r["label"] not in skipped]
    synthetic = [r for r in synthetic if r["label"] not in skipped]

    stats = {
        "received": len(samples),
        "invalid_or_duplicate": invalid,
        "excluded_test_overlap": overlap,
        "skipped_labels": skipped,
        "usable": len(real),
        "by_source": dict(Counter(r["source"] for r in real)),
        "by_label": dict(Counter(r["label"] for r in real)),
    }
    if not real or len({r["label"] for r in synthetic + real}) < 2:
        raise NotEnoughData(stats)

    # ---------- فصل جزء من البلاغات الحقيقية للاختبار ----------
    if len(real) >= REAL_TEST_MIN_SAMPLES:
        real_train, real_test = dataset.stratified_split(real, REAL_TEST_RATIO, seed=42, min_per_label=2)
        # تصنيف لم يبقَ له في التدريب 3 عينات => نعيد عيناته من الاختبار إلى التدريب
        train_counts = Counter(r["label"] for r in synthetic + real_train)
        real_train += [r for r in real_test if train_counts[r["label"]] < MIN_PER_LABEL]
        real_test = [r for r in real_test if train_counts[r["label"]] >= MIN_PER_LABEL]
    else:
        real_train, real_test = real, []

    config = selected_config()
    current = classifier.bundle
    candidate = train(synthetic + real_train, config=config)

    trained_on = set((current or {}).get("train_hashes") or [])
    fair_real_test = [r for r in real_test if dataset.text_key(r["text"]) not in trained_on]
    evaluation = {name: _compare(current, candidate, rows) for name, rows in (
        ("real_test", fair_real_test), ("heldout", dataset.heldout()), ("challenge", dataset.challenge()))}

    # أسوأ بدلالة إحصائية: تراجعات أكثر من التحسينات و p < 0.05
    worse = [name for name, e in evaluation.items()
             if e["p_value"] is not None and e["regressions"] > e["improvements"] and e["p_value"] < SIGNIFICANCE]
    promoted = force or not worse

    evaluated_at = datetime.now(timezone.utc).isoformat(timespec="seconds")
    if promoted:
        final = train(synthetic + real, config=config, metrics={
            "kind": "retrain",
            "evaluated_at": evaluated_at,
            "real_test_accuracy": evaluation["real_test"]["candidate"],
            "n_real_test": evaluation["real_test"]["n"],
            "heldout_accuracy": evaluation["heldout"]["candidate"],
            "challenge_accuracy": evaluation["challenge"]["candidate"],
        })
        classifier.replace(final)
        _save_dataset(real)

    result = {
        "status": "promoted" if promoted else "rejected",
        "promoted": promoted,
        "forced": bool(force and worse),
        "message": _message(promoted, bool(force and worse), worse, evaluation),
        "samples": stats,
        "training": {
            "synthetic": len(synthetic),
            "real_train": len(real_train),
            "real_test": len(real_test),
            "real_test_fair": len(fair_real_test),
            "labels": len({r["label"] for r in synthetic + real}),
            "include_synthetic": include_synthetic,
        },
        "evaluation": evaluation,
        "worse_on": worse,
        "evaluated_at": evaluated_at,
        "train_seconds": round(time.time() - started, 2),
        "model": classifier.info,
    }
    _append_history({k: v for k, v in result.items() if k != "model"}
                    | {"model_trained_at": classifier.info.get("trained_at")})
    return result


def _save_dataset(rows: list[dict]) -> None:
    """العينات المعتمدة تُحفظ في data/real/ ليستخدمها التدريب التلقائي والتقييم الكامل (python -m app.ml.train)."""
    folder = settings.data_dir / "real"
    folder.mkdir(parents=True, exist_ok=True)
    with (folder / DATASET_FILE).open("w", encoding="utf-8-sig", newline="") as fh:
        w = csv.writer(fh)
        w.writerow(["text", "label", "source"])
        for r in rows:
            w.writerow([r["text"], r["label"], r["source"]])


def _append_history(entry: dict) -> None:
    settings.model_dir.mkdir(parents=True, exist_ok=True)
    with (settings.model_dir / HISTORY_FILE).open("a", encoding="utf-8") as fh:
        fh.write(json.dumps(entry, ensure_ascii=False) + "\n")


def history(limit: int = 10) -> list[dict]:
    p = settings.model_dir / HISTORY_FILE
    if not p.exists():
        return []
    lines = p.read_text(encoding="utf-8").strip().splitlines()
    out = []
    for line in lines[-limit:]:
        try:
            out.append(json.loads(line))
        except json.JSONDecodeError:
            continue
    return out[::-1]
