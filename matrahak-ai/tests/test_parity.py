# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
التنبؤ والرؤى ومؤشر الخطورة: نتائج Python = نتائج Laravel (LocalForecaster / RiskScoringService) حرفياً،
على بيانات المنصة التجريبية. لذلك لا يتغير أي رقم في لوحة الإدارة عند توقف الخدمة والعمل بالبديل المحلي.
"""
from app.services import forecast, risk


def test_predict_matches_php(php_parity):
    fx = php_parity
    out = forecast.predict(fx["series90"], fx["type_map"], fx["districts"], 7, 90,
                           today=fx["today"], category_names=fx["categories"])
    assert len(out) == len(fx["php_predict"]) > 0
    assert out == fx["php_predict"]


def test_insights_match_php(php_parity):
    fx = php_parity
    out = forecast.insights(fx["series60"], fx["district_map"], fx["categories"], 60, today=fx["today"])
    assert out == fx["php_insights"]


def test_risk_matches_php(php_parity):
    out = risk.scores(php_parity["risk_features"], {"load": 1.0, "overdue": 0.5})
    assert out == php_parity["php_risk"]


def test_api_endpoints_return_same_results(client, headers, php_parity):
    fx = php_parity
    r = client.post("/v1/predict", headers=headers, json={
        "horizon_days": 7, "lookback_days": 90, "today": fx["today"], "type_map": fx["type_map"],
        "districts": fx["districts"], "categories": fx["categories"], "series": fx["series90"],
    })
    assert r.status_code == 200
    assert r.json()["model_version"] == forecast.PREDICT_VERSION
    assert r.json()["predictions"] == fx["php_predict"]

    r = client.post("/v1/insights", headers=headers, json={
        "today": fx["today"], "lookback_days": 60, "districts": fx["district_map"],
        "categories": fx["categories"], "series": fx["series60"],
    })
    assert r.status_code == 200 and r.json()["insights"] == fx["php_insights"]

    r = client.post("/v1/risk", headers=headers, json={"districts": fx["risk_features"],
                                                        "scales": {"load": 1.0, "overdue": 0.5}})
    assert r.status_code == 200 and r.json()["scores"] == fx["php_risk"]


def test_risk_is_monotonic():
    base = {"district_id": 1, "population": 100000, "open_critical": 1, "open_high": 1, "open_medium": 1,
            "open_low": 1, "overdue": 1, "last30": 5, "prev30": 5, "total": 20, "resolved": 15}
    s0 = risk.score(base)
    for key in ("open_critical", "open_high", "overdue", "last30"):
        assert risk.score({**base, key: base[key] + 5}) >= s0, key
    assert 0 <= s0 <= 100


def test_php_empty_arrays_are_accepted(client, headers):
    """PHP يرسل المصفوفة الترابطية الفارغة كـ [] — يجب ألا ترجع الخدمة 422."""
    r = client.post("/v1/predict", headers=headers, json={"type_map": [], "districts": [], "categories": [], "series": []})
    assert r.status_code == 200 and r.json()["predictions"] == []
    r = client.post("/v1/insights", headers=headers, json={"districts": [], "categories": [], "series": []})
    assert r.status_code == 200 and r.json()["insights"] == []
    r = client.post("/v1/risk", headers=headers, json={"districts": [], "scales": []})
    assert r.status_code == 200 and r.json()["scores"] == []
