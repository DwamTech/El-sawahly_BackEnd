# توثيق API Endpoints - FileArchiveController

## 1. الحصول على قائمة الملفات المحفوظة
**GET** `/api/files/archive`

**الطلب:**
```
GET http://127.0.0.1:8000/api/files/archive
```

**المعاملات (Query Parameters):**
```
page: 1          // رقم الصفحة (اختياري)
per_page: 50     // عدد الملفات في الصفحة (اختياري)
```

**الرد:**
```json
{
  "success": true,
  "data": {
    "files": [
      {
        "id": 7,
        "file_name": "501_answers_1772275441_fYL35PRP.pdf",
        "original_name": "501+answers.pdf",
        "file_type": "document",
        "mime_type": "application/pdf",
        "file_size": 2442124,
        "file_path": "files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf",
        "file_url": "http://localhost:8000/storage/files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf",
        "status": "completed",
        "created_at": "2026-02-28T12:00:00Z",
        "updated_at": "2026-02-28T12:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 50,
      "total": 10,
      "last_page": 1,
      "from": 1,
      "to": 10
    }
  }
}
```

---

## 2. الحصول على تفاصيل ملف واحد
**GET** `/api/files/archive/{id}`

**الطلب:**
```
GET http://127.0.0.1:8000/api/files/archive/7
```

**الرد (نجاح):**
```json
{
  "success": true,
  "data": {
    "id": 7,
    "file_name": "501_answers_1772275441_fYL35PRP.pdf",
    "original_name": "501+answers.pdf",
    "file_type": "document",
    "mime_type": "application/pdf",
    "file_size": 2442124,
    "file_path": "files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf",
    "file_url": "http://localhost:8000/storage/files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf",
    "status": "completed",
    "created_at": "2026-02-28T12:00:00Z"
  }
}
```

**الرد (ملف غير موجود):**
```json
{
  "success": false,
  "error": {
    "code": "FILE_NOT_FOUND",
    "message": "File not found"
  }
}
```

**الرد (لا توجد صلاحيات):**
```json
{
  "success": false,
  "error": {
    "code": "UNAUTHORIZED_ACCESS",
    "message": "You do not have permission to access this file"
  }
}
```

---

## 3. البحث والتصفية في الملفات
**POST** `/api/files/archive/search`

**الطلب:**
```json
{
  "filename": "answers",
  "file_type": "document",
  "status": "completed",
  "date_from": "2026-02-01",
  "date_to": "2026-02-28",
  "sort_by": "date",
  "sort_order": "desc",
  "per_page": 20
}
```

**شرح المعاملات:**
- **filename:** اسم الملف (بحث جزئي - اختياري)
- **file_type:** نوع الملف: `image`, `video`, `audio`, `document`, `unclassified` (اختياري)
- **status:** حالة الملف: `pending`, `uploading`, `completed`, `failed` (اختياري)
- **date_from:** تاريخ البداية بصيغة YYYY-MM-DD (اختياري)
- **date_to:** تاريخ النهاية بصيغة YYYY-MM-DD (اختياري)
- **sort_by:** الترتيب حسب: `name`, `date`, `size`, `type` (اختياري، الافتراضي: date)
- **sort_order:** ترتيب تصاعدي أو تنازلي: `asc`, `desc` (اختياري، الافتراضي: desc)
- **per_page:** عدد النتائج في الصفحة (1-100، الافتراضي: 50)

**الرد:**
```json
{
  "success": true,
  "data": {
    "files": [
      {
        "id": 7,
        "file_name": "501_answers_1772275441_fYL35PRP.pdf",
        "original_name": "501+answers.pdf",
        "file_type": "document",
        "file_size": 2442124,
        "status": "completed",
        "created_at": "2026-02-28T12:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 5,
      "last_page": 1,
      "from": 1,
      "to": 5
    }
  }
}
```

---

## أمثلة عملية:

### مثال 1: البحث عن ملفات PDF
```json
{
  "filename": "pdf",
  "file_type": "document"
}
```

### مثال 2: الملفات المرفوعة في فبراير
```json
{
  "date_from": "2026-02-01",
  "date_to": "2026-02-28",
  "sort_by": "date",
  "sort_order": "desc"
}
```

### مثال 3: أكبر الملفات أولاً
```json
{
  "sort_by": "size",
  "sort_order": "desc",
  "per_page": 10
}
```

---

## ملاحظات مهمة:

- جميع الطلبات تتطلب مصادقة (Authentication)
- كل مستخدم يرى فقط ملفاته الخاصة
- البحث عن الاسم غير حساس لحالة الأحرف (case-insensitive)
- الترتيب الافتراضي: حسب التاريخ (الأحدث أولاً)
- الحد الأقصى للملفات في الصفحة: 100
