# API Documentation

Base URL: `http://127.0.0.1:8000/api`

Auth Header: `Authorization: Bearer {token}`

---

## ARTICLES

### POST /admin/articles — إضافة مقال
Auth: required (Admin) | Content-Type: multipart/form-data

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| title | string | yes | max 255 |
| slug | string | yes | unique |
| content | string | yes | |
| status | string | yes | draft / published / archived |
| section_id | int/string | no | ID أو slug أو اسم السيكشن |
| excerpt | string | no | |
| author_name | string | no | |
| featured_image | file | no | jpeg/png/jpg/gif/webp |
| gregorian_date | string | no | 2026-04-13 |
| hijri_date | string | no | 1447-10-15 |
| references | string | no | |
| keywords | string | no | مفصولة بفاصلة |
| published_at | date | no | لو مستقبلي يتحول draft |

Response 201:
```json
{
  "message": "Article created successfully",
  "article": {
    "id": 1, "user_id": 1, "section_id": 12,
    "title": "عنوان المقال", "slug": "article-slug",
    "excerpt": "مقتطف", "content": "المحتوى",
    "author_name": "اسم الكاتب",
    "featured_image": "http://127.0.0.1:8000/storage/articles/...",
    "gregorian_date": "2026-04-13", "hijri_date": "1447-10-15",
    "references": null, "keywords": "كلمة,كلمة",
    "status": "published", "published_at": "2026-04-13T10:00:00.000000Z",
    "views_count": 0,
    "created_at": "2026-04-13T10:00:00.000000Z",
    "updated_at": "2026-04-13T10:00:00.000000Z"
  }
}
```

---

### POST /admin/articles/{id} — تعديل مقال (أو PUT)
Auth: required (Admin) | Content-Type: multipart/form-data

نفس حقول الإضافة — كلها اختيارية (sometimes)

Response 200:
```json
{
  "message": "Article updated successfully",
  "article": { "...same fields..." }
}
```

---

### DELETE /admin/articles/{id} — حذف مقال
Auth: required (Admin)

Response 200:
```json
{ "message": "Article deleted successfully" }
```

---

### GET /articles/{id} — عرض مقال واحد

Response 200:
```json
{
  "article": {
    "id": 165, "user_id": 1, "section_id": 12,
    "title": "مسائل في علم الكلام",
    "slug": "article-slug", "excerpt": "مقتطف",
    "content": "المحتوى الكامل",
    "author_name": "الشيخ محمد العثيمين",
    "featured_image": "http://127.0.0.1:8000/storage/articles/...",
    "gregorian_date": "2025-04-30", "hijri_date": "1446-10-22",
    "references": null, "keywords": "إسلام,عقيدة,توحيد",
    "status": "published", "published_at": "2026-03-31T08:56:26.000000Z",
    "views_count": 4538, "deleted_at": null,
    "created_at": "2026-04-13T08:56:26.000000Z",
    "updated_at": "2026-04-13T08:56:26.000000Z",
    "section": {
      "id": 12, "name": "معتقدات", "name_sw": "ITIKADI",
      "type": "مقال", "slug": "muqatadat"
    }
  },
  "next": {
    "id": 164, "title": "المقال السابق", "slug": "prev-slug",
    "excerpt": "مقتطف", "featured_image": null,
    "published_at": "2026-03-30T08:56:26.000000Z"
  }
}
```
> next = المقال السابق في نفس السيكشن. يرجع null لو مفيش.

---

## BOOKS

### POST /admin/library/books — إضافة كتاب
Auth: required (Admin) | Content-Type: multipart/form-data

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| title | string | yes | |
| description | string | yes | |
| author_name | string | yes | |
| source_type | string | yes | file / link / embed |
| file_path | file | if source_type=file | pdf/doc/docx/epub |
| source_link | string | if source_type=link/embed | |
| cover_type | string | yes | auto / upload |
| cover_path | file | if cover_type=upload | image |
| type | string | yes | single / part |
| book_series_id | integer | if type=part | |
| section_id | integer | no | |
| keywords | array | no | keywords[0]=كلمة |

Response 201:
```json
{
  "message": "تم إضافة الكتاب بنجاح",
  "data": {
    "id": 1, "title": "العقيدة الواسطية",
    "author_name": "ابن تيمية", "description": "وصف الكتاب",
    "source_type": "link", "source_link": "https://example.com/book.pdf",
    "file_path": null, "cover_type": "auto",
    "cover_path": "defaults/book_cover.png",
    "type": "single", "book_series_id": null, "section_id": 16,
    "keywords": ["إسلام", "عقيدة"],
    "views_count": 0, "rating_sum": "0.00", "rating_count": 0,
    "average_rating": 0,
    "created_at": "2026-04-13T10:00:00.000000Z",
    "updated_at": "2026-04-13T10:00:00.000000Z"
  }
}
```

