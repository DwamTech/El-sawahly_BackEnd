# توثيق Models - قاعدة البيانات

## 1. User (المستخدمين)
**الجدول:** `users`

**الخصائص:**
- `id` - معرف المستخدم
- `name` - الاسم
- `email` - البريد الإلكتروني
- `password` - كلمة المرور (مشفرة)
- `role` - الدور: `admin`, `editor`, `author`, `user`
- `email_verified_at` - تاريخ تأكيد البريد
- `remember_token` - رمز التذكر

**العلاقات:**
- لا توجد علاقات مباشرة (يستخدم في جميع الجداول الأخرى)

---

## 2. File (الملفات)
**الجدول:** `files`

**الخصائص:**
- `id` - معرف الملف
- `user_id` - معرف المستخدم
- `file_name` - اسم الملف المعالج
- `original_name` - الاسم الأصلي
- `file_type` - نوع الملف: `image`, `video`, `audio`, `document`, `unclassified`
- `mime_type` - نوع MIME
- `file_size` - حجم الملف (بايت)
- `file_path` - مسار الملف
- `file_url` - رابط الملف
- `status` - الحالة: `pending`, `uploading`, `completed`, `failed`
- `upload_session_id` - معرف جلسة الرفع
- `total_chunks` - عدد الأجزاء الكلي
- `uploaded_chunks` - عدد الأجزاء المرفوعة
- `metadata` - بيانات إضافية (JSON)

**العلاقات:**
- `user()` - ينتمي لمستخدم
- `chunks()` - له أجزاء متعددة
- `session()` - ينتمي لجلسة رفع

---

## 3. FileChunk (أجزاء الملفات)
**الجدول:** `file_chunks`

**الخصائص:**
- `id` - معرف الجزء
- `file_id` - معرف الملف
- `chunk_index` - رقم الجزء
- `chunk_path` - مسار الجزء
- `chunk_size` - حجم الجزء
- `uploaded_at` - تاريخ الرفع

**العلاقات:**
- `file()` - ينتمي لملف

---

## 4. UploadSession (جلسات الرفع)
**الجدول:** `upload_sessions`

**الخصائص:**
- `id` - معرف الجلسة
- `user_id` - معرف المستخدم
- `session_id` - معرف الجلسة (UUID)
- `status` - الحالة: `active`, `completed`, `expired`
- `total_files` - عدد الملفات الكلي
- `completed_files` - عدد الملفات المكتملة
- `failed_files` - عدد الملفات الفاشلة
- `expires_at` - تاريخ انتهاء الصلاحية

**العلاقات:**
- `user()` - ينتمي لمستخدم

---

## 5. Section (الأقسام)
**الجدول:** `sections`

**الخصائص:**
- `id` - معرف القسم
- `name` - اسم القسم
- `slug` - الرابط الصديق
- `description` - الوصف
- `is_active` - نشط أم لا
- `user_id` - معرف المستخدم

**العلاقات:**
- `user()` - ينتمي لمستخدم
- `articles()` - له مقالات متعددة

---

## 6. Article (المقالات)
**الجدول:** `articles`

**الخصائص:**
- `id` - معرف المقال
- `user_id` - معرف المستخدم
- `section_id` - معرف القسم
- `title` - العنوان
- `slug` - الرابط الصديق
- `excerpt` - المقتطف
- `content` - المحتوى
- `author_name` - اسم الكاتب
- `featured_image` - الصورة البارزة
- `gregorian_date` - التاريخ الميلادي
- `hijri_date` - التاريخ الهجري
- `references` - المراجع
- `keywords` - الكلمات المفتاحية
- `status` - الحالة: `draft`, `published`, `scheduled`
- `published_at` - تاريخ النشر
- `views_count` - عدد المشاهدات

**العلاقات:**
- `section()` - ينتمي لقسم

---

## 7. Issue (الأعداد)
**الجدول:** `issues`

