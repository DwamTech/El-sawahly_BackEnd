# تحليل تقني شامل لميزة إدارة الملفات (File Management Feature)

## نظرة عامة (Overview)

نظام إدارة ملفات متكامل مبني على Laravel يوفر ثلاث وظائف رئيسية:
1. **File Upload System** - نظام رفع الملفات بتقنية Chunked Upload
2. **File Explorer** - متصفح ملفات لإدارة الملفات والمجلدات
3. **File Archive** - أرشيف الملفات مع البحث والفلترة

---

## البنية المعمارية (Architecture)

### 1. النمط المعماري (Architectural Pattern)
- **Service-Oriented Architecture (SOA)**
- **Repository Pattern** عبر Eloquent Models
- **Separation of Concerns** - فصل المنطق إلى Controllers, Services, Models

### 2. المكونات الرئيسية (Core Components)

```
Controllers (HTTP Layer)
├── FileUploadController      → رفع الملفات
├── FileExplorerController    → تصفح وإدارة الملفات
└── FileArchiveController     → أرشيف وبحث الملفات

Services (Business Logic)
├── ChunkProcessorService     → معالجة القطع (Chunks)
├── FileValidationService     → التحقق من الملفات
└── StorageHandlerService     → إدارة التخزين

Models (Data Layer)
├── File                      → بيانات الملف
├── FileChunk                 → قطع الملف المؤقتة
└── UploadSession             → جلسة الرفع
```

---

## الميزة الأولى: نظام رفع الملفات (File Upload System)

### المفهوم الأساسي

نظام رفع ملفات متقدم يستخدم تقنية **Chunked Upload** لرفع الملفات الكبيرة بشكل موثوق وآمن.

### آلية العمل (Workflow)

```
1. Initiate Upload
   ↓
2. Upload Chunks (متعدد ومتوازي)
   ↓
3. Finalize Upload (تجميع القطع)
   ↓
4. File Ready
```

### Endpoints

#### 1. Initiate Upload
```
POST /api/files/upload/initiate
```

**Request Body:**
```json
{
  "fileName": "document.pdf",
  "fileSize": 10485760,
  "fileType": "document",
  "mimeType": "application/pdf",
  "totalChunks": 5,
  "targetPath": "documents/2024" // اختياري
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "fileId": 123,
    "sessionId": "uuid-string",
    "uploadUrl": "https://api.example.com/files/upload/chunk",
    "expiresAt": "2024-02-26T12:00:00Z"
  }
}
```

**الوظيفة:**
- إنشاء سجل File في قاعدة البيانات بحالة `pending`
- إنشاء أو استخدام UploadSession موجودة
- التحقق من نوع الملف وحجمه
- منع الملفات الخطرة (exe, bat, sh, etc.)
- تنظيف اسم الملف من الأحرف الخاصة

**معايير الأمان:**
- Whitelist للـ MIME types المسموحة
- Blacklist للامتدادات الخطرة
- فحص Double Extensions (file.pdf.exe)
- حد أقصى لحجم الملف (100MB افتراضي)
- Rate Limiting على endpoint



#### 2. Upload Chunk
```
POST /api/files/upload/chunk
```

**Request (multipart/form-data):**
```
fileId: 123
chunkIndex: 0
totalChunks: 5
chunk: [binary data]
```

**Response:**
```json
{
  "success": true,
  "data": {
    "chunkId": 456,
    "chunkIndex": 0,
    "uploadedChunks": 1,
    "totalChunks": 5,
    "progress": 20.0
  }
}
```

**الوظيفة:**
- تخزين القطعة مؤقتاً في `storage/app/temp/chunks/{fileId}/chunk_{index}`
- إنشاء سجل FileChunk في قاعدة البيانات
- تحديث عداد `uploaded_chunks` في جدول files
- تغيير حالة الملف إلى `uploading` عند أول قطعة
- منع رفع نفس القطعة مرتين

**معايير الموثوقية:**
- التحقق من ملكية المستخدم للملف
- التحقق من تطابق totalChunks
- التحقق من صحة chunkIndex (0 إلى totalChunks-1)
- Rate Limiting مخصص للـ chunks

#### 3. Finalize Upload
```
POST /api/files/upload/finalize
```

**Request Body:**
```json
{
  "fileId": 123
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "fileId": 123,
    "url": "https://storage.example.com/files/2024/02/1/document_1234567890_abc123.pdf",
    "fileName": "document.pdf",
    "fileSize": 10485760,
    "fileType": "document",
    "uploadedAt": "2024-02-25T10:30:00Z"
  }
}
```

**الوظيفة:**
1. التحقق من اكتمال جميع القطع
2. تجميع القطع في ملف واحد
3. التحقق من حجم الملف النهائي
4. نقل الملف إلى التخزين الدائم
5. تحديث سجل File بالمسار والرابط
6. حذف القطع المؤقتة
7. تحديث إحصائيات UploadSession

**آلية التجميع:**
```php
// Pseudo-code
foreach (chunks ordered by index) {
    read chunk file
    append to final file
}
verify final file size
move to permanent storage
cleanup temp chunks
```



#### 4. Cancel Upload
```
DELETE /api/files/upload/cancel/{fileId}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "fileId": 123,
    "message": "Upload cancelled successfully",
    "chunksDeleted": 3
  }
}
```

**الوظيفة:**
- حذف جميع القطع المؤقتة
- تحديث حالة الملف إلى `failed`
- تحديث إحصائيات UploadSession
- لا يمكن إلغاء ملف مكتمل

---

## الميزة الثانية: متصفح الملفات (File Explorer)

### المفهوم الأساسي
واجهة لتصفح وإدارة الملفات والمجلدات في التخزين العام (public storage).

### Endpoints

#### 1. Browse Directory
```
GET /api/files/explorer/browse?path=documents/2024
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_path": "documents/2024",
    "parent_path": "documents",
    "directories": [
      {
        "name": "january",
        "path": "documents/2024/january",
        "type": "directory",
        "size": null,
        "modified_at": 1708876800
      }
    ],
    "files": [
      {
        "name": "report.pdf",
        "path": "documents/2024/report.pdf",
        "type": "file",
        "size": 1048576,
        "mime_type": "application/pdf",
        "url": "https://storage.example.com/documents/2024/report.pdf",
        "modified_at": 1708876800
      }
    ],
    "total_items": 2
  }
}
```

