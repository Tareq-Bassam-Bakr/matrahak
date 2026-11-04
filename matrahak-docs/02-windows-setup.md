# دليل التشغيل على Windows — منصة «مطرحك»

من جهاز جديد حتى تعمل المنصة كاملة: قاعدة البيانات، ثم Laravel ولوحة الإدارة، ثم خدمة الذكاء الاصطناعي، ثم التطبيقان.

> **قاعدة الأوامر:** نفّذ كل سطر في CMD وحده، ولا تنسخ معه أي شرح.

---

## 1. البرامج المطلوبة

| البرنامج | الإصدار | ملاحظة |
|---|---|---|
| PHP | 8.2 أو أحدث | مع الإضافات `gd, intl, zip, mbstring, pdo_mysql, openssl, fileinfo`. Laragon يأتي بها جاهزة |
| Composer | 2 | من getcomposer.org |
| MySQL | 8 (أو MariaDB 10.6+) | Laragon أو XAMPP |
| Python | 3.10 أو أحدث | من python.org، مع خيار **Add python.exe to PATH**. جُرّبت الخدمة على 3.13 |
| Flutter | 3.35 أو أحدث | فُحص الكود على 3.35.7 و3.47.7. مع Android Studio للمحاكي |
| Node.js | اختياري | فقط لتشغيل اختبارات Postman من سطر الأوامر (newman) |

**إعداد `php.ini` مرة واحدة** (حتى تُرفع الصور والفيديو حتى 20 MB للملف، 5 ملفات في البلاغ):
```
upload_max_filesize = 25M
post_max_size = 120M
```
لمعرفة مكان الملف: `php --ini`. بعد التعديل أعد تشغيل الخادم.

## 2. المجلدات

فك ضغط الملفات الخمسة داخل `C:\Users\SOLAV`، فيصبح:

| المجلد | المحتوى |
|---|---|
| `C:\Users\SOLAV\smart-city-api` | Laravel 12 API + لوحة الإدارة Filament 3 |
| `C:\Users\SOLAV\matrahak-ai` | خدمة الذكاء الاصطناعي FastAPI |
| `C:\Users\SOLAV\matrahak_citizen` | تطبيق المواطن «مطرحك» |
| `C:\Users\SOLAV\matrahak_employee` | تطبيق الموظف «مطرحك ميدان» |
| `C:\Users\SOLAV\matrahak-docs` | التوثيق والمخططات ومجموعة Postman |

## 3. قاعدة البيانات

شغّل MySQL (في Laragon: **Start All**)، ثم أنشئ القاعدتين. الأولى للعمل، والثانية للاختبارات الآلية فقط:
```
mysql -u root -e "CREATE DATABASE smart_city_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -e "CREATE DATABASE smart_city_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```
إن لم يكن الأمر `mysql` معروفاً، أنشئهما من HeidiSQL أو phpMyAdmin بنفس الاسمين والترميز.

## 4. Laravel ولوحة الإدارة — النافذة 1

أول مرة فقط:
```
cd C:\Users\SOLAV\smart-city-api
composer install
copy .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate:fresh --seed
```
- `composer install` يحتاج إنترنت وقد يأخذ عدة دقائق.
- إن كان لمستخدم MySQL كلمة مرور، عدّل `DB_PASSWORD` في `.env` قبل `migrate`.
- `migrate:fresh --seed` يبني 35 جدولاً ويعبّئها بالمحافظات والمديريات والتصنيفات والحسابات وبلاغات عرض حقيقية الشكل.

التشغيل (كل مرة):
```
cd C:\Users\SOLAV\smart-city-api
php artisan serve --host=0.0.0.0 --port=8000
```
افتح http://127.0.0.1:8000/admin وادخل بـ `admin@matrahak.sy` / `Admin@1234`. باقي الحسابات في `smart-city-api\README.md`.

## 5. المهام المجدولة — النافذة 2

