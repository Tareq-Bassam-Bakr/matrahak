# مرجع الـ API — منصة «مطرحك»

**كل الأمثلة في هذا الملف ردود حقيقية** سُجّلت من خادم Laravel يعمل على MySQL 8.4، لا أمثلة مكتوبة يدوياً.
- **التسجيل:** أداة `tools/capture.py`، وحُجبت منها الرموز فقط.
- **الاختصار:** في القوائم الطويلة يظهر أول عنصرين أو ثلاثة، ثم سطر مثل «… و5 عناصر أخرى».
- **الردود كاملة:** في مجلد `api-examples/`.
- **التجربة الفورية:** مجموعة Postman في `postman/` (القسم 12).

## 1. الأساسيات

| البند | القيمة |
|---|---|
| العنوان الأساسي | `http://127.0.0.1:8000/api/v1`. من محاكي Android: `http://10.0.2.2:8000/api/v1`. من هاتف حقيقي: `http://IP-الكمبيوتر:8000/api/v1` |
| الصيغة | JSON بترميز UTF-8. رفع الملفات بـ `multipart/form-data` |
| المصادقة | Laravel Sanctum: `Authorization: Bearer <TOKEN>` من الدخول أو التسجيل |
| الجلسات | رمز لكل تطبيق: `citizen-app` و`employee-app`. الدخول الجديد يُبطل رمز نفس التطبيق السابق، ولا تتقادم الرموز تلقائياً |
| الصلاحيات | مسارات `/citizen/*` لحسابات المواطنين فقط، و`/employee/*` للموظفين والمدراء. رمز من النوع الخطأ يعيد **403** |
| حد الطلبات | 120 طلباً في الدقيقة لكل مستخدم أو IP. الدخول والتسجيل 10 محاولات في الدقيقة لكل IP، وتجاوزه يعيد **429** |
| المعرّفات | البلاغ يُعرَّف في الروابط بـ `uuid` (لا يمكن تخمينه)، و`ticket_number` (`MTR-2026-000706`) للعرض للمواطن |
| التواريخ | ISO 8601 بتوقيت دمشق، مثل `2026-10-25T18:19:37+03:00` |
| اللغة | كل الرسائل بالعربية، ومنها رسائل التحقق لكل حقل |

### 1.1 شكل الرد الموحّد

**النجاح:**

```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "…": "…"
  }
}
```
**القوائم المرقّمة (Pagination)** تضيف `meta`:
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    "…"
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 15,
    "total": 23,
    "has_more": true
  }
}
```
**الخطأ:** `message` هو أول خطأ، و`errors` كل أخطاء الحقول (في 422 فقط):
```json
{
  "success": false,
  "message": "الاسم يجب أن يكون 3 أحرف على الأقل",
  "errors": {
    "name": [
      "الاسم يجب أن يكون 3 أحرف على الأقل"
    ],
    "phone": [
      "رقم الجوال يجب أن يكون سورياً بصيغة 09XXXXXXXX"
    ]
  }
}
```

### 1.2 رموز الحالة

| الرمز | المعنى | مثال حقيقي |
|---|---|---|
| 200 / 201 | نجاح / تم الإنشاء | — |
| 401 | لا يوجد رمز أو انتهت الجلسة | «انتهت الجلسة — سجّل الدخول مجدداً» |
| 403 | الرمز صحيح لكن الإجراء غير مسموح | «هذا البلاغ ليس مسنداً إليك — استلمه أولاً» |
| 404 | غير موجود أو خارج نطاق المستخدم | «البلاغ غير موجود أو لا يتبع مديريتك» |
| 422 | بيانات غير صحيحة أو قاعدة أعمال | «لا يمكن نقل البلاغ من «جاري المعالجة» إلى «مغلق»» |
| 429 | تجاوز حد الطلبات | «محاولات كثيرة، انتظر دقيقة ثم أعد المحاولة» |
| 500 | خطأ في الخادم (دون تفاصيل في الإنتاج) | «حدث خطأ في الخادم — حاول لاحقاً» |

**401 — طلب محمي بلا رمز**
```http
GET {{base_url}}/citizen/profile
Accept: application/json
```
الرد `401`:
```json
{
  "success": false,
  "message": "انتهت الجلسة — سجّل الدخول مجدداً",
  "errors": null
}
```
**403 — رمز مواطن على مسار موظف**
```http
GET {{base_url}}/employee/reports/assigned
Authorization: Bearer <TOKEN>
Accept: application/json
```
الرد `403`:
```json
{
  "success": false,
  "message": "هذا الحساب غير مصرح له باستخدام تطبيق الموظف"
}
```
**404 — بلاغ خارج مديرية الموظف**
```http
GET {{base_url}}/employee/reports/00000000-0000-0000-0000-000000000000
Authorization: Bearer <TOKEN>
Accept: application/json
```
الرد `404`:
```json
{
  "success": false,
  "message": "البلاغ غير موجود أو لا يتبع مديريتك",
  "errors": null
}
```

## 2. فهرس المسارات (43 مساراً)

| # | الطريقة | المسار | المصادقة | الوظيفة |
|---|---|---|---|---|
| 1 | `GET` | [`/health`](#get--health) | بلا تسجيل دخول | حالة الخادم |
| 2 | `GET` | [`/shared/categories`](#get--shared-categories) | بلا تسجيل دخول | التصنيفات المفعّلة |
| 3 | `GET` | [`/shared/departments`](#get--shared-departments) | بلا تسجيل دخول | المديريات المفعّلة |
| 4 | `GET` | [`/shared/districts`](#get--shared-districts) | بلا تسجيل دخول | المحافظات |
| 5 | `POST` | [`/citizen/auth/register`](#post--citizen-auth-register) | بلا تسجيل دخول | إنشاء حساب مواطن |
| 6 | `POST` | [`/citizen/auth/login`](#post--citizen-auth-login) | بلا تسجيل دخول | تسجيل الدخول |
| 7 | `POST` | [`/citizen/auth/logout`](#post--citizen-auth-logout) | رمز المواطن | تسجيل الخروج |
| 8 | `GET` | [`/citizen/profile`](#get--citizen-profile) | رمز المواطن | الملف الشخصي |
| 9 | `POST|PUT` | [`/citizen/profile`](#post--citizen-profile) | رمز المواطن | تعديل الملف الشخصي وكلمة المرور |
| 10 | `GET` | [`/citizen/reports`](#get--citizen-reports) | رمز المواطن | بلاغاتي |
| 11 | `POST` | [`/citizen/reports`](#post--citizen-reports) | رمز المواطن | رفع بلاغ |
| 12 | `GET` | [`/citizen/reports/stats`](#get--citizen-reports-stats) | رمز المواطن | ملخص بلاغاتي |
| 13 | `GET` | [`/citizen/reports/{uuid}`](#get--citizen-reports-uuid) | رمز المواطن | تفاصيل بلاغ |
| 14 | `DELETE` | [`/citizen/reports/{uuid}`](#delete--citizen-reports-uuid) | رمز المواطن | حذف بلاغ |
| 15 | `POST` | [`/citizen/reports/{uuid}/rate`](#post--citizen-reports-uuid-rate) | رمز المواطن | تقييم الخدمة |
| 16 | `POST` | [`/citizen/reports/{uuid}/upvote`](#post--citizen-reports-uuid-upvote) | رمز المواطن | تأييد بلاغ (تبديل) |
| 17 | `GET` | [`/citizen/map/nearby`](#get--citizen-map-nearby) | رمز المواطن | البلاغات القريبة |
| 18 | `GET` | [`/citizen/map/heatmap`](#get--citizen-map-heatmap) | رمز المواطن | نقاط الخريطة الحرارية |
| 19 | `GET` | [`/citizen/map/layers`](#get--citizen-map-layers) | رمز المواطن | طبقات الخريطة العامة |
| 20 | `GET` | [`/citizen/map/districts/{district}/stats`](#get--citizen-map-districts-district-stats) | رمز المواطن | مؤشرات محافظة |
| 21 | `POST` | [`/employee/auth/login`](#post--employee-auth-login) | بلا تسجيل دخول | دخول الموظف |
| 22 | `POST` | [`/employee/auth/logout`](#post--employee-auth-logout) | رمز الموظف | تسجيل الخروج |
| 23 | `GET` | [`/employee/profile`](#get--employee-profile) | رمز الموظف | الملف الشخصي (مع المديرية) |
| 24 | `POST|PUT` | [`/employee/profile`](#post--employee-profile) | رمز الموظف | تعديل الملف الشخصي |
| 25 | `GET` | [`/employee/reports/assigned`](#get--employee-reports-assigned) | رمز الموظف | البلاغات المسندة إليّ |
| 26 | `GET` | [`/employee/reports/pool`](#get--employee-reports-pool) | رمز الموظف | قائمة القسم (غير المستلمة) |
| 27 | `GET` | [`/employee/reports/history`](#get--employee-reports-history) | رمز الموظف | سجلي (المنجزة) |
| 28 | `GET` | [`/employee/reports/{uuid}`](#get--employee-reports-uuid) | رمز الموظف | تفاصيل بلاغ للموظف |
| 29 | `POST` | [`/employee/reports/{uuid}/claim`](#post--employee-reports-uuid-claim) | رمز الموظف | استلام بلاغ من القسم |
| 30 | `POST` | [`/employee/reports/{uuid}/status`](#post--employee-reports-uuid-status) | رمز الموظف | تغيير الحالة |
| 31 | `POST` | [`/employee/reports/{uuid}/notes`](#post--employee-reports-uuid-notes) | رمز الموظف | إضافة ملاحظة |
| 32 | `POST` | [`/employee/reports/{uuid}/reply`](#post--employee-reports-uuid-reply) | رمز الموظف | الرد على المواطن |
| 33 | `POST` | [`/employee/reports/{uuid}/transfer`](#post--employee-reports-uuid-transfer) | رمز الموظف | تحويل لمديرية أخرى |
| 34 | `POST` | [`/employee/reports/{uuid}/attachments`](#post--employee-reports-uuid-attachments) | رمز الموظف | صور بعد المعالجة |
| 35 | `GET` | [`/employee/map/department-issues`](#get--employee-map-department-issues) | رمز الموظف | خريطة بلاغات القسم |
| 36 | `GET` | [`/employee/analytics/my-stats`](#get--employee-analytics-my-stats) | رمز الموظف | إحصائياتي |
| 37 | `GET` | [`/employee/analytics/workload`](#get--employee-analytics-workload) | رمز الموظف | عبء العمل الحالي |
| 38 | `GET` | [`/employee/analytics/department-stats`](#get--employee-analytics-department-stats) | رمز الموظف | مؤشرات المديرية |
| 39 | `GET` | [`/notifications`](#get--notifications) | رمز أي من التطبيقين | قائمة الإشعارات |
| 40 | `GET` | [`/notifications/unread-count`](#get--notifications-unread-count) | رمز أي من التطبيقين | عدد غير المقروء |
| 41 | `POST` | [`/notifications/{id}/read`](#post--notifications-id-read) | رمز أي من التطبيقين | تعليم إشعار كمقروء |
| 42 | `POST` | [`/notifications/read-all`](#post--notifications-read-all) | رمز أي من التطبيقين | تعليم الكل كمقروء |
| 43 | `POST` | [`/notifications/device-token`](#post--notifications-device-token) | رمز أي من التطبيقين | رمز FCM للجهاز |

## 3. عام — بلا تسجيل دخول

<a id="get--health"></a>
### `GET` /health — حالة الخادم

**المصادقة:** بلا تسجيل دخول.
يفحص أن الخادم يعمل، ويعرض حالة خدمة الذكاء الاصطناعي وPush. يستخدمه التطبيقان في «فحص الاتصال».

**مثال** — الرد `200`
```http
GET {{base_url}}/health
Accept: application/json
```
```json
{
  "success": true,
  "message": "السيرفر يعمل",
  "data": {
    "status": "ok",
    "time": "2026-10-25T19:23:37+03:00",
    "ai_service": {
      "reachable": true,
      "backend": "ml-hybrid"
    },
    "push_notifications": false
  }
}
```

<a id="get--shared-categories"></a>
### `GET` /shared/categories — التصنيفات المفعّلة

**المصادقة:** بلا تسجيل دخول.
التصنيفات مع المديرية والأيقونة واللون. كاش 10 دقائق يُمسح فور أي تعديل من لوحة الإدارة.

**مثال** — الرد `200`
```http
GET {{base_url}}/shared/categories
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": 3,
      "name": "النفايات والقمامة",
      "name_en": "Garbage",
      "slug": "garbage",
      "icon": "delete",
      "color": "#8D6E63",
      "sla_hours": 24,
      "department": {
        "id": 1,
        "name": "مديرية الخدمات والصيانة",
        "code": "MUN"
      }
    },
    {
      "id": 2,
      "name": "تلف الأرصفة",
      "name_en": "Sidewalk Damage",
      "slug": "sidewalk-damage",
      "icon": "directions_walk",
      "color": "#F17C67",
      "sla_hours": 120,
      "department": {
        "id": 1,
        "name": "مديرية الخدمات والصيانة",
        "code": "MUN"
      }
    },
    {
      "id": 1,
      "name": "حفر وتلف الطرق",
      "name_en": "Road Damage",
      "slug": "road-damage",
      "icon": "construction",
      "color": "#E4572E",
      "sla_hours": 72,
      "department": {
        "id": 1,
        "name": "مديرية الخدمات والصيانة",
        "code": "MUN"
      }
    },
    "… و21 عنصراً آخر"
  ]
}
```

<a id="get--shared-departments"></a>
### `GET` /shared/departments — المديريات المفعّلة

**المصادقة:** بلا تسجيل دخول.
تستخدمها شاشة «تحويل البلاغ» في تطبيق الموظف.

**مثال** — الرد `200`
```http
GET {{base_url}}/shared/departments
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": 6,
      "name": "الدفاع المدني والسلامة العامة",
      "code": "SAFETY",
      "categories": [
        {
          "id": 18,
          "name": "مبنى آيل للسقوط"
        },
        {
          "id": 19,
          "name": "حريق"
        },
        "… وعنصر آخر"
      ]
    },
    {
      "id": 7,
      "name": "مديرية الاتصالات والتقانة",
      "code": "TELECOM",
      "categories": [
        {
          "id": 21,
          "name": "انقطاع الإنترنت"
        },
        {
          "id": 22,
          "name": "عطل في شبكة الاتصالات"
        }
      ]
    },
    {
      "id": 5,
      "name": "مديرية البيئة",
      "code": "ENV",
      "categories": [
        {
          "id": 15,
          "name": "مخالفة بيئية"
        },
        {
          "id": 16,
          "name": "حرق نفايات"
        },
        "… وعنصر آخر"
      ]
    },
    "… و5 عناصر أخرى"
  ]
}
```

<a id="get--shared-districts"></a>
### `GET` /shared/districts — المحافظات

**المصادقة:** بلا تسجيل دخول.
المحافظات الـ 14 مع المركز والسكان ومؤشر الخطورة (بدون الحدود الجغرافية لتخفيف الحجم).

**مثال** — الرد `200`
```http
GET {{base_url}}/shared/districts
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": 1,
      "name": "محافظة دمشق",
      "name_en": "Damascus",
      "code": "DAM",
      "city": "دمشق",
      "center": {
        "lat": 33.5138,
        "lng": 36.2765
      },
      "population": 1754000,
      "risk_score": 61.58,
      "risk_level": "high"
    },
    {
      "id": 2,
      "name": "محافظة ريف دمشق",
      "name_en": "Rif Dimashq",
      "code": "RIF",
      "city": "دوما",
      "center": {
        "lat": 33.5731,
        "lng": 36.4003
      },
      "population": 2836000,
      "risk_score": 75.54,
      "risk_level": "critical"
    },
    {
      "id": 3,
      "name": "محافظة حلب",
      "name_en": "Aleppo",
      "code": "ALP",
      "city": "حلب",
      "center": {
        "lat": 36.2021,
        "lng": 37.1343
      },
      "population": 4868000,
      "risk_score": 53.31,
      "risk_level": "high"
    },
    "… و11 عنصراً آخر"
  ]
}
```


## 4. المواطن — الحساب

<a id="post--citizen-auth-register"></a>
### `POST` /citizen/auth/register — إنشاء حساب مواطن

**المصادقة:** بلا تسجيل دخول.
ينشئ الحساب ويعيد رمز الدخول مباشرةً. حد المحاولات: 10 في الدقيقة لكل IP.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `name` | JSON | نص 3–100 | نعم |
| `email` | JSON | بريد صحيح، فريد | نعم |
| `phone` | JSON | جوال سوري `09XXXXXXXX` (يُقبل +963 و00963)، فريد | نعم |
| `password` | JSON | 8 أحرف على الأقل، حروف وأرقام | نعم |
| `password_confirmation` | JSON | مطابق لكلمة المرور | نعم |
| `national_id` | JSON | 11 رقماً، فريد | لا |
| `district_id` | JSON | معرّف محافظة | لا |
| `fcm_token` | JSON | رمز FCM للجهاز | لا |

**مثال** — الرد `201`
```http
POST {{base_url}}/citizen/auth/register
Accept: application/json
Content-Type: application/json

