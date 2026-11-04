# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""إعادة التدريب من بلاغات Laravel الموثّقة (POST /v1/train) — على نسخة معزولة من النموذج والبيانات."""
import csv
import json

import pytest

from app.config import settings
from app.ml import retrain
from app.ml.model import classifier

# بلاغات «حقيقية» بصياغات جديدة غير موجودة في التدريب أو الاختبار
REAL = [
    ("ضو العامود مطفي من جمعة والحارة عتمة بالليل", "street-light-failure"),
    ("لمبات الشارع كلها طافية بعد العاصفة وما حدا صلحها", "street-light-failure"),
    ("الإنارة العامة بالأوتوستراد مقطوعة والسواقين ما عم يشوفوا", "street-light-failure"),
    ("الحاوية ما انفرغت صارلها عشر تيام والزبالة عم تطوف", "garbage"),
    ("سيارة البلدية ما مرقت هالأسبوع والكياس مكومة جنب الدرج", "garbage"),
    ("في كومة نفايات كبيرة ورا المدرسة والدبان مالي المكان", "garbage"),
    ("نزل الطريق وصار فيه حفرة عميقة بعد تصليح خط الهاتف", "road-damage"),
    ("الزفت مقشور والجورة عم تكبر كل يوم بنص الطريق", "road-damage"),
    ("المطب اللي قدام الجامع انهدم وصار جورة بتخرب السيارات", "road-damage"),
    ("بوري المي فاقع تحت الرصيف والمي عم تنزل عالطريق ليل نهار", "street-water-leak"),
    ("في نبعة مي طالعة من الزفت من يومين والطريق صار وحل", "street-water-leak"),
    ("عداد المي عم يسرب والمي ضايعة عالأرض", "street-water-leak"),
] * 2  # 24 عينة (مع تكرار مقصود يُزال أثناء التنظيف)


def _samples(rows, source="resolved"):
    return [{"text": t, "label": lab, "source": source} for t, lab in rows]


def test_retrain_promotes_and_hot_swaps_model(isolated, client, headers):
    before = classifier.info["trained_at"]
    r = client.post("/v1/train", headers=headers, json={"samples": _samples(REAL), "include_synthetic": True})
    assert r.status_code == 200, r.text
    body = r.json()
    assert body["promoted"] is True and body["status"] == "promoted"
    assert body["samples"]["received"] == 24 and body["samples"]["invalid_or_duplicate"] == 12
    assert body["samples"]["usable"] == 12
    assert body["evaluation"]["challenge"]["n"] == 48
    assert "تم اعتماد النموذج الجديد" in body["message"]
    # النموذج الجديد يعمل فوراً بدون إعادة تشغيل
    assert classifier.info["trained_at"] >= before
    assert classifier.info["sources"].get("resolved") == 12
    assert classifier.info["metrics"]["kind"] == "retrain"
    # نسخة احتياطية + سجل + حفظ العينات المعتمدة للتقييم الكامل
    assert (settings.model_dir / "classifier.prev.joblib").exists()
    history = [json.loads(x) for x in (settings.model_dir / "training_history.jsonl").read_text(encoding="utf-8").splitlines()]
    assert history[-1]["promoted"] is True
    with (settings.data_dir / "real" / retrain.DATASET_FILE).open(encoding="utf-8-sig") as fh:
        assert len(list(csv.DictReader(fh))) == 12
    assert client.get("/v1/model", headers=headers).json()["history"][0]["status"] == "promoted"


def test_demo_reports_matching_test_set_are_rejected_with_clear_message(isolated, client, headers):
    demo = [{"text": "تراكم القمامة في الحي — الزبالة متراكمة جنب الحاوية بالميدان من أكثر من ٣ أيام والريحة صارت لا تحتمل، والقطط والكلاب عم تفرّقها بالشارع.",
             "label": "garbage", "source": "resolved"}]
    r = client.post("/v1/train", headers=headers, json={"samples": demo})
    assert r.status_code == 422
    detail = r.json()["detail"]
    assert detail["samples"]["excluded_test_overlap"] == 1
    assert "شبه مطابقة لبيانات الاختبار: 1" in detail["message"]


def test_weaker_candidate_is_rejected_unless_forced(isolated):
    """تدريب على 12 بلاغاً فقط بدون البيانات الأولية => نموذج أضعف كثيراً => لا يُعتمد."""
    current = classifier.info["trained_at"]
    result = retrain.retrain(_samples(REAL), include_synthetic=False)
    assert result["promoted"] is False and result["status"] == "rejected"
    assert "challenge" in result["worse_on"]
    assert classifier.info["trained_at"] == current
    assert not (settings.data_dir / "real" / retrain.DATASET_FILE).exists()

    forced = retrain.retrain(_samples(REAL), include_synthetic=False, force=True)
    assert forced["promoted"] is True and forced["forced"] is True


def test_new_category_with_few_samples_is_skipped(isolated):
    rows = REAL[:12] + [("تعدي على الرصيف ببسطة خضرة", "public-space-encroachment"),
                        ("محل مطلّع بضاعته عالرصيف", "public-space-encroachment")]
    result = retrain.retrain(_samples(rows))
    assert result["samples"]["skipped_labels"] == ["public-space-encroachment"]
    assert result["promoted"] is True


def test_personal_data_is_scrubbed():
    text = retrain.scrub("انقطاع مي من يومين اتصلوا على 0933 123 456 أو +963944111222 أو ali@example.com")
    assert "0933" not in text and "963944111222" not in text and "@" not in text
    assert "انقطاع مي من يومين" in text
    assert retrain.scrub("من ٣ أيام الساعة 10 - 12") == "من ٣ أيام الساعة 10 - 12"


def test_only_one_training_at_a_time(isolated, client, headers):
    assert retrain._lock.acquire(blocking=False)
    try:
        r = client.post("/v1/train", headers=headers, json={"samples": _samples(REAL)})
        assert r.status_code == 409
    finally:
        retrain._lock.release()


def test_retrain_requires_key(client):
    assert client.post("/v1/train", json={"samples": []}).status_code == 401


def test_mcnemar_exact_p_values():
    assert retrain.mcnemar_worse_p(0, 0) == 1.0
    assert retrain.mcnemar_worse_p(2, 0) == 0.25          # تذبذب عينتين ليس دليلاً على التراجع
    assert retrain.mcnemar_worse_p(5, 0) == 0.03125       # 5 تراجعات بلا تحسينات => تراجع معنوي
    assert retrain.mcnemar_worse_p(3, 3) > 0.5