```
cd C:\Users\SOLAV\smart-city-api
php artisan schedule:work
```
تنفّذ تلقائياً: الإسناد التلقائي للبلاغات المعلّقة كل 5 دقائق، وتنبيه البلاغات المتأخرة كل ساعة، والإغلاق التلقائي للبلاغات المحلولة بعد 7 أيام، ولقطات التحليلات، ومؤشر الخطورة والتنبؤات يومياً.
على خادم حقيقي يُستبدل هذا بمهمة Cron كل دقيقة: `php artisan schedule:run`.

**الطابور (اختياري):** افتراضياً `QUEUE_CONNECTION=sync`، ويعمل التحليل بالذكاء الاصطناعي بعد إرسال الرد للمواطن.
لطابور حقيقي ضع `QUEUE_CONNECTION=database` في `.env`، ثم نفّذ `php artisan config:clear` وشغّل في نافذة إضافية:
```
cd C:\Users\SOLAV\smart-city-api
php artisan queue:work
```

## 6. خدمة الذكاء الاصطناعي — النافذة 3

```
cd C:\Users\SOLAV\matrahak-ai
run.bat
```
- أول مرة ينشئ `run.bat` بيئة `.venv` ويثبّت المكتبات (دقائق)، وينسخ `.env.example` إلى `.env`.
- تعمل الخدمة على http://127.0.0.1:8001، وتوثيقها التفاعلي على http://127.0.0.1:8001/docs.
- **المفتاح الداخلي:** القيمة الافتراضية `change-this-internal-key` متطابقة في الطرفين، فتعمل محلياً دون تعديل.
  لتغييرها ضع القيمة نفسها في `INTERNAL_API_KEY` في `matrahak-ai\.env` وفي `AI_SERVICE_KEY` في `smart-city-api\.env`، ثم `php artisan config:clear` وأعد تشغيل `run.bat`.
- **للتحقق:** صفحة «إعدادات النظام» في لوحة الإدارة تعرض «● تعمل» مع الدقة وآخر تدريب.
- إن توقفت الخدمة لا يتعطل شيء: Laravel يستخدم المصنّف المحلي البديل تلقائياً.

## 7. التطبيقان — النافذة 4

أول مرة لكل تطبيق (`setup.bat` ينشئ مجلدات المنصات لإصدار Flutter عندك دون أن يستبدل الكود، ثم `pub get` و`analyze`):
```
cd C:\Users\SOLAV\matrahak_citizen
setup.bat
```
```
cd C:\Users\SOLAV\matrahak_employee
setup.bat
```
التشغيل على محاكي Android:
```
cd C:\Users\SOLAV\matrahak_citizen
flutter run
```
- المحاكي يصل للكمبيوتر على `http://10.0.2.2:8000`، وهو العنوان الافتراضي في التطبيقين. على Windows أو Chrome العنوان `http://127.0.0.1:8000`.
- حساب المواطن: `citizen@matrahak.sy` / `Citizen@1234`. حساب موظف: `employee.mun@matrahak.sy` / `Employee@1234`.

### على هاتف حقيقي
1. الهاتف والكمبيوتر على نفس شبكة Wi-Fi.
2. اعرف IP الكمبيوتر بالأمر `ipconfig`، مثلاً `192.168.1.10`.
3. في `smart-city-api\.env` ضع `APP_URL=http://192.168.1.10:8000` ثم `php artisan config:clear` (حتى تعمل الصور ورابط QR من الهاتف).
4. شغّل Laravel بـ `--host=0.0.0.0` كما في القسم 4.
5. اسمح بالمنفذ في جدار الحماية، من CMD بصلاحية المسؤول (Run as administrator):
```
netsh advfirewall firewall add rule name="Matrahak API 8000" dir=in action=allow protocol=TCP localport=8000
```
6. في شاشة الدخول: «تغيير الخادم» ← `http://192.168.1.10:8000` ← «فحص الاتصال».

بناء APK بعنوان افتراضي:
```
flutter build apk --release --dart-define=API_URL=http://192.168.1.10:8000
```

## 8. التشغيل اليومي (بعد أول مرة)