**الوظيفة:**
- عرض محتويات المجلد
- فصل المجلدات عن الملفات
- ترتيب أبجدي
- حساب المسار الأب (parent path)
- توليد روابط الملفات

**معايير الأمان:**
- Sanitization للمسار لمنع Directory Traversal
- إزالة `../` و `./`
- منع الوصول خارج public storage



#### 2. Rename File/Folder
```
POST /api/files/explorer/rename
```

**Request Body:**
```json
{
  "path": "documents/old_name.pdf",
  "new_name": "new_name.pdf"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "old_path": "documents/old_name.pdf",
    "new_path": "documents/new_name.pdf",
    "message": "تم إعادة التسمية بنجاح"
  }
}
```

**الوظيفة:**
- إعادة تسمية ملف أو مجلد
- التحقق من عدم وجود اسم مكرر
- تنظيف الاسم الجديد من الأحرف الخاصة
- دعم الأحرف العربية والإنجليزية

#### 3. Delete File/Folder
```
DELETE /api/files/explorer/delete
```

**Request Body:**
```json
{
  "path": "documents/file.pdf"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "تم الحذف بنجاح"
  }
}
```

**الوظيفة:**
- حذف ملف أو مجلد كامل
- حذف تلقائي للمحتويات الداخلية للمجلد

#### 4. Create Folder
```
POST /api/files/explorer/create-folder
```

**Request Body:**
```json
{
  "path": "documents",
  "name": "new_folder"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "path": "documents/new_folder",
    "message": "تم إنشاء المجلد بنجاح"
  }
}
```

**الوظيفة:**
- إنشاء مجلد جديد
- التحقق من عدم وجود مجلد بنفس الاسم
- تنظيف اسم المجلد

#### 5. Download File
```
GET /api/files/explorer/download?path=documents/file.pdf
```

**Response:**
- Binary file download
- Headers: Content-Disposition, Content-Type

**الوظيفة:**
- تحميل الملف مباشرة
- منع تحميل المجلدات
- التحقق من وجود الملف



---

## الميزة الثالثة: أرشيف الملفات (File Archive)

### المفهوم الأساسي
نظام لعرض وإدارة الملفات المرفوعة من قبل المستخدم مع إمكانيات بحث وفلترة متقدمة.

### Endpoints

#### 1. List Files
```
GET /api/files/archive?page=1&per_page=20
```

**Response:**
```json
{
  "success": true,
  "data": {
    "files": [
      {
        "id": 123,
        "user_id": 1,
        "file_name": "document_1234567890_abc123.pdf",
        "original_name": "document.pdf",
        "file_type": "document",
        "mime_type": "application/pdf",
        "file_size": 10485760,
        "file_path": "files/2024/02/1/document_1234567890_abc123.pdf",
        "file_url": "https://storage.example.com/files/2024/02/1/document_1234567890_abc123.pdf",
        "status": "completed",
        "created_at": "2024-02-25T10:30:00Z",
        "updated_at": "2024-02-25T10:35:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 150,
      "last_page": 8,
      "from": 1,
      "to": 20
    }
  }
}
```

**الوظيفة:**
- عرض ملفات المستخدم المصادق عليه فقط
- ترتيب حسب تاريخ الإنشاء (الأحدث أولاً)
- Pagination بحد أقصى 50 عنصر
- Eager Loading للعلاقات

#### 2. Get Single File
```
GET /api/files/archive/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "file_name": "document.pdf",
    "file_url": "https://...",
    // ... باقي البيانات
  }
}
```

**الوظيفة:**
- عرض تفاصيل ملف واحد
- التحقق من ملكية المستخدم
- رفض الوصول غير المصرح (403)



#### 3. Search & Filter Files
```
POST /api/files/archive/search
```

**Request Body:**
```json
{
  "filename": "report",
  "file_type": "document",
  "status": "completed",
  "date_from": "2024-01-01",
  "date_to": "2024-02-25",
  "sort_by": "date",
  "sort_order": "desc",
  "per_page": 20
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "files": [...],
    "pagination": {...}
  }
}
```

**معايير البحث:**

| Parameter | Type | Values | Description |
|-----------|------|--------|-------------|
| filename | string | any | بحث جزئي في اسم الملف (LIKE) |
| file_type | enum | image, video, audio, document, unclassified | نوع الملف |
| status | enum | pending, uploading, completed, failed | حالة الرفع |
| date_from | date | YYYY-MM-DD | من تاريخ |
| date_to | date | YYYY-MM-DD | إلى تاريخ |
| sort_by | enum | name, date, size, type | ترتيب حسب |
| sort_order | enum | asc, desc | اتجاه الترتيب |
| per_page | integer | 1-100 | عدد النتائج |

**الوظيفة:**
- بحث متقدم مع فلاتر متعددة
- دعم البحث الجزئي (partial match)
- ترتيب ديناميكي
- Pagination مرن

---

## قاعدة البيانات (Database Schema)

### جدول files

```sql
CREATE TABLE files (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type ENUM('image', 'video', 'audio', 'document', 'unclassified'),
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    file_path TEXT NOT NULL,
    file_url TEXT NOT NULL,
    status ENUM('pending', 'uploading', 'completed', 'failed') DEFAULT 'pending',
    upload_session_id VARCHAR(36) NULL,
    total_chunks INT UNSIGNED DEFAULT 1,
    uploaded_chunks INT UNSIGNED DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_file_type (file_type),
    INDEX idx_status (status),
    INDEX idx_upload_session_id (upload_session_id),
    INDEX idx_created_at (created_at)
);
```



### جدول file_chunks

```sql
CREATE TABLE file_chunks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    file_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    chunk_path TEXT NOT NULL,
    chunk_size INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL,
    
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY unique_file_chunk (file_id, chunk_index),
    INDEX idx_file_id (file_id)
);
```

