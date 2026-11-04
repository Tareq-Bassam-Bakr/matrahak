# قاموس البيانات — قاعدة بيانات مطرحك

مستخرج آلياً من قاعدة البيانات الفعلية (`information_schema` في MySQL 8.4) بعد `php artisan migrate --seed` وبيانات العرض.
الأعمدة مرتبة كما في الجدول. **المفتاح:** PRI أساسي · UNI فريد · MUL مفهرس.

| الجدول | الوصف | الأعمدة | الصفوف في بيانات العرض |
|---|---|---|---|
| [`ai_classifications`](#ai_classifications) | كل محاولة تصنيف آلي مع الثقة والتفسير | 16 | 14 |
| [`ai_insights`](#ai_insights) | الرؤى: اتجاه، شذوذ، توصية، تنبيه | 15 | 9 |
| [`ai_predictions`](#ai_predictions) | التنبؤات والتحقق من دقتها | 17 | 55 |
| [`analytics_snapshots`](#analytics_snapshots) | لقطات المؤشرات اليومية والأسبوعية والشهرية | 20 | 23 |
| [`audit_logs`](#audit_logs) | سجل التدقيق | 10 | 133 |
| [`categories`](#categories) | تصنيفات البلاغات وكلماتها المفتاحية | 16 | 24 |
| [`departments`](#departments) | المديريات والجهات المختصة | 15 | 8 |
| [`districts`](#districts) | المحافظات الـ 14 بحدودها الرسمية | 18 | 14 |
| [`employee_stats`](#employee_stats) | ملخص أداء كل موظف | 12 | 27 |
| [`geofence_zones`](#geofence_zones) | مناطق المراقبة الجغرافية | 14 | 0 |
| [`heatmap_snapshots`](#heatmap_snapshots) | نقاط الخريطة الحرارية المحسوبة مسبقاً | 10 | 30 |
| [`map_layers`](#map_layers) | طبقات الخريطة وإعداداتها | 14 | 4 |
| [`model_has_permissions`](#model_has_permissions) | صلاحيات مباشرة لمستخدم | 3 | 0 |
| [`model_has_roles`](#model_has_roles) | أدوار كل مستخدم | 3 | 94 |
| [`notifications`](#notifications) | إشعارات التطبيقين ولوحة الإدارة | 8 | 62 |
| [`permissions`](#permissions) | الصلاحيات (Spatie) | 5 | 18 |
| [`personal_access_tokens`](#personal_access_tokens) | رموز الدخول للتطبيقين (Sanctum) | 10 | 1 |
| [`report_assignments`](#report_assignments) | تاريخ الإسناد (موظف فعّال واحد) | 10 | 695 |
| [`report_attachments`](#report_attachments) | صور وفيديو قبل المعالجة وبعدها | 16 | 19 |
| [`report_ratings`](#report_ratings) | تقييم المواطن (واحد لكل بلاغ) | 7 | 278 |
| [`report_updates`](#report_updates) | السجل الزمني لكل حدث على البلاغ | 9 | 4,840 |
| [`report_upvotes`](#report_upvotes) | تأييد المواطنين (واحد لكل مستخدم) | 4 | 2,002 |
| [`reports`](#reports) | البلاغات — الكيان المركزي | 39 | 708 |
| [`role_has_permissions`](#role_has_permissions) | صلاحيات كل دور | 2 | 30 |
| [`roles`](#roles) | الأدوار (Spatie) | 5 | 4 |
| [`settings`](#settings) | إعدادات النظام | 5 | 9 |
| [`users`](#users) | كل الحسابات: مواطن، موظف، مدير مدينة، مدير نظام | 22 | 94 |
| [`cache`](#cache) | الكاش (database store) | 3 | 0 |
| [`cache_locks`](#cache_locks) | أقفال الكاش | 3 | 0 |
| [`failed_jobs`](#failed_jobs) | المهام الفاشلة | 7 | 0 |
| [`job_batches`](#job_batches) | دفعات المهام | 10 | 0 |
| [`jobs`](#jobs) | الطابور (database queue) | 7 | 0 |
| [`migrations`](#migrations) | سجل الترحيلات | 3 | 26 |
| [`password_reset_tokens`](#password_reset_tokens) | رموز استعادة كلمة المرور | 3 | 0 |
| [`sessions`](#sessions) | جلسات لوحة الإدارة | 6 | 3 |

## ai_classifications

**كل محاولة تصنيف آلي مع الثقة والتفسير** — InnoDB · utf8mb4_unicode_ci · 14 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `report_id` | `bigint unsigned` | لا | MUL |  | → `reports.id` (CASCADE) |
| `model_version` | `varchar(80)` | لا |  |  |  |
| `backend` | `varchar(40)` | نعم |  |  |  |
| `category_id` | `bigint unsigned` | نعم | MUL |  | → `categories.id` (SET NULL) |
| `department_id` | `bigint unsigned` | نعم | MUL |  | → `departments.id` (SET NULL) |
| `priority` | `enum('low','medium','high','critical')` | نعم |  |  |  |
| `confidence` | `decimal(5,4)` | لا | MUL | `0.0000` |  |
| `summary` | `text` | نعم |  |  |  |
| `keywords` | `json` | نعم |  |  |  |
| `alternatives` | `json` | نعم |  |  |  |
| `raw_response` | `json` | نعم |  |  |  |
| `processing_ms` | `int unsigned` | نعم |  |  |  |
| `was_applied` | `tinyint(1)` | لا |  | `0` |  |
| `is_accepted` | `tinyint(1)` | نعم |  |  |  |
| `created_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `ai_classifications_category_id_foreign` (category_id) · `ai_classifications_confidence_index` (confidence) · `ai_classifications_department_id_foreign` (department_id) · `ai_classifications_report_id_index` (report_id)

## ai_insights

**الرؤى: اتجاه، شذوذ، توصية، تنبيه** — InnoDB · utf8mb4_unicode_ci · 9 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `char(36)` | لا | UNI |  |  |
| `type` | `enum('trend','anomaly','recommendation','alert')` | لا | MUL |  |  |
| `title` | `varchar(255)` | لا |  |  |  |
| `content` | `text` | لا |  |  |  |
| `severity` | `enum('info','warning','critical')` | لا | MUL | `info` |  |
| `district_id` | `bigint unsigned` | نعم | MUL |  | → `districts.id` (SET NULL) |
| `category_id` | `bigint unsigned` | نعم | MUL |  | → `categories.id` (SET NULL) |
| `department_id` | `bigint unsigned` | نعم | MUL |  | → `departments.id` (SET NULL) |
| `data_payload` | `json` | نعم |  |  |  |
| `fingerprint` | `varchar(64)` | نعم | MUL |  |  |
| `is_read` | `tinyint(1)` | لا | MUL | `0` |  |
| `is_dismissed` | `tinyint(1)` | لا |  | `0` |  |
| `expires_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `ai_insights_category_id_foreign` (category_id) · `ai_insights_department_id_foreign` (department_id) · `ai_insights_district_id_foreign` (district_id) · `ai_insights_fingerprint_index` (fingerprint) · `ai_insights_is_read_index` (is_read) · `ai_insights_severity_index` (severity) · `ai_insights_type_index` (type) · `ai_insights_uuid_unique` (uuid, فريد)

## ai_predictions

**التنبؤات والتحقق من دقتها** — InnoDB · utf8mb4_unicode_ci · 55 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `char(36)` | لا | UNI |  |  |
| `prediction_type` | `enum('electricity_failure','water_problem','traffic_congestion','high_risk_area','seasonal_trend')` | لا | MUL |  |  |
| `district_id` | `bigint unsigned` | نعم | MUL |  | → `districts.id` (SET NULL) |
| `category_id` | `bigint unsigned` | نعم | MUL |  | → `categories.id` (SET NULL) |
| `predicted_date` | `date` | لا | MUL |  |  |
| `window_end` | `date` | نعم |  |  |  |
| `probability` | `decimal(5,4)` | لا |  |  |  |
| `expected_count` | `decimal(8,2)` | نعم |  |  |  |
| `severity` | `enum('low','medium','high','critical')` | نعم |  |  |  |
| `description` | `text` | نعم |  |  |  |
| `supporting_data` | `json` | نعم |  |  |  |
| `model_version` | `varchar(80)` | نعم |  |  |  |
| `was_accurate` | `tinyint(1)` | نعم |  |  |  |
| `actual_count` | `int unsigned` | نعم |  |  |  |
| `verified_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `ai_predictions_category_id_foreign` (category_id) · `ai_predictions_district_id_index` (district_id) · `ai_predictions_predicted_date_index` (predicted_date) · `ai_predictions_prediction_type_index` (prediction_type) · `ai_predictions_uuid_unique` (uuid, فريد)

## analytics_snapshots

**لقطات المؤشرات اليومية والأسبوعية والشهرية** — InnoDB · utf8mb4_unicode_ci · 23 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `snapshot_type` | `enum('daily','weekly','monthly')` | لا | MUL |  |  |
| `snapshot_date` | `date` | لا | MUL |  |  |
| `scope` | `varchar(40)` | لا |  |  |  |
| `district_id` | `bigint unsigned` | نعم | MUL |  | → `districts.id` (SET NULL) |
| `department_id` | `bigint unsigned` | نعم | MUL |  | → `departments.id` (SET NULL) |
| `total_reports` | `int unsigned` | لا |  | `0` |  |
| `new_reports` | `int unsigned` | لا |  | `0` |  |
| `open_reports` | `int unsigned` | لا |  | `0` |  |
| `resolved_reports` | `int unsigned` | لا |  | `0` |  |
| `rejected_reports` | `int unsigned` | لا |  | `0` |  |
| `overdue_reports` | `int unsigned` | لا |  | `0` |  |
| `avg_resolution_hours` | `decimal(8,2)` | نعم |  |  |  |
| `avg_rating` | `decimal(3,2)` | نعم |  |  |  |
| `citizen_satisfaction` | `decimal(5,2)` | نعم |  |  |  |
| `sla_compliance` | `decimal(5,2)` | نعم |  |  |  |
| `by_category` | `json` | نعم |  |  |  |
| `by_priority` | `json` | نعم |  |  |  |
| `by_status` | `json` | نعم |  |  |  |
| `created_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `analytics_snapshots_department_id_foreign` (department_id) · `analytics_snapshots_district_id_foreign` (district_id) · `analytics_snapshots_snapshot_date_index` (snapshot_date) · `unq_snapshot_scope` (snapshot_type,snapshot_date,scope, فريد)

## audit_logs

**سجل التدقيق** — InnoDB · utf8mb4_unicode_ci · 133 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `user_id` | `bigint unsigned` | نعم | MUL |  | → `users.id` (SET NULL) |
| `action` | `varchar(100)` | لا | MUL |  |  |
| `model_type` | `varchar(150)` | نعم | MUL |  |  |
| `model_id` | `bigint unsigned` | نعم |  |  |  |
| `old_values` | `json` | نعم |  |  |  |
| `new_values` | `json` | نعم |  |  |  |
| `ip_address` | `varchar(45)` | نعم |  |  |  |
| `user_agent` | `varchar(500)` | نعم |  |  |  |
| `created_at` | `timestamp` | لا | MUL | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `audit_logs_action_index` (action) · `audit_logs_created_at_index` (created_at) · `audit_logs_model_type_model_id_index` (model_type,model_id) · `audit_logs_user_id_foreign` (user_id)

## categories

**تصنيفات البلاغات وكلماتها المفتاحية** — InnoDB · utf8mb4_unicode_ci · 24 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `char(36)` | لا | UNI |  |  |
| `name` | `varchar(100)` | لا |  |  |  |
| `name_en` | `varchar(100)` | نعم |  |  |  |
| `slug` | `varchar(100)` | لا | UNI |  |  |
| `icon` | `varchar(100)` | نعم |  |  |  |
| `color` | `varchar(7)` | نعم |  |  |  |
| `department_id` | `bigint unsigned` | لا | MUL |  | → `departments.id` (RESTRICT) |
| `parent_id` | `bigint unsigned` | نعم | MUL |  | → `categories.id` (SET NULL) |
| `keywords` | `json` | نعم |  |  |  |
| `priority_weight` | `tinyint unsigned` | لا |  | `5` |  |
| `sla_hours` | `int unsigned` | لا |  | `48` |  |
| `is_active` | `tinyint(1)` | لا | MUL | `1` |  |
| `deleted_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `categories_department_id_foreign` (department_id) · `categories_is_active_index` (is_active) · `categories_parent_id_foreign` (parent_id) · `categories_slug_unique` (slug, فريد) · `categories_uuid_unique` (uuid, فريد)

## departments

**المديريات والجهات المختصة** — InnoDB · utf8mb4_unicode_ci · 8 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `char(36)` | لا | UNI |  |  |
| `name` | `varchar(150)` | لا |  |  |  |
| `name_en` | `varchar(150)` | نعم |  |  |  |
| `code` | `varchar(20)` | لا | UNI |  |  |
| `description` | `text` | نعم |  |  |  |
| `manager_id` | `bigint unsigned` | نعم | MUL |  | → `users.id` (SET NULL) |
| `parent_id` | `bigint unsigned` | نعم | MUL |  | → `departments.id` (SET NULL) |
| `contact_email` | `varchar(150)` | نعم |  |  |  |
| `contact_phone` | `varchar(20)` | نعم |  |  |  |
| `avg_resolution_hours` | `int unsigned` | لا |  | `72` |  |
| `is_active` | `tinyint(1)` | لا | MUL | `1` |  |
| `deleted_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `departments_code_unique` (code, فريد) · `departments_is_active_index` (is_active) · `departments_manager_id_foreign` (manager_id) · `departments_parent_id_foreign` (parent_id) · `departments_uuid_unique` (uuid, فريد)

## districts

**المحافظات الـ 14 بحدودها الرسمية** — InnoDB · utf8mb4_unicode_ci · 14 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `char(36)` | لا | UNI |  |  |
| `name` | `varchar(150)` | لا |  |  |  |
| `name_en` | `varchar(150)` | نعم |  |  |  |
| `code` | `varchar(20)` | لا | UNI |  |  |
| `iso_code` | `varchar(10)` | نعم | UNI |  |  |
| `city` | `varchar(100)` | نعم |  |  |  |
| `population` | `int unsigned` | نعم |  |  |  |
| `area_km2` | `decimal(10,2)` | نعم |  |  |  |
| `boundary` | `json` | نعم |  |  |  |
| `center_lat` | `decimal(10,8)` | لا | MUL |  |  |
| `center_lng` | `decimal(11,8)` | لا |  |  |  |
| `risk_score` | `decimal(5,2)` | لا |  | `0.00` |  |
| `risk_updated_at` | `timestamp` | نعم |  |  |  |
| `is_active` | `tinyint(1)` | لا | MUL | `1` |  |
| `deleted_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `districts_center_lat_center_lng_index` (center_lat,center_lng) · `districts_code_unique` (code, فريد) · `districts_is_active_index` (is_active) · `districts_iso_code_unique` (iso_code, فريد) · `districts_uuid_unique` (uuid, فريد)

## employee_stats

**ملخص أداء كل موظف** — InnoDB · utf8mb4_unicode_ci · 27 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `employee_id` | `bigint unsigned` | لا | UNI |  | → `users.id` (CASCADE) |
| `total_assigned` | `int unsigned` | لا |  | `0` |  |
| `total_resolved` | `int unsigned` | لا |  | `0` |  |
| `total_rejected` | `int unsigned` | لا |  | `0` |  |
| `current_workload` | `int unsigned` | لا |  | `0` |  |
| `overdue_count` | `int unsigned` | لا |  | `0` |  |
| `avg_resolution_hours` | `decimal(8,2)` | لا |  | `0.00` |  |
| `avg_rating` | `decimal(3,2)` | لا |  | `0.00` |  |
| `last_active_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `employee_stats_employee_id_unique` (employee_id, فريد)

## geofence_zones

**مناطق المراقبة الجغرافية** — InnoDB · utf8mb4_unicode_ci · 0 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `char(36)` | لا | UNI |  |  |
| `name` | `varchar(150)` | لا |  |  |  |
| `type` | `enum('alert','monitoring','restricted')` | لا | MUL | `monitoring` |  |
| `boundary` | `json` | نعم |  |  |  |
| `center_lat` | `decimal(10,8)` | نعم |  |  |  |
| `center_lng` | `decimal(11,8)` | نعم |  |  |  |
| `radius_meters` | `int unsigned` | نعم |  |  |  |
| `min_priority` | `enum('low','medium','high','critical')` | نعم |  |  |  |
| `alert_config` | `json` | نعم |  |  |  |
| `is_active` | `tinyint(1)` | لا | MUL | `1` |  |
| `created_by` | `bigint unsigned` | نعم | MUL |  | → `users.id` (SET NULL) |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `geofence_zones_created_by_foreign` (created_by) · `geofence_zones_is_active_index` (is_active) · `geofence_zones_type_index` (type) · `geofence_zones_uuid_unique` (uuid, فريد)

## heatmap_snapshots

**نقاط الخريطة الحرارية المحسوبة مسبقاً** — InnoDB · utf8mb4_unicode_ci · 30 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `layer_id` | `bigint unsigned` | نعم | MUL |  | → `map_layers.id` (SET NULL) |
| `period_type` | `enum('daily','weekly','monthly')` | لا | MUL |  |  |
| `period_date` | `date` | لا |  |  |  |
| `district_id` | `bigint unsigned` | نعم | MUL |  | → `districts.id` (SET NULL) |
| `category_id` | `bigint unsigned` | نعم | MUL |  | → `categories.id` (SET NULL) |
| `scope_key` | `varchar(60)` | لا |  |  |  |
| `data_points` | `json` | لا |  |  |  |
| `total_points` | `int unsigned` | لا |  | `0` |  |
| `generated_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `heatmap_snapshots_category_id_foreign` (category_id) · `heatmap_snapshots_district_id_foreign` (district_id) · `heatmap_snapshots_layer_id_foreign` (layer_id) · `unq_heatmap_scope` (period_type,period_date,scope_key, فريد)

## map_layers

**طبقات الخريطة وإعداداتها** — InnoDB · utf8mb4_unicode_ci · 4 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `char(36)` | لا | UNI |  |  |
| `key` | `varchar(50)` | لا | UNI |  |  |
| `name` | `varchar(100)` | لا |  |  |  |
| `type` | `enum('markers','heatmap','cluster','geofence','district','custom')` | لا |  |  |  |
| `description` | `text` | نعم |  |  |  |
| `config` | `json` | نعم |  |  |  |
| `is_active` | `tinyint(1)` | لا | MUL | `1` |  |
| `is_public` | `tinyint(1)` | لا |  | `1` |  |
| `default_visible` | `tinyint(1)` | لا |  | `0` |  |
| `sort_order` | `tinyint unsigned` | لا |  | `0` |  |
| `created_by` | `bigint unsigned` | نعم | MUL |  | → `users.id` (SET NULL) |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `map_layers_created_by_foreign` (created_by) · `map_layers_is_active_is_public_index` (is_active,is_public) · `map_layers_key_unique` (key, فريد) · `map_layers_uuid_unique` (uuid, فريد)

## model_has_permissions

**صلاحيات مباشرة لمستخدم** — InnoDB · utf8mb4_unicode_ci · 0 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `permission_id` | `bigint unsigned` | لا | PRI |  | → `permissions.id` (CASCADE) |
| `model_type` | `varchar(255)` | لا | PRI |  |  |
| `model_id` | `bigint unsigned` | لا | PRI |  |  |

**الفهارس:** `model_has_permissions_model_id_model_type_index` (model_id,model_type)

## model_has_roles

**أدوار كل مستخدم** — InnoDB · utf8mb4_unicode_ci · 94 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `role_id` | `bigint unsigned` | لا | PRI |  | → `roles.id` (CASCADE) |
| `model_type` | `varchar(255)` | لا | PRI |  |  |
| `model_id` | `bigint unsigned` | لا | PRI |  |  |

**الفهارس:** `model_has_roles_model_id_model_type_index` (model_id,model_type)

## notifications

**إشعارات التطبيقين ولوحة الإدارة** — InnoDB · utf8mb4_unicode_ci · 62 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `char(36)` | لا | PRI |  |  |
| `type` | `varchar(255)` | لا |  |  |  |
| `notifiable_type` | `varchar(255)` | لا | MUL |  |  |
| `notifiable_id` | `bigint unsigned` | لا |  |  |  |
| `data` | `text` | لا |  |  |  |
| `read_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `notifications_notifiable_type_notifiable_id_index` (notifiable_type,notifiable_id)

## permissions

**الصلاحيات (Spatie)** — InnoDB · utf8mb4_unicode_ci · 18 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `name` | `varchar(255)` | لا | MUL |  |  |
| `guard_name` | `varchar(255)` | لا |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `permissions_name_guard_name_unique` (name,guard_name, فريد)

## personal_access_tokens

**رموز الدخول للتطبيقين (Sanctum)** — InnoDB · utf8mb4_unicode_ci · 1 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `tokenable_type` | `varchar(255)` | لا | MUL |  |  |
| `tokenable_id` | `bigint unsigned` | لا |  |  |  |
| `name` | `text` | لا |  |  |  |
| `token` | `varchar(64)` | لا | UNI |  |  |
| `abilities` | `text` | نعم |  |  |  |
| `last_used_at` | `timestamp` | نعم |  |  |  |
| `expires_at` | `timestamp` | نعم | MUL |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `personal_access_tokens_expires_at_index` (expires_at) · `personal_access_tokens_token_unique` (token, فريد) · `personal_access_tokens_tokenable_type_tokenable_id_index` (tokenable_type,tokenable_id)

## report_assignments

**تاريخ الإسناد (موظف فعّال واحد)** — InnoDB · utf8mb4_unicode_ci · 695 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `report_id` | `bigint unsigned` | لا | MUL |  | → `reports.id` (CASCADE) |
| `employee_id` | `bigint unsigned` | لا | MUL |  | → `users.id` (RESTRICT) |
| `assigned_by` | `bigint unsigned` | نعم | MUL |  | → `users.id` (SET NULL) |
| `department_id` | `bigint unsigned` | لا | MUL |  | → `departments.id` (RESTRICT) |
| `method` | `enum('auto','manual','claim')` | لا |  | `auto` |  |
| `is_active` | `tinyint(1)` | لا |  | `1` |  |
| `notes` | `text` | نعم |  |  |  |
| `assigned_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |
| `completed_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `report_assignments_assigned_by_foreign` (assigned_by) · `report_assignments_department_id_foreign` (department_id) · `report_assignments_employee_id_is_active_index` (employee_id,is_active) · `report_assignments_report_id_is_active_index` (report_id,is_active)

## report_attachments

**صور وفيديو قبل المعالجة وبعدها** — InnoDB · utf8mb4_unicode_ci · 19 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `report_id` | `bigint unsigned` | لا | MUL |  | → `reports.id` (CASCADE) |
| `uploader_id` | `bigint unsigned` | لا | MUL |  | → `users.id` (RESTRICT) |
| `file_type` | `enum('image','video','document')` | لا |  |  |  |
| `stage` | `enum('before','after')` | لا |  | `before` |  |
| `file_path` | `varchar(500)` | لا |  |  |  |
| `file_name` | `varchar(255)` | لا |  |  |  |
| `file_size` | `int unsigned` | لا |  |  |  |
| `mime_type` | `varchar(100)` | لا |  |  |  |
| `thumbnail` | `varchar(500)` | نعم |  |  |  |
| `ai_tags` | `json` | نعم |  |  |  |
| `ai_severity` | `tinyint unsigned` | نعم |  |  |  |
| `sort_order` | `tinyint unsigned` | لا |  | `0` |  |
| `deleted_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `report_attachments_report_id_file_type_index` (report_id,file_type) · `report_attachments_uploader_id_foreign` (uploader_id)

## report_ratings

**تقييم المواطن (واحد لكل بلاغ)** — InnoDB · utf8mb4_unicode_ci · 278 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `report_id` | `bigint unsigned` | لا | UNI |  | → `reports.id` (CASCADE) |
| `citizen_id` | `bigint unsigned` | لا | MUL |  | → `users.id` (RESTRICT) |
| `employee_id` | `bigint unsigned` | نعم | MUL |  | → `users.id` (SET NULL) |
| `rating` | `tinyint unsigned` | لا | MUL |  |  |
| `feedback` | `text` | نعم |  |  |  |
| `rated_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `report_ratings_citizen_id_foreign` (citizen_id) · `report_ratings_employee_id_foreign` (employee_id) · `report_ratings_rating_index` (rating) · `report_ratings_report_id_unique` (report_id, فريد)

## report_updates

**السجل الزمني لكل حدث على البلاغ** — InnoDB · utf8mb4_unicode_ci · 4,840 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `report_id` | `bigint unsigned` | لا | MUL |  | → `reports.id` (CASCADE) |
| `user_id` | `bigint unsigned` | نعم | MUL |  | → `users.id` (SET NULL) |
| `type` | `enum('created','status_change','assignment','note','citizen_response','transfer','ai_update','rating','attachment')` | لا | MUL |  |  |
| `old_value` | `varchar(150)` | نعم |  |  |  |
| `new_value` | `varchar(150)` | نعم |  |  |  |
| `note` | `text` | نعم |  |  |  |
| `is_public` | `tinyint(1)` | لا |  | `0` |  |
| `created_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `report_updates_report_id_is_public_index` (report_id,is_public) · `report_updates_type_index` (type) · `report_updates_user_id_foreign` (user_id)

## report_upvotes

**تأييد المواطنين (واحد لكل مستخدم)** — InnoDB · utf8mb4_unicode_ci · 2,002 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `report_id` | `bigint unsigned` | لا | MUL |  | → `reports.id` (CASCADE) |
| `user_id` | `bigint unsigned` | لا | MUL |  | → `users.id` (CASCADE) |
| `created_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `report_upvotes_report_id_user_id_unique` (report_id,user_id, فريد) · `report_upvotes_user_id_foreign` (user_id)

## reports

**البلاغات — الكيان المركزي** — InnoDB · utf8mb4_unicode_ci · 708 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `char(36)` | لا | UNI |  |  |
| `ticket_number` | `varchar(30)` | لا | UNI |  |  |
| `citizen_id` | `bigint unsigned` | لا | MUL |  | → `users.id` (RESTRICT) |
| `category_id` | `bigint unsigned` | نعم | MUL |  | → `categories.id` (SET NULL) |
| `department_id` | `bigint unsigned` | نعم | MUL |  | → `departments.id` (SET NULL) |
| `district_id` | `bigint unsigned` | نعم | MUL |  | → `districts.id` (SET NULL) |
| `title` | `varchar(255)` | لا | MUL |  |  |
| `description` | `text` | لا |  |  |  |
| `status` | `enum('pending','under_review','assigned','in_progress','resolved','closed','rejected')` | لا | MUL | `pending` |  |
| `priority` | `enum('low','medium','high','critical')` | لا | MUL | `medium` |  |
| `latitude` | `decimal(10,8)` | لا | MUL |  |  |
| `longitude` | `decimal(11,8)` | لا |  |  |  |
| `address` | `varchar(500)` | نعم |  |  |  |
| `location_notes` | `varchar(255)` | نعم |  |  |  |
| `ai_category_id` | `bigint unsigned` | نعم | MUL |  | → `categories.id` (SET NULL) |
| `ai_department_id` | `bigint unsigned` | نعم | MUL |  | → `departments.id` (SET NULL) |
| `ai_priority` | `enum('low','medium','high','critical')` | نعم |  |  |  |
| `ai_confidence` | `decimal(5,4)` | نعم |  |  |  |
| `ai_summary` | `text` | نعم |  |  |  |
| `ai_is_duplicate` | `tinyint(1)` | لا |  | `0` |  |
| `ai_duplicate_of` | `bigint unsigned` | نعم | MUL |  | → `reports.id` (SET NULL) |
| `ai_duplicate_score` | `decimal(5,4)` | نعم |  |  |  |
| `ai_processed_at` | `timestamp` | نعم |  |  |  |
| `assigned_at` | `timestamp` | نعم |  |  |  |
| `started_at` | `timestamp` | نعم |  |  |  |
| `resolved_at` | `timestamp` | نعم |  |  |  |
| `closed_at` | `timestamp` | نعم |  |  |  |
| `expected_resolution` | `timestamp` | نعم | MUL |  |  |
| `overdue_notified_at` | `timestamp` | نعم |  |  |  |
| `is_anonymous` | `tinyint(1)` | لا |  | `0` |  |
| `is_featured` | `tinyint(1)` | لا |  | `0` |  |
| `views_count` | `int unsigned` | لا |  | `0` |  |
| `upvotes_count` | `int unsigned` | لا |  | `0` |  |
| `qr_code_path` | `varchar(500)` | نعم |  |  |  |
| `geofence_zone_ids` | `json` | نعم |  |  |  |
| `deleted_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | نعم | MUL |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `reports_ai_category_id_foreign` (ai_category_id) · `reports_ai_department_id_foreign` (ai_department_id) · `reports_ai_duplicate_of_foreign` (ai_duplicate_of) · `reports_category_id_foreign` (category_id) · `reports_citizen_id_foreign` (citizen_id) · `reports_created_at_index` (created_at) · `reports_department_id_foreign` (department_id) · `reports_district_id_status_index` (district_id,status) · `reports_expected_resolution_index` (expected_resolution) · `reports_latitude_longitude_index` (latitude,longitude) · `reports_priority_index` (priority) · `reports_status_department_id_created_at_index` (status,department_id,created_at) · `reports_status_index` (status) · `reports_ticket_number_unique` (ticket_number, فريد) · `reports_title_description_fulltext` (title,description, FULLTEXT) · `reports_uuid_unique` (uuid, فريد)

## role_has_permissions

**صلاحيات كل دور** — InnoDB · utf8mb4_unicode_ci · 30 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `permission_id` | `bigint unsigned` | لا | PRI |  | → `permissions.id` (CASCADE) |
| `role_id` | `bigint unsigned` | لا | PRI |  | → `roles.id` (CASCADE) |

**الفهارس:** `role_has_permissions_role_id_foreign` (role_id)

## roles

**الأدوار (Spatie)** — InnoDB · utf8mb4_unicode_ci · 4 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `name` | `varchar(255)` | لا | MUL |  |  |
| `guard_name` | `varchar(255)` | لا |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `roles_name_guard_name_unique` (name,guard_name, فريد)

## settings

**إعدادات النظام** — InnoDB · utf8mb4_unicode_ci · 9 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `key` | `varchar(100)` | لا | UNI |  |  |
| `value` | `json` | نعم |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `settings_key_unique` (key, فريد)

## users

**كل الحسابات: مواطن، موظف، مدير مدينة، مدير نظام** — InnoDB · utf8mb4_unicode_ci · 94 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `char(36)` | لا | UNI |  |  |
| `name` | `varchar(100)` | لا |  |  |  |
| `email` | `varchar(150)` | لا | UNI |  |  |
| `phone` | `varchar(20)` | نعم | UNI |  |  |
| `national_id` | `varchar(20)` | نعم | UNI |  |  |
| `user_type` | `enum('citizen','employee','manager','admin')` | لا | MUL | `citizen` |  |
| `avatar` | `varchar(500)` | نعم |  |  |  |
| `department_id` | `bigint unsigned` | نعم | MUL |  | → `departments.id` (SET NULL) |
| `district_id` | `bigint unsigned` | نعم | MUL |  | → `districts.id` (SET NULL) |
| `is_active` | `tinyint(1)` | لا | MUL | `1` |  |
| `is_verified` | `tinyint(1)` | لا |  | `0` |  |
| `citizen_fcm_token` | `varchar(500)` | نعم |  |  |  |
| `employee_fcm_token` | `varchar(500)` | نعم |  |  |  |
| `preferred_lang` | `enum('ar','en')` | لا |  | `ar` |  |
| `last_login_at` | `timestamp` | نعم |  |  |  |
| `email_verified_at` | `timestamp` | نعم |  |  |  |
| `password` | `varchar(255)` | لا |  |  |  |
| `remember_token` | `varchar(100)` | نعم |  |  |  |
| `deleted_at` | `timestamp` | نعم |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |
| `updated_at` | `timestamp` | نعم |  |  |  |

**الفهارس:** `users_department_id_index` (department_id) · `users_district_id_index` (district_id) · `users_email_unique` (email, فريد) · `users_is_active_index` (is_active) · `users_national_id_unique` (national_id, فريد) · `users_phone_unique` (phone, فريد) · `users_user_type_index` (user_type) · `users_uuid_unique` (uuid, فريد)

## cache

**الكاش (database store)** — InnoDB · utf8mb4_unicode_ci · 0 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `key` | `varchar(255)` | لا | PRI |  |  |
| `value` | `mediumtext` | لا |  |  |  |
| `expiration` | `int` | لا | MUL |  |  |

**الفهارس:** `cache_expiration_index` (expiration)

## cache_locks

**أقفال الكاش** — InnoDB · utf8mb4_unicode_ci · 0 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `key` | `varchar(255)` | لا | PRI |  |  |
| `owner` | `varchar(255)` | لا |  |  |  |
| `expiration` | `int` | لا | MUL |  |  |

**الفهارس:** `cache_locks_expiration_index` (expiration)

## failed_jobs

**المهام الفاشلة** — InnoDB · utf8mb4_unicode_ci · 0 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `uuid` | `varchar(255)` | لا | UNI |  |  |
| `connection` | `text` | لا |  |  |  |
| `queue` | `text` | لا |  |  |  |
| `payload` | `longtext` | لا |  |  |  |
| `exception` | `longtext` | لا |  |  |  |
| `failed_at` | `timestamp` | لا |  | `CURRENT_TIMESTAMP` | DEFAULT_GENERATED |

**الفهارس:** `failed_jobs_uuid_unique` (uuid, فريد)

## job_batches

**دفعات المهام** — InnoDB · utf8mb4_unicode_ci · 0 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `varchar(255)` | لا | PRI |  |  |
| `name` | `varchar(255)` | لا |  |  |  |
| `total_jobs` | `int` | لا |  |  |  |
| `pending_jobs` | `int` | لا |  |  |  |
| `failed_jobs` | `int` | لا |  |  |  |
| `failed_job_ids` | `longtext` | لا |  |  |  |
| `options` | `mediumtext` | نعم |  |  |  |
| `cancelled_at` | `int` | نعم |  |  |  |
| `created_at` | `int` | لا |  |  |  |
| `finished_at` | `int` | نعم |  |  |  |

## jobs

**الطابور (database queue)** — InnoDB · utf8mb4_unicode_ci · 0 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | لا | PRI |  | auto_increment |
| `queue` | `varchar(255)` | لا | MUL |  |  |
| `payload` | `longtext` | لا |  |  |  |
| `attempts` | `tinyint unsigned` | لا |  |  |  |
| `reserved_at` | `int unsigned` | نعم |  |  |  |
| `available_at` | `int unsigned` | لا |  |  |  |
| `created_at` | `int unsigned` | لا |  |  |  |

**الفهارس:** `jobs_queue_index` (queue)

## migrations

**سجل الترحيلات** — InnoDB · utf8mb4_unicode_ci · 26 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `int unsigned` | لا | PRI |  | auto_increment |
| `migration` | `varchar(255)` | لا |  |  |  |
| `batch` | `int` | لا |  |  |  |

## password_reset_tokens

**رموز استعادة كلمة المرور** — InnoDB · utf8mb4_unicode_ci · 0 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `email` | `varchar(255)` | لا | PRI |  |  |
| `token` | `varchar(255)` | لا |  |  |  |
| `created_at` | `timestamp` | نعم |  |  |  |

## sessions

**جلسات لوحة الإدارة** — InnoDB · utf8mb4_unicode_ci · 3 صفاً في بيانات العرض

| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |
|---|---|---|---|---|---|
| `id` | `varchar(255)` | لا | PRI |  |  |
| `user_id` | `bigint unsigned` | نعم | MUL |  |  |
| `ip_address` | `varchar(45)` | نعم |  |  |  |
| `user_agent` | `text` | نعم |  |  |  |
| `payload` | `longtext` | لا |  |  |  |
| `last_activity` | `int` | لا | MUL |  |  |

**الفهارس:** `sessions_last_activity_index` (last_activity) · `sessions_user_id_index` (user_id)