{
  "name": "مواطن التوثيق",
  "email": "docs.945417@example.com",
  "phone": "0928803791",
  "password": "Docs@2026x",
  "password_confirmation": "Docs@2026x",
  "district_id": 1
}
```
```json
{
  "success": true,
  "message": "تم إنشاء الحساب بنجاح",
  "data": {
    "user": {
      "id": "f9252e73-8be5-47be-871d-b91ad9f98ae0",
      "name": "مواطن التوثيق",
      "email": "docs.945417@example.com",
      "phone": "0928803791",
      "avatar": null,
      "user_type": "citizen",
      "is_verified": false,
      "preferred_lang": null,
      "district": {
        "id": 1,
        "name": "محافظة دمشق"
      },
      "department": null,
      "created_at": "2026-10-25T19:23:38+03:00"
    },
    "token": "<TOKEN>"
  }
}
```

**مثال: بريد مستخدم + جوال غير سوري + كلمة مرور ضعيفة** — الرد `422`
```http
POST {{base_url}}/citizen/auth/register
Accept: application/json
Content-Type: application/json

{
  "name": "أ",
  "email": "docs.945417@example.com",
  "phone": "0512345678",
  "password": "123",
  "password_confirmation": "321"
}
```
```json
{
  "success": false,
  "message": "الاسم يجب أن يكون 3 أحرف على الأقل",
  "errors": {
    "name": [
      "الاسم يجب أن يكون 3 أحرف على الأقل"
    ],
    "email": [
      "البريد الإلكتروني مستخدم مسبقاً"
    ],
    "phone": [
      "رقم الجوال يجب أن يكون سورياً بصيغة 09XXXXXXXX"
    ],
    "password": [
      "تأكيد كلمة المرور غير مطابق",
      "كلمة المرور يجب أن تكون 8 أحرف على الأقل",
      "… وعنصر آخر"
    ]
  }
}
```

<a id="post--citizen-auth-login"></a>
### `POST` /citizen/auth/login — تسجيل الدخول

**المصادقة:** بلا تسجيل دخول.
الحقل `email` يقبل البريد **أو** رقم الجوال. الدخول الجديد يُبطل رمز المواطن السابق (جلسة واحدة للتطبيق).

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `email` | JSON | البريد أو الجوال | نعم |
| `password` | JSON | كلمة المرور | نعم |
| `fcm_token` | JSON | رمز FCM | لا |

**مثال: الدخول برقم الجوال (أو البريد) في نفس الحقل email** — الرد `200`
```http
POST {{base_url}}/citizen/auth/login
Accept: application/json
Content-Type: application/json

{
  "email": "0928803791",
  "password": "Docs@2026x"
}
```
```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "user": {
      "id": "f9252e73-8be5-47be-871d-b91ad9f98ae0",
      "name": "مواطن التوثيق",
      "email": "docs.945417@example.com",
      "phone": "0928803791",
      "avatar": null,
      "user_type": "citizen",
      "is_verified": false,
      "preferred_lang": "ar",
      "district": {
        "id": 1,
        "name": "محافظة دمشق"
      },
      "department": null,
      "created_at": "2026-10-25T19:23:38+03:00"
    },
    "token": "<TOKEN>"
  }
}
```

**مثال** — الرد `422`
```http
POST {{base_url}}/citizen/auth/login
Accept: application/json
Content-Type: application/json

{
  "email": "docs.945417@example.com",
  "password": "wrong-pass-1"
}
```
```json
{
  "success": false,
  "message": "البريد الإلكتروني/رقم الجوال أو كلمة المرور غير صحيحة",
  "errors": {
    "email": [
      "البريد الإلكتروني/رقم الجوال أو كلمة المرور غير صحيحة"
    ]
  }
}
```

<a id="post--citizen-auth-logout"></a>
### `POST` /citizen/auth/logout — تسجيل الخروج

**المصادقة:** رمز المواطن.
يحذف الرمز الحالي ورمز FCM للجهاز.

**مثال** — الرد `200`
```http
POST {{base_url}}/citizen/auth/logout
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم تسجيل الخروج",
  "data": null
}
```

<a id="get--citizen-profile"></a>
### `GET` /citizen/profile — الملف الشخصي

**المصادقة:** رمز المواطن.

**مثال** — الرد `200`
```http
GET {{base_url}}/citizen/profile
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "id": "f9252e73-8be5-47be-871d-b91ad9f98ae0",
    "name": "مواطن التوثيق",
    "email": "docs.945417@example.com",
    "phone": "0928803791",
    "national_id": null,
    "avatar": null,
    "user_type": "citizen",
    "is_verified": false,
    "preferred_lang": "ar",
    "district": {
      "id": 1,
      "name": "محافظة دمشق"
    },
    "department": null,
    "created_at": "2026-10-25T19:23:38+03:00"
  }
}
```

<a id="post--citizen-profile"></a>
### `POST|PUT` /citizen/profile — تعديل الملف الشخصي وكلمة المرور

**المصادقة:** رمز المواطن.
كل الحقول اختيارية. لتغيير كلمة المرور أرسل `current_password` مع الجديدة. الصورة بـ multipart.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `name` | JSON | نص 3–100 | لا |
| `phone` | JSON | جوال سوري فريد | لا |
| `district_id` | JSON | معرّف محافظة أو null | لا |
| `preferred_lang` | JSON | `ar` أو `en` | لا |
| `avatar` | form-data | صورة حتى 4 MB | لا |
| `current_password` | JSON | إلزامي مع password | لا |
| `password` | JSON | 8+ حروف وأرقام + `password_confirmation` | لا |

**مثال** — الرد `200`
```http
POST {{base_url}}/citizen/profile
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "name": "مواطن التوثيق المعدّل"
}
```
```json
{
  "success": true,
  "message": "تم تحديث الملف الشخصي",
  "data": {
    "id": "f9252e73-8be5-47be-871d-b91ad9f98ae0",
    "name": "مواطن التوثيق المعدّل",
    "email": "docs.945417@example.com",
    "phone": "0928803791",
    "national_id": null,
    "avatar": null,
    "user_type": "citizen",
    "is_verified": false,
    "preferred_lang": "ar",
    "district": {
      "id": 1,
      "name": "محافظة دمشق"
    },
    "department": null,
    "created_at": "2026-10-25T19:23:38+03:00"
  }
}
```

**مثال: تغيير كلمة المرور عبر نفس المسار** — الرد `200`
```http
POST {{base_url}}/citizen/profile
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "current_password": "Docs@2026x",
  "password": "Docs@2026y",
  "password_confirmation": "Docs@2026y"
}
```
```json
{
  "success": true,
  "message": "تم تحديث الملف الشخصي",
  "data": {
    "id": "f9252e73-8be5-47be-871d-b91ad9f98ae0",
    "name": "مواطن التوثيق المعدّل",
    "email": "docs.945417@example.com",
    "phone": "0928803791",
    "national_id": null,
    "avatar": null,
    "user_type": "citizen",
    "is_verified": false,
    "preferred_lang": "ar",
    "district": {
      "id": 1,
      "name": "محافظة دمشق"
    },
    "department": null,
    "created_at": "2026-10-25T19:23:38+03:00"
  }
}
```


## 5. المواطن — البلاغات

<a id="get--citizen-reports"></a>
### `GET` /citizen/reports — بلاغاتي

**المصادقة:** رمز المواطن.
قائمة مرقّمة (Pagination) ببلاغات المواطن، الأحدث أولاً.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `status` | Query | حالة واحدة | لا |
| `priority` | Query | أولوية | لا |
| `open` | Query | `1` المفتوحة فقط | لا |
| `page` | Query | رقم الصفحة | لا |
| `per_page` | Query | 1–50 (افتراضي 15) | لا |

**مثال** — الرد `200`
```http
GET {{base_url}}/citizen/reports?per_page=2
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم جلب البلاغات",
  "data": [
    {
      "id": "608b2ad5-1bbe-42f6-b75c-89ace5a9d096",
      "ticket_number": "MTR-2026-000720",
      "title": "عمود كهرباء مائل فوق الرصيف",
      "description": "عمود الكهرباء مايل وأسلاكه متدلية فوق الرصيف قرب المدرسة",
      "status": "pending",
      "status_ar": "في الانتظار",
      "priority": "high",
      "priority_ar": "عالية",
      "is_open": true,
      "is_anonymous": false,
      "citizen": {
        "name": "مواطن التوثيق المعدّل",
        "is_me": true
      },
      "category": {
        "id": 1,
        "name": "حفر وتلف الطرق",
        "slug": "road-damage",
        "icon": "construction",
        "color": "#E4572E"
      },
      "department": {
        "id": 1,
        "name": "مديرية الخدمات والصيانة"
      },
      "district": {
        "id": 1,
        "name": "محافظة دمشق"
      },
      "location": {
        "latitude": 33.513,
        "longitude": 36.284,
        "address": null,
        "notes": null
      },
      "attachments": [],
      "after_attachments": [],
      "updates": [],
      "rating": null,
      "ai_summary": "عمود كهرباء مائل فوق الرصيف — عمود الكهرباء مايل وأسلاكه متدلية فوق الرصيف قرب المدرسة",
      "stats": {
        "views": 0,
        "upvotes": 0
      },
      "has_upvoted": false,
      "is_owner": true,
      "can_delete": true,
      "can_rate": false,
      "can_upvote": false,
      "sla": {
        "expected_resolution": "2026-10-28T19:23:42+03:00",
        "is_overdue": false
      },
      "dates": {
        "created": "2026-10-25T19:23:42+03:00",
        "assigned": null,
        "started": null,
        "resolved": null,
        "closed": null
      },
      "qr_code": "http://127.0.0.1:8000/storage/qrcodes/608b2ad5-1bbe-42f6-b75c-89ace5a9d096.svg",
      "tracking_url": "http://127.0.0.1:8000/track/608b2ad5-1bbe-42f6-b75c-89ace5a9d096"
    },
    "… وبلاغ آخر في هذه الصفحة"
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 2,
    "total": 2,
    "has_more": false
  }
}
```

**مثال** — الرد `200`
```http
GET {{base_url}}/citizen/reports?open=1&per_page=2
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم جلب البلاغات",
  "data": [
    {
      "id": "608b2ad5-1bbe-42f6-b75c-89ace5a9d096",
      "ticket_number": "MTR-2026-000720",
      "title": "عمود كهرباء مائل فوق الرصيف",
      "status": "pending",
      "is_open": true,
      "stats": {
        "views": 0,
        "upvotes": 0
      },
      "…": "نفس شكل العنصر في المثال السابق"
    },
    {
      "id": "7bcdeae9-e2ad-4c79-a33b-ebc67a582a25",
      "ticket_number": "MTR-2026-000719",
      "title": "حفرة عميقة قرب دوار المدينة",
      "status": "pending",
      "is_open": true,
      "stats": {
        "views": 0,
        "upvotes": 0
      },
      "…": "نفس شكل العنصر في المثال السابق"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 2,
    "total": 2,
    "has_more": false
  }
}
```

<a id="post--citizen-reports"></a>
### `POST` /citizen/reports — رفع بلاغ

**المصادقة:** رمز المواطن.
`multipart/form-data`. التصنيف اختياري: إن لم يُرسل يحدده الذكاء الاصطناعي بعد الرد بثوانٍ. الخادم يحدد المحافظة من الإحداثيات، ويحسب المهلة، ويولّد رقم المتابعة ورمز QR، ويسند البلاغ تلقائياً.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `title` | form-data | نص 5–255 | نعم |
| `description` | form-data | نص 10–5000 | نعم |
| `latitude` | form-data | −90..90 | نعم |
| `longitude` | form-data | −180..180 | نعم |
| `category_id` | form-data | معرّف تصنيف | لا |
| `address` | form-data | حتى 500 | لا |
| `location_notes` | form-data | حتى 255 | لا |
| `is_anonymous` | form-data | `0` أو `1` | لا |
| `attachments[]` | form-data | حتى 5 ملفات × 20 MB: jpg, png, webp, mp4, mov, 3gp | لا |

**مثال: multipart/form-data — التصنيف اختياري ويحدده الذكاء الاصطناعي** — الرد `201`
```http
POST {{base_url}}/citizen/reports
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: multipart/form-data