**ملاحظات:**
- لا يستخدم timestamps تلقائية
- UNIQUE constraint على (file_id, chunk_index) لمنع التكرار
- يتم حذف السجلات بعد تجميع الملف

### جدول upload_sessions

```sql
CREATE TABLE upload_sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(36) UNIQUE NOT NULL,
    status ENUM('active', 'completed', 'expired', 'cancelled') DEFAULT 'active',
    total_files INT UNSIGNED DEFAULT 0,
    completed_files INT UNSIGNED DEFAULT 0,
    failed_files INT UNSIGNED DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id),
    INDEX idx_expires_at (expires_at)
);
```

**الغرض:**
- تجميع عدة ملفات في جلسة واحدة
- تتبع إحصائيات الرفع
- انتهاء صلاحية بعد 24 ساعة

---

## الخدمات (Services)

### 1. ChunkProcessorService

**المسؤوليات:**
- تخزين القطع المؤقتة
- التحقق من تسلسل القطع
- تجميع القطع في ملف واحد
- تنظيف القطع المؤقتة

**الدوال الرئيسية:**

```php
// تخزين قطعة
storeChunk(UploadedFile $chunk, int $fileId, int $chunkIndex): array

// التحقق من اكتمال القطع
validateChunkSequence(int $fileId): array

// تجميع القطع
assembleChunks(int $fileId): array

// حذف القطع المؤقتة
cleanupChunks(int $fileId): array
```

**آلية التخزين المؤقت:**
```
storage/app/temp/chunks/{fileId}/chunk_{index}
```



### 2. FileValidationService

**المسؤوليات:**
- التحقق من نوع الملف
- التحقق من حجم الملف
- التحقق من اسم الملف
- كشف الملفات الخطرة

**الدوال الرئيسية:**

```php
// التحقق من النوع
validateFileType(UploadedFile $file): array

// التحقق من الحجم
validateFileSize(UploadedFile $file): array

// التحقق من الاسم
validateFileName(UploadedFile $file): array

// كشف الملفات الخطرة
detectDangerousFile(UploadedFile $file): array

// التحقق الشامل
validate(UploadedFile $file): array
```

**MIME Types المسموحة:**
```php
// Images
'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'

// Videos
'video/mp4', 'video/mpeg', 'video/quicktime', 'video/webm'

// Audio
'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg'

// Documents
'application/pdf', 'application/msword',
'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
'application/vnd.ms-excel',
'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
'text/plain', 'text/csv'
```

**الامتدادات المحظورة:**
```php
'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar',
'sh', 'app', 'deb', 'rpm', 'dmg', 'pkg', 'run', 'bin',
'ps1', 'psm1', 'psd1', 'ps1xml', 'psc1', 'msi', 'gadget'
```

### 3. StorageHandlerService

**المسؤوليات:**
- تخزين الملفات في Local أو S3
- توليد روابط الملفات
- تنظيم مسارات الملفات
- حذف الملفات

**الدوال الرئيسية:**

```php
// تخزين محلي
storeFile(UploadedFile $file, int $userId, ?string $customFilename, ?string $targetPath): array

// تخزين S3
storeFileToS3(UploadedFile $file, int $userId, ?string $customFilename): array

// توليد الرابط
generateFileUrl(string $path, string $storageType): string

// تنظيم المسار
organizeFilePath(int $userId, string $filename, ?string $targetPath): string

// حذف الملف
deleteFile(string $path, ?string $storageType): array
```

**هيكل المسارات:**

```
// بدون targetPath (افتراضي)
files/{year}/{month}/{user_id}/{filename}_{timestamp}_{random}.ext

// مع targetPath
{targetPath}/{filename}_{timestamp}_{random}.ext

// مثال
files/2024/02/1/document_1708876800_abc12345.pdf
documents/reports/report_1708876800_xyz98765.pdf
```



---

## معايير الأمان (Security Standards)

### 1. Authentication & Authorization
```php
// جميع endpoints تتطلب مصادقة
Route::middleware('auth:sanctum')->group(function() {
    // File routes
});

// التحقق من ملكية الملف
if ($file->user_id !== Auth::id()) {
    return response()->json(['error' => 'Unauthorized'], 403);
}
```

### 2. File Validation
- **Whitelist MIME Types** - قائمة محددة من الأنواع المسموحة
- **Blacklist Extensions** - منع الامتدادات الخطرة
- **Double Extension Check** - كشف file.pdf.exe
- **File Size Limit** - حد أقصى 100MB (قابل للتعديل)
- **Filename Sanitization** - تنظيف الأسماء من الأحرف الخاصة

### 3. Path Traversal Prevention
```php
// تنظيف المسار
private function sanitizePath(string $path): string
{
    $path = trim($path, '/\\');
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/\.+/#', '/', $path);  // إزالة ../
    $path = preg_replace('#^\.+/#', '', $path);
    $path = preg_replace('#/+#', '/', $path);
    return $path;
}
```

### 4. Rate Limiting
```php
// في routes/api.php
Route::post('/files/upload/initiate', ...)
    ->middleware('throttle:upload_initiate');

Route::post('/files/upload/chunk', ...)
    ->middleware('throttle:chunk_upload');

Route::get('/files/archive', ...)
    ->middleware('throttle:archive_query');
```

**التكوين المقترح:**
```php
// في RateLimitServiceProvider
'upload_initiate' => [10, 1],  // 10 requests per minute
'chunk_upload' => [100, 1],    // 100 chunks per minute
'archive_query' => [60, 1],    // 60 queries per minute
```

### 5. Logging & Monitoring
```php
// تسجيل محاولات رفع ملفات خطرة
Log::warning('Dangerous file upload attempt blocked', [
    'filename' => $filename,
    'extension' => $extension,
    'user_id' => Auth::id(),
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
]);

// تسجيل العمليات الناجحة
Log::info('Upload finalized successfully', [
    'file_id' => $fileId,
    'file_name' => $file->file_name,
    'file_url' => $file->file_url,
]);
```



---

## معايير الأداء (Performance Standards)