---

### PUT /admin/library/books/{id} — تعديل كتاب
Auth: required (Admin) | Content-Type: multipart/form-data

نفس حقول الإضافة — كلها اختيارية

Response 200:
```json
{
  "message": "تم تحديث بيانات الكتاب بنجاح",
  "data": { "...same fields..." }
}
```

---

### DELETE /admin/library/books/{id} — حذف كتاب
Auth: required (Admin)

Response 200:
```json
{ "message": "تم حذف الكتاب بنجاح" }
```

---

### GET /library/books/{id} — عرض كتاب واحد

Response 200:
```json
{
  "book": {
    "id": 1, "title": "العقيدة الواسطية",
    "author_name": "ابن تيمية", "description": "وصف الكتاب",
    "source_type": "link", "source_link": "https://example.com/book.pdf",
    "cover_type": "auto", "cover_path": "defaults/book_cover.png",
    "type": "single", "book_series_id": null, "section_id": 16,
    "keywords": ["إسلام", "عقيدة"],
    "views_count": 101, "average_rating": 4.5,
    "section": { "id": 16, "name": "كتب", "name_sw": "VITUKO" },
    "series": null
  },
  "next": {
    "id": 2, "title": "كتاب التوحيد", "author_name": "ابن القيم",
    "cover_path": "defaults/book_cover.png", "cover_type": "auto",
    "created_at": "2026-04-13T10:00:00.000000Z"
  },
  "related_parts": []
}
```
> related_parts يظهر فقط لو type=part وعنده book_series_id.

---

## AUDIOS (صوتيات)

### POST /admin/audios — إضافة صوتية
Auth: required (Admin) | Content-Type: multipart/form-data

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| title | string | yes | max 255 |
| type | string | yes | upload / link |
| file | file | if type=upload | |
| url | string (URL) | if type=link | YouTube أو رابط مباشر |
| section_id | int/string | no | |
| description | string | no | |
| thumbnail | file (image) | no | |
| keywords | string | no | |
| rating | float | no | 0 - 5 |

Response 201:
```json
{
  "message": "Audio created successfully",
  "audio": {
    "id": 1, "section_id": 18, "user_id": 1,
    "title": "تلاوة سورة البقرة", "description": "وصف",
    "type": "link", "file_path": null,
    "url": "https://www.youtube.com/watch?v=xxx",
    "thumbnail": "https://img.youtube.com/vi/xxx/hqdefault.jpg",
    "embed_url": "https://www.youtube-nocookie.com/embed/xxx?rel=0",
    "keywords": "إسلام,صوت", "views_count": 0, "rating": null,
    "created_at": "2026-04-13T10:00:00.000000Z",
    "updated_at": "2026-04-13T10:00:00.000000Z"
  }
}
```

---

### POST /admin/audios/{id} — تعديل صوتية (أو PUT)
Auth: required (Admin) | Content-Type: multipart/form-data

نفس حقول الإضافة — كلها اختيارية

Response 200:
```json
{
  "message": "Audio updated successfully",
  "audio": { "...same fields..." }
}
```

---

### DELETE /admin/audios/{id} — حذف صوتية
Auth: required (Admin)

Response 200:
```json
{ "message": "Audio deleted successfully" }
```

---

### GET /audios/{id} — عرض صوتية واحدة

Response 200:
```json
{
  "audio": {
    "id": 1, "section_id": 18, "user_id": 1,
    "title": "تلاوة سورة البقرة", "description": "وصف",
    "type": "link", "file_path": null,
    "url": "https://www.youtube.com/watch?v=xxx",
    "thumbnail": "https://img.youtube.com/vi/xxx/hqdefault.jpg",
    "embed_url": "https://www.youtube-nocookie.com/embed/xxx?rel=0",
    "keywords": "إسلام,صوت", "views_count": 150, "rating": 4.5,
    "section": { "id": 18, "name": "صوتيات", "name_sw": "SAUTI" },
    "user": { "id": 1, "name": "Admin" }
  },
  "next": {
    "id": 2, "title": "الصوتية السابقة",
    "thumbnail": "https://img.youtube.com/vi/yyy/hqdefault.jpg",
    "created_at": "2026-04-12T10:00:00.000000Z"
  }
}
```

