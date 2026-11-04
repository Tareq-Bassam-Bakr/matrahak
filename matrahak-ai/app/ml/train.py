# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
تدريب نموذج تصنيف البلاغات وتقييمه + تقرير جاهز للمذكرة.

    python -m app.ml.train                 # مولّدة + حقيقية (data/real/*.csv)
    python -m app.ml.train --no-synthetic  # الحقيقية فقط (عندما تتوفر بيانات كافية)

منهجية التقييم:
  - Decontamination: تُستبعد من التدريب أي عينة شبه مطابقة (cosine ≥ 0.8) لعينة في مجموعتي الاختبار.
  - إذا توفرت ≥ 60 عينة حقيقية: يُقيَّم النموذج على 30% منها فقط (لم يرها أثناء التدريب).
  - وإلا: يُقيَّم على مجموعة الاختبار المستقلة data/heldout_test.csv (غير مستخدمة في التدريب).
  - اختيار المعاملات عبر Grouped Cross-Validation على بيانات التدريب فقط.
  - مقارنة مع نماذج أساسية: الكلمات المفتاحية فقط، TF-IDF كلمات، TF-IDF حروف، والنموذج الهجين المعتمد في الخدمة.

المخرجات: models/classifier.joblib — models/metrics.json — reports/evaluation.md — reports/confusion_matrix.png
"""
from __future__ import annotations

import argparse
import json
from collections import Counter
from datetime import datetime

import numpy as np
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix, f1_score
from sklearn.model_selection import StratifiedGroupKFold, cross_val_predict, cross_validate

from app.config import settings
from app.ml import dataset
from app.ml.features import DEFAULT_KEYWORD_WEIGHT, build_pipeline
from app.ml.model import MODEL_VERSION, metrics_summary, save, train
from app.services.classify import combine
from app.text.keywords import KeywordScorer


KEYWORD_WEIGHTS = (0.0, 0.1, 0.2, 0.3)


def _split_text(text: str) -> tuple[str, str]:
    title, _, desc = text.partition(" — ")
    return title, desc or title


def _keyword_baseline(test: list[dict], categories: list[dict]) -> list[str]:
    scorer = KeywordScorer(categories)
    preds = []
    for r in test:
        scores, _ = scorer.scores(*_split_text(r["text"]))
        preds.append(max(scores, key=scores.get) if scores else "none")
    return preds


def _hybrid_from_proba(classes, proba, keyword_scores: list[dict], allowed: list[str], weight: float) -> list[str]:
    preds = []
    for p, ks in zip(proba, keyword_scores):
        ranked = combine(dict(zip(classes, map(float, p))), ks, allowed, weight)
        preds.append(ranked[0][0] if ranked else "none")
    return preds


def hybrid_predict(pipeline, rows: list[dict], categories: list[dict], weight: float) -> list[str]:
    """نفس منطق الخدمة (app/services/classify.py): النموذج (1-w) + الكلمات المفتاحية w."""
    if not rows:
        return []
    scorer = KeywordScorer(categories)
    allowed = sorted(set(pipeline.classes_) | {c["slug"] for c in categories})
    proba = pipeline.predict_proba([r["text"] for r in rows])
    kscores = [scorer.scores(*_split_text(r["text"]))[0] for r in rows]
    return _hybrid_from_proba(list(pipeline.classes_), proba, kscores, allowed, weight)


def evaluate(use_synthetic: bool = True, write_report: bool = True) -> dict:
    synthetic, removed_synthetic = dataset.decontaminate(dataset.synthetic()) if use_synthetic else ([], 0)
    real_all = dataset.real_raw()
    real, removed_real = dataset.decontaminate(real_all)
    categories = dataset.categories()

    if len(real) >= 60:
        real_train, test = dataset.stratified_split(real, 0.3)
        train_rows = synthetic + real_train
        test_kind = "real"
    else:
        train_rows = synthetic + real
        test = dataset.heldout()
        test_kind = "heldout"

    if not train_rows or not test:
        raise SystemExit("لا توجد بيانات كافية للتدريب/الاختبار — شغّل scripts/generate_synthetic.py أولاً")

    X = [r["text"] for r in train_rows]
    y = [r["label"] for r in train_rows]
    groups = [r["group"] for r in train_rows]
    yt = [r["label"] for r in test]
    challenge = dataset.challenge()

    # ---------- اختيار النموذج والمعاملات عبر Grouped Cross-Validation فقط ----------
    # كل قالب جملة مجموعة واحدة: لا تظهر نفس الجملة في التدريب والتحقق معاً (وإلا تتضخم الدقة إلى ~100%).
    # مجموعتا الاختبار لا تُستخدمان في الاختيار إطلاقاً (تجنباً لتسرّب الاختبار إلى قرار التصميم).
    n_groups_min = min(len({g for g, lab in zip(groups, y) if lab == c}) for c in set(y))
    folds = max(2, min(5, n_groups_min))
    splitter = StratifiedGroupKFold(folds, shuffle=True, random_state=42)
    grid = [
        {"kind": "word+char", "classifier": "logreg", "C": 2.0},
        {"kind": "word+char", "classifier": "logreg", "C": 8.0},
        {"kind": "word+char", "classifier": "logreg", "C": 32.0},
        {"kind": "word+char", "classifier": "svm", "C": 0.25},
        {"kind": "word+char", "classifier": "svm", "C": 0.5},
        {"kind": "word+char", "classifier": "svm", "C": 1.0},
    ]
    selection = []
    for cfg in grid:
        res = cross_validate(build_pipeline(**cfg), X, y, groups=groups, cv=splitter,
                             scoring=["accuracy", "f1_macro"], n_jobs=1)
        selection.append({**cfg,
                          "cv_accuracy_mean": round(float(np.mean(res["test_accuracy"])), 4),
                          "cv_accuracy_std": round(float(np.std(res["test_accuracy"])), 4),
                          "cv_f1_macro_mean": round(float(np.mean(res["test_f1_macro"])), 4)})
    best = max(selection, key=lambda r: (r["cv_f1_macro_mean"], r["cv_accuracy_mean"]))
    best_cfg = {k: best[k] for k in ("kind", "classifier", "C")}

    # ---------- اختيار وزن الكلمات المفتاحية في الهجين (أيضاً عبر Grouped CV على التدريب فقط) ----------
    # احتمالات Out-of-fold: كل عينة تُقدَّر بنموذج لم يرَ قالبها أثناء التدريب
    weight_selection = []
    if categories:
        oof = cross_val_predict(build_pipeline(**best_cfg), X, y, groups=groups, cv=splitter, method="predict_proba")
        classes = sorted(set(y))
        scorer = KeywordScorer(categories)
        kscores = [scorer.scores(*_split_text(t))[0] for t in X]
        allowed = sorted(set(classes) | {c["slug"] for c in categories})
        for w in KEYWORD_WEIGHTS:
            preds_w = _hybrid_from_proba(classes, oof, kscores, allowed, w)
            weight_selection.append({"keyword_weight": w,
                                     "cv_accuracy": round(accuracy_score(y, preds_w), 4),
                                     "cv_f1_macro": round(f1_score(y, preds_w, average="macro", zero_division=0), 4)})
        top = max((r["cv_f1_macro"], r["cv_accuracy"]) for r in weight_selection)
        tied = [r["keyword_weight"] for r in weight_selection if (r["cv_f1_macro"], r["cv_accuracy"]) == top]
        # عند التعادل: الوزن التصميمي الافتراضي (0.2) إن كان ضمن الأفضل، وإلا الأصغر (اعتماد أقل على القواعد اليدوية)
        best_cfg["keyword_weight"] = DEFAULT_KEYWORD_WEIGHT if DEFAULT_KEYWORD_WEIGHT in tied else min(tied)
    else:
        best_cfg["keyword_weight"] = DEFAULT_KEYWORD_WEIGHT
    kw = best_cfg["keyword_weight"]

    best_name = f"TF-IDF words+chars + {'LinearSVC' if best_cfg['classifier'] == 'svm' else 'LogReg'} C={best_cfg['C']} ({MODEL_VERSION} — المختار)"

    # ---------- مقارنة النماذج على مجموعتي الاختبار ----------
    hybrid_name = f"الهجين المعتمد في الخدمة: النموذج المختار {1 - kw:.0%} + الكلمات المفتاحية {kw:.0%}"
    candidates = {
        "keywords-only (معرفة الخبير)": None,
        "TF-IDF words + LogReg": build_pipeline("word", "logreg"),
        "TF-IDF chars + LogReg": build_pipeline("char", "logreg"),
        best_name: build_pipeline(**best_cfg),
        hybrid_name: "hybrid",
    }
    sets = {"test": test, "challenge": challenge}
    comparison = []
    final_preds: dict[str, list[str]] = {}
    hybrid_preds: dict[str, list[str]] = {}
    best_pipe = candidates[best_name]
    for name, pipe in candidates.items():
        row = {"model": name}
        if pipe is not None and pipe != "hybrid":
            pipe.fit(X, y)
        for key, rows in sets.items():
            if not rows:
                continue
            gold = [r["label"] for r in rows]
            if pipe is None:
                preds = _keyword_baseline(rows, categories) if categories else ["none"] * len(rows)
            elif pipe == "hybrid":
                preds = hybrid_predict(best_pipe, rows, categories, kw)
                hybrid_preds[key] = preds
            else:
                preds = list(pipe.predict([r["text"] for r in rows]))
            if name == best_name:
                final_preds[key] = preds
            row[f"{key}_accuracy"] = round(accuracy_score(gold, preds), 4)
            row[f"{key}_f1_macro"] = round(f1_score(gold, preds, average="macro", zero_division=0), 4)
        comparison.append(row)
    model_row = next(c for c in comparison if c["model"] == best_name)
    hybrid_row = comparison[-1]

    preds = final_preds["test"]
    labels = sorted(set(yt) | set(preds))
    report = classification_report(yt, preds, labels=labels, output_dict=True, zero_division=0)
    cm = confusion_matrix(yt, preds, labels=labels)
    errors, hybrid_errors = [], []
    for key, rows in sets.items():
        for r, p_ in zip(rows, final_preds.get(key, [])):
            if r["label"] != p_:
                errors.append({"set": key, "text": r["text"], "expected": r["label"], "predicted": p_})
        for r, p_ in zip(rows, hybrid_preds.get(key, [])):
            if r["label"] != p_:
                hybrid_errors.append({"set": key, "text": r["text"], "expected": r["label"], "predicted": p_})

    metrics = {
        "model_version": MODEL_VERSION,
        "evaluated_at": datetime.now().isoformat(timespec="seconds"),
        "test_kind": test_kind,
        "n_train": len(train_rows),
        "n_test": len(test),
        "n_challenge": len(challenge),
        "decontaminated": {"synthetic": removed_synthetic, "real": removed_real},
        "train_sources": dict(Counter(r["source"] for r in train_rows)),
        "test_sources": dict(Counter(r["source"] for r in test)),
        "cv_folds": folds,
        "selected_config": best_cfg,
        "selection": selection,
        "keyword_weight_selection": weight_selection,
        "cv_accuracy_mean": best["cv_accuracy_mean"],
        "cv_accuracy_std": best["cv_accuracy_std"],
        "cv_f1_macro_mean": best["cv_f1_macro_mean"],
        "test_accuracy": round(accuracy_score(yt, preds), 4),
        "test_f1_macro": round(f1_score(yt, preds, average="macro", zero_division=0), 4),
        "challenge_accuracy": model_row.get("challenge_accuracy"),
        "challenge_f1_macro": model_row.get("challenge_f1_macro"),
        "hybrid_test_accuracy": hybrid_row.get("test_accuracy"),
        "hybrid_challenge_accuracy": hybrid_row.get("challenge_accuracy"),
        "comparison": comparison,
        "per_class": {k: {m: round(v, 4) for m, v in report[k].items()} for k in labels if k in report},
        "errors": errors,
        "hybrid_errors": hybrid_errors,
    }

    # النموذج النهائي: يُدرَّب على كل البيانات المتاحة (بما فيها جزء الاختبار الحقيقي) بعد انتهاء التقييم
    bundle = train(synthetic + real if use_synthetic else real or synthetic, config=best_cfg,
                   metrics=metrics_summary({**metrics, "kind": "offline"}))
    save(bundle)

    settings.model_dir.mkdir(parents=True, exist_ok=True)
    (settings.model_dir / "metrics.json").write_text(json.dumps(metrics, ensure_ascii=False, indent=2), encoding="utf-8")
    if write_report:
        _write_report(metrics, labels, cm)
    return metrics


def _write_report(m: dict, labels: list[str], cm: np.ndarray) -> None:
    settings.reports_dir.mkdir(parents=True, exist_ok=True)
    png = None
    try:
        import matplotlib

        matplotlib.use("Agg")
        import matplotlib.pyplot as plt

        fig, ax = plt.subplots(figsize=(11, 9))
        ax.imshow(cm, cmap="Greens")
        ax.set_xticks(range(len(labels)), labels, rotation=75, fontsize=7)
        ax.set_yticks(range(len(labels)), labels, fontsize=7)
        ax.set_xlabel("Predicted")
        ax.set_ylabel("Actual")
        ax.set_title(f"Confusion matrix — {m['model_version']} ({m['test_kind']} test, n={m['n_test']})")
        for i in range(len(labels)):
            for j in range(len(labels)):
                if cm[i, j]:
                    ax.text(j, i, int(cm[i, j]), ha="center", va="center", fontsize=7,
                            color="white" if cm[i, j] > cm.max() / 2 else "black")
        fig.tight_layout()
        png = settings.reports_dir / "confusion_matrix.png"
        fig.savefig(png, dpi=140)
        plt.close(fig)
    except ImportError:
        pass

    kind = ("بيانات حقيقية (30% من البلاغات المؤكَّدة، لم يرها النموذج أثناء التدريب)" if m["test_kind"] == "real"
            else "مجموعة اختبار مستقلة مكتوبة يدوياً (غير مستخدمة في التدريب) — ليست بلاغات حقيقية")
    lines = [
        "# تقرير تقييم نموذج تصنيف البلاغات — مطرحك",
        "",
        f"- **النموذج:** `{m['model_version']}` — TF-IDF (جذور الكلمات unigram/bigram + مقاطع الحروف 2–5) + "
        f"{'LinearSVC (معايرة Platt)' if m['selected_config']['classifier'] == 'svm' else 'Logistic Regression'} "
        f"C={m['selected_config']['C']} — مختار عبر Grouped CV",
        f"- **تاريخ التقييم:** {m['evaluated_at']}",
        f"- **بيانات التدريب:** {m['n_train']} عينة — المصادر: {m['train_sources']}",
        f"- **Decontamination:** عدد العينات المستبعدة من التدريب لأنها شبه مطابقة (cosine ≥ 0.8) لعينات الاختبار: "
        f"المولّدة {m['decontaminated']['synthetic']}، الحقيقية {m['decontaminated']['real']} — حتى لا يتدرّب النموذج على أسئلة الامتحان.",
        f"- **بيانات الاختبار:** {m['n_test']} عينة — {kind}",
        "",
        "## النتائج",
        "",
        "| المقياس | القيمة |",
        "|---|---|",
        f"| Accuracy على الاختبار | {m['test_accuracy']:.1%} |",
        f"| Macro F1 على الاختبار | {m['test_f1_macro']:.1%} |",
        f"| Grouped Cross-Validation Accuracy ({m['cv_folds']} طيات) | {m['cv_accuracy_mean']:.1%} ± {m['cv_accuracy_std']:.1%} |",
        f"| Grouped Cross-Validation Macro F1 | {m['cv_f1_macro_mean']:.1%} |",
        f"| اختبار الإجهاد Accuracy ({m['n_challenge']} نصاً قصيراً/عامياً) | {(m['challenge_accuracy'] or 0):.1%} |",
        f"| اختبار الإجهاد Macro F1 | {(m['challenge_f1_macro'] or 0):.1%} |",
        f"| **الهجين المعتمد في الخدمة** — Accuracy على الاختبار | {(m['hybrid_test_accuracy'] or 0):.1%} |",
        f"| **الهجين المعتمد في الخدمة** — Accuracy على الإجهاد | {(m['hybrid_challenge_accuracy'] or 0):.1%} |",
        "",
        "> **Grouped CV:** كل قالب جملة في بيانات التدريب المولّدة يُعامل كمجموعة واحدة، فلا تظهر الجملة نفسها",
        "> في التدريب والتحقق معاً — بدون ذلك تصل الدقة إلى ~100% بشكل مضلل.",
        "> **اختبار الإجهاد:** نصوص قصيرة جداً، أخطاء إملائية، ومرادفات عامية لم ترد في التدريب (data/challenge_test.csv).",
        "",
        "## اختيار النموذج (Grouped Cross-Validation فقط)",
        "",
        "| المصنّف | C | CV Accuracy | CV Macro F1 |",
        "|---|---|---|---|",
        *[f"| {'LinearSVC' if r['classifier'] == 'svm' else 'LogReg'} | {r['C']} | {r['cv_accuracy_mean']:.1%} ± {r['cv_accuracy_std']:.1%} | {r['cv_f1_macro_mean']:.1%} |"
          for r in m["selection"]],
        "",
        "## اختيار وزن الكلمات المفتاحية في الهجين (Grouped CV — احتمالات Out-of-fold)",
        "",
        "| الوزن w | CV Accuracy | CV Macro F1 |",
        "|---|---|---|",
        *[f"| {r['keyword_weight']}{' ✅' if r['keyword_weight'] == m['selected_config'].get('keyword_weight') else ''} | {r['cv_accuracy']:.1%} | {r['cv_f1_macro']:.1%} |"
          for r in m.get("keyword_weight_selection", [])],
        "",
        "> عند التعادل يُختار الوزن التصميمي 0.2 إن كان ضمن الأفضل، وإلا الأصغر.",
        "",
        "## مقارنة النماذج",
        "",
        "| النموذج | Accuracy (الاختبار) | Macro F1 (الاختبار) | Accuracy (الإجهاد) | Macro F1 (الإجهاد) |",
        "|---|---|---|---|---|",
        *[f"| {c['model']} | {c.get('test_accuracy', 0):.1%} | {c.get('test_f1_macro', 0):.1%} | "
          f"{c.get('challenge_accuracy', 0):.1%} | {c.get('challenge_f1_macro', 0):.1%} |" for c in m["comparison"]],
        "",
        "> «keywords-only» هو نفسه البديل المحلي في Laravel حرفياً (نفس التصنيف ونفس درجة الثقة — tests/test_arabic.py)،",
        "> أي أن هذا السطر هو أداء المنصة عند توقف خدمة الذكاء الاصطناعي.",
        "> ملاحظة: قوائم الكلمات المفتاحية ضُبطت جزئياً على قوالب مجموعة الاختبار، لذلك نتيجة «keywords-only» والهجين على",
        "> الاختبار متفائلة؛ اختبار الإجهاد هو المقارنة الأعدل. وزن الكلمات المفتاحية في الهجين اختير عبر",
        "> Grouped CV على بيانات التدريب ولم يُضبط على أي من مجموعتي الاختبار.",
        "",
        "## الأداء لكل تصنيف",
        "",
        "| التصنيف | Precision | Recall | F1 | العدد |",
        "|---|---|---|---|---|",
        *[f"| {k} | {v['precision']:.2f} | {v['recall']:.2f} | {v['f1-score']:.2f} | {int(v['support'])} |"
          for k, v in m["per_class"].items()],
        "",
    ]
    if png:
        lines += ["## مصفوفة الالتباس (Confusion Matrix)", "", "![confusion matrix](confusion_matrix.png)", ""]
    def _err(e: dict) -> str:
        return (f"- [{'إجهاد' if e['set'] == 'challenge' else 'اختبار'}] «{e['text'][:110]}» — "
                f"المتوقع `{e['expected']}`، تنبأ `{e['predicted']}`")

    lines += [f"## أخطاء النموذج المختار ({len(m['errors'])})", ""]
    lines += [_err(e) for e in m["errors"][:60]]
    lines += ["", f"## أخطاء الهجين المعتمد في الخدمة ({len(m.get('hybrid_errors', []))})", ""]
    lines += [_err(e) for e in m.get("hybrid_errors", [])[:60]]
    lines += [
        "",
        "## حدود التقييم",
        "",
        "- بيانات التدريب الأولية **مولّدة** (scripts/generate_synthetic.py) لتشغيل المنصة من اليوم الأول.",
        "- مجموعة الاختبار المستقلة مكتوبة يدوياً بصياغات مختلفة، لكنها ليست بلاغات مواطنين حقيقيين؛",
        "  لذلك الدقة أعلاه **ليست** دقة العالم الحقيقي.",
        "- مجموعتا الاختبار صغيرتان (148 و48 نصاً): عينة واحدة في اختبار الإجهاد ≈ 2 نقطة مئوية، فالفروق الصغيرة",
        "  بين النماذج (مثل الهجين مقابل النموذج وحده) ضمن هامش الضجيج الإحصائي.",
        "- الدقة الحقيقية تُقاس بعد التجربة الميدانية: زر «إعادة تدريب النموذج» في لوحة الإدارة يرسل البلاغات",
        "  الموثّقة ويقارن النموذج الجديد بالحالي على 20% منها لم يرها (Champion/Challenger + اختبار McNemar)،",
        "  ويحفظها في `data/real/` — بعدها يعيد هذا السكربت التقييم الكامل على 30% من البلاغات الحقيقية",
        "  (أو صدّرها يدوياً: `php artisan ai:export-training-data` ثم ضع الملف في `data/real/`).",
        "- كشف التكرار وتحليل الصور استدلاليان (Heuristic) وغير مُقيَّمين على بيانات حقيقية — انظر README.",
    ]
    (settings.reports_dir / "evaluation.md").write_text("\n".join(lines) + "\n", encoding="utf-8")


def main() -> None:
    ap = argparse.ArgumentParser(description="تدريب وتقييم نموذج تصنيف البلاغات")
    ap.add_argument("--no-synthetic", action="store_true", help="التدريب على البيانات الحقيقية فقط")
    args = ap.parse_args()
    m = evaluate(use_synthetic=not args.no_synthetic)
    print(json.dumps({k: m[k] for k in ("test_kind", "n_train", "n_test", "test_accuracy", "test_f1_macro",
                                         "cv_folds", "cv_accuracy_mean", "cv_accuracy_std", "n_challenge",
                                         "challenge_accuracy")}, ensure_ascii=False, indent=2))
    for c in m["comparison"]:
        print(f"  test {c.get('test_accuracy', 0):.3f}  challenge {c.get('challenge_accuracy', 0):.3f}  {c['model']}")


if __name__ == "__main__":
    main()