### 1. Chunked Upload Benefits
- **موثوقية أعلى** - إعادة رفع القطع الفاشلة فقط
- **رفع متوازي** - رفع عدة قطع في نفس الوقت
- **تجربة مستخدم أفضل** - progress bar دقيق
- **تجاوز حدود الخادم** - تجاوز upload_max_filesize

### 2. Database Optimization
```php
// Indexes للبحث السريع
INDEX idx_user_id (user_id)
INDEX idx_file_type (file_type)
INDEX idx_status (status)
INDEX idx_created_at (created_at)

// Eager Loading لتقليل N+1 queries
$files = File::where('user_id', Auth::id())
    ->with(['user:id,name,email'])
    ->paginate($perPage);
```

### 3. Storage Optimization
- **تنظيم هرمي** - files/year/month/user_id
- **أسماء فريدة** - timestamp + random string
- **حذف تلقائي** - تنظيف القطع المؤقتة
- **دعم S3** - للتخزين السحابي

### 4. Memory Management
```php
// Stream chunks بدلاً من تحميلها في الذاكرة
while (!feof($chunkHandle)) {
    $buffer = fread($chunkHandle, 8192);  // 8KB buffer
    fwrite($tempHandle, $buffer);
}
```

---

## معايير الموثوقية (Reliability Standards)

### 1. Transaction Management
```php
DB::beginTransaction();
try {
    // Assemble chunks
    // Update file record
    // Cleanup chunks
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### 2. Error Handling
```php
// استجابات خطأ موحدة
return response()->json([
    'success' => false,
    'error' => [
        'code' => 'ERROR_CODE',
        'message' => 'User-friendly message',
        'details' => [...] // في debug mode فقط
    ]
], 400);
```

### 3. Validation
- **Request Validation** - Laravel Form Requests
- **Chunk Sequence Validation** - التحقق من اكتمال القطع
- **File Size Validation** - مقارنة الحجم النهائي
- **Ownership Validation** - التحقق من الملكية

### 4. Cleanup Mechanisms
```php
// حذف القطع المؤقتة بعد النجاح
$this->cleanupChunks($fileId);

// حذف المجلدات الفارغة
$this->cleanupEmptyDirectories($fileId);

// انتهاء صلاحية الجلسات
'expires_at' => now()->addHours(24)
```



---

## المميزات الرئيسية (Key Features)

### ✅ 1. Chunked Upload System
- رفع ملفات كبيرة بشكل موثوق
- دعم الرفع المتوازي
- إعادة رفع القطع الفاشلة
- Progress tracking دقيق

### ✅ 2. Multi-Storage Support
- Local filesystem (public disk)
- AWS S3 cloud storage
- سهولة التبديل بين الأنظمة

### ✅ 3. Advanced Security
- MIME type whitelist
- Dangerous extension blacklist
- Double extension detection
- Path traversal prevention
- Rate limiting
- Authentication & authorization

### ✅ 4. File Explorer
- تصفح الملفات والمجلدات
- إنشاء وحذف وإعادة تسمية
- تحميل الملفات
- دعم الأحرف العربية

### ✅ 5. Advanced Search
- بحث بالاسم
- فلترة بالنوع والحالة
- فلترة بالتاريخ
- ترتيب ديناميكي
- Pagination

### ✅ 6. Upload Sessions
- تجميع عدة ملفات
- تتبع الإحصائيات
- انتهاء صلاحية تلقائي

### ✅ 7. Organized Storage
- هيكل هرمي (year/month/user)
- أسماء ملفات فريدة
- دعم المسارات المخصصة

### ✅ 8. Comprehensive Logging
- تسجيل العمليات الناجحة
- تسجيل محاولات الاختراق
- تتبع الأخطاء

---

## متطلبات التشغيل (Requirements)

### Backend Requirements
```json
{
  "php": "^8.1",
  "laravel": "^11.0",
  "database": "MySQL 8.0+ / PostgreSQL 13+",
  "storage": "Local filesystem or AWS S3",
  "extensions": [
    "php-fileinfo",
    "php-gd",
    "php-mbstring"
  ]
}
```

### Configuration Files

#### config/filesystems.php
```php
'disks' => [
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],
    
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
    ],
],

// إضافة
'max_file_size' => env('MAX_FILE_SIZE', 104857600), // 100MB
'chunk_size' => env('CHUNK_SIZE', 2097152), // 2MB
```



#### .env Configuration
```env
# Storage
FILESYSTEM_DISK=public
MAX_FILE_SIZE=104857600
CHUNK_SIZE=2097152

# AWS S3 (optional)
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket

# Rate Limiting
UPLOAD_INITIATE_LIMIT=10
CHUNK_UPLOAD_LIMIT=100
ARCHIVE_QUERY_LIMIT=60
```

---

## خطوات التنفيذ (Implementation Steps)

### 1. Database Migration
```bash
php artisan migrate
```

### 2. Storage Setup
```bash
# إنشاء symbolic link للـ public storage
php artisan storage:link

# إنشاء المجلدات المطلوبة
mkdir -p storage/app/temp/chunks
mkdir -p storage/app/temp
chmod -R 775 storage/app/temp
```

### 3. Service Provider Registration
```php
// في config/app.php أو bootstrap/providers.php
App\Providers\RateLimitServiceProvider::class,
```

### 4. Routes Registration
```php
// في routes/api.php
require __DIR__.'/api/files.php';
```

### 5. Middleware Configuration
```php
// في app/Http/Kernel.php أو bootstrap/app.php
protected $middlewareGroups = [
    'api' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];
```

---

## أمثلة الاستخدام (Usage Examples)

### Frontend Integration (JavaScript)

#### 1. Chunked Upload Implementation
```javascript
class ChunkedUploader {
    constructor(file, chunkSize = 2 * 1024 * 1024) {
        this.file = file;
        this.chunkSize = chunkSize;
        this.totalChunks = Math.ceil(file.size / chunkSize);
        this.fileId = null;
        this.sessionId = null;
    }

    async upload(onProgress) {
        // Step 1: Initiate upload
        const initResponse = await this.initiateUpload();
        this.fileId = initResponse.data.fileId;
        this.sessionId = initResponse.data.sessionId;

        // Step 2: Upload chunks
        for (let i = 0; i < this.totalChunks; i++) {
            await this.uploadChunk(i);
            if (onProgress) {
                onProgress((i + 1) / this.totalChunks * 100);
            }
        }

        // Step 3: Finalize upload
        const finalResponse = await this.finalizeUpload();
        return finalResponse.data;
    }

