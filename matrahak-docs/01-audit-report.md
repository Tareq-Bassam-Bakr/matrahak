# تقرير التدقيق ومطابقة المتطلبات — منصة «مطرحك»

**مطرحك معروف، وبلاغك متابع.**

| البند | القيمة |
|---|---|
| المشروع | منصة «مطرحك» — Smart City Digital Twin Platform |
| مرجع المطابقة | وثيقة المتطلبات الأصلية «SMART CITY DIGITAL TWIN PLATFORM» |
| تاريخ التقرير | 25 تشرين الأول 2026 |
| النطاق | الأجزاء الأربعة: الخادم ولوحة الإدارة، خدمة الذكاء الاصطناعي، تطبيق المواطن، تطبيق الموظف |

---

## 1. الملخص التنفيذي

أُعيد بناء المنصة كاملةً من الكود الأصلي المرسل، ثم اختُبرت على قاعدة بيانات MySQL حقيقية وخادم يعمل فعلاً، لا على بيانات وهمية.
بعد ذلك طوبق كل بند في وثيقة المتطلبات الأصلية مع مكان تنفيذه في الكود (القسم 4).

| الجزء | التقنية | الحجم | نتيجة الفحص |
|---|---|---|---|
| **smart-city-api** — الخادم ولوحة الإدارة | Laravel 12، Filament 3.3، Sanctum، Spatie Permission | 177 ملف PHP (≈ 12,600 سطر)، 43 مسار API، 20 نموذجاً، 25 خدمة، 9 أحداث | **103 اختبارات ناجحة (832 تحققاً)** على MySQL 8.4 |
| **matrahak-ai** — خدمة الذكاء الاصطناعي | Python، FastAPI، scikit-learn | 20 ملف Python، 9 مسارات، نموذج مدرّب | **43 اختبار pytest ناجح** + تقرير تقييم موثّق |
| **matrahak_citizen** — تطبيق المواطن «مطرحك» | Flutter 3.35+، Riverpod 3، GoRouter، flutter_map | 80 ملف Dart (≈ 10,000 سطر) | `dart analyze` بلا ملاحظات · 26 اختبار وحدة · 30/30 خطوة على الخادم الحقيقي |
| **matrahak_employee** — تطبيق الموظف «مطرحك ميدان» | Flutter 3.35+، Riverpod 3 | 88 ملف Dart (≈ 10,700 سطر) | `dart analyze` بلا ملاحظات · 31 اختبار وحدة · 29/29 خطوة على الخادم الحقيقي |

**خلاصة المطابقة:** طوبقت **219 بنداً** من الوثيقة، وهي كل سطر فيها، كلٌّ في سطر مستقل من المصفوفة (القسم 4):
- **198 بنداً منفّذ كما في الوثيقة** (90%).
- **12 بنداً منفّذ ببديل مكافئ**، وهي انحرافات مقصودة ومبرّرة في القسم 5، مثل OpenStreetMap بدل Google Maps.
- **8 بنود منفّذة جزئياً**، مع ذكر ما ينقص كل منها بوضوح.
- **بند واحد غير منفّذ:** OpenAI Compatible Architecture، ومبرّره في القسم 5.

---

## 2. منهجية التدقيق

1. **مراجعة الكود الأصلي:** كل ملفات Laravel وFilament وFastAPI وتطبيقي Flutter المرسلة، مع تصنيف كل عيب حسب الخطورة (حرج / عالٍ / متوسط) وتحديد سببه الجذري.
2. **الإصلاح من الجذر:** لا ترقيع للأعراض. مثلاً رقم التذكرة المكرر عولج بتغيير طريقة التوليد نفسها، لا بالتقاط الخطأ.
3. **الاختبار على بيئة حقيقية:** MySQL 8.4 وPHP 8.3 وPython 3.13، والتطبيقان مربوطان بخادم Laravel يعمل.
   - ردود الـ API المستخدمة في الاختبارات والتوثيق مسجّلة من الخادم الحقيقي.
4. **مطابقة كل بند في وثيقة المتطلبات** مع ملفه في الكود، والتصريح بأي انحراف أو نقص.
5. **سدّ الفجوات التي ظهرت أثناء المطابقة نفسها** قبل كتابة هذا التقرير (القسم 3.4)، مع اختبار لكل منها.

---

## 3. العيوب التي وُجدت وكيف عولجت

### 3.1 الخادم ولوحة الإدارة (Laravel)