**الخصائص:**
- `id` - معرف العدد
- `title` - العنوان
- `slug` - الرابط الصديق
- `issue_number` - رقم العدد
- `cover_image` - صورة الغلاف
- `cover_image_alt` - صورة الغلاف البديلة
- `pdf_file` - ملف PDF
- `hijri_date` - التاريخ الهجري
- `gregorian_date` - التاريخ الميلادي
- `views_count` - عدد المشاهدات
- `status` - الحالة
- `published_at` - تاريخ النشر
- `is_featured` - مميز أم لا
- `sort_order` - ترتيب العرض
- `user_id` - معرف المستخدم

**العلاقات:**
- `user()` - ينتمي لمستخدم

---

## 8. Visual (المرئيات)
**الجدول:** `visuals`

**الخصائص:**
- `id` - معرف المرئي
- `section_id` - معرف القسم
- `user_id` - معرف المستخدم
- `title` - العنوان
- `description` - الوصف
- `type` - النوع: `upload`, `link`
- `file_path` - مسار الملف
- `url` - الرابط
- `thumbnail` - الصورة المصغرة
- `keywords` - الكلمات المفتاحية
- `views_count` - عدد المشاهدات
- `rating` - التقييم

**العلاقات:**
- `section()` - ينتمي لقسم
- `user()` - ينتمي لمستخدم

---

## 9. Audio (الصوتيات)
**الجدول:** `audios`

**الخصائص:**
- `id` - معرف الصوتي
- `section_id` - معرف القسم
- `user_id` - معرف المستخدم
- `title` - العنوان
- `description` - الوصف
- `type` - النوع: `upload`, `link`
- `file_path` - مسار الملف
- `url` - الرابط
- `thumbnail` - الصورة المصغرة
- `keywords` - الكلمات المفتاحية
- `views_count` - عدد المشاهدات
- `rating` - التقييم

**الخصائص المحسوبة:**
- `embed_url` - رابط التضمين (YouTube)

**العلاقات:**
- `section()` - ينتمي لقسم
- `user()` - ينتمي لمستخدم

---

## 10. Gallery (المعارض)
**الجدول:** `galleries`

**الخصائص:**
- `id` - معرف المعرض
- `section_id` - معرف القسم
- `user_id` - معرف المستخدم
- `name` - الاسم
- `description` - الوصف
- `cover_image` - صورة الغلاف
- `keywords` - الكلمات المفتاحية
- `views_count` - عدد المشاهدات
- `rating` - التقييم

**العلاقات:**
- `section()` - ينتمي لقسم
- `user()` - ينتمي لمستخدم
- `images()` - له صور متعددة

---

## 11. GalleryImage (صور المعرض)
**الجدول:** `gallery_images`

**الخصائص:**
- `id` - معرف الصورة
- `gallery_id` - معرف المعرض
- `image_path` - مسار الصورة
- `sort_order` - ترتيب العرض

**العلاقات:**
- `gallery()` - ينتمي لمعرض

---

## 12. Link (الروابط)
**الجدول:** `links`

**الخصائص:**
- `id` - معرف الرابط
- `section_id` - معرف القسم
- `user_id` - معرف المستخدم
- `title` - العنوان
- `description` - الوصف
- `url` - الرابط
- `image_path` - مسار الصورة
- `keywords` - الكلمات المفتاحية
- `views_count` - عدد المشاهدات
- `rating` - التقييم

**العلاقات:**
- `section()` - ينتمي لقسم
- `user()` - ينتمي لمستخدم

---

## 13. Book (الكتب)
**الجدول:** `books`

**الخصائص:**
- `id` - معرف الكتاب
- `title` - العنوان
- `description` - الوصف
- `source_type` - نوع المصدر: `upload`, `link`
- `file_path` - مسار الملف
- `source_link` - رابط المصدر
- `cover_type` - نوع الغلاف: `upload`, `link`
- `cover_path` - مسار الغلاف
- `keywords` - الكلمات المفتاحية (JSON)
- `views_count` - عدد المشاهدات
- `rating_sum` - مجموع التقييمات
- `rating_count` - عدد التقييمات
- `author_name` - اسم المؤلف
- `type` - النوع
- `book_series_id` - معرف السلسلة
- `section_id` - معرف القسم