---

## VISUALS (فيديوهات)

### POST /admin/visuals — إضافة فيديو
Auth: required (Admin) | Content-Type: multipart/form-data

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| title | string | yes | max 255 |
| type | string | yes | upload / link |
| file | file | if type=upload | |
| url | string (URL) | if type=link | |
| section_id | int/string | no | |
| description | string | no | |
| thumbnail | file (image) | no | |
| keywords | string | no | |
| rating | float | no | 0 - 5 |

Response 201:
```json
{
  "message": "Visual created successfully",
  "visual": {
    "id": 1, "section_id": 17, "user_id": 1,
    "title": "شرح العقيدة الطحاوية", "description": "وصف",
    "type": "link", "file_path": null,
    "url": "https://www.youtube.com/watch?v=xxx",
    "thumbnail": "http://127.0.0.1:8000/storage/visuals/thumbnails/...",
    "keywords": "إسلام,فيديو", "views_count": 0, "rating": null,
    "created_at": "2026-04-13T10:00:00.000000Z",
    "updated_at": "2026-04-13T10:00:00.000000Z"
  }
}
```

---

### POST /admin/visuals/{id} — تعديل فيديو (أو PUT)
Auth: required (Admin) | Content-Type: multipart/form-data

نفس حقول الإضافة — كلها اختيارية

Response 200:
```json
{
  "message": "Visual updated successfully",
  "visual": { "...same fields..." }
}
```

---

### DELETE /admin/visuals/{id} — حذف فيديو
Auth: required (Admin)

Response 200:
```json
{ "message": "Visual deleted successfully" }
```

---

### GET /visuals/{id} — عرض فيديو واحد

Response 200:
```json
{
  "visual": {
    "id": 1, "section_id": 17, "user_id": 1,
    "title": "شرح العقيدة الطحاوية", "description": "وصف",
    "type": "link", "file_path": null,
    "url": "https://www.youtube.com/watch?v=xxx",
    "thumbnail": "http://127.0.0.1:8000/storage/visuals/thumbnails/...",
    "keywords": "إسلام,فيديو", "views_count": 320, "rating": 4.2,
    "section": { "id": 17, "name": "فيديوهات", "name_sw": "VEDIO" },
    "user": { "id": 1, "name": "Admin" }
  },
  "next": {
    "id": 2, "title": "الفيديو السابق",
    "thumbnail": "http://127.0.0.1:8000/storage/visuals/thumbnails/...",
    "created_at": "2026-04-12T10:00:00.000000Z"
  }
}
```

---

## LIST ALL CONTENT

### GET /articles — جميع المقالات

Query params (optional):
- `section_id` — فلتر بالسيكشن
- `status` — `draft` / `published` / `archived`
- `author` — اسم الكاتب (partial match)
- `date` — تاريخ ميلادي `2026-04-13`
- `page` — رقم الصفحة (default: 1)

Response 200:
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 165, "title": "مسائل في علم الكلام",
      "slug": "article-slug", "excerpt": "مقتطف",
      "featured_image": "http://...", "status": "published",
      "published_at": "2026-03-31T08:56:26.000000Z",
      "views_count": 4538,
      "section": { "id": 12, "name": "معتقدات" }
    }
  ],
  "per_page": 15, "total": 40,
  "next_page_url": "http://127.0.0.1:8000/api/articles?page=2",
  "prev_page_url": null
}
```

---

### GET /audios — جميع الصوتيات

Query params (optional):
- `section_id` — فلتر بالسيكشن
- `type` — `upload` / `link`
- `author` — user_id الكاتب
- `page`

Response 200:
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1, "title": "تلاوة سورة البقرة",
      "type": "link",
      "url": "https://www.youtube.com/watch?v=xxx",
      "thumbnail": "https://img.youtube.com/vi/xxx/hqdefault.jpg",
      "embed_url": "https://www.youtube-nocookie.com/embed/xxx?rel=0",
      "views_count": 150, "rating": 4.5,
      "section": { "id": 18, "name": "صوتيات" },
      "user": { "id": 1, "name": "Admin" }
    }
  ],
  "per_page": 15, "total": 70,
  "next_page_url": "http://127.0.0.1:8000/api/audios?page=2",
  "prev_page_url": null
}
```

---

### GET /visuals — جميع الفيديوهات

Query params (optional):
- `section_id`
- `type` — `upload` / `link`
- `author` — user_id
- `page`