| # | العيب في النسخة الأصلية | الأثر | الإصلاح | مكانه الآن |
|---|---|---|---|---|
| 1 | ملف `District.php` لم يكن موجوداً أصلاً | خطأ قاتل في كل استعلام يمس المحافظات | كُتب النموذج كاملاً مع الحدود الجغرافية ومؤشر الخطورة | `app/Models/District.php` |
| 2 | حقلا `citizen_fcm_token` و`employee_fcm_token` خارج `$fillable` | Laravel يتجاهلهما بصمت، فلا يُحفظ أي FCM Token | إضافتهما، ومسار مستقل لتحديث الرمز | `app/Models/User.php` · `NotificationController@deviceToken` |
| 3 | رقم التذكرة يُولَّد بـ `count()+1` | بعد حذف أي بلاغ يتكرر الرقم، ثم Unique violation ويفشل الرفع | توليد من المعرّف التسلسلي بعد الإدراج: `MTR-YYYY-NNNNNN` | `app/Models/Report.php` |
| 4 | تحليل الذكاء الاصطناعي في Closure يلتقط `$this` | فشل تسلسل كائنات الخدمات، فلا يعمل التحليل | Job حقيقي يمرّر المعرّف فقط | `app/Jobs/ProcessReportWithAi.php` |
| 5 | التحقق من الجوال بالصيغة السعودية `^(05)[0-9]{8}$` | رفض كل الأرقام السورية عند التسجيل | قاعدة للأرقام السورية تقبل `09XXXXXXXX` و`+963` و`00963` | `app/Support/SyrianPhone.php` |
| 6 | لوحة الإدارة تعتمد خدمة ui-avatars.com الخارجية | صور مكسورة دون إنترنت أو عند حجب الخدمة | مولّد صور بالأحرف الأولى محلياً | `app/Filament/Support/InitialsAvatarProvider.php` |
| 7 | استخدام واجهة Intervention Image v2 مع الإصدار 3 | خطأ عند رفع أي صورة | واجهة v3: `ImageManager` + `GdDriver` | `app/Services/Reports/AttachmentService.php` |
| 8 | البلاغ لا يأخذ أي محافظة من إحداثياته | كل إحصائيات المحافظات ومؤشراتها فارغة | Point-in-Polygon على حدود المحافظات الرسمية (OCHA) | `app/Services/Geo/DistrictLocator.php` |
| 9 | حلقة الإسناد غير موجودة: لا كود يكتب في `report_assignments` | تطبيق الموظف فارغ دائماً | إسناد تلقائي: محافظة البلاغ أولاً، ثم الفريق المركزي، ثم الأقل عبئاً؛ واستلام من قائمة القسم | `app/Services/Reports/AssignmentService.php` |
| 10 | التحويل لمديرية أخرى يترك البلاغ بلا موظف إلى الأبد | بلاغات معلّقة لا يراها أحد | إسناد تلقائي في المديرية الجديدة بعد التحويل | `ReportWorkflowService::transfer` |
| 11 | `expected_resolution` لا يُعبَّأ أبداً | لا يوجد بلاغ «متأخر»، ولا قياس للالتزام بالمهلة (SLA) | حساب المهلة من التصنيف ثم المديرية، وتنبيه مرة واحدة عند التأخر | `app/Services/Reports/SlaCalculator.php` · `reports:check-overdue` |
| 12 | `predict.py` يطلب حقلاً `raw_reports` غير موجود، والتنبؤات تُحذف قبل انتهاء نافذتها | لا تنبؤات إطلاقاً، ولا تحقق من دقة أي تنبؤ | سلسلة يومية حقيقية، والاحتفاظ بالتنبؤ حتى نهاية نافذته ثم حساب `was_accurate` | `app/Services/Ai/PredictionService.php` |
| 13 | خدمة Python تتصل عكسياً بمسار Laravel محمي بـ Sanctum | 401 ونتيجة فارغة دائماً، وتجمّد `php artisan serve` (Deadlock) | خدمة Stateless: يرسل Laravel كل البيانات في الطلب، ومفتاح داخلي `X-Internal-Key` | `app/Services/Ai/AiServiceClient.php` |
| 14 | مؤشر الخطورة risk-v1 يقسم المتأخر على المفتوح | إضافة بلاغات حرجة جديدة **تخفض** المؤشر | risk-v2: مكوّنات لكل 100 ألف نسمة، ورتيب (Monotonic): لا ينخفض بزيادة الخطر | `app/Services/Ai/RiskScoringService.php` |
| 15 | مفتاح كاش الخريطة لا يتضمن الفلاتر | الفلترة بالتصنيف تعيد نتائج غير مفلترة | البلاغات القريبة بلا كاش، لأنها تختلف لكل مستخدم. والخريطة الحرارية بمفتاح يشمل كل الفلاتر | `app/Services/Map/MapService.php` |
| 16 | `GROUP BY ROUND(latitude,3)` في الخريطة الحرارية | خطأ MySQL 1055 تحت `ONLY_FULL_GROUP_BY` | تجميع صحيح بالأعمدة المحسوبة نفسها | `app/Services/Map/MapService.php` |
| 17 | إحصائيات المواطن تُحسب من أول 10 بلاغات فقط | أرقام خاطئة في الشاشة الرئيسية | مسار مستقل `/citizen/reports/stats` يحسب على كل البلاغات | `Citizen\ReportController@stats` |
| 18 | فتح بلاغ مواطن آخر من الخريطة يعيد 404 | لا يمكن رؤية البلاغات القريبة أو تأييدها | عرض البلاغ باسم صاحبه فقط، أو «مجهول» إن اختار الإخفاء، دون جواله وبريده | `Citizen\ReportController@show` · `ReportResource` |
| 19 | التأييد يزيد العداد بلا حد ولا يُعرف من صوّت | تلاعب سهل بالأولوية | جدول `report_upvotes` بقيد فريد (مستخدم + بلاغ)، والضغطة الثانية تلغي التأييد | `Citizen\ReportController@upvote` |
| 20 | مسار `/shared/departments` يستخدمه التطبيق وليس موجوداً | خطأ في شاشات الموظف | المسار مع كاش يُمسح عند التعديل | `Shared\LookupController` |
| 21 | `NotificationController` مستدعى في routes وغير موجود | كل مسارات الإشعارات تفشل | Controller كامل: القائمة، العدّاد، القراءة، Device Token | `app/Http/Controllers/Api/V1/NotificationController.php` |

### 3.2 خدمة الذكاء الاصطناعي (FastAPI)

| # | العيب | الإصلاح |
|---|---|---|
| 1 | الاتصال العكسي بـ Laravel، والبند 13 أعلاه | خدمة Stateless لا تتصل بقاعدة البيانات أبداً، وكل مسارات `/v1` محمية بـ `X-Internal-Key` |
| 2 | التنبؤ معطّل (`raw_reports`)، والبند 12 أعلاه | خوارزمية Poisson-EWMA على سلسلة يرسلها Laravel، ونفسها بـ PHP كبديل محلي |
| 3 | لا يوجد نموذج تصنيف مدرّب فعلاً | نموذج TF-IDF + LinearSVC مدرّب ومقيَّم، مع تقرير تقييم صريح ومجموعة اختبار إجهاد، وإعادة تدريب من البلاغات الحقيقية الموثّقة |
| 4 | الخدمة إن توقفت تتعطل المنصة | بديل محلي في Laravel بنفس الخوارزميات، ومطابقته لنتائج Python مُثبتة باختبارات Parity |

### 3.3 تطبيقا Flutter

أُعيدت كتابة التطبيقين بالكامل، لأن العيوب كانت في البنية نفسها لا في سطور متفرقة:

| # | العيب في النسخة الأصلية | الإصلاح |
|---|---|---|
| 1 | أخطاء في الـ Router وشاشات لا يمكن الوصول إليها | GoRouter 17 مع `StatefulShellRoute` وإعادة توجيه حسب الجلسة |
| 2 | وظائف وهمية: بيانات ثابتة بدل استدعاء الـ API | كل شاشة مربوطة بالـ API الحقيقي، وطبقة البيانات مختبرة على خادم حقيقي (30/30 و29/29) |
| 3 | شاشات ناقصة من قائمة الوثيقة | كل شاشات الوثيقة موجودة، والقسم 4 يطابقها شاشة شاشة |
| 4 | الإشعارات تعتمد على Firebase، فلا تعمل دونه | الإشعارات داخل التطبيق تعمل دون Firebase: استطلاع كل 45 ثانية وعند العودة للتطبيق. وFCM من الخادم اختياري |
| 5 | Google Maps (يحتاج مفتاحاً، وغير متاح في سوريا) | flutter_map + OpenStreetMap، والعنوان من Nominatim |
| 6 | لا يوجد تمييز بين التطبيقين | هوية مستقلة لتطبيق الموظف: «مطرحك ميدان»، بأيقونة وألوان ومعرّف حزمة خاصة به |

### 3.4 فجوات اكتُشفت أثناء المطابقة النهائية وأُصلحت في هذه الدفعة

