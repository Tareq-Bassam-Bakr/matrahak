# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""جودة النموذج (حماية من التراجع) + التحميل والتدريب التلقائي."""
import joblib
import sklearn

from app.config import settings
from app.ml import dataset
from app.ml.model import MODEL_FILE, ClassifierService, classifier
from app.services.classify import classify


def _accuracy(rows, categories, hybrid=True):
    ok = 0
    for r in rows:
        title, _, desc = r["text"].partition(" — ")
        if hybrid:
            pred = classify(title, desc or title, categories)["category_slug"]
        else:
            p = classifier.predict_proba(r["text"])
            pred = max(p, key=p.get)
        ok += pred == r["label"]
    return ok / len(rows)


def test_accuracy_does_not_regress(categories):
    """أرقام التقرير reports/evaluation.md — تفشل الاختبارات إن تراجع النموذج."""
    assert _accuracy(dataset.heldout(), categories, hybrid=False) >= 0.97
    assert _accuracy(dataset.challenge(), categories, hybrid=False) >= 0.85
    assert _accuracy(dataset.heldout(), categories) >= 0.97
    assert _accuracy(dataset.challenge(), categories) >= 0.85


def test_training_data_does_not_contain_test_samples():
    """Decontamination: لا عينة تدريب شبه مطابقة لعينة اختبار."""
    kept, removed = dataset.decontaminate(dataset.synthetic())
    again, removed_again = dataset.decontaminate(kept)
    assert removed_again == 0 and len(again) == len(kept)
    trained = set(classifier.bundle["train_hashes"])
    for r in dataset.heldout() + dataset.challenge():
        assert dataset.text_key(r["text"]) not in trained


def test_demo_seeder_style_text_is_excluded_from_training():
    """البلاغات التجريبية = قوالب heldout مع اسم حي مختلف => تُستبعد من التدريب."""
    demo = {"text": "تراكم القمامة في الحي — الزبالة متراكمة جنب الحاوية بالمزة من أكثر من ٣ أيام والريحة صارت لا تحتمل، والقطط والكلاب عم تفرّقها بالشارع.",
            "label": "garbage"}
    fresh = {"text": "الحاوية ما انفرغت — صارلها عشر تيام ما إجت سيارة البلدية والزبالة عم تطوف", "label": "garbage"}
    kept, removed = dataset.decontaminate([demo, fresh])
    assert removed == 1 and kept == [fresh]


def test_explanation_lists_contributing_terms():
    terms = classifier.explain("انقطاع الكهرباء عن الحي من الصبح", "power-outage")
    assert terms and all(t["weight"] > 0 for t in terms)
    assert any("كهرب" in t["term"] for t in terms)


def test_auto_retrain_when_sklearn_version_differs(isolated):
    """نموذج محفوظ بإصدار scikit-learn مختلف => يُعاد تدريبه تلقائياً عند التشغيل (لا أخطاء Pickle)."""
    path = settings.model_dir / MODEL_FILE
    bundle = joblib.load(path)
    bundle["sklearn"] = "0.0-old"
    joblib.dump(bundle, path)

    service = ClassifierService()
    service.load()
    assert service.ready
    assert service.bundle["sklearn"] == sklearn.__version__
    assert joblib.load(path)["sklearn"] == sklearn.__version__
    assert service.info["metrics"].get("kind") == "offline"


def test_missing_model_is_trained_on_startup(isolated):
    (settings.model_dir / MODEL_FILE).unlink()
    service = ClassifierService()
    service.load()
    assert service.ready and (settings.model_dir / MODEL_FILE).exists()
    p = service.predict_proba("في حفرة كبيرة بالشارع")
    assert max(p, key=p.get) == "road-damage"