    async initiateUpload() {
        const response = await fetch('/api/files/upload/initiate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                fileName: this.file.name,
                fileSize: this.file.size,
                fileType: this.getFileType(),
                mimeType: this.file.type,
                totalChunks: this.totalChunks
            })
        });
        return await response.json();
    }

    async uploadChunk(chunkIndex) {
        const start = chunkIndex * this.chunkSize;
        const end = Math.min(start + this.chunkSize, this.file.size);
        const chunk = this.file.slice(start, end);

        const formData = new FormData();
        formData.append('fileId', this.fileId);
        formData.append('chunkIndex', chunkIndex);
        formData.append('totalChunks', this.totalChunks);
        formData.append('chunk', chunk);

        const response = await fetch('/api/files/upload/chunk', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });
        return await response.json();
    }

    async finalizeUpload() {
        const response = await fetch('/api/files/upload/finalize', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                fileId: this.fileId
            })
        });
        return await response.json();
    }

    getFileType() {
        const mimeType = this.file.type;
        if (mimeType.startsWith('image/')) return 'image';
        if (mimeType.startsWith('video/')) return 'video';
        if (mimeType.startsWith('audio/')) return 'audio';
        if (mimeType.includes('pdf') || mimeType.includes('document')) return 'document';
        return 'unclassified';
    }
}

// Usage
const uploader = new ChunkedUploader(file);
const result = await uploader.upload((progress) => {
    console.log(`Upload progress: ${progress}%`);
});
console.log('File uploaded:', result.url);
```



#### 2. File Explorer Integration
```javascript
class FileExplorer {
    constructor(apiUrl, token) {
        this.apiUrl = apiUrl;
        this.token = token;
        this.currentPath = '';
    }

    async browse(path = '') {
        const response = await fetch(
            `${this.apiUrl}/files/explorer/browse?path=${encodeURIComponent(path)}`,
            {
                headers: {
                    'Authorization': `Bearer ${this.token}`
                }
            }
        );
        return await response.json();
    }

    async createFolder(parentPath, folderName) {
        const response = await fetch(`${this.apiUrl}/files/explorer/create-folder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            },
            body: JSON.stringify({
                path: parentPath,
                name: folderName
            })
        });
        return await response.json();
    }

    async rename(oldPath, newName) {
        const response = await fetch(`${this.apiUrl}/files/explorer/rename`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            },
            body: JSON.stringify({
                path: oldPath,
                new_name: newName
            })
        });
        return await response.json();
    }

    async delete(path) {
        const response = await fetch(`${this.apiUrl}/files/explorer/delete`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            },
            body: JSON.stringify({ path })
        });
        return await response.json();
    }

    getDownloadUrl(path) {
        return `${this.apiUrl}/files/explorer/download?path=${encodeURIComponent(path)}`;
    }
}

// Usage
const explorer = new FileExplorer('https://api.example.com', token);

// Browse directory
const contents = await explorer.browse('documents/2024');
console.log('Directories:', contents.data.directories);
console.log('Files:', contents.data.files);

// Create folder
await explorer.createFolder('documents', 'new_folder');

// Rename file
await explorer.rename('documents/old.pdf', 'new.pdf');

// Delete file
await explorer.delete('documents/file.pdf');

// Download file
window.location.href = explorer.getDownloadUrl('documents/file.pdf');
```



#### 3. File Archive Integration
```javascript
class FileArchive {
    constructor(apiUrl, token) {
        this.apiUrl = apiUrl;
        this.token = token;
    }

    async listFiles(page = 1, perPage = 20) {
        const response = await fetch(
            `${this.apiUrl}/files/archive?page=${page}&per_page=${perPage}`,
            {
                headers: {
                    'Authorization': `Bearer ${this.token}`
                }
            }
        );
        return await response.json();
    }

    async getFile(fileId) {
        const response = await fetch(`${this.apiUrl}/files/archive/${fileId}`, {
            headers: {
                'Authorization': `Bearer ${this.token}`
            }
        });
        return await response.json();
    }

    async searchFiles(filters) {
        const response = await fetch(`${this.apiUrl}/files/archive/search`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`
            },
            body: JSON.stringify(filters)
        });
        return await response.json();
    }
}

// Usage
const archive = new FileArchive('https://api.example.com', token);

// List files
const files = await archive.listFiles(1, 20);
console.log('Files:', files.data.files);
console.log('Pagination:', files.data.pagination);

// Get single file
const file = await archive.getFile(123);
console.log('File URL:', file.data.file_url);