| # | الفجوة | الأثر | الإصلاح | الاختبار |
|---|---|---|---|---|
| 1 | لا توجد طبقة **Events / Listeners**، والوثيقة تطلبها صراحةً | الخدمات مرتبطة مباشرةً بالإشعارات والإحصائيات | 9 أحداث في `app/Events` و3 مستمعين في `app/Listeners`. كل حدث يُطلق بعد نجاح المعاملة (`ShouldDispatchAfterCommit`) | `DomainEventsTest` (10 اختبارات) |
| 2 | **Queues:** المهام تعمل بعد الرد فقط ولا يمكن تحويلها لطابور حقيقي | لا توزيع للحمل في الإنتاج | عند `QUEUE_CONNECTION=database` تدخل المهام الطابور ويعالجها `queue:work`. الوضع الافتراضي على Windows لم يتغير | `test_ai_job_goes_to_the_real_queue_when_configured` |
| 3 | تنبيهات الإدارة (بلاغ حرج، متأخر، منطقة مراقبة) تصل **لكل** مدراء المحافظات | مدير حلب يرى رقم وعنوان بلاغ في دمشق لا يحق له فتحه | التنبيه لمدير النظام ومدير المحافظة المعنية فقط، بنفس قاعدة `ManagerScope` | `test_manager_alerts_are_limited_to_their_governorate` |
| 4 | إنشاء دور جديد من «الأدوار والصلاحيات» يفشل بـ `Unknown column 'permissions'` | لا يمكن إنشاء أدوار مخصصة | `disabled()` في Filament كان يعيد إرسال حقل الصلاحيات مع بيانات الدور، فأوقفنا إرساله: `->dehydrated(false)` | `test_custom_role_create_and_delete_are_audited` |
| 5 | سجل التدقيق لا يسجّل تغييرات الصلاحيات ولا إعدادات النظام | أهم عمليات الأمان بلا أثر | تسجيل إنشاء الدور وتعديل صلاحياته وحذفه، مع الصلاحيات المضافة والمحذوفة. وتسجيل مفاتيح الإعدادات التي تغيّرت فقط | `test_role_permission_changes_are_audited` · `test_system_settings_changes_are_audited_with_changed_keys_only` |
| 6 | تطبيق المواطن بلا `manifest` للويب | يُثبَّت كتطبيق ويب باسم وأيقونة Flutter الافتراضيين | `web/manifest.json` عربي مع الأيقونات، وأيقونة Windows، وشاشة إقلاع Android | — |
| 7 | معرّف الحزمة في `AppConfig` لتطبيق المواطن لا يطابق الحقيقي | ترويسة User-Agent لطلبات OSM وNominatim بمعرّف خاطئ | `sy.gov.matrahak.matrahak_citizen` | — |

أُضيف أيضاً اختبار لإدارة البيانات المرجعية من لوحة الإدارة، كما يفعلها المدير فعلاً: المديريات والتصنيفات وطبقات الخريطة والمحافظات والمستخدمون (`ReferenceDataCrudTest`، 7 اختبارات).

---

## 4. مصفوفة تتبع المتطلبات (Requirements Traceability Matrix)

**مفتاح الحالة:**
- ✅ منفّذ كما في الوثيقة.
- 🔶 منفّذ ببديل مكافئ، وهو انحراف مقصود مبرَّر في القسم 5.
- ⚠️ منفّذ جزئياً، والناقص مذكور.
- ❌ غير منفّذ.

### 4.0 الملخص بالأرقام

| القسم في الوثيقة | البنود | ✅ | 🔶 | ⚠️ | ❌ |
|---|---|---|---|---|---|
| Tech Stack | 23 | 15 | 5 | 2 | 1 |
| System Modules | 7 | 7 | — | — | — |
| User Types | 24 | 24 | — | — | — |
| Core Idea | 10 | 10 | — | — | — |
| Digital Twin Features | 10 | 10 | — | — | — |
| AI Features + المثال | 12 | 10 | — | 2 | — |
| Database Design | 22 | 21 | 1 | — | — |
| UML Required | 7 | 7 | — | — | — |
| Backend Required | 14 | 13 | — | 1 | — |
| API Required | 13 | 8 | 5 | — | — |
| Flutter Required | 12 | 11 | 1 | — | — |
| الشاشات: Flutter + Employee + Admin | 27 | 27 | — | — | — |
| Smart City Dashboard | 10 | 10 | — | — | — |
| Advanced Features | 11 | 8 | — | 3 | — |
| Predictive Analytics | 5 | 5 | — | — | — |
| Development Strategy | 12 | 12 | — | — | — |
| **المجموع** | **219** | **198** | **12** | **8** | **1** |

### 4.1 Tech Stack

| المتطلب | الحالة | التنفيذ | ملاحظات |
|---|---|---|---|
| Laravel 12 | ✅ | `laravel/framework ^12.0` | — |
| PHP 8.4 | ✅ | `composer.lock` مقفل على PHP 8.2 كحد أدنى | يعمل على 8.2 و8.3 و8.4. نُفّذت الاختبارات هنا على 8.3 |
| REST API | ✅ | 43 مساراً تحت `/api/v1` | غلاف موحد `{success, message, data, meta}` — انظر `03-api-reference.md` |
| Laravel Sanctum | ✅ | Bearer Token لكل تطبيق | جلسة واحدة لكل نوع تطبيق: الدخول الجديد يُبطل الرمز السابق |
| Repository Pattern | ⚠️ | `ReportRepositoryInterface` ← `Eloquent\ReportRepository` | مطبّق على البلاغات، حيث كل الاستعلامات المعقدة. باقي الكيانات بـ Eloquent مباشرة (القسم 5) |
| Service Layer Pattern | ✅ | 25 خدمة في `app/Services` | الـ Controllers ولوحة Filament تستدعي الخدمات نفسها |
| MySQL | ✅ | MySQL 8 / MariaDB 10.6+ | الاختبارات نفسها تعمل على MySQL، لا على SQLite |
| Flutter | ✅ | تطبيقان، Flutter 3.35+ | فُحصا على 3.35.7 و3.47.7 |
| Clean Architecture | ✅ | `core/` + `features/<x>/{domain, data, application, presentation}` | طبقتا domain وdata لا تستوردان Flutter |
| Riverpod | ✅ | Riverpod 3: `Notifier` و`AsyncNotifier` | — |
| Filament Admin Panel | ✅ | Filament 3.3 | — |
| Google Maps | 🔶 | OpenStreetMap: `flutter_map` في التطبيقين وLeaflet في لوحة الإدارة | Google Maps يحتاج مفتاحاً مدفوعاً وغير متاح في سوريا |
| Google Places API | 🔶 | Nominatim (OpenStreetMap) لتحويل الإحداثيات إلى عنوان | نفس السبب |
| Geolocation Services | ✅ | `geolocator` + `DistrictLocator` (Point-in-Polygon) | المحافظة تُحدَّد على الخادم، لا من الهاتف |
| Firebase Cloud Messaging | ⚠️ | الخادم كامل: FCM HTTP v1 وتوقيع JWT وحذف الرموز المنتهية (`FcmPushTest`) | التطبيقان لا يتضمنان `firebase_messaging`، والبديل استطلاع كل 45 ثانية (القسم 5) |
| Python FastAPI Microservice | ✅ | `matrahak-ai` | Stateless ومحمية بمفتاح داخلي |
| Transformers | 🔶 | TF-IDF (كلمات + مقاطع حروف) + LinearSVC مع معايرة احتمالية | القسم 5: دقة 91.7% على اختبار الإجهاد، على CPU، دون إنترنت |
| Sentence Embeddings | 🔶 | التشابه الدلالي من متجه احتمالات النموذج + TF-IDF مقاطع الحروف | يُستخدم في كشف التكرار |
| OpenAI Compatible Architecture | ❌ | — | لا يوجد استدعاء لنموذج لغوي (القسم 5) |
| ChartJS | ✅ | Filament ChartWidget مبني على Chart.js | — |
| Laravel Charts | 🔶 | Widgets في Filament بدل حزمة `consoletvs/charts` | نفس المكتبة (Chart.js) دون حزمة إضافية |
| Exports: PDF | ✅ | تقرير المدينة عبر mPDF بالعربية (RTL وخط عربي) | بالفلاتر ونطاق المحافظة |
| Exports: Excel | ✅ | ملف XLSX عبر OpenSpout | بالفلاتر ونطاق المحافظة |

