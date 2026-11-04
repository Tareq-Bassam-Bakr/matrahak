# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
كشف التكرار على أمثلة توضيحية مكتوبة يدوياً (ليست تقييماً على بيانات حقيقية — انظر README).
البلاغان في نفس المكان تقريباً (~58 متر) — الفرق فقط في النص.
"""
import pytest

from app.services.duplicates import find

DUPLICATES = [
    ("حفرة كبيرة بالشارع — في حفرة كبيرة قدام المدرسة والسيارات عم تتضرر",
     "حفرة قدام المدرسة — الحفرة اللي قدام المدرسة كبرت كتير وصارت خطر عالسيارات"),
    ("تسرب مياه — في كسر بخط المي والمي عم تطوف بالشارع", "طوفان مي بالشارع — بوري المي مكسور والشارع غرقان مي"),
    ("عمود كهرباء مائل — عمود الكهربا جنب الفرن مايل ورح يوقع", "عمود مايل جنب الفرن — العمود اللي جنب الفرن مايل كتير وخطر"),
    ("قمامة متراكمة — الزبالة متراكمة جنب الحاوية من أسبوع", "زبالة — الحاوية طافحة والزبالة عالأرض من أسبوع"),
]
# نفس المكان ونفس الكلمات المشتركة، لكن مشكلتان مختلفتان => ليس تكراراً
DIFFERENT = [
    ("حفرة بالشارع — في حفرة كبيرة قدام المدرسة", "ضو الشارع مطفي — ضو الشارع قدام المدرسة مطفي من أسبوع"),
    ("قمامة متراكمة — الزبالة متراكمة جنب الحاوية قدام البناية", "تسرب مياه — المي عم تطوف جنب الحاوية قدام البناية"),
    ("انقطاع الكهرباء — الكهربا مقطوعة عن الحي من الصبح", "انقطاع الإنترنت — الإنترنت مقطوع عن الحي من الصبح"),
    ("ازدحام مروري — عجقة سير كبيرة عند الدوار كل يوم الصبح", "إشارة مرور معطلة — الإشارة عند الدوار معطلة من يومين"),
    ("حريق — في نار بمحل بالسوق", "دخان حرق نفايات — في حدا عم يحرق زبالة جنب السوق"),
]


def _check(a: str, b: str) -> dict:
    return find({"id": "1", "text": a, "lat": 33.5, "lng": 36.3}, [{"id": "2", "text": b, "lat": 33.5004, "lng": 36.3004}], 300)


@pytest.mark.parametrize("a,b", DUPLICATES)
def test_same_problem_different_wording_is_duplicate(a, b):
    r = _check(a, b)
    assert r["is_duplicate"] is True and r["duplicate_of"] == "2", r


@pytest.mark.parametrize("a,b", DIFFERENT)
def test_different_problems_at_same_place_are_not_duplicates(a, b):
    r = _check(a, b)
    assert r["is_duplicate"] is False and r["duplicate_of"] is None, r


def test_far_away_report_is_not_duplicate():
    a, b = DUPLICATES[0]
    r = find({"id": "1", "text": a, "lat": 33.5, "lng": 36.3}, [{"id": "2", "text": b, "lat": 33.51, "lng": 36.31}], 300)
    assert r["distance_m"] > 1000 and r["is_duplicate"] is False


def test_picks_matching_candidate_among_several(client, headers):
    a, b = DUPLICATES[2]
    r = client.post("/v1/duplicates", headers=headers, json={
        "report": {"id": 50, "text": a, "lat": "33.5000000", "lng": "36.3000000"},
        "candidates": [
            {"id": 7, "text": DIFFERENT[2][1], "lat": 33.5001, "lng": 36.3001},
            {"id": 8, "text": b, "lat": 33.5004, "lng": 36.3004},
            {"id": 9, "text": "تراكم قمامة — الزبالة بالشارع", "lat": None, "lng": None},
        ],
        "radius_m": 300,
    })
    assert r.status_code == 200
    body = r.json()
    assert body["is_duplicate"] is True and body["duplicate_of"] == "8"
    assert body["method"] == "model-semantic+tfidf-char+geo"


def test_no_candidates():
    assert find({"id": "1", "text": "x"}, [], 300)["is_duplicate"] is False
