# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""إعدادات الخدمة — من متغيرات البيئة أو ملف .env"""
from __future__ import annotations

from pathlib import Path

from pydantic_settings import BaseSettings, SettingsConfigDict

BASE_DIR = Path(__file__).resolve().parent.parent


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=BASE_DIR / ".env", env_file_encoding="utf-8", extra="ignore")

    # يجب أن يطابق AI_SERVICE_KEY في ملف .env الخاص بـ Laravel
    internal_api_key: str = "change-this-internal-key"
    host: str = "127.0.0.1"
    port: int = 8001

    data_dir: Path = BASE_DIR / "data"
    model_dir: Path = BASE_DIR / "models"
    reports_dir: Path = BASE_DIR / "reports"

    # تدريب النموذج تلقائياً عند التشغيل إن لم يوجد أو كان من إصدار scikit-learn مختلف
    auto_train: bool = True

    # وزن الكلمات المفتاحية (معرفة الخبير) في النموذج الهجين — فارغ = الوزن المختار عبر Grouped CV
    # (models/metrics.json)؛ ضع رقماً بين 0 و 1 لتجاوزه يدوياً
    keyword_weight: float | None = None

    service_version: str = "1.0.0"


settings = Settings()