title = تسرب مياه من أنبوب رئيسي
description = في تسريب مي قوي من أنبوب مكسور عم يغرق الشارع قدام البناية
latitude = 33.5165000
longitude = 36.2912000
location_notes = قرب الصيدلية
is_anonymous = 0
attachments[] (leak.png)
```
```json
{
  "success": true,
  "message": "تم رفع البلاغ بنجاح — رقم المتابعة: MTR-2026-000718",
  "data": {
    "id": "bbdc7c6a-ae0a-4c63-8a36-01dc84bdff42",
    "ticket_number": "MTR-2026-000718",
    "title": "تسرب مياه من أنبوب رئيسي",
    "description": "في تسريب مي قوي من أنبوب مكسور عم يغرق الشارع قدام البناية",
    "status": "pending",
    "status_ar": "في الانتظار",
    "priority": "medium",
    "priority_ar": "متوسطة",
    "is_open": true,
    "is_anonymous": false,
    "citizen": {
      "name": "مواطن التوثيق المعدّل",
      "is_me": true
    },
    "category": null,
    "department": null,
    "district": {
      "id": 1,
      "name": "محافظة دمشق"
    },
    "location": {
      "latitude": 33.5165,
      "longitude": 36.2912,
      "address": null,
      "notes": "قرب الصيدلية"
    },
    "attachments": [
      {
        "id": 28,
        "type": "image",
        "stage": "before",
        "url": "http://127.0.0.1:8000/storage/reports/bbdc7c6a-ae0a-4c63-8a36-01dc84bdff42/OLwBMWX4szYvsE043eRNR6GN0u59mryIt167XD2z.png",
        "thumbnail": null,
        "name": "leak.png",
        "size": "0.1 KB",
        "mime": "image/png",
        "ai_tags": null
      }
    ],
    "after_attachments": [],
    "updates": [
      {
        "id": 4892,
        "type": "created",
        "type_ar": "استلام البلاغ",
        "old_value": null,
        "new_value": "pending",
        "old_value_ar": null,
        "new_value_ar": "في الانتظار",
        "note": "تم استلام البلاغ — محافظة دمشق",
        "is_public": true,
        "by": {
          "name": "مواطن التوثيق المعدّل",
          "type": "citizen"
        },
        "date": "2026-10-25T19:23:39+03:00"
      }
    ],
    "rating": null,
    "ai_summary": null,
    "stats": {
      "views": 0,
      "upvotes": 0
    },
    "has_upvoted": false,
    "is_owner": true,
    "can_delete": true,
    "can_rate": false,
    "can_upvote": false,
    "sla": {
      "expected_resolution": "2026-10-28T19:23:39+03:00",
      "is_overdue": false
    },
    "dates": {
      "created": "2026-10-25T19:23:39+03:00",
      "assigned": null,
      "started": null,
      "resolved": null,
      "closed": null
    },
    "qr_code": "http://127.0.0.1:8000/storage/qrcodes/bbdc7c6a-ae0a-4c63-8a36-01dc84bdff42.svg",
    "tracking_url": "http://127.0.0.1:8000/track/bbdc7c6a-ae0a-4c63-8a36-01dc84bdff42"
  }
}
```

**مثال** — الرد `422`
```http
POST {{base_url}}/citizen/reports
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: multipart/form-data

title = قص
description = قصير
latitude = 200
```
```json
{
  "success": false,
  "message": "العنوان قصير جداً (5 أحرف على الأقل)",
  "errors": {
    "title": [
      "العنوان قصير جداً (5 أحرف على الأقل)"
    ],
    "description": [
      "الوصف قصير جداً (10 أحرف على الأقل)"
    ],
    "latitude": [
      "يجب أن تكون قيمة خط العرض بين -90 و 90."
    ],
    "longitude": [
      "الموقع الجغرافي مطلوب"
    ]
  }
}
```

<a id="get--citizen-reports-stats"></a>
### `GET` /citizen/reports/stats — ملخص بلاغاتي

**المصادقة:** رمز المواطن.
بطاقات الشاشة الرئيسية، محسوبة على كل البلاغات.

**مثال** — الرد `200`
```http
GET {{base_url}}/citizen/reports/stats
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "total": 2,
    "open": 2,
    "resolved": 0,
    "rejected": 0
  }
}
```

<a id="get--citizen-reports-uuid"></a>
### `GET` /citizen/reports/{uuid} — تفاصيل بلاغ

**المصادقة:** رمز المواطن.
بلاغ المواطن نفسه بكل تفاصيله، أو بلاغ مواطن آخر (من الخريطة) يظهر فيه **اسم صاحبه فقط** (إلا إن اختار إخفاءه)، دون جواله وبريده وردود الجهة عليه، ومع إمكانية التأييد. فتح بلاغ مواطن آخر يزيد عدّاد مشاهداته.

**مثال: بعد الحل والتقييم: الصور قبل/بعد والسجل العام** — الرد `200`
```http
GET {{base_url}}/citizen/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "id": "7bcdeae9-e2ad-4c79-a33b-ebc67a582a25",
    "ticket_number": "MTR-2026-000719",
    "title": "حفرة عميقة قرب دوار المدينة",
    "description": "في حفرة عميقة عم تخرّب دواليب السيارات وخطيرة بالليل",
    "status": "closed",
    "status_ar": "مغلق",
    "priority": "high",
    "priority_ar": "عالية",
    "is_open": false,
    "is_anonymous": false,
    "citizen": {
      "name": "مواطن التوثيق المعدّل",
      "is_me": true
    },
    "category": {
      "id": 1,
      "name": "حفر وتلف الطرق",
      "slug": "road-damage",
      "icon": "construction",
      "color": "#E4572E"
    },
    "department": {
      "id": 1,
      "name": "مديرية الخدمات والصيانة"
    },
    "district": {
      "id": 1,
      "name": "محافظة دمشق"
    },
    "location": {
      "latitude": 33.511,
      "longitude": 36.286,
      "address": null,
      "notes": null
    },
    "attachments": [
      {
        "id": 29,
        "type": "image",
        "stage": "before",
        "url": "http://127.0.0.1:8000/storage/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/oiVI9JttkADul2QJKEBKB2TECuD88xR4TgoVhjU7.png",
        "thumbnail": null,
        "name": "pothole.png",
        "size": "0.1 KB",
        "mime": "image/png",
        "ai_tags": [
          "تعذّرت قراءة الصورة"
        ]
      }
    ],
    "after_attachments": [
      {
        "id": 30,
        "type": "image",
        "stage": "after",
        "url": "http://127.0.0.1:8000/storage/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/f9lqtYp4zX2cJawb3YKU1XBe4TwsBQfww5SsurdD.png",
        "thumbnail": null,
        "name": "after.png",
        "size": "0.1 KB",
        "mime": "image/png",
        "ai_tags": null
      }
    ],
    "updates": [
      {
        "id": 4895,
        "type": "created",
        "type_ar": "استلام البلاغ",
        "old_value": null,
        "new_value": "pending",
        "old_value_ar": null,
        "new_value_ar": "في الانتظار",
        "note": "تم استلام البلاغ — محافظة دمشق",
        "is_public": true,
        "by": {
          "name": "مواطن التوثيق المعدّل",
          "type": "citizen"
        },
        "date": "2026-10-25T19:23:41+03:00"
      },
      {
        "id": 4902,
        "type": "status_change",
        "type_ar": "تغيير الحالة",
        "old_value": null,
        "new_value": "assigned",
        "old_value_ar": null,
        "new_value_ar": "تم الإسناد",
        "note": "تم إسناد بلاغك إلى فريق مديرية الخدمات والصيانة",
        "is_public": true,
        "by": {
          "name": "النظام",
          "type": "system"
        },
        "date": "2026-10-25T19:23:45+03:00"
      },
      {
        "id": 4903,
        "type": "status_change",
        "type_ar": "تغيير الحالة",
        "old_value": "assigned",
        "new_value": "in_progress",
        "old_value_ar": "تم الإسناد",
        "new_value_ar": "جاري المعالجة",
        "note": "الفريق في الموقع وبدأ العمل",
        "is_public": true,
        "by": {
          "name": "سامر الخطيب",
          "type": "employee"
        },
        "date": "2026-10-25T19:23:45+03:00"
      },
      "… و4 عناصر أخرى"
    ],
    "rating": {
      "score": 5,
      "feedback": "شكراً على سرعة الاستجابة"
    },
    "ai_summary": "حفرة عميقة قرب دوار المدينة — في حفرة عميقة عم تخرّب دواليب السيارات وخطيرة بالليل",
    "stats": {
      "views": 0,
      "upvotes": 0
    },
    "has_upvoted": false,
    "is_owner": true,
    "can_delete": false,
    "can_rate": false,
    "can_upvote": false,
    "sla": {
      "expected_resolution": "2026-10-28T19:23:41+03:00",
      "is_overdue": false
    },
    "dates": {
      "created": "2026-10-25T19:23:41+03:00",
      "assigned": "2026-10-25T19:23:45+03:00",
      "started": "2026-10-25T19:23:45+03:00",
      "resolved": "2026-10-25T19:23:45+03:00",
      "closed": "2026-10-25T19:23:46+03:00"
    },
    "qr_code": "http://127.0.0.1:8000/storage/qrcodes/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25.svg",
    "tracking_url": "http://127.0.0.1:8000/track/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25"
  }
}
```

**مثال: بلاغ مواطن آخر: اسم صاحبه فقط (إلا إن اختار إخفاءه)، دون جواله وبريده وردود الجهة عليه، ومع can_upvote** — الرد `200`
```http
GET {{base_url}}/citizen/reports/be1ce261-e935-47cd-b175-0e33d0f82fe8
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "id": "be1ce261-e935-47cd-b175-0e33d0f82fe8",
    "ticket_number": "MTR-2026-000698",
    "status": "assigned",
    "citizen": {
      "name": "مواطن معدّل 768522",
      "is_me": false
    },
    "is_owner": false,
    "has_upvoted": false,
    "can_upvote": true,
    "can_rate": false,
    "can_delete": false,
    "stats": {
      "views": 1,
      "upvotes": 0
    },
    "updates": [
      {
        "id": 4762,
        "type": "created",
        "type_ar": "استلام البلاغ",
        "old_value": null,
        "new_value": "pending",
        "old_value_ar": null,
        "new_value_ar": "في الانتظار",
        "note": "تم استلام البلاغ — محافظة دمشق",
        "is_public": true,
        "by": {
          "name": "مواطن معدّل 768522",
          "type": "citizen"
        },
        "date": "2026-10-23T23:36:09+03:00"
      },
      {
        "id": 4764,
        "type": "status_change",
        "type_ar": "تغيير الحالة",
        "old_value": null,
        "new_value": "pending",
        "old_value_ar": null,
        "new_value_ar": "في الانتظار",
        "note": "تم توجيه بلاغك تلقائياً إلى مديرية الخدمات والصيانة",
        "is_public": true,
        "by": {
          "name": "النظام",
          "type": "system"
        },
        "date": "2026-10-23T23:36:09+03:00"
      },
      {
        "id": 4766,
        "type": "status_change",
        "type_ar": "تغيير الحالة",
        "old_value": null,
        "new_value": "assigned",
        "old_value_ar": null,
        "new_value_ar": "تم الإسناد",
        "note": "تم إسناد بلاغك إلى فريق مديرية الخدمات والصيانة",
        "is_public": true,
        "by": {
          "name": "النظام",
          "type": "system"
        },
        "date": "2026-10-23T23:36:09+03:00"
      }
    ],
    "…": "باقي الحقول كما في المثال السابق"
  }
}
```

<a id="delete--citizen-reports-uuid"></a>
### `DELETE` /citizen/reports/{uuid} — حذف بلاغ

**المصادقة:** رمز المواطن.
مسموح فقط للبلاغ «في الانتظار» قبل بدء معالجته (Soft Delete).

**مثال: مسموح فقط للبلاغ «في الانتظار» قبل إسناده** — الرد `200`
```http
DELETE {{base_url}}/citizen/reports/bbdc7c6a-ae0a-4c63-8a36-01dc84bdff42
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم حذف البلاغ",
  "data": null
}
```

<a id="post--citizen-reports-uuid-rate"></a>
### `POST` /citizen/reports/{uuid}/rate — تقييم الخدمة

**المصادقة:** رمز المواطن.
بعد الحل فقط، ومرة واحدة. التقييم **يغلق** البلاغ ويُحتسب للموظف الذي عالجه.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `rating` | JSON | 1–5 | نعم |
| `feedback` | JSON | حتى 1000 | لا |

**مثال** — الرد `200`
```http
POST {{base_url}}/citizen/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/rate
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "rating": 5,
  "feedback": "شكراً على سرعة الاستجابة"
}
```
```json
{
  "success": true,
  "message": "شكراً على تقييمك",
  "data": null
}
```

**مثال** — الرد `422`
```http
POST {{base_url}}/citizen/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/rate
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "rating": 4
}
```
```json
{
  "success": false,
  "message": "لقد قيّمت هذا البلاغ مسبقاً",
  "errors": {
    "report": [
      "لقد قيّمت هذا البلاغ مسبقاً"
    ]
  }
}
```

<a id="post--citizen-reports-uuid-upvote"></a>
### `POST` /citizen/reports/{uuid}/upvote — تأييد بلاغ (تبديل)

**المصادقة:** رمز المواطن.
تأييد بلاغ مفتوح لمواطن آخر. الضغطة الثانية تلغي التأييد. لا يمكن تأييد بلاغك.

**مثال: تبديل: الضغطة الثانية تلغي التأييد** — الرد `200`
```http
POST {{base_url}}/citizen/reports/be1ce261-e935-47cd-b175-0e33d0f82fe8/upvote
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم تسجيل تأييدك للبلاغ",
  "data": {
    "upvotes": 1,
    "has_upvoted": true
  }
}
```

**مثال** — الرد `200`
```http
POST {{base_url}}/citizen/reports/be1ce261-e935-47cd-b175-0e33d0f82fe8/upvote
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم إلغاء التأييد",
  "data": {
    "upvotes": 0,
    "has_upvoted": false
  }
}
```

**مثال** — الرد `422`
```http
POST {{base_url}}/citizen/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/upvote
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": false,
  "message": "لا يمكنك التصويت على بلاغك",
  "errors": null
}
```


## 6. المواطن — الخريطة

<a id="get--citizen-map-nearby"></a>
### `GET` /citizen/map/nearby — البلاغات القريبة

**المصادقة:** رمز المواطن.
Bounding Box ثم مسافة Haversine، مرتبة بالأقرب، حتى 200 بلاغ.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `lat` | Query | −90..90 | نعم |
| `lng` | Query | −180..180 | نعم |
| `radius_km` | Query | 0.1–25 (افتراضي 5) | لا |
| `category_id` | Query | تصنيف | لا |
| `priority` | Query | أولوية | لا |
| `status` | Query | حالة | لا |

**مثال** — الرد `200`
```http
GET {{base_url}}/citizen/map/nearby?lat=33.5138&lng=36.2765&radius_km=3
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم جلب البلاغات القريبة",
  "data": {
    "count": 29,
    "reports": [
      {
        "id": "cf69475a-ad52-4248-8831-4154c987d3f2",
        "ticket_number": "MTR-2026-000694",
        "title": "شريط كهربا مقطوع قدام المدرسة",
        "status": "resolved",
        "status_ar": "تم الحل",
        "priority": "high",
        "priority_ar": "عالية",
        "latitude": 33.5138,
        "longitude": 36.2765,
        "distance_km": 0,
        "upvotes": 0,
        "has_upvoted": false,
        "is_mine": false,
        "category": {
          "id": 8,
          "name": "أسلاك كهربائية مكشوفة",
          "icon": "bolt",
          "color": "#EA580C"
        },
        "district": "محافظة دمشق",
        "created_at": "2026-10-23T04:19:21+03:00"
      },
      {
        "id": "be1ce261-e935-47cd-b175-0e33d0f82fe8",
        "ticket_number": "MTR-2026-000698",
        "title": "حفرة كبيرة قرب المدرسة",
        "status": "assigned",
        "status_ar": "تم الإسناد",
        "priority": "high",
        "priority_ar": "عالية",
        "latitude": 33.5148,
        "longitude": 36.2775,
        "distance_km": 0.14,
        "upvotes": 0,
        "has_upvoted": false,
        "is_mine": false,
        "category": {
          "id": 1,
          "name": "حفر وتلف الطرق",
          "icon": "construction",
          "color": "#E4572E"
        },
        "district": "محافظة دمشق",
        "created_at": "2026-10-23T23:36:09+03:00"
      },
      "… و27 عنصراً آخر"
    ]
  }
}
```

**مثال** — الرد `422`
```http
GET {{base_url}}/citizen/map/nearby?lat=33.5&radius_km=90
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": false,
  "message": "الحقل خط الطول مطلوب.",
  "errors": {
    "lng": [
      "الحقل خط الطول مطلوب."
    ],
    "radius_km": [
      "يجب ألا تكون قيمة نصف القطر أكبر من 25."
    ]
  }
}
```

<a id="get--citizen-map-heatmap"></a>
### `GET` /citizen/map/heatmap — نقاط الخريطة الحرارية

**المصادقة:** رمز المواطن.
تجميع على شبكة ≈ 110 م، بوزن حسب الأولوية (حرجة 4 … منخفضة 1). كاش 5 دقائق لكل مجموعة فلاتر.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `days` | Query | 1–365 (افتراضي 30) | لا |
| `category_id` | Query | تصنيف | لا |
| `district_id` | Query | محافظة | لا |

**مثال** — الرد `200`
```http
GET {{base_url}}/citizen/map/heatmap?days=30
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "points": [
      {
        "lat": 33.496,
        "lng": 36.247,
        "weight": 3,
        "count": 1
      },
      {
        "lat": 35.136,
        "lng": 36.762,
        "weight": 2,
        "count": 1
      },
      {
        "lat": 36.497,
        "lng": 40.744,
        "weight": 4,
        "count": 1
      },
      "… و252 عنصراً آخر"
    ],
    "total": 275,
    "days": 30,
    "generated_at": "2026-10-25T19:23:44+03:00"
  }
}
```

<a id="get--citizen-map-layers"></a>
### `GET` /citizen/map/layers — طبقات الخريطة العامة

**المصادقة:** رمز المواطن.
الطبقات المفعّلة والعامة من لوحة الإدارة وإعداداتها.

**مثال** — الرد `200`
```http
GET {{base_url}}/citizen/map/layers
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": "a9178b6d-a5f5-4783-9c56-ce639d27291b",
      "key": "reports",
      "name": "البلاغات",
      "type": "cluster",
      "default_visible": true,
      "config": {
        "color_by": "priority"
      }
    },
    {
      "id": "280ac19f-8468-47f1-8820-dccf4fdab456",
      "key": "heatmap",
      "name": "الكثافة الحرارية",
      "type": "heatmap",
      "default_visible": false,
      "config": {
        "blur": 18,
        "days": 30,
        "radius": 25
      }
    },
    {
      "id": "632f134e-b302-4713-9cae-0cdeca4231e1",
      "key": "districts",
      "name": "المحافظات ومؤشر الخطورة",
      "type": "district",
      "default_visible": true,
      "config": {
        "colors": {
          "low": "#22c55e",
          "high": "#f97316",
          "medium": "#eab308",
          "critical": "#dc2626"
        }
      }
    }
  ]
}
```

<a id="get--citizen-map-districts-district-stats"></a>
### `GET` /citizen/map/districts/{district}/stats — مؤشرات محافظة

**المصادقة:** رمز المواطن.
الإجمالي والمفتوح ونسبة الحل والبلاغات لكل 100 ألف نسمة وأكثر التصنيفات.

**مثال** — الرد `200`
```http
GET {{base_url}}/citizen/map/districts/1/stats
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "district": {
      "id": 1,
      "name": "محافظة دمشق",
      "population": 1754000,
      "area_km2": 105,
      "risk_score": 61.58,
      "risk_level": "high",
      "center": {
        "lat": 33.5138,
        "lng": 36.2765
      }
    },
    "stats": {
      "total": 122,
      "resolved": 96,
      "open": 20,
      "avg_resolution_hours": 28.7,
      "resolution_rate": 78.7,
      "per_100k": 6.96
    },
    "top_categories": [
      {
        "name": "حفر وتلف الطرق",
        "color": "#E4572E",
        "count": 25
      },
      {
        "name": "انقطاع الكهرباء",
        "color": "#F59E0B",
        "count": 17
      },
      "… و3 عناصر أخرى"
    ]
  }
}
```


## 7. الموظف — الحساب

<a id="post--employee-auth-login"></a>
### `POST` /employee/auth/login — دخول الموظف

**المصادقة:** بلا تسجيل دخول.
لحسابات الموظفين والمدراء فقط؛ حساب المواطن يُرفض برسالة واضحة. جلسة واحدة لتطبيق الموظف.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `email` | JSON | البريد أو الجوال | نعم |
| `password` | JSON | كلمة المرور | نعم |
| `fcm_token` | JSON | رمز FCM | لا |

**مثال** — الرد `200`
```http
POST {{base_url}}/employee/auth/login
Accept: application/json
Content-Type: application/json