| النافذة | الأوامر |
|---|---|
| — | MySQL من Laragon: Start All |
| 1 | `cd C:\Users\SOLAV\smart-city-api` ثم `php artisan serve --host=0.0.0.0 --port=8000` |
| 2 | `cd C:\Users\SOLAV\smart-city-api` ثم `php artisan schedule:work` |
| 3 | `cd C:\Users\SOLAV\matrahak-ai` ثم `run.bat` |
| 4 | `cd C:\Users\SOLAV\matrahak_citizen` (أو `matrahak_employee`) ثم `flutter run` |

## 9. الاختبارات الآلية

| الجزء | الأمر (من مجلد المشروع) | النتيجة عند التسليم |
|---|---|---|
| Laravel | `php artisan test` (يحتاج القاعدة `smart_city_test`) | 103 اختبارات، 832 تحققاً |
| الذكاء الاصطناعي | `test.bat` | 43 اختباراً |
| تطبيق المواطن | `flutter test` | 26 اختبار وحدة + 4 اختبارات واجهة |
| تطبيق الموظف | `flutter test` | 31 اختبار وحدة + اختبارات الواجهة |
| ربط كل تطبيق بالخادم | `dart run tool/api_check.dart --server http://127.0.0.1:8000 --write` | المواطن 30/30، الموظف 29/29 |
| الـ API كاملاً | مجموعة Postman — `matrahak-docs\postman\README.md` | 81 طلباً، 226 اختباراً |

أوامر Postman من سطر الأوامر:
```
npm install -g newman
cd C:\Users\SOLAV\matrahak-docs\postman
newman run Matrahak.postman_collection.json -e Matrahak.postman_environment.json --working-dir .
```

## 10. حل المشكلات

| العَرَض | السبب والحل |
|---|---|
| `could not find driver` | إضافة `pdo_mysql` معطّلة. فعّلها في `php.ini` (احذف `;` من أول السطر `extension=pdo_mysql`) |
| `composer install` يطلب `ext-gd` أو `ext-intl` أو `ext-zip` | نفس الحل: فعّل الإضافة في `php.ini` ثم أعد الأمر |
| `SQLSTATE[HY000] [2002]` | MySQL لا يعمل. شغّله من Laragon |
| `SQLSTATE[HY000] [1049] Unknown database` | القاعدة غير موجودة. أنشئها كما في القسم 3 |
| `No application encryption key` | لم يُنفَّذ `php artisan key:generate` |
| الصور لا تظهر في التطبيق أو اللوحة | نفّذ `php artisan storage:link`، وتأكد أن `APP_URL` هو العنوان الذي يفتحه الجهاز |
| فشل رفع صورة أو فيديو (413 أو 422 عن الحجم) | `upload_max_filesize` و`post_max_size` في `php.ini` كما في القسم 1 |
| الهاتف لا يتصل | نفس الشبكة، و`--host=0.0.0.0`، وقاعدة جدار الحماية، والعنوان الصحيح في «تغيير الخادم» |
| `429` «محاولات كثيرة» | الدخول والتسجيل محدودان بعشر محاولات في الدقيقة. انتظر دقيقة، أو نفّذ `php artisan cache:clear` |
| لوحة الإدارة: خدمة الذكاء الاصطناعي «غير متاحة» | شغّل `run.bat`، وتأكد من تطابق المفتاحين، ثم `php artisan config:clear` |
| `Python was not found` | ثبّت Python مع **Add python.exe to PATH** ثم افتح نافذة CMD جديدة |
| المنفذ 8000 أو 8001 مستخدم | غيّر `--port` في أمر `serve`، أو المنفذ في `run.bat` و`AI_SERVICE_URL` معاً |
| تعديل `.env` لا يظهر أثره | `php artisan config:clear` ثم أعد تشغيل `serve` |
| `flutter` غير معروف | أضف `flutter\bin` إلى PATH ثم افتح نافذة جديدة. `flutter doctor` يكشف باقي النواقص |
