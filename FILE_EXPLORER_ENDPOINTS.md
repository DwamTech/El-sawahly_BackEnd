# توثيق API Endpoints - FileExplorerController

## 1. تصفح محتويات المجلد
**GET** `/api/files/explorer/browse`

**الطلب:**
```
GET http://127.0.0.1:8000/api/files/explorer/browse?path=files/2026/02
```

**المعاملات (Query Parameters):**
```
path: files/2026/02    // المسار المراد تصفحه (اختياري، الافتراضي: الجذر)
```

**الرد:**
```json
{
  "success": true,
  "data": {
    "current_path": "files/2026/02",
    "parent_path": "files/2026",
    "directories": [
      {
        "name": "9",
        "path": "files/2026/02/9",
        "type": "directory",
        "size": null,
        "modified_at": 1740696000
      }
    ],
    "files": [
      {
        "name": "501_answers_1772275441_fYL35PRP.pdf",
        "path": "files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf",
        "type": "file",
        "size": 2442124,
        "mime_type": "application/pdf",
        "extension": "pdf",
        "url": "http://localhost:8000/storage/files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf",
        "modified_at": 1740696000
      }
    ],
    "total_items": 2
  }
}
```

**الرد (مسار غير موجود):**
```json
{
  "success": false,
  "error": {
    "code": "PATH_NOT_FOUND",
    "message": "المسار غير موجود"
  }
}
```

---

## 2. إعادة تسمية ملف أو مجلد
**POST** `/api/files/explorer/rename`

**الطلب:**
```json
{
  "path": "files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf",
  "new_name": "answers_updated.pdf"
}
```

**شرح المعاملات:**
- **path:** المسار الكامل للملف أو المجلد (مطلوب)
- **new_name:** الاسم الجديد (مطلوب، بحد أقصى 255 حرف)

**الرد (نجاح):**
```json
{
  "success": true,
  "data": {
    "old_path": "files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf",
    "new_path": "files/2026/02/9/answers_updated.pdf",
    "message": "تم إعادة التسمية بنجاح"
  }
}
```

**الرد (الاسم موجود بالفعل):**
```json
{
  "success": false,
  "error": {
    "code": "NAME_EXISTS",
    "message": "الاسم موجود بالفعل"
  }
}
```

---

## 3. حذف ملف أو مجلد
**DELETE** `/api/files/explorer/delete`

**الطلب:**
```json
{
  "path": "files/2026/02/9/answers_updated.pdf"
}
```

**شرح المعاملات:**
- **path:** المسار الكامل للملف أو المجلد (مطلوب)

**الرد (نجاح):**
```json
{
  "success": true,
  "data": {
    "message": "تم الحذف بنجاح"
  }
}
```

**الرد (ملف غير موجود):**
```json
{
  "success": false,
  "error": {
    "code": "FILE_NOT_FOUND",
    "message": "الملف غير موجود"
  }
}
```

**ملاحظة:** إذا كان المسار مجلداً، سيتم حذف المجلد وجميع محتوياته.

---

## 4. إنشاء مجلد جديد
**POST** `/api/files/explorer/create-folder`

**الطلب:**
```json
{
  "path": "files/2026/02",
  "name": "new_folder"
}
```

**شرح المعاملات:**
- **path:** المسار الأب (المجلد الذي سيتم إنشاء المجلد الجديد فيه) (مطلوب)
- **name:** اسم المجلد الجديد (مطلوب، بحد أقصى 255 حرف)

**الرد (نجاح):**
```json
{
  "success": true,
  "data": {
    "path": "files/2026/02/new_folder",
    "message": "تم إنشاء المجلد بنجاح"
  }
}
```

**الرد (المجلد موجود بالفعل):**
```json
{
  "success": false,
  "error": {
    "code": "FOLDER_EXISTS",
    "message": "المجلد موجود بالفعل"
  }
}
```

---

## 5. تحميل ملف
**GET** `/api/files/explorer/download`

**الطلب:**
```
GET http://127.0.0.1:8000/api/files/explorer/download?path=files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf
```

**المعاملات (Query Parameters):**
```
path: files/2026/02/9/501_answers_1772275441_fYL35PRP.pdf    // المسار الكامل للملف (مطلوب)
```

**الرد (نجاح):**
- يتم تحميل الملف مباشرة (binary file download)

**الرد (ملف غير موجود):**
```json
{
  "success": false,
  "error": {
    "code": "FILE_NOT_FOUND",
    "message": "الملف غير موجود"
  }
}
```

**الرد (محاولة تحميل مجلد):**
```json
{
  "success": false,
  "error": {
    "code": "INVALID_FILE",
    "message": "لا يمكن تحميل مجلد"
  }
}
```

---

## أمثلة عملية:

### مثال 1: تصفح الجذر
```
GET /api/files/explorer/browse
```

### مثال 2: تصفح مجلد معين
```
GET /api/files/explorer/browse?path=files/2026/02/9
```

### مثال 3: إنشاء مجلد في الجذر
```json
POST /api/files/explorer/create-folder
{
  "path": "",
  "name": "documents"
}
```

### مثال 4: إعادة تسمية مجلد
```json
POST /api/files/explorer/rename
{
  "path": "documents",
  "new_name": "my_documents"
}
```

### مثال 5: حذف مجلد كامل
```json
DELETE /api/files/explorer/delete
{
  "path": "my_documents"
}
```

---

## ملاحظات مهمة:

- جميع الطلبات تتطلب مصادقة (Authentication)
- المسارات يتم تنظيفها تلقائياً لمنع هجمات directory traversal
- أسماء الملفات والمجلدات تدعم العربية والإنجليزية والأرقام والرموز الشائعة
- عند حذف مجلد، يتم حذف جميع محتوياته تلقائياً
- الملفات والمجلدات يتم ترتيبها أبجدياً في النتائج
- حجم المجلد يكون `null` (يتم حساب حجم الملفات فقط)