{
  "email": "employee.mun@matrahak.sy",
  "password": "Employee@1234"
}
```
```json
{
  "success": true,
  "message": "مرحباً سامر الخطيب",
  "data": {
    "user": {
      "id": "858d0e8c-6824-4d65-ad4c-98e5f414c1b7",
      "name": "سامر الخطيب",
      "email": "employee.mun@matrahak.sy",
      "phone": "0931000001",
      "avatar": null,
      "user_type": "employee",
      "is_verified": true,
      "preferred_lang": "ar",
      "district": {
        "id": 1,
        "name": "محافظة دمشق"
      },
      "department": {
        "id": 1,
        "name": "مديرية الخدمات والصيانة",
        "code": "MUN"
      },
      "created_at": "2026-10-23T02:54:07+03:00"
    },
    "token": "<TOKEN>"
  }
}
```

**مثال: حساب مواطن في تطبيق الموظف** — الرد `422`
```http
POST {{base_url}}/employee/auth/login
Accept: application/json
Content-Type: application/json

{
  "email": "0928803791",
  "password": "Docs@2026y"
}
```
```json
{
  "success": false,
  "message": "هذا حساب مواطن — استخدم تطبيق المواطن",
  "errors": {
    "email": [
      "هذا حساب مواطن — استخدم تطبيق المواطن"
    ]
  }
}
```

<a id="post--employee-auth-logout"></a>
### `POST` /employee/auth/logout — تسجيل الخروج

**المصادقة:** رمز الموظف.

**مثال** — الرد `200`
```http
POST {{base_url}}/employee/auth/logout
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم تسجيل الخروج",
  "data": null
}
```

<a id="get--employee-profile"></a>
### `GET` /employee/profile — الملف الشخصي (مع المديرية)

**المصادقة:** رمز الموظف.

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/profile
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "id": "858d0e8c-6824-4d65-ad4c-98e5f414c1b7",
    "name": "سامر الخطيب",
    "email": "employee.mun@matrahak.sy",
    "phone": "0931000001",
    "national_id": null,
    "avatar": null,
    "user_type": "employee",
    "is_verified": true,
    "preferred_lang": "ar",
    "district": {
      "id": 1,
      "name": "محافظة دمشق"
    },
    "department": {
      "id": 1,
      "name": "مديرية الخدمات والصيانة",
      "code": "MUN"
    },
    "created_at": "2026-10-23T02:54:07+03:00"
  }
}
```

<a id="post--employee-profile"></a>
### `POST|PUT` /employee/profile — تعديل الملف الشخصي

**المصادقة:** رمز الموظف.
نفس حقول المواطن.

**مثال** — الرد `200`
```http
POST {{base_url}}/employee/profile
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "phone": "0931000001"
}
```
```json
{
  "success": true,
  "message": "تم تحديث الملف الشخصي",
  "data": {
    "id": "858d0e8c-6824-4d65-ad4c-98e5f414c1b7",
    "name": "سامر الخطيب",
    "email": "employee.mun@matrahak.sy",
    "phone": "0931000001",
    "national_id": null,
    "avatar": null,
    "user_type": "employee",
    "is_verified": true,
    "preferred_lang": "ar",
    "district": {
      "id": 1,
      "name": "محافظة دمشق"
    },
    "department": {
      "id": 1,
      "name": "مديرية الخدمات والصيانة",
      "code": "MUN"
    },
    "created_at": "2026-10-23T02:54:07+03:00"
  }
}
```


## 8. الموظف — البلاغات

<a id="get--employee-reports-assigned"></a>
### `GET` /employee/reports/assigned — البلاغات المسندة إليّ

**المصادقة:** رمز الموظف.
المفتوحة افتراضياً، مرتبة بالأولوية ثم بأقرب مهلة.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `status` | Query | حالة | لا |
| `priority` | Query | أولوية | لا |
| `district_id` | Query | محافظة | لا |
| `search` | Query | في العنوان أو رقم المتابعة، حتى 100 | لا |
| `include_closed` | Query | `1` مع المغلقة | لا |
| `per_page` | Query | 1–50 | لا |

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/reports/assigned?per_page=2
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": "c088091a-1fb4-4866-abf8-488443a23cbf",
      "ticket_number": "MTR-2026-000697",
      "title": "حفرة كبيرة قرب المدرسة",
      "description": "في جورة كبيرة بنص الطريق عم تسبب حوادث للسيارات — فحص آلي من tool/api_check.dart",
      "status": "assigned",
      "status_ar": "تم الإسناد",
      "priority": "high",
      "priority_ar": "عالية",
      "citizen": {
        "name": "مواطن معدّل 003787",
        "phone": "0994194623"
      },
      "is_anonymous": false,
      "category": {
        "id": 1,
        "name": "حفر وتلف الطرق",
        "icon": "construction",
        "color": "#E4572E"
      },
      "department": {
        "id": 1,
        "name": "مديرية الخدمات والصيانة"
      },
      "district": {
        "id": 1,
        "name": "محافظة دمشق"
      },
      "location": {
        "latitude": 33.5148,
        "longitude": 36.2775,
        "address": null,
        "notes": "قرب المدرسة"
      },
      "ai": {
        "category": null,
        "priority": "high",
        "confidence": 0.8949,
        "summary": "حفرة كبيرة قرب المدرسة — في جورة كبيرة بنص الطريق عم تسبب حوادث للسيارات — فحص آلي من tool/api_check",
        "is_duplicate": false,
        "duplicate_of": null
      },
      "attachments": [
        {
          "id": 2,
          "type": "image",
          "stage": "before",
          "url": "http://127.0.0.1:8000/storage/reports/c088091a-1fb4-4866-abf8-488443a23cbf/nwAFYlZ5xhC61HFppdoyULzwjPPyIRjqpTwVtXGX.png",
          "thumbnail": null,
          "name": "photo.png",
          "size": "0.1 KB",
          "mime": "image/png",
          "ai_tags": null
        }
      ],
      "after_attachments": [],
      "updates": [],
      "assignment": {
        "employee": "سامر الخطيب",
        "is_me": true,
        "method": "auto",
        "method_ar": "تلقائي",
        "assigned_at": "2026-10-23T23:06:45+03:00"
      },
      "rating": null,
      "permissions": {
        "can_act": true,
        "can_claim": false,
        "allowed_statuses": [
          {
            "value": "under_review",
            "label": "قيد المراجعة"
          },
          {
            "value": "in_progress",
            "label": "جاري المعالجة"
          },
          "… وعنصران آخران"
        ]
      },
      "sla": {
        "expected_resolution": "2026-10-26T23:06:45+03:00",
        "is_overdue": false
      },
      "dates": {
        "created": "2026-10-23T23:06:45+03:00",
        "assigned": "2026-10-23T23:06:45+03:00",
        "started": null,
        "resolved": null
      }
    },
    "… وبلاغ آخر في هذه الصفحة"
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 2,
    "total": 3,
    "has_more": true
  }
}
```

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/reports/assigned?priority=high&search=حفرة&per_page=2
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": "c088091a-1fb4-4866-abf8-488443a23cbf",
      "ticket_number": "MTR-2026-000697",
      "title": "حفرة كبيرة قرب المدرسة",
      "status": "assigned",
      "priority": "high",
      "sla": {
        "expected_resolution": "2026-10-26T23:06:45+03:00",
        "is_overdue": false
      },
      "…": "نفس شكل العنصر في المثال السابق"
    },
    {
      "id": "be1ce261-e935-47cd-b175-0e33d0f82fe8",
      "ticket_number": "MTR-2026-000698",
      "title": "حفرة كبيرة قرب المدرسة",
      "status": "assigned",
      "priority": "high",
      "sla": {
        "expected_resolution": "2026-10-26T23:36:09+03:00",
        "is_overdue": false
      },
      "…": "نفس شكل العنصر في المثال السابق"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 2,
    "total": 3,
    "has_more": true
  }
}
```