**الخصائص المحسوبة:**
- `average_rating` - متوسط التقييم

**العلاقات:**
- `series()` - ينتمي لسلسلة
- `section()` - ينتمي لقسم

---

## 14. BookSeries (سلاسل الكتب)
**الجدول:** `book_series`

**الخصائص:**
- `id` - معرف السلسلة
- `name` - الاسم
- `description` - الوصف

**العلاقات:**
- `books()` - له كتب متعددة

---

## 15. Document (المستندات)
**الجدول:** `documents`

**الخصائص:**
- `id` - معرف المستند
- `title` - العنوان
- `description` - الوصف
- `source_type` - نوع المصدر: `upload`, `link`
- `file_path` - مسار الملف
- `source_link` - رابط المصدر
- `cover_type` - نوع الغلاف: `upload`, `link`
- `cover_path` - مسار الغلاف
- `keywords` - الكلمات المفتاحية (JSON)
- `file_type` - نوع الملف
- `file_size` - حجم الملف
- `views_count` - عدد المشاهدات
- `downloads_count` - عدد التحميلات
- `user_id` - معرف المستخدم
- `section_id` - معرف القسم

**العلاقات:**
- `user()` - ينتمي لمستخدم
- `section()` - ينتمي لقسم

---

## 16. IndividualSupportRequest (طلبات الدعم الفردي)
**الجدول:** `individual_support_requests`

**الخصائص:**
- `id` - معرف الطلب
- `request_number` - رقم الطلب
- `full_name` - الاسم الكامل
- `gender` - الجنس
- `nationality` - الجنسية
- `city` - المدينة
- `housing_type` - نوع السكن
- `housing_type_other` - نوع السكن (أخرى)
- `identity_image_path` - مسار صورة الهوية
- `birth_date` - تاريخ الميلاد
- `identity_expiry_date` - تاريخ انتهاء الهوية
- `phone_number` - رقم الهاتف
- `whatsapp_number` - رقم الواتساب
- `email` - البريد الإلكتروني
- `academic_qualification_path` - مسار المؤهل الأكاديمي
- `scientific_activity` - النشاط العلمي
- `scientific_activity_other` - النشاط العلمي (أخرى)
- `cv_path` - مسار السيرة الذاتية
- `workplace` - مكان العمل
- `support_scope` - نطاق الدعم
- `amount_requested` - المبلغ المطلوب
- `support_type` - نوع الدعم
- `support_type_other` - نوع الدعم (أخرى)
- `has_income` - لديه دخل
- `income_source` - مصدر الدخل
- `marital_status` - الحالة الاجتماعية
- `family_members_count` - عدد أفراد الأسرة
- `recommendation_path` - مسار التوصية
- `bank_account_iban` - رقم الآيبان
- `bank_name` - اسم البنك
- `status` - الحالة
- `rejection_reason` - سبب الرفض

---

## 17. InstitutionalSupportRequest (طلبات الدعم المؤسسي)
**الجدول:** `institutional_support_requests`

**الخصائص:**
- `id` - معرف الطلب
- `request_number` - رقم الطلب
- `institution_name` - اسم المؤسسة
- `license_number` - رقم الترخيص
- `license_certificate_path` - مسار شهادة الترخيص
- `email` - البريد الإلكتروني
- `support_letter_path` - مسار خطاب الدعم
- `phone_number` - رقم الهاتف
- `ceo_name` - اسم المدير التنفيذي
- `ceo_mobile` - جوال المدير التنفيذي
- `whatsapp_number` - رقم الواتساب
- `city` - المدينة
- `activity_type` - نوع النشاط
- `activity_type_other` - نوع النشاط (أخرى)
- `project_name` - اسم المشروع
- `project_type` - نوع المشروع
- `project_type_other` - نوع المشروع (أخرى)
- `project_file_path` - مسار ملف المشروع
- `project_manager_name` - اسم مدير المشروع
- `project_manager_mobile` - جوال مدير المشروع
- `goal_1` - الهدف 1
- `goal_2` - الهدف 2
- `goal_3` - الهدف 3
- `goal_4` - الهدف 4
- `other_goals` - أهداف أخرى
- `beneficiaries` - المستفيدون
- `beneficiaries_other` - المستفيدون (أخرى)
- `project_cost` - تكلفة المشروع
- `project_outputs` - مخرجات المشروع
- `operational_plan_path` - مسار الخطة التشغيلية
- `support_scope` - نطاق الدعم
- `amount_requested` - المبلغ المطلوب
- `account_name` - اسم الحساب
- `bank_account_iban` - رقم الآيبان
- `bank_name` - اسم البنك
- `bank_certificate_path` - مسار شهادة البنك
- `status` - الحالة
- `rejection_reason` - سبب الرفض