### 4.2 System Modules

| الوحدة | الحالة | التنفيذ |
|---|---|---|
| Citizen Module | ✅ | تطبيق «مطرحك» + `/api/v1/citizen/*` |
| Government Employee Module | ✅ | تطبيق «مطرحك ميدان» + `/api/v1/employee/*` |
| City Administration Module | ✅ | لوحة Filament: البلاغات، الإسناد، الأداء، المستخدمون |
| Smart Maps Module | ✅ | الخريطة التحليلية في الإدارة، وخرائط التطبيقين، وطبقات الخريطة، ومناطق المراقبة |
| AI Analytics Module | ✅ | `matrahak-ai` + `app/Services/Ai` + صفحتا الرؤى والتنبؤات |
| Reporting Module | ✅ | تقرير المدينة PDF + تصدير Excel بالفلاتر وبنطاق المحافظة |
| Prediction Module | ✅ | `ai:predictions` يومياً + التحقق من الدقة بعد انتهاء النافذة |

### 4.3 User Types

| الدور | القدرة | الحالة | التنفيذ |
|---|---|---|---|
| Citizen | Register | ✅ | `POST /citizen/auth/register`: الجوال السوري يُتحقق منه، والمحافظة اختيارية |
| | Login | ✅ | `POST /citizen/auth/login` بالبريد **أو** الجوال السوري في الحقل نفسه |
| | Submit Reports | ✅ | `POST /citizen/reports` — التصنيف اختياري، ويحدده الذكاء الاصطناعي |
| | Upload Images | ✅ | حتى 5 مرفقات × 20 MB: jpg و png و webp، وتُضغط الصور قبل الرفع |
| | Upload Videos | ✅ | mp4 و mov و 3gp، من الكاميرا أو المعرض |
| | Track Reports | ✅ | بلاغاتي، والسجل الزمني العام، ورمز QR، وصفحة تتبع عامة `/track/{uuid}` |
| | View Nearby Issues | ✅ | `GET /citizen/map/nearby` (1–25 كم)، مع تجميع وخريطة حرارية |
| | Receive Notifications | ✅ | 5 مسارات إشعارات. داخل التطبيق دون Firebase، وPush اختياري |
| | Rate Services | ✅ | `POST /citizen/reports/{uuid}/rate` (1–5) — التقييم يغلق البلاغ |
| Employee | View Assigned Cases | ✅ | `/employee/reports/assigned` + قائمة القسم `pool` + السجل `history` |
| | Update Status | ✅ | `POST /employee/reports/{uuid}/status` — مقيّد بآلة الحالات |
| | Add Internal Notes | ✅ | `POST /employee/reports/{uuid}/notes` (داخلية أو عامة) |
| | Transfer Cases | ✅ | `POST /employee/reports/{uuid}/transfer` + إسناد تلقائي في المديرية الجديدة |
| | Respond To Citizens | ✅ | `POST /employee/reports/{uuid}/reply` + إشعار للمواطن |
| City Manager | Monitor Entire City | ✅ | لوحة التحكم + الخريطة التحليلية. مدير المحافظة يرى محافظته فقط |
| | View Statistics | ✅ | 8 مؤشرات حية + 3 رسوم بيانية + جداول |
| | Monitor Departments | ✅ | «أداء المديريات» + صفحة «أداء الموظفين» |
| | Generate Reports | ✅ | PDF وExcel من لوحة التحكم والخريطة |
| | Review AI Insights | ✅ | صفحتا «رؤى الذكاء الاصطناعي» و«التنبؤات»، ومراجعة اقتراح كل بلاغ |
| System Administrator | Manage Users | ✅ | `UserResource` |
| | Manage Roles | ✅ | `RoleResource`: إنشاء وتعديل وحذف، والأدوار الأساسية محمية |
| | Manage Permissions | ✅ | إسناد الصلاحيات للأدوار من الصفحة نفسها. تعريف الصلاحيات في الكود (القسم 5) |
| | Manage Departments | ✅ | `DepartmentResource` (والمحافظات والتصنيفات) |
| | Manage AI Settings | ✅ | «إعدادات النظام»: حد التطبيق التلقائي، ونصف قطر التكرار، وتحليل الصور، وإعادة التدريب |

الأدوار 4 والصلاحيات 18، معرّفة في `RolesAndPermissionsSeeder`.

### 4.4 Core Idea — أنواع المشكلات

| المطلوب | الحالة | التصنيفات الفعلية (slug) |
|---|---|---|
| Road Damage | ✅ | `road-damage` · `sidewalk-damage` |
| Electricity Problems | ✅ | `power-outage` · `dangerous-electric-pole` · `exposed-wires` |
| Water Leaks | ✅ | `street-water-leak` · `drinking-water-outage` · `sewage-overflow` · `blocked-drains` |
| Garbage Issues | ✅ | `garbage` |
| Traffic Problems | ✅ | `traffic-congestion` · `traffic-signal-failure` · `damaged-road-sign` |
| Street Lighting Failures | ✅ | `street-light-failure` |
| Environmental Violations | ✅ | `environmental-violation` · `waste-burning` · `water-pollution` |
| Public Safety Issues | ✅ | `public-safety-hazard` · `fire` · `collapsing-building` |
| Every report is stored geographically | ✅ | `latitude` و`longitude` إلزاميان، مع فهرس، والمحافظة تُشتق تلقائياً |
| All reports appear on a Smart City Map | ✅ | الخريطة التحليلية + خريطة المواطن + خريطة القسم للموظف |

المجموع 24 تصنيفاً في 8 مديريات، منها إضافة للمطلوب: الاتصالات والصحة. تُدار كلها من لوحة الإدارة.

### 4.5 Digital Twin Features

| الميزة | الحالة | التنفيذ |
|---|---|---|
| Interactive City Map | ✅ | الخريطة التحليلية `/admin/analytics-map` (Leaflet + OSM) مع الفلاتر |
| Heat Maps | ✅ | `leaflet-heat` + `heatmap_snapshots` + `GET /citizen/map/heatmap` |
| Issue Clustering | ✅ | `leaflet.markercluster` في الإدارة، وتجميع شبكي (Web Mercator) في التطبيقين |
| Urban Health Indicators | ✅ | نسبة الحل، والالتزام بالمهلة، ورضا المواطنين، والبلاغات لكل 100 ألف نسمة |
| District Performance Indicators | ✅ | جدول «مؤشر خطورة المحافظات» + صفحة كل محافظة + `GET /citizen/map/districts/{id}/stats` |
| Department Performance Indicators | ✅ | «أداء المديريات»: الإجمالي، والمفتوحة، والمتأخرة، ونسبة الحل، والمهلة، والتقييم |
| Live Statistics | ✅ | المؤشرات تتحدث كل 120 ثانية، والعاجلة كل 60 ثانية، والإشعارات كل 60 ثانية |
| Smart Layers | ✅ | `map_layers` تُدار من الإدارة: تجميع، حرارية، محافظات، مناطق مراقبة؛ وما هو «عام» يظهر للمواطن |
| Timeline Replay | ✅ | شريط زمني في الخريطة التحليلية يعيد حالة البلاغات يوماً بيوم |
| City Status Dashboard | ✅ | لوحة التحكم بـ 8 Widgets |