<a id="get--employee-reports-pool"></a>
### `GET` /employee/reports/pool — قائمة القسم (غير المستلمة)

**المصادقة:** رمز الموظف.
بلاغات مديرية الموظف المفتوحة التي لم يُسند لها أحد.

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/reports/pool?per_page=2
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": "6391f1e5-67cc-4113-b79f-2f20e99e984a",
      "ticket_number": "MTR-2026-000713",
      "title": "حفرة عميقة قرب دوار المدينة",
      "status": "pending",
      "priority": "high",
      "district": {
        "id": 1,
        "name": "محافظة دمشق"
      },
      "sla": {
        "expected_resolution": "2026-10-28T19:22:55+03:00",
        "is_overdue": false
      },
      "…": "نفس شكل عنصر «البلاغات المسندة إليّ»"
    },
    {
      "id": "28c82e6a-25fa-4c8c-981e-4403ec24a8ac",
      "ticket_number": "MTR-2026-000714",
      "title": "عمود كهرباء مائل فوق الرصيف",
      "status": "pending",
      "priority": "high",
      "district": {
        "id": 1,
        "name": "محافظة دمشق"
      },
      "sla": {
        "expected_resolution": "2026-10-28T19:22:56+03:00",
        "is_overdue": false
      },
      "…": "نفس شكل عنصر «البلاغات المسندة إليّ»"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 2,
    "total": 5,
    "has_more": true
  }
}
```

<a id="get--employee-reports-history"></a>
### `GET` /employee/reports/history — سجلي (المنجزة)

**المصادقة:** رمز الموظف.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `from_date` | Query | تاريخ | لا |
| `to_date` | Query | ≥ from_date | لا |
| `per_page` | Query | 1–50 | لا |

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/reports/history?per_page=2
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": "7bcdeae9-e2ad-4c79-a33b-ebc67a582a25",
      "ticket_number": "MTR-2026-000719",
      "title": "حفرة عميقة قرب دوار المدينة",
      "status": "resolved",
      "rating": null,
      "dates": {
        "created": "2026-10-25T19:23:41+03:00",
        "assigned": "2026-10-25T19:23:45+03:00",
        "started": "2026-10-25T19:23:45+03:00",
        "resolved": "2026-10-25T19:23:45+03:00"
      },
      "…": "نفس شكل عنصر «البلاغات المسندة إليّ»"
    },
    {
      "id": "37190ec5-3d70-4485-a097-e41066a0d23f",
      "ticket_number": "MTR-2026-000716",
      "title": "حفرة عميقة قرب دوار المدينة",
      "status": "closed",
      "rating": {
        "score": 5,
        "feedback": "شكراً على سرعة الاستجابة"
      },
      "dates": {
        "created": "2026-10-25T19:23:12+03:00",
        "assigned": "2026-10-25T19:23:16+03:00",
        "started": "2026-10-25T19:23:16+03:00",
        "resolved": "2026-10-25T19:23:16+03:00"
      },
      "…": "نفس شكل عنصر «البلاغات المسندة إليّ»"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 23,
    "per_page": 2,
    "total": 45,
    "has_more": true
  }
}
```

<a id="get--employee-reports-uuid"></a>
### `GET` /employee/reports/{uuid} — تفاصيل بلاغ للموظف

**المصادقة:** رمز الموظف.
بلاغات مديرية الموظف فقط. يتضمن بيانات المواطن (إن لم يكن مجهولاً)، والسجل الكامل مع الملاحظات الداخلية، وتحليل الذكاء الاصطناعي، والصلاحيات المتاحة (`permissions`).

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "id": "7bcdeae9-e2ad-4c79-a33b-ebc67a582a25",
    "ticket_number": "MTR-2026-000719",
    "title": "حفرة عميقة قرب دوار المدينة",
    "description": "في حفرة عميقة عم تخرّب دواليب السيارات وخطيرة بالليل",
    "status": "resolved",
    "status_ar": "تم الحل",
    "priority": "high",
    "priority_ar": "عالية",
    "citizen": {
      "name": "مواطن التوثيق المعدّل",
      "phone": "0928803791"
    },
    "is_anonymous": false,
    "category": {
      "id": 1,
      "name": "حفر وتلف الطرق",
      "icon": "construction",
      "color": "#E4572E"
    },
    "department": {
      "id": 1,
      "name": "مديرية الخدمات والصيانة"
    },
    "district": {
      "id": 1,
      "name": "محافظة دمشق"
    },
    "location": {
      "latitude": 33.511,
      "longitude": 36.286,
      "address": null,
      "notes": null
    },
    "ai": {
      "category": "حفر وتلف الطرق",
      "priority": "high",
      "confidence": 0.815,
      "summary": "حفرة عميقة قرب دوار المدينة — في حفرة عميقة عم تخرّب دواليب السيارات وخطيرة بالليل",
      "is_duplicate": true,
      "duplicate_of": {
        "id": "6391f1e5-67cc-4113-b79f-2f20e99e984a",
        "ticket_number": "MTR-2026-000713",
        "score": 1
      }
    },
    "attachments": [
      {
        "id": 29,
        "type": "image",
        "stage": "before",
        "url": "http://127.0.0.1:8000/storage/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/oiVI9JttkADul2QJKEBKB2TECuD88xR4TgoVhjU7.png",
        "thumbnail": null,
        "name": "pothole.png",
        "size": "0.1 KB",
        "mime": "image/png",
        "ai_tags": [
          "تعذّرت قراءة الصورة"
        ]
      }
    ],
    "after_attachments": [
      {
        "id": 30,
        "type": "image",
        "stage": "after",
        "url": "http://127.0.0.1:8000/storage/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/f9lqtYp4zX2cJawb3YKU1XBe4TwsBQfww5SsurdD.png",
        "thumbnail": null,
        "name": "after.png",
        "size": "0.1 KB",
        "mime": "image/png",
        "ai_tags": null
      }
    ],
    "updates": [
      {
        "id": 4895,
        "type": "created",
        "type_ar": "استلام البلاغ",
        "old_value": null,
        "new_value": "pending",
        "old_value_ar": null,
        "new_value_ar": "في الانتظار",
        "note": "تم استلام البلاغ — محافظة دمشق",
        "is_public": true,
        "by": {
          "name": "مواطن التوثيق المعدّل",
          "type": "citizen"
        },
        "date": "2026-10-25T19:23:41+03:00"
      },
      {
        "id": 4896,
        "type": "ai_update",
        "type_ar": "تحليل آلي",
        "old_value": null,
        "new_value": "road-damage",
        "old_value_ar": null,
        "new_value_ar": "road-damage",
        "note": "اقتراح الذكاء الاصطناعي: حفر وتلف الطرق — ثقة 82% — رُفعت الأولوية إلى عالية",
        "is_public": false,
        "by": {
          "name": "النظام",
          "type": "system"
        },
        "date": "2026-10-25T19:23:42+03:00"
      },
      {
        "id": 4897,
        "type": "ai_update",
        "type_ar": "تحليل آلي",
        "old_value": null,
        "new_value": "MTR-2026-000713",
        "old_value_ar": null,
        "new_value_ar": "MTR-2026-000713",
        "note": "يُحتمل أن يكون مكرراً للبلاغ MTR-2026-000713 (تشابه 100%) — للمراجعة",
        "is_public": false,
        "by": {
          "name": "النظام",
          "type": "system"
        },
        "date": "2026-10-25T19:23:42+03:00"
      },
      "… و7 عناصر أخرى"
    ],
    "assignment": {
      "employee": "سامر الخطيب",
      "is_me": true,
      "method": "claim",
      "method_ar": "استلام ذاتي",
      "assigned_at": "2026-10-25T19:23:45+03:00"
    },
    "rating": null,
    "permissions": {
      "can_act": false,
      "can_claim": false,
      "allowed_statuses": []
    },
    "sla": {
      "expected_resolution": "2026-10-28T19:23:41+03:00",
      "is_overdue": false
    },
    "dates": {
      "created": "2026-10-25T19:23:41+03:00",
      "assigned": "2026-10-25T19:23:45+03:00",
      "started": "2026-10-25T19:23:45+03:00",
      "resolved": "2026-10-25T19:23:45+03:00"
    }
  }
}
```

**مثال** — الرد `404`
```http
GET {{base_url}}/employee/reports/00000000-0000-0000-0000-000000000000
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": false,
  "message": "البلاغ غير موجود أو لا يتبع مديريتك",
  "errors": null
}
```

<a id="post--employee-reports-uuid-claim"></a>
### `POST` /employee/reports/{uuid}/claim — استلام بلاغ من القسم

**المصادقة:** رمز الموظف.
يسند البلاغ للموظف نفسه. 422 إن كان مُسنداً لغيره.

**مثال** — الرد `200`
```http
POST {{base_url}}/employee/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/claim
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم استلام البلاغ",
  "data": {
    "id": "7bcdeae9-e2ad-4c79-a33b-ebc67a582a25",
    "ticket_number": "MTR-2026-000719",
    "status": "assigned",
    "status_ar": "تم الإسناد",
    "assignment": {
      "employee": "سامر الخطيب",
      "is_me": true,
      "method": "claim",
      "method_ar": "استلام ذاتي",
      "assigned_at": "2026-10-25T19:23:45+03:00"
    },
    "permissions": {
      "can_act": true,
      "can_claim": false,
      "allowed_statuses": [
        {
          "value": "under_review",
          "label": "قيد المراجعة"
        },
        {
          "value": "in_progress",
          "label": "جاري المعالجة"
        },
        "… وعنصران آخران"
      ]
    },
    "…": "باقي حقول البلاغ كما في «تفاصيل بلاغ للموظف»"
  }
}
```

**مثال** — الرد `422`
```http
POST {{base_url}}/employee/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/claim
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": false,
  "message": "البلاغ مُسند لموظف آخر بالفعل",
  "errors": {
    "report": [
      "البلاغ مُسند لموظف آخر بالفعل"
    ]
  }
}
```

<a id="post--employee-reports-uuid-status"></a>
### `POST` /employee/reports/{uuid}/status — تغيير الحالة

**المصادقة:** رمز الموظف.
للبلاغ المسند إليه فقط، وضمن آلة الحالات. الحالات المتاحة للموظف: `under_review` · `in_progress` · `resolved` · `rejected`.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `status` | JSON | إحدى الحالات الأربع | نعم |
| `note` | JSON | 5–1000 (تظهر للمواطن) | نعم |

**مثال** — الرد `200`
```http
POST {{base_url}}/employee/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/status
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "status": "in_progress",
  "note": "الفريق في الموقع وبدأ العمل"
}
```
```json
{
  "success": true,
  "message": "تم تحديث حالة البلاغ",
  "data": {
    "id": "7bcdeae9-e2ad-4c79-a33b-ebc67a582a25",
    "ticket_number": "MTR-2026-000719",
    "status": "in_progress",
    "status_ar": "جاري المعالجة",
    "permissions": {
      "can_act": true,
      "can_claim": false,
      "allowed_statuses": [
        {
          "value": "under_review",
          "label": "قيد المراجعة"
        },
        {
          "value": "resolved",
          "label": "تم الحل"
        },
        "… وعنصر آخر"
      ]
    },
    "sla": {
      "expected_resolution": "2026-10-28T19:23:41+03:00",
      "is_overdue": false
    },
    "dates": {
      "created": "2026-10-25T19:23:41+03:00",
      "assigned": "2026-10-25T19:23:45+03:00",
      "started": "2026-10-25T19:23:45+03:00",
      "resolved": null
    },
    "…": "باقي حقول البلاغ كما في «تفاصيل بلاغ للموظف»"
  }
}
```

**مثال** — الرد `200`
```http
POST {{base_url}}/employee/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/status
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "status": "resolved",
  "note": "تم الإصلاح والتأكد من سلامة الموقع"
}
```
```json
{
  "success": true,
  "message": "تم تحديث حالة البلاغ",
  "data": {
    "id": "7bcdeae9-e2ad-4c79-a33b-ebc67a582a25",
    "ticket_number": "MTR-2026-000719",
    "status": "resolved",
    "status_ar": "تم الحل",
    "permissions": {
      "can_act": false,
      "can_claim": false,
      "allowed_statuses": []
    },
    "dates": {
      "created": "2026-10-25T19:23:41+03:00",
      "assigned": "2026-10-25T19:23:45+03:00",
      "started": "2026-10-25T19:23:45+03:00",
      "resolved": "2026-10-25T19:23:45+03:00"
    },
    "…": "باقي حقول البلاغ كما في «تفاصيل بلاغ للموظف»"
  }
}
```

**مثال: حالة غير متاحة للموظف + ملاحظة قصيرة** — الرد `422`
```http
POST {{base_url}}/employee/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/status
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "status": "closed",
  "note": "قص"
}
```
```json
{
  "success": false,
  "message": "هذه الحالة غير متاحة للموظف",
  "errors": {
    "status": [
      "هذه الحالة غير متاحة للموظف"
    ],
    "note": [
      "الملاحظة قصيرة جداً (5 أحرف على الأقل)"
    ]
  }
}
```

**مثال** — الرد `403`
```http
POST {{base_url}}/employee/reports/32876f15-10d0-48c8-ad6f-5e5ec67163d6/status
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "status": "in_progress",
  "note": "محاولة على بلاغ زميل"
}
```
```json
{
  "success": false,
  "message": "هذا البلاغ ليس مسنداً إليك — استلمه أولاً",
  "errors": null
}
```

<a id="post--employee-reports-uuid-notes"></a>
### `POST` /employee/reports/{uuid}/notes — إضافة ملاحظة

**المصادقة:** رمز الموظف.
داخلية للفريق (`is_public=false`) أو عامة تصل للمواطن كإشعار.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `note` | JSON | 3–1000 | نعم |
| `is_public` | JSON | منطقية | نعم |

**مثال** — الرد `201`
```http
POST {{base_url}}/employee/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/notes
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "note": "يحتاج آلية ردم — تم التنسيق مع الورشة",
  "is_public": false
}
```
```json
{
  "success": true,
  "message": "تمت إضافة الملاحظة",
  "data": {
    "id": 4904,
    "type": "note",
    "type_ar": "ملاحظة",
    "old_value": null,
    "new_value": null,
    "old_value_ar": null,
    "new_value_ar": null,
    "note": "يحتاج آلية ردم — تم التنسيق مع الورشة",
    "is_public": false,
    "by": {
      "name": "سامر الخطيب",
      "type": "employee"
    },
    "date": "2026-10-25T19:23:45+03:00"
  }
}
```

<a id="post--employee-reports-uuid-reply"></a>
### `POST` /employee/reports/{uuid}/reply — الرد على المواطن

**المصادقة:** رمز الموظف.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `message` | JSON | 3–1000 | نعم |

**مثال** — الرد `201`
```http
POST {{base_url}}/employee/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/reply
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "message": "شكراً لبلاغك، الفريق في الموقع الآن."
}
```
```json
{
  "success": true,
  "message": "تم إرسال الرد للمواطن",
  "data": {
    "id": 4905,
    "type": "citizen_response",
    "type_ar": "رد على المواطن",
    "old_value": null,
    "new_value": null,
    "old_value_ar": null,
    "new_value_ar": null,
    "note": "شكراً لبلاغك، الفريق في الموقع الآن.",
    "is_public": true,
    "by": {
      "name": "سامر الخطيب",
      "type": "employee"
    },
    "date": "2026-10-25T19:23:45+03:00"
  }
}
```

<a id="post--employee-reports-uuid-transfer"></a>
### `POST` /employee/reports/{uuid}/transfer — تحويل لمديرية أخرى

**المصادقة:** رمز الموظف.
يفك إسناد الموظف، ويعيد البلاغ «قيد المراجعة»، ويحسب مهلة جديدة، ثم يُسند تلقائياً في المديرية الجديدة.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `department_id` | JSON | المديرية الجديدة | نعم |
| `category_id` | JSON | تصنيف يتبعها | لا |
| `reason` | JSON | 5–500 | نعم |

**مثال** — الرد `200`
```http
POST {{base_url}}/employee/reports/608b2ad5-1bbe-42f6-b75c-89ace5a9d096/transfer
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "department_id": 2,
  "category_id": 5,
  "reason": "المشكلة في شبكة الكهرباء وليست من اختصاص الخدمات"
}
```
```json
{
  "success": true,
  "message": "تم تحويل البلاغ — لم يعد ضمن بلاغاتك",
  "data": null
}
```

<a id="post--employee-reports-uuid-attachments"></a>
### `POST` /employee/reports/{uuid}/attachments — صور بعد المعالجة

**المصادقة:** رمز الموظف.
`multipart/form-data`، وتُحفظ بالمرحلة `after`.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `attachments[]` | form-data | 1–5 ملفات × 20 MB | نعم |
| `note` | form-data | حتى 500 | لا |

**مثال** — الرد `201`
```http
POST {{base_url}}/employee/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/attachments
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: multipart/form-data

