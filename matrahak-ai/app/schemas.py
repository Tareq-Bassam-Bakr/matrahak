# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
نماذج الطلبات والاستجابات (Pydantic) — تطابق ما يرسله Laravel (App\\Services\\Ai\\AiServiceClient).

ملاحظة PHP: المصفوفة الترابطية الفارغة تُرمَّز في JSON كـ [] وليس {} — لذلك الحقول من نوع dict تقبل [] أيضاً.
"""
from __future__ import annotations

from datetime import date
from typing import Any

from pydantic import BaseModel, ConfigDict, Field, field_validator


class _In(BaseModel):
    model_config = ConfigDict(extra="ignore")


def _assoc(v: Any) -> Any:
    """PHP: [] (مصفوفة فارغة) => {}"""
    if v is None or v == []:
        return {}
    return v


def _iso_date(v: Any) -> Any:
    if v in (None, ""):
        return None
    date.fromisoformat(str(v)[:10])
    return str(v)[:10]


# ----------------------------------------------------------------- التصنيف
class CategoryIn(_In):
    slug: str = Field(min_length=1, max_length=150)
    name: str = ""
    name_en: str | None = None
    keywords: list[str] = Field(default_factory=list)
    department_code: str | None = None
    priority_weight: int | None = 5

    @field_validator("name", mode="before")
    @classmethod
    def _name(cls, v: Any) -> str:
        return v or ""

    @field_validator("keywords", mode="before")
    @classmethod
    def _keywords(cls, v: Any) -> list[str]:
        if v is None:
            return []
        if isinstance(v, str):
            v = v.split(",")
        if isinstance(v, dict):
            v = list(v.values())
        return [str(k).strip() for k in v if k is not None and str(k).strip()]


class ClassifyIn(_In):
    title: str = ""
    description: str = ""
    categories: list[CategoryIn] = Field(min_length=1, max_length=500)

    @field_validator("title", "description", mode="before")
    @classmethod
    def _text(cls, v: Any) -> str:
        return str(v or "")[:10000]


class Term(BaseModel):
    term: str
    weight: float


class Alternative(BaseModel):
    slug: str
    score: float


class ClassifyOut(BaseModel):
    category_slug: str | None
    department_code: str | None = None
    confidence: float
    priority: str | None = None
    urgency_signal: str | None = None
    summary: str | None = None
    keywords_matched: list[str] = Field(default_factory=list)
    explanation: list[Term] = Field(default_factory=list)
    alternatives: list[Alternative] = Field(default_factory=list)
    model_version: str
    backend: str
    processing_ms: int = 0


# ----------------------------------------------------------------- التكرار
class DuplicateItem(_In):
    id: str
    text: str = ""
    lat: float | None = None
    lng: float | None = None

    @field_validator("id", mode="before")
    @classmethod
    def _id(cls, v: Any) -> str:
        return str(v)

    @field_validator("text", mode="before")
    @classmethod
    def _text(cls, v: Any) -> str:
        return str(v or "")[:10000]

    @field_validator("lat", "lng", mode="before")
    @classmethod
    def _coord(cls, v: Any) -> Any:
        return None if v in ("", None) else v


class DuplicatesIn(_In):
    report: DuplicateItem
    candidates: list[DuplicateItem] = Field(default_factory=list, max_length=1000)
    radius_m: float = Field(300, gt=0, le=100000)


class DuplicatesOut(BaseModel):
    is_duplicate: bool
    duplicate_of: str | None = None
    similarity: float
    text_similarity: float | None = None
    semantic_similarity: float | None = None
    distance_m: float | None = None
    method: str | None = None


# ----------------------------------------------------------------- الصور
class ImageOut(BaseModel):
    available: bool
    method: str
    tags: list[str] = Field(default_factory=list)
    severity: int | None = None
    quality_ok: bool | None = None
    metrics: dict[str, float | int] | None = None
    error: str | None = None


# ----------------------------------------------------------------- التنبؤ والرؤى
class SeriesRow(_In):
    date: str
    district_id: int
    category_slug: str
    count: int = 0
    high_count: int = 0

    @field_validator("date", mode="before")
    @classmethod
    def _date(cls, v: Any) -> Any:
        return _iso_date(v)


class DistrictRow(_In):
    id: int
    name: str = ""
    population: int | None = None


class PredictIn(_In):
    horizon_days: int = Field(7, ge=1, le=90)
    lookback_days: int = Field(90, ge=7, le=730)
    today: str | None = None
    type_map: dict[str, str] = Field(default_factory=dict)
    districts: list[DistrictRow] = Field(default_factory=list)
    categories: dict[str, str] = Field(default_factory=dict)
    series: list[SeriesRow] = Field(default_factory=list)

    @field_validator("type_map", "categories", mode="before")
    @classmethod
    def _maps(cls, v: Any) -> Any:
        return _assoc(v)

    @field_validator("today", mode="before")
    @classmethod
    def _today(cls, v: Any) -> Any:
        return _iso_date(v)


class InsightsIn(_In):
    today: str | None = None
    lookback_days: int = Field(60, ge=7, le=730)
    districts: dict[int, str] = Field(default_factory=dict)
    categories: dict[str, str] = Field(default_factory=dict)
    series: list[SeriesRow] = Field(default_factory=list)

    @field_validator("districts", "categories", mode="before")
    @classmethod
    def _maps(cls, v: Any) -> Any:
        return _assoc(v)

    @field_validator("today", mode="before")
    @classmethod
    def _today(cls, v: Any) -> Any:
        return _iso_date(v)


class PredictOut(BaseModel):
    model_version: str
    predictions: list[dict[str, Any]]


class InsightsOut(BaseModel):
    model_version: str
    insights: list[dict[str, Any]]


# ----------------------------------------------------------------- مؤشر الخطورة
class RiskFeatures(_In):
    district_id: int
    population: int = 100000
    open_critical: int = 0
    open_high: int = 0
    open_medium: int = 0
    open_low: int = 0
    overdue: int = 0
    last30: int = 0
    prev30: int = 0
    total: int = 0
    resolved: int = 0


class RiskScales(_In):
    load: float = 1.0
    overdue: float = 0.5


class RiskIn(_In):
    districts: list[RiskFeatures] = Field(default_factory=list)
    scales: RiskScales = Field(default_factory=RiskScales)

    @field_validator("scales", mode="before")
    @classmethod
    def _scales(cls, v: Any) -> Any:
        return _assoc(v)


class RiskScore(BaseModel):
    district_id: int
    score: float


class RiskOut(BaseModel):
    model_version: str
    scores: list[RiskScore]


# ----------------------------------------------------------------- إعادة التدريب
class TrainSample(_In):
    text: str
    label: str
    source: str | None = None


class TrainIn(_In):
    samples: list[TrainSample] = Field(default_factory=list, max_length=200000)
    include_synthetic: bool = True
    force: bool = False