### 4.6 AI Features

| الميزة | الحالة | التنفيذ | ملاحظات |
|---|---|---|---|
| Automatic Category Classification | ✅ | `/v1/classify`: نموذج هجين `0.7·النموذج + 0.3·الكلمات المفتاحية` | على التصنيفات المفعّلة فقط |
| Department Recommendation | ✅ | المديرية من التصنيف المقترح | تُطبَّق آلياً إن كانت الثقة ≥ الحد (0.55 افتراضياً) |
| Priority Detection | ✅ | أولوية التصنيف + مؤشر خطورة من النص (حريق، ماس كهربائي، أطفال...) | الأولوية لا تُخفَّض آلياً أبداً |
| Automatic Summarization | ⚠️ | تلخيص استخلاصي: العنوان + أول جملة من الوصف (140 حرفاً) | ليس تلخيصاً توليدياً (Abstractive) |
| Duplicate Detection | ✅ | `/v1/duplicates`: دلالي + لفظي + جغرافي | اقتراح للمراجعة، لا دمج تلقائي |
| Image Analysis | ⚠️ | `/v1/analyze-image`: جودة الصورة ومؤشرات لونية (حريق، نبات، مياه) | استدلالي (Heuristic) معلن، لا نموذج رؤية مدرّب |
| Issue Trend Detection | ✅ | `/v1/insights`: الاتجاه الأسبوعي للمشكلات المتصاعدة | — |
| Anomaly Detection | ✅ | z-score لمتوسط آخر 3 أيام مقابل خط الأساس | — |
| Predictive Analytics | ✅ | `/v1/predict`: Poisson-EWMA (نصف عمر 14 يوماً) | الدقة تُقاس تلقائياً بعد انتهاء نافذة كل تنبؤ |
| City Risk Scoring | ✅ | `/v1/risk`: risk-v2 (0–100) لكل محافظة، لكل 100 ألف نسمة | يُحسب يومياً `ai:risk-scores` |
| AI Generated Insights | ✅ | رؤى عربية تولَّد من الإحصائيات: ارتفاع مفاجئ، اتجاه، توصية | قوالب نصية على نتائج إحصائية، وليست من نموذج لغوي |
| **المثال في الوثيقة** | ✅ | «في حفرة كبيرة قرب المدرسة عم تسبب حوادث سير» ← `road-damage` · `MUN` · `high` (ثقة 78%) | ناتج حقيقي من الخدمة. النموذج للعربية فقط: النص الإنجليزي يعطي ثقة 10% فلا يُطبَّق |

### 4.7 Database Design

| المطلوب | الحالة | الجدول |
|---|---|---|
| Users | ✅ | `users` |
| Roles | ✅ | `roles` · `model_has_roles` (Spatie) |
| Permissions | ✅ | `permissions` · `role_has_permissions` · `model_has_permissions` (Spatie) |
| Departments | ✅ | `departments` |
| Districts | ✅ | `districts` (14 محافظة بحدودها الرسمية) |
| Reports | ✅ | `reports` |
| Categories | ✅ | `categories` |
| Attachments | ✅ | `report_attachments` |
| Updates | ✅ | `report_updates` |
| Notifications | ✅ | `notifications` |
| Ratings | ✅ | `report_ratings` (+ `report_upvotes`) |
| AI Predictions | ✅ | `ai_predictions` (+ `ai_classifications` · `ai_insights`) |
| Locations | 🔶 | الموقع جزء من `reports` (lat, lng, address, district_id)، والحدود في `districts`، والمناطق في `geofence_zones` (القسم 5) |
| Map Layers | ✅ | `map_layers` |
| Heatmap Data | ✅ | `heatmap_snapshots` |
| Analytics Snapshots | ✅ | `analytics_snapshots` (+ `employee_stats`) |
| Audit Logs | ✅ | `audit_logs` |
| ERD | ✅ | 4 مخططات من قاعدة البيانات الفعلية — `04-database.md` |
| Normalization Analysis | ✅ | `04-database.md` القسم 4 |
| Foreign Keys | ✅ | 47 مفتاحاً أجنبياً بقواعد حذف مدروسة: 29 SET NULL، و12 CASCADE، و6 RESTRICT |
| Indexes | ✅ | 108 فهارس غير أساسية، منها 19 مركّباً و23 فريداً، وفهرس FULLTEXT معدّ للتوسع |
| Optimization Strategy | ✅ | `04-database.md` القسم 6 |

### 4.8 UML Required

| المخطط | الحالة | الملف |
|---|---|---|
| Use Case Diagram | ✅ | `diagrams/png/uml-1a-use-case-field.png` · `uml-1b-use-case-admin.png` |
| Class Diagram | ✅ | `uml-2a-class-domain.png` · `uml-2b-class-services.png` · `uml-2c-class-events.png` |
| Activity Diagram | ✅ | `uml-3-activity.png` |
| Sequence Diagram | ✅ | `uml-4a-sequence-submit.png` · `uml-4b-sequence-status.png` |
| State Diagram | ✅ | `uml-5-state.png` |
| Deployment Diagram | ✅ | `uml-6-deployment.png` |
| Component Diagram | ✅ | `uml-7-component.png` |

مصادر كل المخططات بصيغة PlantUML في `diagrams/src`، وتُعدَّل وتُعاد كما هي.

### 4.9 Backend Required

| المطلوب | الحالة | العدد والمكان |
|---|---|---|
| Folder Structure | ✅ | انظر `05-uml.md` (مخطط المكوّنات) |
| Models | ✅ | 20 نموذجاً — `app/Models` |
| Migrations | ✅ | 26 ترحيلاً — `database/migrations` |
| Policies | ✅ | 11 سياسة + `PermissionPolicy` أساسية — `app/Policies` |
| Requests | ✅ | 12 FormRequest برسائل عربية — `app/Http/Requests` |
| Controllers | ✅ | 12 — `app/Http/Controllers` |
| Resources | ✅ | 6 JsonResource — `app/Http/Resources` |
| Repositories | ⚠️ | 1: `ReportRepositoryInterface` + التنفيذ (القسم 5) |
| Services | ✅ | 25 خدمة في 9 مجالات — `app/Services` |
| Events | ✅ | 9 أحداث — `app/Events` (أُضيفت في هذه الدفعة) |
| Listeners | ✅ | 3 مستمعين — `app/Listeners` (أُضيفت في هذه الدفعة) |
| Notifications | ✅ | `ReportEventNotification` على قناتين: `database` و`FcmChannel` |
| Queues | ✅ | Jobs: `ProcessReportWithAi` · `SendFcmMessage`، وطابور حقيقي عند `QUEUE_CONNECTION=database` |
| API Documentation | ✅ | `03-api-reference.md` + مجموعة Postman |

### 4.10 API Required

