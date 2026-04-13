# توثيق API Endpoints - FileUploadController

## 1. بدء عملية الرفع
**POST** `/api/files/upload/initiate`

**الطلب:**
```json
{
  "fileName": "document.pdf",
  "fileSize": 5242880,
  "fileType": "document",
  "mimeType": "application/pdf",
  "totalChunks": 5,
  "targetPath": "documents/",
  "sessionId": "uuid-optional"
}
```

**الرد (نجاح):**
```json
{
  "success": true,
  "data": {
    "fileId": 1,
    "sessionId": "uuid",
    "uploadUrl": "route-url",
    "expiresAt": "2026-02-28T12:00:00Z"
  }
}
```

---

## 2. رفع جزء من الملف
**POST** `/api/files/upload/chunk`

**الطلب (multipart/form-data):**
```
fileId: 1
chunkIndex: 0
chunk: [binary file data]
```

**الرد (جزء من الأجزاء):**
```json
{
  "success": true,
  "data": {
    "fileId": 1,
    "status": "uploading",
    "chunkIndex": 0,
    "uploadedChunks": 1,
    "totalChunks": 5,
    "completed": false
  }
}
```

**الرد (آخر جزء):**
```json
{
  "success": true,
  "data": {
    "fileId": 1,
    "status": "completed",
    "filePath": "path/to/file",
    "fileUrl": "url/to/file",
    "completed": true
  }
}
```

---

## 3. إنهاء عملية الرفع
**POST** `/api/files/upload/finalize`

**الطلب:**
```json
{
  "fileId": 1
}
```

**الرد:**
```json
{
  "success": true,
  "data": {
    "fileId": 1,
    "url": "url/to/file",
    "fileName": "document.pdf",
    "fileSize": 5242880,
    "fileType": "document",
    "uploadedAt": "2026-02-28T12:00:00Z"
  }
}
```

---

## 4. إلغاء عملية الرفع
**DELETE** `/api/files/upload/cancel/{fileId}`

**الرد:**
```json
{
  "success": true,
  "data": {
    "fileId": 1,
    "message": "Upload cancelled successfully",
    "chunksDeleted": 5
  }
}
```

---

## رموز الأخطاء الشائعة

| الكود | المعنى |
|------|--------|
| `VALIDATION_ERROR` | بيانات الطلب غير صحيحة |
| `INVALID_FILE_TYPE` | نوع الملف غير مسموح |
| `FILE_TOO_LARGE` | حجم الملف يتجاوز الحد المسموح |
| `DANGEROUS_FILE_TYPE` | نوع ملف خطير (امتداد مريب) |
| `FILE_NOT_FOUND` | الملف غير موجود أو لا توجد صلاحيات |
| `CHUNK_UPLOAD_FAILED` | فشل رفع الجزء |
| `ASSEMBLY_FAILED` | فشل دمج الأجزاء |
| `INCOMPLETE_UPLOAD` | لم يتم رفع جميع الأجزاء |
| `CANNOT_CANCEL_COMPLETED` | لا يمكن إلغاء ملف مكتمل |

---

## ملاحظات مهمة

- جميع الطلبات تتطلب مصادقة (Authentication)
- الملف يجب أن يكون مملوكاً للمستخدم الحالي
- جلسة الرفع تنتهي بعد 24 ساعة
- أنواع الملفات المسموحة: `image`, `video`, `audio`, `document`, `unclassified`
- يتم التحقق من الملفات الخطيرة (امتدادات مريبة) تلقائياً