note = بعد ردم الحفرة وتزفيت الطريق
attachments[] (after.png)
```
```json
{
  "success": true,
  "message": "تم رفع المرفقات",
  "data": {
    "id": "7bcdeae9-e2ad-4c79-a33b-ebc67a582a25",
    "ticket_number": "MTR-2026-000719",
    "status": "in_progress",
    "after_attachments": [
      {
        "id": 30,
        "type": "image",
        "stage": "after",
        "url": "http://127.0.0.1:8000/storage/reports/7bcdeae9-e2ad-4c79-a33b-ebc67a582a25/f9lqtYp4zX2cJawb3YKU1XBe4TwsBQfww5SsurdD.png",
        "thumbnail": null,
        "name": "after.png",
        "size": "0.1 KB",
        "mime": "image/png",
        "ai_tags": null
      }
    ],
    "…": "باقي حقول البلاغ كما في «تفاصيل بلاغ للموظف»"
  }
}
```

<a id="get--employee-map-department-issues"></a>
### `GET` /employee/map/department-issues — خريطة بلاغات القسم

**المصادقة:** رمز الموظف.
كل البلاغات المفتوحة للمديرية بإحداثياتها، مع `is_mine` و`is_unassigned`.

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/map/department-issues
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": "28c82e6a-25fa-4c8c-981e-4403ec24a8ac",
      "ticket_number": "MTR-2026-000714",
      "title": "عمود كهرباء مائل فوق الرصيف",
      "status": "pending",
      "status_ar": "في الانتظار",
      "priority": "high",
      "priority_ar": "عالية",
      "latitude": 33.513,
      "longitude": 36.284,
      "is_mine": false,
      "is_unassigned": true,
      "category": {
        "name": "حفر وتلف الطرق",
        "icon": "construction",
        "color": "#E4572E"
      }
    },
    {
      "id": "6391f1e5-67cc-4113-b79f-2f20e99e984a",
      "ticket_number": "MTR-2026-000713",
      "title": "حفرة عميقة قرب دوار المدينة",
      "status": "pending",
      "status_ar": "في الانتظار",
      "priority": "high",
      "priority_ar": "عالية",
      "latitude": 33.511,
      "longitude": 36.286,
      "is_mine": false,
      "is_unassigned": true,
      "category": {
        "name": "حفر وتلف الطرق",
        "icon": "construction",
        "color": "#E4572E"
      }
    },
    {
      "id": "9cfaebd1-9feb-4246-8df2-199bfaeb6560",
      "ticket_number": "MTR-2026-000703",
      "title": "رصيف مكسور بشارع بغداد",
      "status": "pending",
      "status_ar": "في الانتظار",
      "priority": "low",
      "priority_ar": "منخفضة",
      "latitude": 33.517,
      "longitude": 36.2985,
      "is_mine": false,
      "is_unassigned": true,
      "category": {
        "name": "تلف الأرصفة",
        "icon": "directions_walk",
        "color": "#F17C67"
      }
    },
    "… و14 عنصراً آخر"
  ]
}
```


## 9. الموظف — التحليلات

<a id="get--employee-analytics-my-stats"></a>
### `GET` /employee/analytics/my-stats — إحصائياتي

**المصادقة:** رمز الموظف.
من `employee_stats` (يُعاد حسابه بعد كل إسناد وتغيير حالة وتقييم).

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/analytics/my-stats
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "total_assigned": 54,
    "total_resolved": 43,
    "total_rejected": 2,
    "total_open": 3,
    "current_workload": 3,
    "overdue_count": 0,
    "avg_resolution_hours": 37.4,
    "avg_rating": 4.05,
    "resolution_rate": 79.6,
    "this_month": 21
  }
}
```

<a id="get--employee-analytics-workload"></a>
### `GET` /employee/analytics/workload — عبء العمل الحالي

**المصادقة:** رمز الموظف.

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/analytics/workload
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "total_open": 3,
    "critical": 0,
    "high": 3,
    "overdue": 0,
    "reports": [
      {
        "id": "c088091a-1fb4-4866-abf8-488443a23cbf",
        "ticket_number": "MTR-2026-000697",
        "title": "حفرة كبيرة قرب المدرسة",
        "priority": "high",
        "priority_ar": "عالية",
        "status": "assigned",
        "status_ar": "تم الإسناد",
        "category": "حفر وتلف الطرق",
        "district": "محافظة دمشق",
        "is_overdue": false,
        "expected_resolution": "2026-10-26T23:06:45+03:00",
        "created_at": "2026-10-23T23:06:45+03:00"
      },
      {
        "id": "be1ce261-e935-47cd-b175-0e33d0f82fe8",
        "ticket_number": "MTR-2026-000698",
        "title": "حفرة كبيرة قرب المدرسة",
        "priority": "high",
        "priority_ar": "عالية",
        "status": "assigned",
        "status_ar": "تم الإسناد",
        "category": "حفر وتلف الطرق",
        "district": "محافظة دمشق",
        "is_overdue": false,
        "expected_resolution": "2026-10-26T23:36:09+03:00",
        "created_at": "2026-10-23T23:36:09+03:00"
      },
      "… وعنصر آخر"
    ]
  }
}
```

<a id="get--employee-analytics-department-stats"></a>
### `GET` /employee/analytics/department-stats — مؤشرات المديرية

**المصادقة:** رمز الموظف.
مؤشرات المديرية والتوزيع حسب التصنيف والمحافظة والاتجاه.

**مثال** — الرد `200`
```http
GET {{base_url}}/employee/analytics/department-stats
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "department": {
      "id": 1,
      "name": "مديرية الخدمات والصيانة",
      "code": "MUN"
    },
    "kpis": {
      "total": 196,
      "open": 17,
      "resolved": 166,
      "rejected": 13,
      "overdue": 9,
      "critical_open": 0,
      "today": 10,
      "resolution_rate": 84.7,
      "avg_resolution_hours": 42.6,
      "sla_compliance": 83.1,
      "avg_rating": 4.01,
      "ratings_count": 85,
      "citizen_satisfaction": 69.4
    },
    "by_category": [
      {
        "id": 1,
        "name": "حفر وتلف الطرق",
        "color": "#E4572E",
        "count": 75
      },
      {
        "id": 3,
        "name": "النفايات والقمامة",
        "color": "#8D6E63",
        "count": 66
      },
      {
        "id": 2,
        "name": "تلف الأرصفة",
        "color": "#F17C67",
        "count": 30
      },
      "… وعنصر آخر"
    ],
    "by_district": [
      {
        "id": 1,
        "name": "محافظة دمشق",
        "count": 48
      },
      {
        "id": 3,
        "name": "محافظة حلب",
        "count": 36
      },
      {
        "id": 2,
        "name": "محافظة ريف دمشق",
        "count": 29
      },
      "… و3 عناصر أخرى"
    ],
    "last_7_days": [
      {
        "date": "2026-10-19",
        "created": 2,
        "resolved": 0
      },
      {
        "date": "2026-10-20",
        "created": 5,
        "resolved": 2
      },
      "… و5 عناصر أخرى"
    ]
  }
}
```


## 10. الإشعارات — للتطبيقين

<a id="get--notifications"></a>
### `GET` /notifications — قائمة الإشعارات

**المصادقة:** رمز أي من التطبيقين.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `unread` | Query | `1` غير المقروءة فقط | لا |
| `per_page` | Query | افتراضي 20 | لا |

**مثال** — الرد `200`
```http
GET {{base_url}}/notifications?per_page=3
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": [
    {
      "id": "51694ce2-67e0-42c0-9df6-9ce30e071aad",
      "type": "transfer",
      "title": "تحويل البلاغ MTR-2026-000720",
      "body": "تم تحويل بلاغك إلى مديرية الكهرباء",
      "data": {
        "report_uuid": "608b2ad5-1bbe-42f6-b75c-89ace5a9d096",
        "ticket_number": "MTR-2026-000720",
        "status": "under_review"
      },
      "read_at": null,
      "created_at": "2026-10-25T19:23:46+03:00"
    },
    {
      "id": "a83e49c2-7dea-4632-9a62-149cfe9b8806",
      "type": "status_change",
      "title": "تحديث البلاغ MTR-2026-000720",
      "body": "تم إسناد بلاغك إلى فريق مديرية الخدمات والصيانة",
      "data": {
        "report_uuid": "608b2ad5-1bbe-42f6-b75c-89ace5a9d096",
        "ticket_number": "MTR-2026-000720",
        "status": "assigned"
      },
      "read_at": null,
      "created_at": "2026-10-25T19:23:46+03:00"
    },
    {
      "id": "b9be9816-6c04-4cd1-a04c-baca6b1373bf",
      "type": "status_change",
      "title": "تحديث البلاغ MTR-2026-000720",
      "body": "تم إسناد بلاغك إلى فريق مديرية الكهرباء",
      "data": {
        "report_uuid": "608b2ad5-1bbe-42f6-b75c-89ace5a9d096",
        "ticket_number": "MTR-2026-000720",
        "status": "assigned"
      },
      "read_at": null,
      "created_at": "2026-10-25T19:23:46+03:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 3,
    "total": 7,
    "has_more": true
  }
}
```

<a id="get--notifications-unread-count"></a>
### `GET` /notifications/unread-count — عدد غير المقروء

**المصادقة:** رمز أي من التطبيقين.
يستطلعه التطبيقان كل 45 ثانية وعند العودة للتطبيق؛ يعيد آخر إشعار لعرضه كرسالة.

**مثال** — الرد `200`
```http
GET {{base_url}}/notifications/unread-count
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تمت العملية بنجاح",
  "data": {
    "count": 7,
    "latest": {
      "id": "51694ce2-67e0-42c0-9df6-9ce30e071aad",
      "type": "transfer",
      "title": "تحويل البلاغ MTR-2026-000720",
      "body": "تم تحويل بلاغك إلى مديرية الكهرباء",
      "data": {
        "report_uuid": "608b2ad5-1bbe-42f6-b75c-89ace5a9d096",
        "ticket_number": "MTR-2026-000720",
        "status": "under_review"
      },
      "read_at": null,
      "created_at": "2026-10-25T19:23:46+03:00"
    }
  }
}
```

<a id="post--notifications-id-read"></a>
### `POST` /notifications/{id}/read — تعليم إشعار كمقروء

**المصادقة:** رمز أي من التطبيقين.

**مثال** — الرد `200`
```http
POST {{base_url}}/notifications/51694ce2-67e0-42c0-9df6-9ce30e071aad/read
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم",
  "data": null
}
```

<a id="post--notifications-read-all"></a>
### `POST` /notifications/read-all — تعليم الكل كمقروء

**المصادقة:** رمز أي من التطبيقين.

**مثال** — الرد `200`
```http
POST {{base_url}}/notifications/read-all
Authorization: Bearer <TOKEN>
Accept: application/json
```
```json
{
  "success": true,
  "message": "تم تعليم كل الإشعارات كمقروءة",
  "data": null
}
```

<a id="post--notifications-device-token"></a>
### `POST` /notifications/device-token — رمز FCM للجهاز

**المصادقة:** رمز أي من التطبيقين.
يحفظ الرمز في الحقل الخاص بالتطبيق الذي سجّل الدخول. `null` يحذفه.

| المعامل | الموضع | القاعدة | إلزامي |
|---|---|---|---|
| `token` | JSON | نص حتى 500 أو null (حقل إلزامي الوجود) | نعم |

