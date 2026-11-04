# Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
# Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
# Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
# © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

"""مكونات scikit-learn للنموذج: معالجة النص العربي + تمثيل TF-IDF (كلمات + حروف)."""
from __future__ import annotations

from sklearn.base import BaseEstimator, TransformerMixin
from sklearn.calibration import CalibratedClassifierCV
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import FeatureUnion, Pipeline
from sklearn.svm import LinearSVC

from app.text.arabic import normalize, preprocess_for_model


class ArabicPreprocessor(BaseEstimator, TransformerMixin):
    """mode='stems' => جذور الكلمات (لـ TF-IDF الكلمات) — mode='normalized' => نص موحّد (لـ TF-IDF الحروف)."""

    def __init__(self, mode: str = "stems"):
        self.mode = mode

    def fit(self, X, y=None):
        return self

    def transform(self, X):
        fn = preprocess_for_model if self.mode == "stems" else normalize
        return [fn(str(x)) for x in X]


def word_features() -> Pipeline:
    return Pipeline([
        ("prep", ArabicPreprocessor("stems")),
        ("tfidf", TfidfVectorizer(ngram_range=(1, 2), sublinear_tf=True, token_pattern=r"(?u)\b\w+\b", min_df=1)),
    ])


def char_features() -> Pipeline:
    # char_wb n-grams: تتحمل اختلاف كتابة العامية والأخطاء الإملائية (كهربا/كهرباء، مي/مياه)
    return Pipeline([
        ("prep", ArabicPreprocessor("normalized")),
        ("tfidf", TfidfVectorizer(analyzer="char_wb", ngram_range=(2, 5), sublinear_tf=True, min_df=2, max_features=80000)),
    ])


# وزن الكلمات المفتاحية التصميمي الافتراضي في الهجين (يُستبدل بالوزن المختار عبر Grouped CV في models/metrics.json)
DEFAULT_KEYWORD_WEIGHT = 0.2

DEFAULT_CONFIG = {"kind": "word+char", "classifier": "logreg", "C": 8.0, "keyword_weight": DEFAULT_KEYWORD_WEIGHT}


def build_pipeline(kind: str = "word+char", classifier: str = "logreg", C: float = 8.0, **_: object) -> Pipeline:
    """(**_: مفاتيح إضافية في الإعدادات مثل keyword_weight تخص الهجين وليس خط المعالجة)"""
    if kind == "word":
        feats = word_features()
    elif kind == "char":
        feats = char_features()
    else:
        feats = FeatureUnion([("word", word_features()), ("char", char_features())])

    if classifier == "svm":
        # LinearSVC مع معايرة احتمالية (Platt) حتى نحصل على درجة ثقة لكل تصنيف
        # random_state ثابت: LinearSVC يخلط البيانات داخلياً — بدونه تختلف النتائج قليلاً بين مرة وأخرى
        clf = CalibratedClassifierCV(LinearSVC(C=C, class_weight="balanced", random_state=42), cv=3, method="sigmoid")
    else:
        clf = LogisticRegression(C=C, max_iter=4000, class_weight="balanced")

    return Pipeline([("features", feats), ("clf", clf)])


def linear_coefficients(clf):
    """(classes, coef) لأي من النموذجين — لتفسير القرار."""
    if hasattr(clf, "coef_"):
        return list(clf.classes_), clf.coef_
    import numpy as np

    coefs = [cc.estimator.coef_ for cc in clf.calibrated_classifiers_]
    return list(clf.classes_), np.mean(coefs, axis=0)