| المطلوب | الحالة | التنفيذ | ملاحظات |
|---|---|---|---|
| Authentication | ✅ | تسجيل ودخول وخروج للمواطن، ودخول وخروج للموظف | الإدارة بجلسة Web في Filament |
| Users CRUD | 🔶 | لوحة الإدارة (`UserResource`) + `GET/POST /citizen/profile` و`/employee/profile` | إدارة الحسابات لا تُعرض كـ REST (القسم 5) |
| Roles CRUD | 🔶 | لوحة الإدارة (`RoleResource`) | نفس السبب |
| Permissions CRUD | 🔶 | لوحة الإدارة — إسناد الصلاحيات للأدوار | الصلاحيات نفسها معرّفة في الكود |
| Departments CRUD | 🔶 | لوحة الإدارة + `GET /shared/departments` للقراءة | — |
| Districts CRUD | 🔶 | لوحة الإدارة (تعديل) + `GET /shared/districts` | المحافظات الـ 14 ثابتة بحدودها الرسمية، فلا إنشاء ولا حذف |
| Reports CRUD | ✅ | إنشاء وعرض وقائمة وحذف قبل الإسناد (مواطن)، وكل إجراءات الحالة (موظف) | والإدارة الكاملة في Filament |
| Attachments CRUD | ✅ | الرفع مع البلاغ، وصور ما بعد المعالجة `POST /employee/reports/{uuid}/attachments`، والعرض داخل البلاغ | الحذف من لوحة الإدارة |
| Notifications | ✅ | 5 مسارات | — |
| Analytics | ✅ | `my-stats` · `workload` · `department-stats` · `citizen/reports/stats` · `districts/{id}/stats` | — |
| Heatmaps | ✅ | `GET /citizen/map/heatmap` + `/admin/analytics-map/data` | — |
| AI Services | ✅ | 9 مسارات في `matrahak-ai` + نتائج التحليل داخل البلاغ + حالة الخدمة في `/health` | — |
| Reports Export | ✅ | `GET /admin/exports/pdf` · `/admin/exports/excel` | بصلاحية `reports.export` ونطاق المحافظة |

### 4.11 Flutter Required

| المطلوب | الحالة | التنفيذ |
|---|---|---|
| Complete Clean Architecture | ✅ | حسب الميزة: `features/<x>/{domain, data, application, presentation}` |
| Features | ✅ | المواطن: 8 ميزات. الموظف: 9 ميزات |
| Data Layer | ✅ | Mappers + `RepositoryImpl` عبر `ApiClient` (Dio) |
| Domain Layer | ✅ | الكيانات + عقود المستودعات بلغة Dart صرفة |
| Presentation Layer | ✅ | الشاشات + Riverpod Notifiers |
| Providers | ✅ | Riverpod 3 |
| Repositories | ✅ | عقد في domain وتنفيذ في data |
| Use Cases | 🔶 | منطق التطبيق في Notifiers طبقة application/presentation، دون أصناف UseCase منفصلة (القسم 5) |
| Services | ✅ | الموقع، والعنوان (Nominatim)، والتطبيقات الخارجية (اتصال، اتجاهات) |
| Routing | ✅ | GoRouter 17 + `StatefulShellRoute` |
| State Management | ✅ | Riverpod 3، مع قناة أحداث `ReportChanges` في تطبيق الموظف لتحديث القوائم |
| Theme System | ✅ | ثيم فاتح وداكن وتلقائي، بخط Cairo واتجاه RTL، وهوية مستقلة لكل تطبيق |

### 4.12 الشاشات

| القائمة | الشاشة | الحالة | التنفيذ |
|---|---|---|---|
| Flutter (المواطن) | Splash | ✅ | `features/auth/presentation/splash_screen.dart`: استعادة الجلسة، ويكمل دون إنترنت بالمستخدم المحفوظ |
| | Login | ✅ | `login_screen.dart` |
| | Register | ✅ | `register_screen.dart` |
| | Home | ✅ | `features/reports/presentation/home_screen.dart` |
| | Map Screen | ✅ | `features/map/presentation/map_screen.dart` |
| | Create Report | ✅ | `create_report_screen.dart` + `location_picker_screen.dart` |
| | Issue Details | ✅ | `report_details_screen.dart` |
| | My Reports | ✅ | `my_reports_screen.dart` + صندوق الانتظار |
| | Notifications | ✅ | `features/notifications/presentation/notifications_screen.dart` |
| | Profile | ✅ | `profile_screen.dart` + `edit_profile_screen.dart` + `change_password_screen.dart` |
| | Settings | ✅ | `settings_screen.dart` + `server_settings_screen.dart` |
| Employee | Dashboard | ✅ | `features/dashboard`: المؤشرات وعبء العمل |
| | Assigned Issues | ✅ | `reports_screen.dart`: المسندة، وقائمة القسم، والسجل |
| | Issue Details | ✅ | `report_details_screen.dart` |
| | Update Status | ✅ | نافذة الحالة، والملاحظات، والرد، والتحويل، وصور ما بعد المعالجة |
| | Department Analytics | ✅ | إحصائياتي + إحصائيات القسم + الرسوم |
| Admin | Dashboard | ✅ | `App\Filament\Pages\Dashboard` (8 Widgets) |
| | Users | ✅ | `UserResource` |
| | Roles | ✅ | `RoleResource` |
| | Permissions | ✅ | داخل `RoleResource`: إسناد الصلاحيات لكل دور |
| | Departments | ✅ | `DepartmentResource` |
| | Districts | ✅ | `DistrictResource` مع معاينة الحدود |
| | Map Layers | ✅ | `MapLayerResource` |
| | Reports | ✅ | `ReportResource`: القائمة بتبويبات، وصفحة البلاغ بكل الإجراءات |
| | Analytics | ✅ | الخريطة التحليلية + «أداء الموظفين» |
| | AI Insights | ✅ | `AiInsightResource` + `AiPredictionResource` |
| | Settings | ✅ | `SystemSettings`: الذكاء الاصطناعي، والإسناد، والإشعارات، وحالة الخدمة |

إضافة للمطلوب: شاشة تأكيد بعد الرفع، وعارض الصور والفيديو، وخريطة القسم للموظف، وإدارة التصنيفات، ومناطق المراقبة، وسجل التدقيق.

### 4.13 Smart City Dashboard

| المؤشر | الحالة | التنفيذ |
|---|---|---|
| Total Issues | ✅ | «إجمالي البلاغات» + بلاغات اليوم + منحنى 14 يوماً |
| Open Issues | ✅ | «البلاغات المفتوحة» + عدد الحرجة |
| Resolved Issues | ✅ | «نسبة الحل» + عدد المحلولة |
| Average Resolution Time | ✅ | «متوسط زمن الحل» بالساعات |
| Citizen Satisfaction | ✅ | «رضا المواطنين» + متوسط التقييم وعدد التقييمات |
| Top Problem Areas | ✅ | «مؤشر خطورة المحافظات» (مرتب) + البلاغات لكل 100 ألف نسمة |
| Top Departments | ✅ | «أداء المديريات» |
| Issue Trends | ✅ | «البلاغات المرفوعة مقابل المحلولة» (7/30/90 يوماً) |
| Heatmaps | ✅ | الخريطة التحليلية (صفحة مستقلة من القائمة) |
| AI Insights | ✅ | «أحدث رؤى الذكاء الاصطناعي» |