---

## 18. Feedback (الملاحظات)
**الجدول:** `feedback`

**الخصائص:**
- `id` - معرف الملاحظة
- `name` - الاسم
- `email` - البريد الإلكتروني
- `phone_number` - رقم الهاتف
- `message` - الرسالة
- `attachment_path` - مسار المرفق
- `type` - النوع

---

## 19. PlatformRating (تقييم المنصة)
**الجدول:** `platform_ratings`

**الخصائص:**
- `id` - معرف التقييم
- `rating` - التقييم (1-5)
- `ip_address` - عنوان IP
- `user_agent` - معلومات المتصفح

---

## 20. SiteContact (معلومات الاتصال)
**الجدول:** `site_contacts`

**الخصائص:**
- `id` - معرف السجل (دائماً 1)
- `youtube` - رابط يوتيوب
- `twitter` - رابط تويتر
- `facebook` - رابط فيسبوك
- `snapchat` - رابط سناب شات
- `instagram` - رابط إنستغرام
- `tiktok` - رابط تيك توك
- `support_phone` - هاتف الدعم
- `management_phone` - هاتف الإدارة
- `backup_phone` - هاتف احتياطي
- `address` - العنوان
- `commercial_register` - السجل التجاري
- `email` - البريد الإلكتروني

**ملاحظة:** هذا الجدول يحتوي على سجل واحد فقط (Singleton)

---

## 21. SupportSetting (إعدادات الدعم)
**الجدول:** `support_settings`

**الخصائص:**
- `id` - معرف الإعداد
- `key` - المفتاح
- `value` - القيمة

---

## 22. SystemContent (محتوى النظام)
**الجدول:** `system_contents`

**الخصائص:**
- `id` - معرف المحتوى
- `key` - المفتاح
- `content` - المحتوى

---

## 23. AdminNotification (إشعارات الإدارة)
**الجدول:** `admin_notifications`

**الخصائص:**
- `id` - معرف الإشعار
- `type` - النوع
- `category` - الفئة: `support`, `system`, `users`, `content`, `engagement`, `library`
- `title` - العنوان
- `message` - الرسالة
- `body` - المحتوى
- `data` - بيانات إضافية (JSON)
- `priority` - الأولوية: `low`, `medium`, `high`
- `is_read` - مقروء أم لا
- `read_at` - تاريخ القراءة
- `action_url` - رابط الإجراء
- `triggered_by` - معرف المستخدم المسبب

**الخصائص المحسوبة:**
- `icon` - أيقونة حسب الفئة
- `color` - لون حسب الفئة
- `priority_color` - لون حسب الأولوية

**العلاقات:**
- `triggeredBy()` - ينتمي لمستخدم

---

## 24. BackupHistory (سجل النسخ الاحتياطي)
**الجدول:** `backup_histories`

**الخصائص:**
- `id` - معرف السجل
- `type` - النوع
- `status` - الحالة
- `file_name` - اسم الملف
- `file_size` - حجم الملف
- `message` - الرسالة
- `user_id` - معرف المستخدم

---

## 25. DailyVisit (الزيارات اليومية)
**الجدول:** `daily_visits`

**الخصائص:**
- `id` - معرف السجل
- `visit_date` - تاريخ الزيارة
- `views_count` - عدد المشاهدات
- `unique_visitors` - عدد الزوار الفريدين
- `platform` - المنصة

---