// Search files
const searchResults = await archive.searchFiles({
    filename: 'report',
    file_type: 'document',
    status: 'completed',
    date_from: '2024-01-01',
    date_to: '2024-02-25',
    sort_by: 'date',
    sort_order: 'desc',
    per_page: 20
});
console.log('Search results:', searchResults.data.files);
```

---

## التعامل مع الأخطاء (Error Handling)

### Error Response Format
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      // Additional error details (optional)
    }
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| VALIDATION_ERROR | 400 | بيانات الطلب غير صحيحة |
| FILE_NOT_FOUND | 404 | الملف غير موجود |
| UNAUTHORIZED_ACCESS | 403 | لا يملك المستخدم صلاحية الوصول |
| INVALID_FILE_TYPE | 400 | نوع الملف غير مسموح |
| FILE_TOO_LARGE | 400 | حجم الملف يتجاوز الحد المسموح |
| DANGEROUS_FILE_TYPE | 400 | ملف خطر محظور |
| CHUNK_COUNT_MISMATCH | 400 | عدم تطابق عدد القطع |
| CHUNK_STORAGE_FAILED | 400 | فشل تخزين القطعة |
| INCOMPLETE_UPLOAD | 400 | رفع غير مكتمل |
| ASSEMBLY_FAILED | 500 | فشل تجميع القطع |
| PATH_NOT_FOUND | 404 | المسار غير موجود |
| NAME_EXISTS | 400 | الاسم موجود بالفعل |
| FOLDER_EXISTS | 400 | المجلد موجود بالفعل |
| INVALID_FILE | 400 | ملف غير صالح |



### Frontend Error Handling Example
```javascript
async function handleFileUpload(file) {
    try {
        const uploader = new ChunkedUploader(file);
        const result = await uploader.upload((progress) => {
            updateProgressBar(progress);
        });
        
        showSuccess('File uploaded successfully!');
        return result;
        
    } catch (error) {
        if (error.response) {
            const errorData = await error.response.json();
            
            switch (errorData.error.code) {
                case 'INVALID_FILE_TYPE':
                    showError('نوع الملف غير مسموح. الرجاء رفع ملف صالح.');
                    break;
                    
                case 'FILE_TOO_LARGE':
                    showError('حجم الملف كبير جداً. الحد الأقصى 100MB.');
                    break;
                    
                case 'DANGEROUS_FILE_TYPE':
                    showError('هذا النوع من الملفات محظور لأسباب أمنية.');
                    break;
                    
                case 'CHUNK_STORAGE_FAILED':
                    showError('فشل رفع جزء من الملف. الرجاء المحاولة مرة أخرى.');
                    break;
                    
                default:
                    showError(errorData.error.message || 'حدث خطأ أثناء رفع الملف.');
            }
        } else {
            showError('خطأ في الاتصال بالخادم.');
        }
    }
}
```

---

## اختبارات الأداء (Performance Testing)

### Load Testing Scenarios

#### 1. Concurrent Uploads
```bash
# Test 100 concurrent file uploads
ab -n 100 -c 10 -p upload.json -T application/json \
   -H "Authorization: Bearer TOKEN" \
   https://api.example.com/files/upload/initiate
```

#### 2. Chunk Upload Throughput
```bash
# Test chunk upload speed
ab -n 1000 -c 50 -p chunk.dat -T multipart/form-data \
   -H "Authorization: Bearer TOKEN" \
   https://api.example.com/files/upload/chunk
```

#### 3. Archive Query Performance
```bash
# Test search performance
ab -n 500 -c 25 -p search.json -T application/json \
   -H "Authorization: Bearer TOKEN" \
   https://api.example.com/files/archive/search
```

### Expected Performance Metrics

| Metric | Target | Notes |
|--------|--------|-------|
| Upload Initiation | < 200ms | Database insert + validation |
| Chunk Upload | < 500ms | Per 2MB chunk |
| Finalize Upload | < 3s | For 100MB file (50 chunks) |
| Archive List | < 300ms | 50 records with pagination |
| Search Query | < 500ms | With multiple filters |
| File Explorer Browse | < 200ms | Per directory |

---

## الصيانة والمراقبة (Maintenance & Monitoring)

### 1. Cleanup Jobs

#### Cleanup Expired Sessions
```php
// app/Console/Commands/CleanupExpiredSessions.php
namespace App\Console\Commands;

use App\Models\UploadSession;
use Illuminate\Console\Command;

class CleanupExpiredSessions extends Command
{
    protected $signature = 'files:cleanup-sessions';
    protected $description = 'Clean up expired upload sessions';

    public function handle()
    {
        $expiredSessions = UploadSession::where('expires_at', '<', now())
            ->where('status', 'active')
            ->get();

        foreach ($expiredSessions as $session) {
            $session->update(['status' => 'expired']);
        }

        $this->info("Cleaned up {$expiredSessions->count()} expired sessions");
    }
}
```



#### Cleanup Orphaned Chunks
```php
// app/Console/Commands/CleanupOrphanedChunks.php
namespace App\Console\Commands;

use App\Models\FileChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedChunks extends Command
{
    protected $signature = 'files:cleanup-chunks';
    protected $description = 'Clean up orphaned file chunks';

    public function handle()
    {
        // Find chunks older than 24 hours
        $oldChunks = FileChunk::where('uploaded_at', '<', now()->subHours(24))
            ->get();

        $deletedCount = 0;
        foreach ($oldChunks as $chunk) {
            $chunkPath = storage_path('app/' . $chunk->chunk_path);
            if (file_exists($chunkPath)) {
                unlink($chunkPath);
                $deletedCount++;
            }
            $chunk->delete();
        }

        $this->info("Cleaned up {$deletedCount} orphaned chunks");
    }
}
```

#### Schedule in Kernel
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Run every hour
    $schedule->command('files:cleanup-sessions')->hourly();
    
    // Run daily at 2 AM
    $schedule->command('files:cleanup-chunks')->dailyAt('02:00');
}
```

### 2. Monitoring Queries

#### Storage Usage by User
```sql
SELECT 
    u.id,
    u.name,
    u.email,
    COUNT(f.id) as total_files,
    SUM(f.file_size) as total_size_bytes,
    ROUND(SUM(f.file_size) / 1048576, 2) as total_size_mb
FROM users u
LEFT JOIN files f ON u.id = f.user_id AND f.status = 'completed'
GROUP BY u.id, u.name, u.email
ORDER BY total_size_bytes DESC
LIMIT 20;
```

#### Upload Success Rate
```sql
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_uploads,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    ROUND(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as success_rate
FROM files
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

#### File Type Distribution
```sql
SELECT 
    file_type,
    COUNT(*) as count,
    ROUND(SUM(file_size) / 1048576, 2) as total_size_mb,
    ROUND(AVG(file_size) / 1048576, 2) as avg_size_mb
FROM files
WHERE status = 'completed'
GROUP BY file_type
ORDER BY count DESC;
```

#### Active Upload Sessions
```sql
SELECT 
    us.session_id,
    us.user_id,
    u.name,
    us.total_files,
    us.completed_files,
    us.failed_files,
    us.expires_at,
    TIMESTAMPDIFF(MINUTE, NOW(), us.expires_at) as minutes_remaining
FROM upload_sessions us
JOIN users u ON us.user_id = u.id
WHERE us.status = 'active'
AND us.expires_at > NOW()
ORDER BY us.created_at DESC;
```



### 3. Health Check Endpoint
```php
// app/Http/Controllers/HealthCheckController.php
namespace App\Http\Controllers;