Response 200:
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1, "title": "شرح العقيدة الطحاوية",
      "type": "link",
      "url": "https://www.youtube.com/watch?v=xxx",
      "thumbnail": "http://127.0.0.1:8000/storage/visuals/thumbnails/...",
      "views_count": 320, "rating": 4.2,
      "section": { "id": 17, "name": "فيديوهات" },
      "user": { "id": 1, "name": "Admin" }
    }
  ],
  "per_page": 15, "total": 50,
  "next_page_url": "http://127.0.0.1:8000/api/visuals?page=2",
  "prev_page_url": null
}
```

---

### GET /library/books — جميع الكتب

Query params (optional):
- `section_id`
- `series_id` — book_series_id
- `type` — `single` / `part`
- `page`

Response 200:
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1, "title": "العقيدة الواسطية",
      "author_name": "ابن تيمية",
      "cover_type": "auto", "cover_path": "defaults/book_cover.png",
      "type": "single", "views_count": 101, "average_rating": 4.5,
      "section": { "id": 16, "name": "كتب" }
    }
  ],
  "per_page": 20, "total": 30,
  "next_page_url": "http://127.0.0.1:8000/api/library/books?page=2",
  "prev_page_url": null
}
```

---

## SECTIONS — Content Endpoints

### GET /sections/{id}/articles — مقالات سيكشن

Query params:
- `per_page` (default: 15)

Response 200:
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 165, "title": "مسائل في علم الكلام",
      "slug": "article-slug", "excerpt": "مقتطف",
      "featured_image": "http://...", "status": "published",
      "published_at": "2026-03-31T08:56:26.000000Z",
      "views_count": 4538
    }
  ],
  "per_page": 15, "total": 40,
  "next_page_url": "http://127.0.0.1:8000/api/sections/12/articles?page=2",
  "prev_page_url": null
}
```

---

### GET /sections/{id}/books — كتب سيكشن

Query params: `per_page` (default: 15)

Response 200: نفس هيكل pagination مع بيانات الكتب

---

### GET /sections/{id}/videos — فيديوهات سيكشن

Query params: `per_page` (default: 15)

Response 200: نفس هيكل pagination مع بيانات الفيديوهات

---

### GET /sections/{id}/audios — صوتيات سيكشن

Query params: `per_page` (default: 15)

Response 200: نفس هيكل pagination مع بيانات الصوتيات

---

## HOMEPAGE

### GET /homepage — الصفحة الرئيسية

Response 200:
```json
[
  {
    "section": {
      "id": 12, "name": "معتقدات", "name_sw": "ITIKADI",
      "type": "مقال", "slug": "muqatadat", "is_active": true
    },
    "content": [
      {
        "id": 165, "title": "مسائل في علم الكلام",
        "slug": "article-slug", "excerpt": "مقتطف",
        "featured_image": "http://...",
        "published_at": "2026-03-31T08:56:26.000000Z"
      }
    ]
  },
  {
    "section": {
      "id": 13, "name": "شبهات", "name_sw": "SHUBUHATI",
      "type": "مقال", "slug": "shubuhat"
    },
    "content": [ "...5 articles..." ]
  },
  {
    "section": {
      "id": 14, "name": "فتاوي", "name_sw": "FATWA",
      "type": "مقال", "slug": "fatawa"
    },
    "content": [ "...9 articles (فتاوي gets 9 items)..." ]
  },
  {
    "section": { "id": 16, "name": "كتب", "name_sw": "VITUKO", "type": "كتب" },
    "content": [ "...5 books..." ]
  },
  {
    "section": { "id": 17, "name": "فيديوهات", "name_sw": "VEDIO", "type": "فيديو" },
    "content": [ "...5 visuals..." ]
  },
  {
    "section": { "id": 18, "name": "صوتيات", "name_sw": "SAUTI", "type": "صوت" },
    "content": [ "...5 audios..." ]
  }
]
```

**Rules:**
- كل سيكشن يرجع 5 items افتراضياً
- سيكشن "فتاوي" يرجع 9 items
- المقالات: published فقط مرتبة بـ published_at
- الكتب/فيديوهات/صوتيات: مرتبة بـ created_at

---

## Error Responses

**401 Unauthorized:**
```json
{ "message": "Unauthenticated." }
```

**403 Forbidden:**
```json
{ "message": "Unauthorized" }
```

**404 Not Found:**
```json
{ "message": "No query results for model..." }
```

**422 Validation Error:**
```json
{
  "message": "The title field is required.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```
