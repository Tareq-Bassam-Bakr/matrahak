# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""عقد الـ API كما يستخدمه Laravel (AiServiceClient + ReportAiProcessor)."""
import io

from PIL import Image, ImageDraw


def test_health_is_public_and_describes_model(client):
    r = client.get("/health")
    assert r.status_code == 200
    body = r.json()
    assert body["status"] == "ok" and body["backend"] == "ml-hybrid"
    assert body["model"]["ready"] is True
    assert "tfidf-hybrid" in body["classifier"]
    assert body["model"]["n_samples"] > 1000
    assert "challenge_accuracy" in body["model"]["metrics"]


def test_v1_requires_internal_key(client):
    assert client.post("/v1/classify", json={}).status_code == 401
    assert client.post("/v1/classify", headers={"X-Internal-Key": "wrong"}, json={}).status_code == 401
    assert client.get("/v1/model", headers={"X-Internal-Key": ""}).status_code == 401


def test_classify_contract(client, headers, categories):
    r = client.post("/v1/classify", headers=headers, json={
        "title": "شريط كهربا مقطوع",
        "description": "سلك كهربا مكشوف عالأرض وعم يطلع شرار قرب المدرسة",
        "categories": categories,
    })
    assert r.status_code == 200
    body = r.json()
    assert body["category_slug"] == "exposed-wires"
    assert body["backend"] == "ml-hybrid"
    assert 0.5 < body["confidence"] <= 1
    assert body["priority"] in ("high", "critical")
    assert body["department_code"]
    assert body["keywords_matched"]
    assert body["explanation"] and {"term", "weight"} <= set(body["explanation"][0])
    assert all(a["slug"] != body["category_slug"] for a in body["alternatives"])
    assert body["summary"].startswith("شريط كهربا مقطوع")


def test_classify_only_suggests_active_categories(client, headers, categories):
    """تصنيف معطّل في لوحة الإدارة (غير مرسل) لا يُقترح أبداً."""
    without_fire = [c for c in categories if c["slug"] != "fire"]
    r = client.post("/v1/classify", headers=headers, json={
        "title": "حريق", "description": "في نار عم تشتعل بمحل بالسوق والدخان كثيف", "categories": without_fire,
    })
    assert r.status_code == 200
    body = r.json()
    assert body["category_slug"] != "fire"
    assert body["urgency_signal"] == "critical"  # مؤشر الخطورة من النص يبقى معتمداً


def test_classify_low_confidence_for_irrelevant_text(client, headers, categories):
    r = client.post("/v1/classify", headers=headers, json={"title": "مرحبا", "description": "كيفكم", "categories": categories})
    body = r.json()
    assert body["confidence"] < 0.3  # أقل من حد التطبيق التلقائي في Laravel
    assert body["keywords_matched"] == []
    assert body["urgency_signal"] == "low"


def test_classify_validation(client, headers):
    assert client.post("/v1/classify", headers=headers, json={"title": "x", "description": "y", "categories": []}).status_code == 422
    r = client.post("/v1/classify", headers=headers, json={
        "title": None, "description": "ما في مي من ٣ ايام",
        "categories": [{"slug": "drinking-water-outage", "keywords": None, "priority_weight": None}],
    })
    assert r.status_code == 200 and r.json()["category_slug"] == "drinking-water-outage"


def _image(color, size=(640, 480), pattern=False) -> bytes:
    img = Image.new("RGB", size, color)
    if pattern:  # تفاصيل حادة حتى لا تُعدّ الصورة «غير واضحة»
        d = ImageDraw.Draw(img)
        for x in range(0, size[0], 16):
            d.line([(x, 0), (x, size[1])], fill=(255, 255, 255), width=2)
    buf = io.BytesIO()
    img.save(buf, "JPEG", quality=92)
    return buf.getvalue()


def test_analyze_image_fire_dark_and_invalid(client, headers):
    r = client.post("/v1/analyze-image", headers=headers, files={"file": ("fire.jpg", _image((235, 120, 25), pattern=True), "image/jpeg")})
    body = r.json()
    assert r.status_code == 200 and body["available"] is True
    assert "احتمال حريق أو لهب" in body["tags"] and body["severity"] == 8

    body = client.post("/v1/analyze-image", headers=headers, files={"file": ("dark.jpg", _image((10, 10, 12)), "image/jpeg")}).json()
    assert "إضاءة ضعيفة" in body["tags"] and body["quality_ok"] is False

    body = client.post("/v1/analyze-image", headers=headers, files={"file": ("small.jpg", _image((90, 90, 90), (200, 150), True), "image/jpeg")}).json()
    assert "دقة منخفضة" in body["tags"]

    body = client.post("/v1/analyze-image", headers=headers, files={"file": ("x.jpg", b"not an image", "image/jpeg")}).json()
    assert body["available"] is True and body["error"] == "unreadable_image"


def test_model_endpoint(client, headers):
    r = client.get("/v1/model", headers=headers)
    assert r.status_code == 200
    assert r.json()["model"]["ready"] is True
    assert isinstance(r.json()["history"], list)