**مثال: null يحذف رمز FCM للجهاز** — الرد `200`
```http
POST {{base_url}}/notifications/device-token
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "token": null
}
```
```json
{
  "success": true,
  "message": "تم تحديث رمز الجهاز",
  "data": null
}
```


## 11. خدمة الذكاء الاصطناعي الداخلية — matrahak-ai

- **العنوان:** `http://127.0.0.1:8001`.
- **المصادقة:** كل مسارات `/v1` تتطلب الترويسة `X-Internal-Key`، وقيمتها تساوي `AI_SERVICE_KEY` في `.env` الخاص بـ Laravel.
- **من يستدعيها:** `App\Services\Ai\AiServiceClient` في Laravel فقط؛ التطبيقان لا يتصلان بها أبداً.
- **التوثيق التفاعلي:** Swagger على `http://127.0.0.1:8001/docs`.

الأمثلة طلبات حقيقية أرسلها Laravel للخدمة، سُجّلت عبر أحداث HTTP Client (`ResponseReceived`)، وأُخذت الردود منها.

**مفتاح خاطئ أو مفقود — الرد `401`:**
```json
{
  "detail": "invalid or missing X-Internal-Key"
}
```

### `GET` /health — حالة الخدمة والنموذج

عام، بلا مفتاح.

**مثال** — الرد `200`
```http
GET http://127.0.0.1:8001/health
X-Internal-Key: <INTERNAL_API_KEY>
Accept: application/json
```
```json
{
  "status": "ok",
  "service": "matrahak-ai",
  "version": "1.0.0",
  "backend": "ml-hybrid",
  "classifier": "tfidf-hybrid-v1 — LinearSVC (Platt calibration), C=0.5, TF-IDF word+char",
  "model": {
    "ready": true,
    "version": "tfidf-hybrid-v1",
    "algorithm": "LinearSVC (Platt calibration), C=0.5, TF-IDF word+char",
    "trained_at": "2026-10-23T01:25:03+00:00",
    "n_samples": 1917,
    "sources": {
      "synthetic": 1917
    },
    "labels": 24,
    "keyword_weight": 0.3,
    "metrics": {
      "kind": "offline",
      "test_kind": "heldout",
      "cv_accuracy_mean": 0.9942,
      "test_accuracy": 1,
      "n_test": 148,
      "challenge_accuracy": 0.9167,
      "evaluated_at": "2026-10-23T04:25:01"
    }
  },
  "capabilities": [
    "classify",
    "duplicates",
    "analyze-image",
    "… و4 عناصر أخرى"
  ]
}
```

### `POST` /v1/classify — تصنيف بلاغ

العنوان والوصف + التصنيفات المفعّلة (يرسلها Laravel). يعيد التصنيف والمديرية والأولوية ومؤشر الخطورة والملخص وتفسير القرار والبدائل.

**مثال** — الرد `200`
```http
POST http://127.0.0.1:8001/v1/classify
X-Internal-Key: <INTERNAL_API_KEY>
Accept: application/json
Content-Type: application/json

{
  "title": "تسرب مياه من أنبوب رئيسي",
  "description": "في تسريب مي قوي من أنبوب مكسور عم يغرق الشارع قدام البناية",
  "categories": [
    {
      "slug": "road-damage",
      "name": "حفر وتلف الطرق",
      "name_en": "Road Damage",
      "keywords": [
        "حفرة",
        "حفر",
        "جورة",
        "جور",
        "طريق",
        "زفت",
        "… و9 عناصر أخرى"
      ],
      "department_code": "MUN",
      "priority_weight": 7
    },
    {
      "slug": "sidewalk-damage",
      "name": "تلف الأرصفة",
      "name_en": "Sidewalk Damage",
      "keywords": [
        "رصيف",
        "ارصفة",
        "بلاط الرصيف",
        "حجر الرصيف",
        "بردورة",
        "بلاط",
        "… و3 عناصر أخرى"
      ],
      "department_code": "MUN",
      "priority_weight": 4
    },
    "… و22 عنصراً آخر"
  ]
}
```
```json
{
  "category_slug": "street-water-leak",
  "department_code": "WATER",
  "confidence": 0.8543,
  "priority": "critical",
  "urgency_signal": "critical",
  "summary": "تسرب مياه من أنبوب رئيسي — في تسريب مي قوي من أنبوب مكسور عم يغرق الشارع قدام البناية",
  "keywords_matched": [
    "تسرب",
    "تسريب",
    "مي عم تطلع",
    "مياه بالشارع",
    "انبوب مكسور",
    "… و4 عناصر أخرى"
  ],
  "explanation": [
    {
      "term": "انبوب",
      "weight": 0.1616
    },
    {
      "term": "تسريب",
      "weight": 0.1062
    },
    {
      "term": "تسريب مي",
      "weight": 0.1062
    },
    {
      "term": "مي",
      "weight": 0.059
    },
    "… وعنصران آخران"
  ],
  "alternatives": [
    {
      "slug": "drinking-water-outage",
      "score": 0.0296
    },
    {
      "slug": "road-damage",
      "score": 0.0209
    },
    {
      "slug": "sewage-overflow",
      "score": 0.019
    }
  ],
  "model_version": "tfidf-hybrid-v1",
  "backend": "ml-hybrid",
  "processing_ms": 24
}
```

### `POST` /v1/duplicates — كشف التكرار

البلاغ الجديد + البلاغات المفتوحة القريبة (Laravel يرشّحها جغرافياً).

**مثال** — الرد `200`
```http
POST http://127.0.0.1:8001/v1/duplicates
X-Internal-Key: <INTERNAL_API_KEY>
Accept: application/json
Content-Type: application/json

{
  "report": {
    "id": "new",
    "text": "عمود كهرباء مائل فوق الرصيف — نفس المشكلة عمود الكهرباء مايل وأسلاكه متدلية فوق الرصيف قرب المدرسة",
    "lat": 33.513,
    "lng": 36.284
  },
  "candidates": [
    {
      "id": "708",
      "text": "عمود كهرباء مائل فوق الرصيف عمود الكهرباء مايل وأسلاكه متدلية فوق الرصيف قرب المدرسة",
      "lat": 33.513,
      "lng": 36.284
    },
    {
      "id": "705",
      "text": "حاوية قمامة مكسورة الحاوية مكسورة والقمامة منتشرة على الرصيف قرب المدرسة",
      "lat": 33.5102,
      "lng": 36.2841
    },
    "… و4 عناصر أخرى"
  ],
  "radius_m": 300
}
```
```json
{
  "is_duplicate": true,
  "duplicate_of": "708",
  "similarity": 0.9844,
  "text_similarity": 0.9554,
  "semantic_similarity": 1,
  "distance_m": 0,
  "method": "model-semantic+tfidf-char+geo"
}
```

### `POST` /v1/analyze-image — تحليل صورة

`multipart/form-data` بحقل `file`، حتى 15 MB.

**مثال** — الرد `200`
```http
POST http://127.0.0.1:8001/v1/analyze-image
X-Internal-Key: <INTERNAL_API_KEY>
Accept: application/json
Content-Type: multipart/form-data

file = <صورة البلاغ>
```
```json
{
  "available": true,
  "method": "heuristic-color-quality-v1",
  "tags": [
    "صورة غير واضحة",
    "احتمال حريق أو لهب"
  ],
  "severity": 8,
  "quality_ok": false,
  "metrics": {
    "width": 640,
    "height": 480,
    "brightness": 112.2,
    "sharpness": 0.8,
    "fire_ratio": 0.2829,
    "green_ratio": 0.0,
    "blue_ratio": 0.0
  },
  "error": null
}
```

### `POST` /v1/predict — التنبؤ

سلسلة يومية (محافظة × تصنيف × يوم) + خريطة التصنيفات إلى أنواع التنبؤ.

**مثال** — الرد `200`
```http
POST http://127.0.0.1:8001/v1/predict
X-Internal-Key: <INTERNAL_API_KEY>
Accept: application/json
Content-Type: application/json

{
  "horizon_days": 7,
  "lookback_days": 90,
  "today": "2026-10-25",
  "type_map": {
    "power-outage": "electricity_failure",
    "street-light-failure": "electricity_failure",
    "dangerous-electric-pole": "electricity_failure",
    "exposed-wires": "electricity_failure",
    "street-water-leak": "water_problem",
    "drinking-water-outage": "water_problem",
    "sewage-overflow": "water_problem",
    "blocked-drains": "water_problem",
    "contaminated-water": "water_problem",
    "traffic-congestion": "traffic_congestion",
    "traffic-signal-failure": "traffic_congestion"
  },
  "districts": [
    {
      "id": 1,
      "name": "محافظة دمشق",
      "population": 1754000
    },
    {
      "id": 2,
      "name": "محافظة ريف دمشق",
      "population": 2836000
    },
    "… و12 عنصراً آخر"
  ],
  "categories": {
    "road-damage": "حفر وتلف الطرق",
    "sidewalk-damage": "تلف الأرصفة",
    "garbage": "النفايات والقمامة",
    "blocked-drains": "مجاري الأمطار المسدودة",
    "power-outage": "انقطاع الكهرباء",
    "street-light-failure": "إنارة شوارع معطلة",
    "dangerous-electric-pole": "عمود كهربائي خطر",
    "exposed-wires": "أسلاك كهربائية مكشوفة",
    "street-water-leak": "تسرب مياه في الشارع",
    "drinking-water-outage": "انقطاع مياه الشرب",
    "sewage-overflow": "طفح الصرف الصحي",
    "traffic-signal-failure": "إشارة مرور معطلة",
    "traffic-congestion": "ازدحام مروري",
    "damaged-road-sign": "لافتة طرقية تالفة",
    "environmental-violation": "مخالفة بيئية",
    "waste-burning": "حرق نفايات",
    "water-pollution": "تلوث مائي",
    "collapsing-building": "مبنى آيل للسقوط",
    "fire": "حريق",
    "public-safety-hazard": "خطر على السلامة العامة",
    "internet-outage": "انقطاع الإنترنت",
    "telecom-network-failure": "عطل في شبكة الاتصالات",
    "health-violation": "مخالفة صحية",
    "contaminated-water": "مياه ملوثة"
  },
  "series": [
    {
      "date": "2026-07-28",
      "district_id": 1,
      "category_slug": "sewage-overflow",
      "count": 1,
      "high_count": 1
    },
    {
      "date": "2026-07-28",
      "district_id": 12,
      "category_slug": "street-water-leak",
      "count": 1,
      "high_count": 0
    },
    "… و614 عنصراً آخر"
  ]
}
```
```json
{
  "model_version": "poisson-ewma-v1",
  "predictions": [
    {
      "prediction_type": "water_problem",
      "district_id": 2,
      "category_slug": null,
      "predicted_date": "2026-10-26",
      "window_end": "2026-11-01",
      "probability": 1,
      "expected_count": 18.56,
      "severity": "critical",
      "description": "متوقع حدوث مشكلة مياه في محافظة ريف دمشق خلال 7 أيام — الاحتمال 100%، والعدد المتوقع ≈ 18.6 بلاغ",
      "supporting_data": {
        "daily_rate": 1.7677,
        "recent_14": 45,
        "previous_14": 6,
        "growth": 6.5,
        "lookback_total": 59
      }
    },
    {
      "prediction_type": "electricity_failure",
      "district_id": 3,
      "category_slug": null,
      "predicted_date": "2026-10-26",
      "window_end": "2026-11-01",
      "probability": 1,
      "expected_count": 11.45,
      "severity": "critical",
      "description": "متوقع حدوث انقطاع كهرباء في محافظة حلب خلال 7 أيام — الاحتمال 100%، والعدد المتوقع ≈ 11.4 بلاغ",
      "supporting_data": {
        "daily_rate": 1.0902,
        "recent_14": 24,
        "previous_14": 3,
        "growth": 7,
        "lookback_total": 47
      }
    },
    "… و23 عنصراً آخر"
  ]
}
```

### `POST` /v1/insights — الرؤى

الارتفاعات المفاجئة (z-score) والاتجاهات الأسبوعية.

**مثال** — الرد `200`
```http
POST http://127.0.0.1:8001/v1/insights
X-Internal-Key: <INTERNAL_API_KEY>
Accept: application/json
Content-Type: application/json

{
  "today": "2026-10-25",
  "lookback_days": 60,
  "districts": {
    "1": "محافظة دمشق",
    "2": "محافظة ريف دمشق",
    "3": "محافظة حلب",
    "4": "محافظة حمص",
    "5": "محافظة حماة",
    "6": "محافظة اللاذقية",
    "7": "محافظة طرطوس",
    "8": "محافظة إدلب",
    "9": "محافظة الحسكة",
    "10": "محافظة دير الزور",
    "11": "محافظة الرقة",
    "12": "محافظة درعا",
    "13": "محافظة السويداء",
    "14": "محافظة القنيطرة"
  },
  "categories": {
    "road-damage": "حفر وتلف الطرق",
    "sidewalk-damage": "تلف الأرصفة",
    "garbage": "النفايات والقمامة",
    "blocked-drains": "مجاري الأمطار المسدودة",
    "power-outage": "انقطاع الكهرباء",
    "street-light-failure": "إنارة شوارع معطلة",
    "dangerous-electric-pole": "عمود كهربائي خطر",
    "exposed-wires": "أسلاك كهربائية مكشوفة",
    "street-water-leak": "تسرب مياه في الشارع",
    "drinking-water-outage": "انقطاع مياه الشرب",
    "sewage-overflow": "طفح الصرف الصحي",
    "traffic-signal-failure": "إشارة مرور معطلة",
    "traffic-congestion": "ازدحام مروري",
    "damaged-road-sign": "لافتة طرقية تالفة",
    "environmental-violation": "مخالفة بيئية",
    "waste-burning": "حرق نفايات",
    "water-pollution": "تلوث مائي",
    "collapsing-building": "مبنى آيل للسقوط",
    "fire": "حريق",
    "public-safety-hazard": "خطر على السلامة العامة",
    "internet-outage": "انقطاع الإنترنت",
    "telecom-network-failure": "عطل في شبكة الاتصالات",
    "health-violation": "مخالفة صحية",
    "contaminated-water": "مياه ملوثة"
  },
  "series": [
    {
      "date": "2026-08-27",
      "district_id": 6,
      "category_slug": "internet-outage",
      "count": 1,
      "high_count": 0
    },
    {
      "date": "2026-08-27",
      "district_id": 6,
      "category_slug": "road-damage",
      "count": 1,
      "high_count": 0
    },
    "… و423 عنصراً آخر"
  ]
}
```
```json
{
  "model_version": "stats-v1",
  "insights": [
    {
      "type": "trend",
      "severity": "critical",
      "title": "اتجاه متصاعد: «انقطاع الكهرباء»",
      "content": "ارتفعت بلاغات «انقطاع الكهرباء» بنسبة 200% خلال الأسبوع الأخير (24 بلاغ مقابل 8 في الأسبوع السابق).",
      "district_id": null,
      "category_slug": "power-outage",
      "data": {
        "last_7": 24,
        "previous_7": 8,
        "growth": 2
      },
      "fingerprint": "trend-power-outage-202643"
    },
    {
      "type": "anomaly",
      "severity": "warning",
      "title": "ارتفاع غير معتاد في «حفر وتلف الطرق» — محافظة دمشق",
      "content": "سُجّل 5 بلاغاً خلال آخر 3 أيام، بينما المعدل اليومي المعتاد 0.1 بلاغ. قد يدل ذلك على عطل واسع أو حادث يحتاج تدخلاً سريعاً.",
      "district_id": 1,
      "category_slug": "road-damage",
      "data": {
        "recent_3_days": 5,
        "baseline_mean": 0.123,
        "baseline_std": 0.5,
        "z_score": 3.09
      },
      "fingerprint": "anomaly-1-road-damage-2026-10-25"
    },
    "… وعنصر آخر"
  ]
}
```