إضافة للمطلوب: «متأخرة عن المهلة»، و«الالتزام بالمهلة SLA»، و«المرفوضة»، و«بلاغات عاجلة مفتوحة».

### 4.14 Advanced Features

| الميزة | الحالة | التنفيذ | الناقص أو الملاحظة |
|---|---|---|---|
| QR Code For Every Report | ✅ | `QrCodeService` (SVG) يفتح `/track/{uuid}` | يعتمد على `APP_URL` |
| Timeline Tracking | ✅ | `report_updates` + `is_public`: العام للمواطن، والكامل للموظف والإدارة | — |
| Real-Time Notifications | ⚠️ | داخل التطبيقين كل 45 ثانية، وجرس الإدارة كل 60 ثانية، وPush عبر FCM عند ضبطه | لحظي تقريباً، لا WebSocket |
| GeoFencing | ✅ | `geofence_zones` (مضلّع أو دائرة): ترفع الأولوية وتنبّه الإدارة | — |
| Offline Reporting | ✅ | صندوق الانتظار في تطبيق المواطن: حفظ مع الصور وإرسال تلقائي | غير متاح في نسخة الويب |
| Audit Logs | ✅ | `Auditable` على 6 نماذج + الأدوار والصلاحيات + الإعدادات، مع المستخدم و IP | للقراءة فقط |
| RBAC | ✅ | Spatie: 4 أدوار و18 صلاحية + Policies + نطاق المحافظة `ManagerScope` | — |
| Multi-language | ⚠️ | البنية جاهزة: ملفات `lang/`، و`preferred_lang` للمستخدم، و`name_en` للتصنيفات والمديريات والمحافظات | الواجهات بالعربية فقط حالياً |
| Arabic Support | ✅ | RTL كامل، وخط Cairo، ورسائل أخطاء عربية، وتطبيع النص العربي في الذكاء الاصطناعي | — |
| Dark Mode | ✅ | التطبيقان (فاتح، داكن، تلقائي) + لوحة الإدارة | — |
| PWA Support | ⚠️ | التطبيقان يُبنيان للويب بـ manifest عربي وأيقونات، ويُثبَّتان على الشاشة الرئيسية | لا يعمل دون اتصال على الويب |

### 4.15 Predictive Analytics

| التنبؤ | الحالة | التنفيذ |
|---|---|---|
| Future Electricity Failures | ✅ | `electricity_failure` من 4 تصنيفات كهرباء |
| Future Water Problems | ✅ | `water_problem` من 5 تصنيفات مياه |
| Future Traffic Congestion | ✅ | `traffic_congestion` |
| Future High-Risk Areas | ✅ | `high_risk_area`: المحافظات بمؤشر خطورة ≥ 50 |
| Seasonal Trends | ✅ | `seasonal_trend`: تصنيف ارتفع ≥ 50% عن الأسبوعين السابقين |

### 4.16 Development Strategy

| المرحلة | الحالة | الدليل |
|---|---|---|
| 1. Requirements Analysis | ✅ | هذا التقرير، القسمان 3 و4 |
| 2. System Architecture | ✅ | مخططا المكوّنات والنشر — `05-uml.md` |
| 3. Database Design | ✅ | `04-database.md` |
| 4. UML Diagrams | ✅ | `05-uml.md` (11 مخططاً) |
| 5. API Design | ✅ | `03-api-reference.md` |
| 6. Backend Development | ✅ | `smart-city-api` |
| 7. Flutter Development | ✅ | `matrahak_citizen` · `matrahak_employee` |
| 8. AI Module Development | ✅ | `matrahak-ai` + `reports/evaluation.md` |
| 9. Dashboard Development | ✅ | لوحة Filament |
| 10. Testing | ✅ | القسم 7 |
| 11. Deployment | ✅ | `02-windows-setup.md` + ملاحظات الإنتاج |
| 12. Documentation | ✅ | هذا المجلد |

---

## 5. الانحرافات المقصودة ومبرراتها

| # | المطلوب | ما نُفّذ | المبرر |
|---|---|---|---|
| 1 | Google Maps + Places API | OpenStreetMap: flutter_map في التطبيقين، وLeaflet في الإدارة، وNominatim للعناوين | Google Maps يحتاج مفتاح API بحساب فوترة، وغير متاح في سوريا. وOSM مجاني ومفتوح. مكتبات الخرائط محلية في لوحة الإدارة (`public/vendor`)، فلا تعتمد على CDN |
| 2 | Firebase Cloud Messaging في التطبيقين | الخادم يرسل FCM كاملاً عند ضبط `FIREBASE_CREDENTIALS`. التطبيقان يعرضان الإشعارات دون Firebase، باستطلاع كل 45 ثانية | `firebase_messaging` يحتاج مشروع Firebase وملف `google-services.json` وخدمات Google Play. لو كان شرطاً لتوقفت الإشعارات كلها عند غيابه، كما حدث في النسخة الأصلية. إضافته لاحقاً لا تتطلب أي تغيير في الخادم |
| 3 | Transformers + Sentence Embeddings | TF-IDF (جذور الكلمات + مقاطع الحروف 2–5) + LinearSVC بمعايرة Platt، مع وزن 0.3 للكلمات المفتاحية | لا توجد بيانات تدريب حقيقية كافية لضبط Transformer عربي. والتشغيل على CPU دون إنترنت شرط للمنصة. ومقاطع الحروف تتحمل اللهجة السورية والأخطاء الإملائية. النتيجة 91.7% على اختبار إجهاد كُتب قبل التقييم. ويمكن استبدال `app/services/classify.py` دون تغيير العقد مع Laravel |
| 4 | OpenAI Compatible Architecture | لا يوجد نموذج لغوي (LLM) | خصوصية بلاغات المواطنين (أسماء، أرقام، مواقع)، والعمل دون إنترنت ودون تكلفة اشتراك. العقد JSON بين Laravel والخدمة يسمح بإضافة مزوّد متوافق مع OpenAI لاحقاً داخل الخدمة وحدها |
| 5 | REST CRUD للمستخدمين والأدوار والصلاحيات والمديريات والمحافظات | لوحة Filament فوق Policies وصلاحيات Spatie نفسها | لا يوجد عميل REST للإدارة؛ الإدارة على الويب. فتح مسارات REST لإدارة الحسابات يوسّع سطح الهجوم بلا مستخدم. الـ REST مخصص للتطبيقين، وكل عملية في Filament تمر عبر الـ Policies وتُسجَّل في سجل التدقيق |
| 6 | Permissions CRUD | الصلاحيات معرّفة في الكود (18 صلاحية)، وتُسند للأدوار من لوحة الإدارة | الكود يتحقق من أسماء صلاحيات محددة. صلاحية تُنشأ من الواجهة لن يتحقق منها أي كود، فلا معنى لإنشائها |
| 7 | جدول Locations مستقل | الموقع أعمدة في `reports`، والحدود في `districts`، والمناطق في `geofence_zones` | الموقع علاقة 1:1 مع البلاغ ولا يُشارك بين بلاغات؛ فصله يضيف JOIN في كل استعلام خريطة دون أي فائدة تطبيعية (تحليل التطبيع في `04-database.md`) |
| 8 | Repository Pattern شامل | مستودع للبلاغات فقط | كل الاستعلامات المعقدة في البلاغات: القرب الجغرافي، والتكرار، والقوائم الخاصة بكل دور. باقي الكيانات CRUD بسيط تديره Filament مباشرة. ومستودع لكل نموذج كان سيكون طبقة تمرير فارغة |
| 9 | Use Cases منفصلة في Flutter | منطق التطبيق في Riverpod Notifiers | النمط الموصى به مع Riverpod 3: الـ Notifier هو Use Case بحالة. أصناف UseCase ذات دالة واحدة تضاعف الملفات دون منطق إضافي |
| 10 | Laravel Charts | Filament Chart Widgets (Chart.js) | المكتبة نفسها دون حزمة إضافية، ومدمجة مع فلاتر اللوحة ونطاق المحافظة |
| 11 | Districts كأحياء مدينة واحدة | المحافظات السورية الـ 14 بحدودها الرسمية (OCHA)، ودمشق مدينة ضمنها | المنصة حكومية على مستوى البلد. «مدير المدينة» في الوثيقة هو مدير المحافظة، ويرى محافظته فقط |

