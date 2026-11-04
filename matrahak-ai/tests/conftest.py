# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
إعدادات pytest المشتركة.
اختبارات إعادة التدريب تعمل على نسخة مؤقتة من models/ و data/ حتى لا تغيّر النموذج الحقيقي.
"""
from __future__ import annotations

import json
import shutil
from pathlib import Path

import pytest
from fastapi.testclient import TestClient

from app.config import settings
from app.ml import dataset
from app.ml.model import classifier

FIXTURES = Path(__file__).parent / "fixtures"


@pytest.fixture(scope="session", autouse=True)
def _model_loaded():
    classifier.load()
    assert classifier.ready, "النموذج غير جاهز — شغّل python -m app.ml.train"
    yield


@pytest.fixture(scope="session")
def client():
    from app.main import app

    with TestClient(app) as c:
        yield c


@pytest.fixture(scope="session")
def headers():
    return {"X-Internal-Key": settings.internal_api_key}


@pytest.fixture(scope="session")
def categories():
    return dataset.categories()


@pytest.fixture(scope="session")
def php_parity():
    return json.loads((FIXTURES / "php_parity.json").read_text(encoding="utf-8"))


@pytest.fixture()
def isolated(tmp_path, monkeypatch):
    """نسخة معزولة من models/ و data/ — وبعد الاختبار يُعاد تحميل النموذج الأصلي."""
    models = tmp_path / "models"
    data = tmp_path / "data"
    shutil.copytree(settings.model_dir, models)
    shutil.copytree(settings.data_dir, data, ignore=shutil.ignore_patterns("real"))
    (data / "real").mkdir()
    original = classifier.bundle
    monkeypatch.setattr(settings, "model_dir", models)
    monkeypatch.setattr(settings, "data_dir", data)
    yield tmp_path
    with classifier._lock:
        classifier._bundle = original