### `POST` /v1/risk — مؤشر الخطورة

مكوّنات كل محافظة (المفتوح حسب الأولوية، المتأخر، النمو، السكان).

**مثال** — الرد `200`
```http
POST http://127.0.0.1:8001/v1/risk
X-Internal-Key: <INTERNAL_API_KEY>
Accept: application/json
Content-Type: application/json

{
  "districts": [
    {
      "district_id": 1,
      "population": 1754000,
      "open_critical": 1,
      "open_high": 6,
      "open_medium": 6,
      "open_low": 1,
      "overdue": 8,
      "last30": 44,
      "prev30": 35,
      "total": 114,
      "resolved": 94
    },
    {
      "district_id": 2,
      "population": 2836000,
      "open_critical": 1,
      "open_high": 0,
      "open_medium": 23,
      "open_low": 0,
      "overdue": 24,
      "last30": 71,
      "prev30": 29,
      "total": 134,
      "resolved": 95
    },
    "… و12 عنصراً آخر"
  ],
  "scales": {
    "load": 1,
    "overdue": 0.5
  }
}
```
```json
{
  "model_version": "risk-v2",
  "scores": [
    {
      "district_id": 1,
      "score": 61.58
    },
    {
      "district_id": 2,
      "score": 75.54
    },
    {
      "district_id": 3,
      "score": 53.31
    },
    "… و11 عنصراً آخر"
  ]
}
```

### `POST` /v1/train — إعادة التدريب

بلاغات موثّقة `{text, label}`؛ Champion/Challenger + McNemar. 409 إن كان تدريب آخر جارياً، و422 إن لم تكفِ العينات.

**مثال: شُغّل على نسخة معزولة من الخدمة حتى لا يتغير النموذج المرفق** — الرد `200`
```http
POST http://127.0.0.1:8001/v1/train
X-Internal-Key: <INTERNAL_API_KEY>
Accept: application/json
Content-Type: application/json

{
  "samples": [
    {
      "text": "شريط كهربا مقطوع قدام المدرسة — سلك كهربا مكشوف عالأرض وعم يطلع شرار والولاد عم يلعبوا جنبه",
      "label": "exposed-wires",
      "source": "resolved"
    },
    {
      "text": "ما في مي من تلت ايام — المي مقطوعة عن الحارة كلها من تلت ايام والصهاريج غالية كتير",
      "label": "drinking-water-outage",
      "source": "resolved"
    },
    "… و4 عناصر أخرى"
  ],
  "include_synthetic": true
}
```
```json
{
  "status": "promoted",
  "promoted": true,
  "forced": false,
  "message": "تم اعتماد النموذج الجديد. الدقة (الحالي ← الجديد): مجموعة الاختبار المستقلة: 100.0% ← 100.0% (n=148) — اختبار الإجهاد: 91.7% ← 89.6% (n=48)",
  "samples": {
    "received": 6,
    "invalid_or_duplicate": 0,
    "excluded_test_overlap": 0,
    "skipped_labels": [],
    "usable": 6,
    "by_source": {
      "resolved": 6
    },
    "by_label": {
      "exposed-wires": 1,
      "drinking-water-outage": 2,
      "garbage": 1,
      "road-damage": 2
    }
  },
  "training": {
    "synthetic": 1917,
    "real_train": 6,
    "real_test": 0,
    "real_test_fair": 0,
    "labels": 24,
    "include_synthetic": true
  },
  "evaluation": {
    "real_test": {
      "n": 0,
      "current": null,
      "candidate": null,
      "regressions": 0,
      "improvements": 0,
      "p_value": null
    },
    "heldout": {
      "n": 148,
      "current": 1.0,
      "candidate": 1.0,
      "regressions": 0,
      "improvements": 0,
      "p_value": 1.0
    },
    "challenge": {
      "n": 48,
      "current": 0.9167,
      "candidate": 0.8958,
      "regressions": 1,
      "improvements": 0,
      "p_value": 0.5
    }
  },
  "worse_on": [],
  "evaluated_at": "2026-10-25T16:21:24+00:00",
  "train_seconds": 3.81,
  "model": {
    "ready": true,
    "version": "tfidf-hybrid-v1",
    "algorithm": "LinearSVC (Platt calibration), C=0.5, TF-IDF word+char",
    "config": {
      "kind": "word+char",
      "classifier": "svm",
      "C": 0.5,
      "keyword_weight": 0.3
    },
    "keyword_weight": 0.3,
    "trained_at": "2026-10-25T16:21:25+00:00",
    "train_seconds": 1.35,
    "n_samples": 1923,
    "sources": {
      "synthetic": 1917,
      "resolved": 6
    },
    "labels": 24,
    "sklearn": "1.9.1",
    "metrics": {
      "kind": "retrain",
      "heldout_accuracy": 1.0,
      "challenge_accuracy": 0.8958,
      "n_real_test": 0,
      "evaluated_at": "2026-10-25T16:21:24+00:00"
    }
  }
}
```

### `GET` /v1/model — تفاصيل النموذج وسجل التدريب


**مثال** — الرد `200`
```http
GET http://127.0.0.1:8001/v1/model
X-Internal-Key: <INTERNAL_API_KEY>
Accept: application/json
```
```json
{
  "model": {
    "ready": true,
    "version": "tfidf-hybrid-v1",
    "algorithm": "LinearSVC (Platt calibration), C=0.5, TF-IDF word+char",
    "config": {
      "kind": "word+char",
      "classifier": "svm",
      "C": 0.5,
      "keyword_weight": 0.3
    },
    "keyword_weight": 0.3,
    "trained_at": "2026-10-23T01:25:03+00:00",
    "train_seconds": 1.1,
    "n_samples": 1917,
    "sources": {
      "synthetic": 1917
    },
    "labels": 24,
    "sklearn": "1.9.1",
    "metrics": {
      "kind": "offline",
      "test_kind": "heldout",
      "cv_accuracy_mean": 0.9942,
      "test_accuracy": 1,
      "n_test": 148,
      "challenge_accuracy": 0.9167,
      "evaluated_at": "2026-10-23T04:25:01"
    }
  },
  "history": []
}
```

## 12. مجموعة Postman واختبارها بـ newman

في مجلد `postman/` مجموعة تشغّل سيناريو المنصة كاملاً على الخادم الحقيقي: **81 طلباً في 8 مجلدات**.
لكل طلب اختبارات تتحقق من رمز الحالة ومن الحقول التي تهم السيناريو، ومثال رد حقيقي من تشغيل فعلي (الرموز محجوبة).
وكل مجلد من مجلدات Laravel يتحقق بعد كل طلب من الغلاف الموحّد: `success` يطابق رمز الحالة، و`message` موجود.

| الملف | المحتوى |
|---|---|
| `Matrahak.postman_collection.json` | المجموعة (Postman Collection v2.1) |
| `Matrahak.postman_environment.json` | البيئة المحلية: `base_url` و`ai_url` و`ai_key` و`employee_email` و`employee_password` |
| `sample-before.jpg` و`sample-after.jpg` | صورتا «قبل/بعد» يرفعهما الطلبان 2.1 و4.14 |
| `test-report.html` | تقرير آخر تشغيل: كل طلب ورمز حالته وزمنه ونتيجة كل اختبار |
| `README.md` | الاستيراد والتشغيل والملاحظات |

**السيناريو:**

| المجلد | الطلبات | يغطي |
|---|---|---|
| 0 · فحص عام وبيانات مرجعية | 4 | مسارات عامة بلا تسجيل دخول. تحفظ في متغيرات المجموعة ما يحتاجه باقي السيناريو: أرقام التصنيفات والمديريات، ومركز محافظة دمشق الجغرافي (موقع البلاغات). |
| 1 · حساب المواطن | 9 | تسجيل مواطنَين جديدين (أ) و(ب) بأرقام جوال عشوائية بصيغة 09XXXXXXXX، والدخول بالجوال، وتعديل الملف وكلمة المرور، وحالات الخطأ 401 و422. طلبات التسجيل والدخول محدودة بعشر محاولات في الدقيقة لكل IP، والمجموعة كلها تستخدم سبعاً منها. |
| 2 · بلاغات المواطن (أ) | 16 | رفع البلاغات بـ multipart/form-data مع صورة، وحذف بلاغ لم تبدأ معالجته، والقوائم والإحصائيات والخريطة، وصفحة التتبع العامة التي يفتحها رمز QR. البلاغ الأول «انقطاع الإنترنت» في دمشق يُسند تلقائياً لموظف الاتصالات في دمشق؛ والثاني اختار له المواطن تصنيف الاتصالات خطأً، فيحوّله الموظف للكهرباء في المجلد 4. |
| 3 · مواطن آخر (ب): الخصوصية والتأييد | 5 | المواطن (ب) يرى بلاغ (أ) العام دون جواله وبريده، ويؤيده ثم يلغي تأييده. وصاحب البلاغ لا يؤيد بلاغه، ورمز المواطن مرفوض على مسارات الموظف. |
| 4 · تطبيق الموظف «مطرحك ميدان» | 26 | موظف مديرية الاتصالات في دمشق (من البيئة: employee_email / employee_password). الإسناد التلقائي يختار موظف محافظة البلاغ أولاً، لذلك يصل إليه بلاغ دمشق دائماً. إن كان الإسناد التلقائي معطلاً من لوحة الإدارة يُنفَّذ طلبا «الاستلام» 4.6 و4.17، وإلا يُتخطّيان. |
| 5 · المواطن بعد الحل: الإشعارات والتقييم | 11 | المواطن (أ) تصله إشعارات الإسناد وتغيّر الحالة والرد، ويرى صورة «بعد» والرد دون الملاحظة الداخلية، ثم يقيّم البلاغ فيُغلق. والمواطن (ب) لا يرى ردود الجهة على بلاغ غيره. |
| 6 · تسجيل الخروج وإبطال الرموز | 5 | الخروج يحذف الرمز الحالي ورمز FCM، فيُرفض الرمز نفسه بعدها بـ 401. |
| 7 · خدمة الذكاء الاصطناعي (داخلية) | 5 | خدمة FastAPI على العنوان ai_url (افتراضياً http://127.0.0.1:8001). لا يستدعيها التطبيقان؛ Laravel وحده يرسل إليها بالترويسة X-Internal-Key. هذا المجلد لاختبار الخدمة مباشرة، ويحتاج تشغيلها. لا يتضمن /v1/train عمداً حتى لا يتغير النموذج المرفق. |

**آخر تشغيل كامل** (2026-10-26 00:41 بتوقيت دمشق، newman 6.2.3):
**79 طلباً منفّذاً** وطلبان تخطّاهما شرطهما، و**226 اختباراً، فشل منها 0**.
متوسط زمن الرد 72 ms، والأقصى 496 ms في الطلب «1.7 تغيير كلمة المرور».


**اختبار الاختبارات نفسها:** حتى لا تكون النتيجة الخضراء شكلية، شُغّلت المجموعة أيضاً في ظروف مختلفة:

| الظرف | النتيجة |
|---|---|
| الإسناد التلقائي معطّل من الإعدادات | يُنفَّذ طلبا الاستلام 4.6 و4.17: 81 طلباً، 232 اختباراً، 0 فشل |
| خدمة الذكاء الاصطناعي متوقفة | المجلدات 0 إلى 6 تنجح كلها بالمصنّف المحلي البديل؛ يفشل المجلد 7 وحده (5 طلبات بلا اتصال) |
| كلمة مرور موظف خاطئة في البيئة | يفشل 53 اختباراً في المجلدات 4 و5 و6، وينتهي newman برمز خروج 1 |

**الاستيراد في Postman:**
1. **Import** ← اختر ملفي المجموعة والبيئة، ثم اختر البيئة «مطرحك — محلي (127.0.0.1)».
2. **Settings ← General ← Working directory**: مجلد `postman`، ليجد طلبا رفع الصور ملفيهما.
3. على المجموعة: **Run**. شغّل المجلدات بالترتيب، لأن كل طلب يحفظ ما يحتاجه التالي في متغيرات المجموعة.

**من سطر الأوامر (Windows CMD):**
```
npm install -g newman
cd C:\Users\SOLAV\matrahak-docs\postman
newman run Matrahak.postman_collection.json -e Matrahak.postman_environment.json --working-dir .
```

**ملاحظات:**
- التسجيل والدخول محدودان بعشر محاولات في الدقيقة لكل IP، والتشغيل الواحد يستخدم سبعاً؛ انتظر دقيقة بين تشغيلين وإلا يظهر 429.
- كل تشغيل يضيف بيانات تجريبية: مواطنَين `postman.*@example.com` وثلاثة بلاغات، يُحذف أحدها ويُغلق الثاني ويبقى الثالث مفتوحاً في مديرية الكهرباء.
- المجلد 7 لا يتضمن `/v1/train` عمداً، حتى لا يتغير النموذج المرفق مع المشروع.
- التوليد: `tools/gen_postman.py` للمجموعة، و`tools/newman_report.py` للتقرير من نتيجة newman بصيغة JSON.