use App\Models\File;
use App\Models\UploadSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    public function fileSystem()
    {
        try {
            // Check database connection
            DB::connection()->getPdo();
            
            // Check storage disk
            $diskAvailable = Storage::disk('public')->exists('');
            
            // Get statistics
            $stats = [
                'total_files' => File::count(),
                'completed_files' => File::where('status', 'completed')->count(),
                'pending_uploads' => File::whereIn('status', ['pending', 'uploading'])->count(),
                'active_sessions' => UploadSession::where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->count(),
                'storage_available' => $diskAvailable,
                'temp_chunks_dir' => is_dir(storage_path('app/temp/chunks')),
            ];
            
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'statistics' => $stats,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }
}
```

---

## التوسعات المستقبلية (Future Enhancements)

### 1. Image Processing
```php
// إضافة معالجة الصور
use Intervention\Image\Facades\Image;

public function processImage(File $file)
{
    $image = Image::make(storage_path('app/public/' . $file->file_path));
    
    // Generate thumbnails
    $thumbnail = $image->fit(200, 200);
    $thumbnailPath = 'thumbnails/' . $file->id . '.jpg';
    $thumbnail->save(storage_path('app/public/' . $thumbnailPath));
    
    // Update file metadata
    $file->metadata = array_merge($file->metadata ?? [], [
        'thumbnail_path' => $thumbnailPath,
        'dimensions' => [
            'width' => $image->width(),
            'height' => $image->height(),
        ],
    ]);
    $file->save();
}
```

### 2. Video Processing
```php
// إضافة معالجة الفيديو
use FFMpeg\FFMpeg;

public function processVideo(File $file)
{
    $ffmpeg = FFMpeg::create();
    $video = $ffmpeg->open(storage_path('app/public/' . $file->file_path));
    
    // Generate thumbnail from first frame
    $frame = $video->frame(TimeCode::fromSeconds(1));
    $thumbnailPath = 'thumbnails/' . $file->id . '.jpg';
    $frame->save(storage_path('app/public/' . $thumbnailPath));
    
    // Get video metadata
    $format = $ffmpeg->getFFProbe()
        ->format(storage_path('app/public/' . $file->file_path));
    
    $file->metadata = array_merge($file->metadata ?? [], [
        'thumbnail_path' => $thumbnailPath,
        'duration' => $format->get('duration'),
        'bitrate' => $format->get('bit_rate'),
    ]);
    $file->save();
}
```

### 3. File Sharing
```php
// إضافة مشاركة الملفات
Schema::create('file_shares', function (Blueprint $table) {
    $table->id();
    $table->foreignId('file_id')->constrained()->onDelete('cascade');
    $table->foreignId('shared_by')->constrained('users')->onDelete('cascade');
    $table->string('share_token', 64)->unique();
    $table->timestamp('expires_at')->nullable();
    $table->integer('max_downloads')->nullable();
    $table->integer('download_count')->default(0);
    $table->boolean('require_password')->default(false);
    $table->string('password')->nullable();
    $table->timestamps();
});
```



### 4. File Versioning
```php
// إضافة إصدارات الملفات
Schema::create('file_versions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('file_id')->constrained()->onDelete('cascade');
    $table->integer('version_number');
    $table->string('file_path');
    $table->string('file_url');
    $table->bigInteger('file_size');
    $table->foreignId('uploaded_by')->constrained('users');
    $table->text('change_description')->nullable();
    $table->timestamps();
    
    $table->unique(['file_id', 'version_number']);
});
```

### 5. Virus Scanning
```php
// إضافة فحص الفيروسات
use Xenolope\Quahog\Client as ClamAVClient;

public function scanFile(File $file)
{
    $clamav = new ClamAVClient('unix:///var/run/clamav/clamd.ctl');
    $result = $clamav->scanFile(storage_path('app/public/' . $file->file_path));
    
    if ($result['status'] === 'FOUND') {
        // Virus detected
        $file->update(['status' => 'failed']);
        
        Log::critical('Virus detected in uploaded file', [
            'file_id' => $file->id,
            'virus_name' => $result['reason'],
            'user_id' => $file->user_id,
        ]);
        
        // Delete infected file
        Storage::disk('public')->delete($file->file_path);
        
        return false;
    }
    
    return true;
}
```

### 6. CDN Integration
```php
// إضافة دعم CDN
public function getCdnUrl(File $file)
{
    $cdnDomain = config('filesystems.cdn_domain');
    
    if ($cdnDomain) {
        return $cdnDomain . '/' . $file->file_path;
    }
    
    return $file->file_url;
}
```

### 7. Compression
```php
// إضافة ضغط الملفات
use ZipArchive;

