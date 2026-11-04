# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""
خدمة الذكاء الاصطناعي لمنصة «مطرحك» — FastAPI.

    uvicorn app.main:app --host 127.0.0.1 --port 8001
    التوثيق التفاعلي (Swagger):  http://127.0.0.1:8001/docs

- الحماية: كل مسارات /v1 تتطلب الترويسة X-Internal-Key وقيمتها = AI_SERVICE_KEY في ملف .env الخاص بـ Laravel.
- الخدمة Stateless: Laravel يرسل كل البيانات اللازمة في الطلب، ولا تتصل الخدمة بقاعدة البيانات أبداً.
- إن توقفت الخدمة يعمل Laravel بالبديل المحلي (نفس الخوارزميات بلغة PHP) فلا تتعطل المنصة.
"""
from __future__ import annotations

import logging
import secrets
from contextlib import asynccontextmanager

from fastapi import Depends, FastAPI, File, Header, HTTPException, Request, UploadFile
from fastapi.concurrency import run_in_threadpool
from fastapi.responses import JSONResponse

from app import schemas
from app.config import settings
from app.ml import retrain
from app.ml.model import classifier
from app.services import classify as classify_service
from app.services import duplicates, forecast, risk, vision

log = logging.getLogger("matrahak")

MAX_IMAGE_BYTES = 15 * 1024 * 1024
DEFAULT_KEY = "change-this-internal-key"


@asynccontextmanager
async def lifespan(_: FastAPI):
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
    if settings.internal_api_key == DEFAULT_KEY:
        log.warning("INTERNAL_API_KEY still has the default value — change it in .env (and AI_SERVICE_KEY in Laravel)")
    # تحميل النموذج (أو تدريبه تلقائياً إن لم يوجد / اختلف إصدار scikit-learn)
    await run_in_threadpool(classifier.load)
    info = classifier.info
    log.info("classifier ready=%s version=%s samples=%s", info.get("ready"), info.get("version"), info.get("n_samples"))
    yield


app = FastAPI(
    title="Matrahak AI Service — خدمة الذكاء الاصطناعي لمنصة مطرحك",
    version=settings.service_version,
    description="تصنيف البلاغات (نموذج تعلم آلي مدرّب + كلمات مفتاحية)، كشف التكرار، تحليل الصور، "
                "التنبؤ بالمشكلات، الرؤى، مؤشر خطورة المحافظات، وإعادة تدريب النموذج من البلاغات الموثّقة.",
    lifespan=lifespan,
)


def require_key(x_internal_key: str | None = Header(default=None, description="= AI_SERVICE_KEY في Laravel")) -> None:
    expected = settings.internal_api_key.encode("utf-8")
    if not x_internal_key or not secrets.compare_digest(x_internal_key.encode("utf-8"), expected):
        raise HTTPException(status_code=401, detail="invalid or missing X-Internal-Key")


protected = [Depends(require_key)]


@app.exception_handler(Exception)
async def unhandled(request: Request, exc: Exception) -> JSONResponse:
    log.exception("unhandled error on %s", request.url.path)
    return JSONResponse(status_code=500, content={"detail": "internal error"})


# ----------------------------------------------------------------- الحالة
@app.get("/health", tags=["status"])
def health() -> dict:
    """حالة الخدمة والنموذج (عامة — لا تحتاج مفتاحاً)."""
    info = classifier.info
    ready = bool(info.get("ready"))
    return {
        "status": "ok" if ready else "degraded",
        "service": "matrahak-ai",
        "version": settings.service_version,
        "backend": "ml-hybrid" if ready else "keywords",
        "classifier": f"{info['version']} — {info['algorithm']}" if ready else "keywords-only (النموذج غير جاهز)",
        "model": {k: info.get(k) for k in ("ready", "version", "algorithm", "trained_at", "n_samples", "sources",
                                            "labels", "keyword_weight", "metrics")},
        "capabilities": ["classify", "duplicates", "analyze-image", "predict", "insights", "risk", "train"],
    }


@app.get("/v1/model", tags=["status"], dependencies=protected)
def model_info() -> dict:
    """تفاصيل النموذج الحالي + آخر محاولات إعادة التدريب."""
    return {"model": classifier.info, "history": retrain.history(10)}


# ----------------------------------------------------------------- البلاغات
@app.post("/v1/classify", tags=["reports"], dependencies=protected, response_model=schemas.ClassifyOut)
def classify(body: schemas.ClassifyIn) -> dict:
    """تصنيف البلاغ واقتراح المديرية والأولوية والملخص — مع تفسير القرار."""
    categories = [c.model_dump() for c in body.categories]
    return classify_service.classify(body.title, body.description, categories)


@app.post("/v1/duplicates", tags=["reports"], dependencies=protected, response_model=schemas.DuplicatesOut)
def find_duplicates(body: schemas.DuplicatesIn) -> dict:
    """هل البلاغ الجديد مكرر لبلاغ مفتوح قريب؟ (تشابه النص + القرب الجغرافي)"""
    return duplicates.find(body.report.model_dump(), [c.model_dump() for c in body.candidates], body.radius_m)


@app.post("/v1/analyze-image", tags=["reports"], dependencies=protected, response_model=schemas.ImageOut)
async def analyze_image(file: UploadFile = File(..., description="صورة البلاغ")) -> dict:
    """فحص جودة الصورة + مؤشرات لونية (احتمال حريق، غطاء نباتي، مياه)."""
    data = await file.read(MAX_IMAGE_BYTES + 1)
    if len(data) > MAX_IMAGE_BYTES:
        raise HTTPException(status_code=413, detail="image too large")
    return await run_in_threadpool(vision.analyze, data)


# ----------------------------------------------------------------- التحليلات
@app.post("/v1/predict", tags=["analytics"], dependencies=protected, response_model=schemas.PredictOut)
def predict(body: schemas.PredictIn) -> dict:
    """التنبؤ بالمشكلات خلال الأيام القادمة لكل محافظة (Poisson موزون زمنياً)."""
    predictions = forecast.predict(
        [r.model_dump() for r in body.series], body.type_map, [d.model_dump() for d in body.districts],
        body.horizon_days, body.lookback_days, today=body.today, category_names=body.categories,
    )
    return {"model_version": forecast.PREDICT_VERSION, "predictions": predictions}


@app.post("/v1/insights", tags=["analytics"], dependencies=protected, response_model=schemas.InsightsOut)
def insights(body: schemas.InsightsIn) -> dict:
    """كشف الارتفاعات المفاجئة (z-score) والاتجاهات الأسبوعية المتصاعدة."""
    items = forecast.insights([r.model_dump() for r in body.series], body.districts, body.categories,
                              body.lookback_days, today=body.today)
    return {"model_version": forecast.INSIGHTS_VERSION, "insights": items}


@app.post("/v1/risk", tags=["analytics"], dependencies=protected, response_model=schemas.RiskOut)
def risk_scores(body: schemas.RiskIn) -> dict:
    """مؤشر خطورة كل محافظة (0-100)."""
    return {"model_version": risk.VERSION,
            "scores": risk.scores([d.model_dump() for d in body.districts], body.scales.model_dump())}


# ----------------------------------------------------------------- إعادة التدريب
@app.post("/v1/train", tags=["model"], dependencies=protected)
def train(body: schemas.TrainIn) -> dict:
    """إعادة تدريب النموذج على البلاغات الموثّقة من Laravel (Champion/Challenger) — يُستبدل النموذج دون إيقاف الخدمة."""
    try:
        return retrain.retrain([s.model_dump() for s in body.samples],
                               include_synthetic=body.include_synthetic, force=body.force)
    except retrain.TrainingBusy:
        raise HTTPException(status_code=409, detail={"message": "يوجد تدريب قيد التنفيذ حالياً، حاول بعد قليل."})
    except retrain.NotEnoughData as e:
        stats = e.args[0]
        raise HTTPException(status_code=422, detail={"message": retrain.not_enough_message(stats), "samples": stats})


if __name__ == "__main__":  # python -m app.main
    import uvicorn

    uvicorn.run("app.main:app", host=settings.host, port=settings.port)
