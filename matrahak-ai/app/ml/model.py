# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
نموذج تصنيف البلاغات: TF-IDF (جذور الكلمات + مقاطع الحروف) + مصنّف خطي (LinearSVC معايَر / Logistic Regression).

- train(): تدريب على البيانات المتاحة وإرجاع حزمة النموذج (bundle)
- ClassifierService: تحميل النموذج (أو تدريبه تلقائياً) + التنبؤ + تفسير القرار + استبدال آمن بعد إعادة التدريب
"""
from __future__ import annotations

import logging
import shutil
import threading
import time
from datetime import datetime, timezone

import joblib
import sklearn

from app.config import settings
from app.ml import dataset
from app.ml.features import DEFAULT_CONFIG, DEFAULT_KEYWORD_WEIGHT, build_pipeline, linear_coefficients

log = logging.getLogger("matrahak.ml")

MODEL_VERSION = "tfidf-hybrid-v1"
MODEL_FILE = "classifier.joblib"
BACKUP_FILE = "classifier.prev.joblib"


def train(rows: list[dict], *, metrics: dict | None = None, config: dict | None = None) -> dict:
    """يدرّب النموذج على كل الصفوف ويعيد حزمة النموذج.
    config: الإعدادات المختارة عبر Grouped CV (models/metrics.json) — أو الافتراضية."""
    config = config or selected_config()
    texts = [r["text"] for r in rows]
    labels = [r["label"] for r in rows]
    pipeline = build_pipeline(config["kind"], config["classifier"], config["C"])
    started = time.time()
    pipeline.fit(texts, labels)
    sources: dict[str, int] = {}
    for r in rows:
        sources[r.get("source", "unknown")] = sources.get(r.get("source", "unknown"), 0) + 1

    return {
        "pipeline": pipeline,
        "version": MODEL_VERSION,
        "config": config,
        "labels": list(pipeline.classes_),
        "trained_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "train_seconds": round(time.time() - started, 2),
        "n_samples": len(rows),
        "sources": sources,
        # بصمات نصوص التدريب: عند إعادة التدريب لا نقارن النموذج الحالي على عينات سبق أن تدرّب عليها
        "train_hashes": sorted({dataset.text_key(t) for t in texts}),
        "sklearn": sklearn.__version__,
        "metrics": metrics or {},
    }


def selected_config() -> dict:
    """الإعدادات الفائزة في آخر تقييم (python -m app.ml.train) — تُستخدم أيضاً عند التدريب التلقائي."""
    import json

    p = settings.model_dir / "metrics.json"
    if p.exists():
        try:
            cfg = json.loads(p.read_text(encoding="utf-8")).get("selected_config")
            if cfg:
                return {**DEFAULT_CONFIG, **cfg}
        except Exception:
            pass
    return dict(DEFAULT_CONFIG)


def default_training_rows() -> list[dict]:
    """بيانات التدريب الافتراضية: المولّدة + الحقيقية (data/real) بعد استبعاد ما يطابق مجموعات الاختبار."""
    return dataset.decontaminate(dataset.synthetic())[0] + dataset.real()


def save(bundle: dict) -> None:
    settings.model_dir.mkdir(parents=True, exist_ok=True)
    joblib.dump(bundle, settings.model_dir / MODEL_FILE, compress=3)


def metrics_summary(metrics: dict | None) -> dict:
    """أهم أرقام التقييم (من التقييم الكامل python -m app.ml.train أو من آخر إعادة تدريب)."""
    m = metrics or {}
    keys = ("kind", "test_kind", "cv_accuracy_mean", "test_accuracy", "n_test", "heldout_accuracy",
            "challenge_accuracy", "real_test_accuracy", "n_real_test", "evaluated_at")
    return {k: m[k] for k in keys if m.get(k) is not None}


class ClassifierService:
    """غلاف آمن للنموذج: تحميل، استبدال ذرّي بعد إعادة التدريب (بدون إيقاف الخدمة)، وقفل للخيوط."""

    def __init__(self) -> None:
        self._bundle: dict | None = None
        self._lock = threading.Lock()

    # ------------------------------------------------------------------
    def load(self) -> None:
        path = settings.model_dir / MODEL_FILE
        bundle = None
        if path.exists():
            try:
                bundle = joblib.load(path)
                if bundle.get("sklearn") != sklearn.__version__:
                    log.warning("model trained with scikit-learn %s, installed %s => retraining",
                                bundle.get("sklearn"), sklearn.__version__)
                    bundle = None
            except Exception as e:  # ملف تالف أو غير متوافق
                log.warning("cannot load model (%s) => retraining", e)
                bundle = None

        if bundle is None and settings.auto_train:
            rows = default_training_rows()
            if rows:
                log.info("training classifier on %d samples ...", len(rows))
                bundle = train(rows, metrics=self._offline_metrics())
                save(bundle)

        with self._lock:
            self._bundle = bundle

    @staticmethod
    def _offline_metrics() -> dict:
        import json

        p = settings.model_dir / "metrics.json"
        try:
            m = json.loads(p.read_text(encoding="utf-8")) if p.exists() else {}
        except Exception:
            m = {}
        return metrics_summary({**m, "kind": "offline"}) if m else {}

    def replace(self, bundle: dict) -> None:
        """يعتمد نموذجاً جديداً: نسخة احتياطية من الحالي (classifier.prev.joblib) ثم الحفظ والتبديل."""
        current = settings.model_dir / MODEL_FILE
        if current.exists():
            shutil.copy2(current, settings.model_dir / BACKUP_FILE)
        save(bundle)
        with self._lock:
            self._bundle = bundle

    @property
    def bundle(self) -> dict | None:
        return self._bundle

    @property
    def keyword_weight(self) -> float:
        """وزن الكلمات المفتاحية: من .env إن ضُبط، وإلا المختار عبر Grouped CV والمحفوظ مع النموذج."""
        if settings.keyword_weight is not None:
            return min(1.0, max(0.0, float(settings.keyword_weight)))
        b = self._bundle
        return float((b or {}).get("config", {}).get("keyword_weight", DEFAULT_KEYWORD_WEIGHT))

    @property
    def ready(self) -> bool:
        return self._bundle is not None

    @property
    def info(self) -> dict:
        b = self._bundle
        if not b:
            return {"ready": False}
        cfg = b.get("config") or {}
        return {
            "ready": True,
            "version": b["version"],
            "algorithm": ("LinearSVC (Platt calibration)" if cfg.get("classifier") == "svm" else "Logistic Regression")
                         + f", C={cfg.get('C')}, TF-IDF {cfg.get('kind')}",
            "config": cfg,
            "keyword_weight": self.keyword_weight,
            "trained_at": b["trained_at"],
            "train_seconds": b.get("train_seconds"),
            "n_samples": b["n_samples"],
            "sources": b["sources"],
            "labels": len(b["labels"]),
            "sklearn": b.get("sklearn"),
            "metrics": metrics_summary(b.get("metrics")),
        }

    # ------------------------------------------------------------------
    def predict_proba(self, text: str) -> dict[str, float]:
        b = self._bundle
        if not b:
            return {}
        proba = b["pipeline"].predict_proba([text])[0]
        return {label: float(p) for label, p in zip(b["pipeline"].classes_, proba)}

    def proba_matrix(self, texts: list[str]):
        """مصفوفة احتمالات التصنيفات لعدة نصوص (تمثيل «دلالي»: ما نوع المشكلة؟) أو None."""
        b = self._bundle
        if not b or not texts:
            return None
        return b["pipeline"].predict_proba(texts)

    def explain(self, text: str, label: str, top: int = 6) -> list[dict]:
        """أهم الكلمات (الجذور) التي دفعت النموذج لهذا التصنيف: مساهمة = TF-IDF × وزن الكلمة للتصنيف."""
        b = self._bundle
        if not b or label not in b["labels"]:
            return []
        pipe = b["pipeline"]
        feats = pipe.named_steps["features"]
        if not hasattr(feats, "transformer_list"):
            return []
        word_pipe = dict(feats.transformer_list).get("word")
        if word_pipe is None:
            return []
        vocab = word_pipe.named_steps["tfidf"].get_feature_names_out()
        x_word = word_pipe.transform([text])  # أول قسم من FeatureUnion
        classes, coefs = linear_coefficients(pipe.named_steps["clf"])
        coef = coefs[classes.index(label)][: len(vocab)]
        row = x_word.tocoo()
        contrib = [(vocab[j], float(v * coef[j])) for j, v in zip(row.col, row.data)]
        contrib = [c for c in contrib if c[1] > 0]
        contrib.sort(key=lambda c: c[1], reverse=True)
        return [{"term": t, "weight": round(w, 4)} for t, w in contrib[:top]]

    def text_vectors(self, texts: list[str]):
        """تمثيل الحروف من النموذج المدرّب (لكشف التكرار)، أو None إن لم يكن جاهزاً."""
        b = self._bundle
        if not b:
            return None
        feats = b["pipeline"].named_steps["features"]
        char_pipe = dict(feats.transformer_list).get("char") if hasattr(feats, "transformer_list") else None
        return char_pipe.transform(texts) if char_pipe is not None else None


classifier = ClassifierService()