---

## 6. القيود المعروفة والمخاطر

| # | القيد | الأثر | التوصية |
|---|---|---|---|
| 1 | دقة التصنيف على بلاغات المواطنين **الحقيقية** غير معروفة بعد | بيانات التدريب الأولية مولّدة (1,917 جملة) | بعد التجربة الميدانية: زر «إعادة تدريب النموذج». الاعتماد عبر Champion/Challenger واختبار McNemar |
| 2 | مؤشر الخطورة النصي يطابق أجزاء الكلمات | «يغرق الشارع» تطابق «غرق» فتصير الأولوية حرجة | الأولوية العالية الخاطئة أخف ضرراً من حالة خطرة فائتة؛ والمدير يصحّحها بنقرة. يمكن لاحقاً مطابقة الكلمة كاملة |
| 3 | النموذج للعربية فقط | النص الإنجليزي يعطي ثقة منخفضة | لا يُطبَّق تلقائياً تحت حد الثقة، فلا ضرر. إضافة بيانات إنجليزية إن لزم |
| 4 | تحليل الصور استدلالي | الوسوم مساعدة للموظف، وليست تشخيصاً | استبدال `app/services/vision.py` بنموذج رؤية (مثل YOLO) دون تغيير الـ API |
| 5 | خادم بلاطات OSM العام | سياسة الاستخدام العادل لا تناسب النشر الواسع | خادم بلاطات خاص عند النشر — `osmTileUrl` في `AppConfig` |
| 6 | `usesCleartextTraffic=true` في Android ورموز Sanctum بلا انتهاء | مناسب للتطوير على شبكة محلية | في الإنتاج: HTTPS، وحذف cleartext، وضبط `expiration` في `config/sanctum.php` |
| 7 | شعار الدولة في لوحة الإدارة وصفحة التتبع وتقرير PDF | استخدامه مقيّد قانونياً بالجهات الرسمية | مناسب للعرض الأكاديمي؛ وعند النشر العام موافقة الجهة المعنية أو `BRAND_STATE_EMBLEM=false` |
| 8 | واجهات Flutter لم تُشغَّل على جهاز في بيئة الفحص | لا يوجد محرك Flutter فيها | فُحص كل شيء سوى الرسم: التحليل الثابت، واختبارات الوحدة، وطبقة البيانات، ومنطق الشاشات على الخادم الحقيقي. التشغيل على جهاز هو الخطوة الأخيرة |

---

## 7. نتائج الاختبارات

### 7.1 الخادم — `php artisan test` على MySQL 8.4

| المجموعة | الملف | ما تغطيه |
|---|---|---|
| Unit | `GeoAndPhoneTest` · `LocalClassifierAccuracyTest` · `LocalForecasterTest` | تحديد المحافظة، والجوال السوري، ودقة البديل المحلي، والتنبؤ |
| API المواطن | `CitizenAuthTest` · `CitizenReportTest` | التسجيل والدخول، ورفع البلاغ بالصور، والخريطة، والتأييد، والتقييم، والحذف |
| API الموظف | `EmployeeFlowTest` | آلة الحالات، والملاحظات، والرد، والتحويل، والاستلام، والإحصائيات، والإشعارات |
| FCM | `FcmPushTest` | توقيع JWT، وحذف الرموز المنتهية، والعمل دون Firebase |
| الأحداث والطابور | `DomainEventsTest` | ربط الأحداث بالمستمعين، وترتيبها، والطابور الحقيقي، وعدم الإطلاق عند إلغاء المعاملة |
| لوحة الإدارة | `AdminPanelTest` · `ReferenceDataCrudTest` · `ManagerScopeTest` | كل الصفحات، والإجراءات، والتصدير، والأدوار، وسجل التدقيق، ونطاق المحافظة |
| خدمة AI | `AiServiceIntegrationTest` | التكامل عبر `Http::fake` والبديل المحلي وإعادة التدريب |
| المجدول | `ScheduledCommandsTest` | الإسناد، والتأخر، والإغلاق التلقائي، واللقطات، وأوامر الذكاء الاصطناعي |
| **المجموع** | | **103 اختبارات، 832 تحققاً — كلها ناجحة** |

### 7.2 خدمة الذكاء الاصطناعي — `test.bat`

- **43 اختباراً ناجحاً.**
- تشمل: تطابق المعالجة العربية والتنبؤ والخطورة مع PHP، وعقد الـ API وحماية المفتاح، وحداً أدنى للدقة.
- وتشمل أيضاً: خلو بيانات التدريب من نصوص الاختبار، وكشف التكرار، وتحليل الصور، وإعادة التدريب (الاعتماد، والرفض، وMcNemar، وحذف البيانات الشخصية).

### 7.3 تطبيقا Flutter

| الفحص | المواطن | الموظف |
|---|---|---|
| `dart analyze` (Flutter 3.35.7 و3.47.7) | 0 ملاحظات | 0 ملاحظات |
| اختبارات الوحدة | 26 ناجحاً | 31 ناجحاً |
| طبقة البيانات على الخادم الحقيقي (`tool/api_check.dart`) | 30/30 | 29/29 |
| منطق الشاشات (Riverpod Controllers) على الخادم الحقيقي | 47/47 | 61/61 |
| اختبارات الواجهة (`flutter test` على جهازك) | 4 | 4 |

### 7.4 تقييم نموذج التصنيف — `matrahak-ai/reports/evaluation.md`

| المقياس | النتيجة |
|---|---|
| Grouped CV (5 طيات) | 99.4% ± 0.4% |
| مجموعة الاختبار المستقلة (148) | 100% — متفائلة لأن نصوصها قريبة من القوالب |
| **اختبار الإجهاد (48 نصاً عامياً وبأخطاء)** — النموذج | **91.7%** |
| اختبار الإجهاد — الكلمات المفتاحية فقط (البديل المحلي) | 68.8% |