public function compressFiles(array $fileIds, string $archiveName)
{
    $zip = new ZipArchive();
    $archivePath = storage_path('app/temp/' . $archiveName . '.zip');
    
    if ($zip->open($archivePath, ZipArchive::CREATE) === TRUE) {
        $files = File::whereIn('id', $fileIds)->get();
        
        foreach ($files as $file) {
            $filePath = storage_path('app/public/' . $file->file_path);
            $zip->addFile($filePath, $file->original_name);
        }
        
        $zip->close();
        
        return $archivePath;
    }
    
    return null;
}
```

---

## أفضل الممارسات (Best Practices)

### 1. Security
- ✅ استخدام HTTPS فقط
- ✅ التحقق من المصادقة والتفويض
- ✅ Whitelist للأنواع المسموحة
- ✅ Sanitization للمدخلات
- ✅ Rate limiting
- ✅ Logging للعمليات الحساسة
- ✅ تشفير البيانات الحساسة

### 2. Performance
- ✅ استخدام Chunked Upload للملفات الكبيرة
- ✅ Eager Loading للعلاقات
- ✅ Indexes على الأعمدة المستخدمة في البحث
- ✅ Pagination للنتائج الكبيرة
- ✅ Caching للبيانات المتكررة
- ✅ CDN للملفات الثابتة

### 3. Reliability
- ✅ Transaction management
- ✅ Error handling شامل
- ✅ Validation على جميع المستويات
- ✅ Cleanup للبيانات المؤقتة
- ✅ Backup منتظم
- ✅ Monitoring مستمر

### 4. Maintainability
- ✅ فصل المنطق إلى Services
- ✅ استخدام Design Patterns
- ✅ Documentation شامل
- ✅ Unit tests
- ✅ Code standards
- ✅ Version control



---

## الخلاصة (Summary)

### نقاط القوة (Strengths)
1. **نظام رفع متقدم** - Chunked upload موثوق للملفات الكبيرة
2. **أمان عالي** - طبقات حماية متعددة ضد الملفات الخطرة
3. **مرونة التخزين** - دعم Local و S3
4. **بحث متقدم** - فلاتر وترتيب ديناميكي
5. **معمارية نظيفة** - فصل واضح للمسؤوليات
6. **قابلية التوسع** - سهولة إضافة ميزات جديدة
7. **موثوقية عالية** - معالجة شاملة للأخطاء
8. **أداء محسّن** - Indexes، Pagination، Eager Loading

### حالات الاستخدام (Use Cases)
- ✅ منصات إدارة المحتوى (CMS)
- ✅ أنظمة إدارة الوثائق (DMS)
- ✅ منصات التعليم الإلكتروني
- ✅ تطبيقات مشاركة الملفات
- ✅ أنظمة النسخ الاحتياطي
- ✅ منصات الوسائط المتعددة

### متطلبات الإنتاج (Production Requirements)

#### Infrastructure
```yaml
Web Server:
  - Nginx/Apache with PHP-FPM
  - PHP 8.1+
  - Memory: 512MB minimum per worker

Database:
  - MySQL 8.0+ or PostgreSQL 13+
  - Connections: 100+
  - Storage: Based on file metadata

Storage:
  - Local: SSD recommended
  - S3: Standard or Infrequent Access tier
  - Backup: Daily snapshots

Monitoring:
  - Application logs
  - Error tracking (Sentry, Bugsnag)
  - Performance monitoring (New Relic, DataDog)
  - Uptime monitoring
```

#### Scaling Considerations
```yaml
Horizontal Scaling:
  - Load balancer for multiple app servers
  - Shared storage (S3, NFS, GlusterFS)
  - Database replication (master-slave)
  - Redis for session management

Vertical Scaling:
  - Increase PHP memory_limit
  - Increase upload_max_filesize
  - Increase max_execution_time
  - Optimize database queries

Caching:
  - Redis/Memcached for file metadata
  - CDN for static file delivery
  - Browser caching headers
```

---

## الدعم والتواصل (Support & Contact)

### Documentation
- API Documentation: Swagger/OpenAPI
- Code Documentation: PHPDoc
- User Guide: Markdown files

### Testing
```bash
# Run tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Generate coverage report
php artisan test --coverage
```

### Deployment Checklist
- [ ] Run migrations: `php artisan migrate`
- [ ] Create storage link: `php artisan storage:link`
- [ ] Set proper permissions on storage directories
- [ ] Configure environment variables
- [ ] Set up scheduled tasks (cron)
- [ ] Configure rate limiting
- [ ] Set up monitoring and logging
- [ ] Test file upload flow
- [ ] Test file explorer functionality
- [ ] Test search and filtering
- [ ] Verify security measures
- [ ] Load testing
- [ ] Backup strategy

---

## الملاحق (Appendices)

### A. API Endpoints Summary

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| POST | /api/files/upload/initiate | بدء رفع ملف | ✓ |
| POST | /api/files/upload/chunk | رفع قطعة | ✓ |
| POST | /api/files/upload/finalize | إنهاء الرفع | ✓ |
| DELETE | /api/files/upload/cancel/{id} | إلغاء الرفع | ✓ |
| GET | /api/files/explorer/browse | تصفح المجلدات | ✓ |
| POST | /api/files/explorer/rename | إعادة تسمية | ✓ |
| DELETE | /api/files/explorer/delete | حذف ملف/مجلد | ✓ |
| POST | /api/files/explorer/create-folder | إنشاء مجلد | ✓ |
| GET | /api/files/explorer/download | تحميل ملف | ✓ |
| GET | /api/files/archive | قائمة الملفات | ✓ |
| GET | /api/files/archive/{id} | تفاصيل ملف | ✓ |
| POST | /api/files/archive/search | بحث وفلترة | ✓ |

### B. Database Tables Summary

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| files | بيانات الملفات | id, user_id, file_name, file_path, status |
| file_chunks | قطع الملفات المؤقتة | id, file_id, chunk_index, chunk_path |
| upload_sessions | جلسات الرفع | id, session_id, user_id, expires_at |

### C. Configuration Variables

| Variable | Default | Description |
|----------|---------|-------------|
| MAX_FILE_SIZE | 104857600 | حجم الملف الأقصى (بايت) |
| CHUNK_SIZE | 2097152 | حجم القطعة (بايت) |
| FILESYSTEM_DISK | public | نظام التخزين |
| UPLOAD_INITIATE_LIMIT | 10 | حد بدء الرفع (دقيقة) |
| CHUNK_UPLOAD_LIMIT | 100 | حد رفع القطع (دقيقة) |
| ARCHIVE_QUERY_LIMIT | 60 | حد استعلامات الأرشيف (دقيقة) |

---

**تاريخ الإنشاء:** 25 فبراير 2024  
**الإصدار:** 1.0  
**المؤلف:** تحليل تقني شامل لنظام إدارة الملفات

---

## ملاحظات ختامية

هذا النظام يوفر حلاً متكاملاً وآمناً لإدارة الملفات في تطبيقات Laravel. تم تصميمه مع مراعاة أفضل الممارسات في الأمان والأداء والموثوقية. يمكن تخصيصه وتوسيعه بسهولة لتلبية احتياجات مختلف المشاريع.

للحصول على أفضل النتائج، يُنصح بـ:
- اختبار شامل قبل النشر في الإنتاج
- مراقبة مستمرة للأداء والأخطاء
- نسخ احتياطي منتظم للبيانات والملفات
- تحديث دوري للمكتبات والتبعيات
- مراجعة دورية لسجلات الأمان
