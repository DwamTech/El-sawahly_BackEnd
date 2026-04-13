<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Audio;
use App\Models\Book;
use App\Models\Section;
use App\Models\User;
use App\Models\Visual;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // We need at least one user to assign content to
        $user = User::where('role', 'admin')->first()
            ?? User::where('role', 'author')->first()
            ?? User::first();

        if (! $user) {
            $this->command->error('No users found. Run RolesAndUsersSeeder first.');
            return;
        }

        $sections = Section::all()->keyBy('slug');

        // ── مقال sections ──────────────────────────────────────────
        foreach (['muqatadat', 'shubuhat', 'fatawa', 'maqalat'] as $slug) {
            $section = $sections->get($slug);
            if (! $section) {
                $this->command->warn("Section [{$slug}] not found, skipping.");
                continue;
            }

            $this->seedArticles($section->id, $user->id, 10);
            $this->command->info("Articles seeded for section: {$section->name}");
        }

        // ── كتب ────────────────────────────────────────────────────
        $booksSection = $sections->get('kutub');
        if ($booksSection) {
            $this->seedBooks($booksSection->id, 10);
            $this->command->info("Books seeded for section: {$booksSection->name}");
        }

        // ── فيديوهات ───────────────────────────────────────────────
        $videosSection = $sections->get('videos');
        if ($videosSection) {
            $this->seedVisuals($videosSection->id, $user->id, 10);
            $this->command->info("Visuals seeded for section: {$videosSection->name}");
        }

        // ── صوتيات ─────────────────────────────────────────────────
        $audiosSection = $sections->get('audios');
        if ($audiosSection) {
            $this->seedAudios($audiosSection->id, $user->id, 10);
            $this->command->info("Audios seeded for section: {$audiosSection->name}");
        }
    }

    // ---------------------------------------------------------------

    private function seedArticles(int $sectionId, int $userId, int $count): void
    {
        $arabicTitles = [
            'الإسلام والعقيدة الصحيحة', 'مفهوم التوحيد في الإسلام', 'الرد على الشبهات العقدية',
            'أصول الإيمان وأركانه', 'العقيدة السلفية وأهميتها', 'التوسل المشروع وغير المشروع',
            'حكم الاستغاثة بالأولياء', 'مسائل في علم الكلام', 'الفرق بين التوحيد والشرك',
            'منهج أهل السنة في العقيدة', 'الإيمان بالقضاء والقدر', 'أسماء الله الحسنى وصفاته',
            'الولاء والبراء في الإسلام', 'حكم الاحتفال بالمولد النبوي', 'التبرك المشروع في الإسلام',
        ];

        for ($i = 0; $i < $count; $i++) {
            $title = $arabicTitles[$i % count($arabicTitles)].' '.($i + 1);
            $slug  = Str::slug('article-'.$sectionId.'-'.($i + 1).'-'.Str::random(4));

            Article::create([
                'section_id'     => $sectionId,
                'user_id'        => $userId,
                'title'          => $title,
                'slug'           => $slug,
                'excerpt'        => 'مقتطف من المقال: '.$title,
                'content'        => $this->arabicContent(),
                'author_name'    => $this->randomArabicName(),
                'gregorian_date' => now()->subDays(rand(1, 365))->toDateString(),
                'hijri_date'     => '1446-'.rand(1, 12).'-'.rand(1, 28),
                'keywords'       => 'إسلام,عقيدة,توحيد',
                'status'         => 'published',
                'published_at'   => now()->subDays(rand(1, 300)),
                'views_count'    => rand(50, 5000),
            ]);
        }
    }

    private function seedBooks(int $sectionId, int $count): void
    {
        $titles = [
            'العقيدة الواسطية', 'كتاب التوحيد', 'الإبانة عن أصول الديانة',
            'شرح السنة للبغوي', 'لمعة الاعتقاد', 'الفتوى الحموية الكبرى',
            'درء تعارض العقل والنقل', 'منهاج السنة النبوية', 'الصواعق المرسلة',
            'اجتماع الجيوش الإسلامية', 'إغاثة اللهفان', 'مدارج السالكين',
        ];

        $authors = [
            'ابن تيمية', 'ابن القيم الجوزية', 'الإمام البخاري',
            'الإمام مسلم', 'ابن كثير', 'الإمام الذهبي',
        ];

        for ($i = 0; $i < $count; $i++) {
            Book::create([
                'section_id'   => $sectionId,
                'title'        => $titles[$i % count($titles)],
                'author_name'  => $authors[$i % count($authors)],
                'description'  => 'وصف الكتاب: '.$titles[$i % count($titles)],
                'source_type'  => 'link',
                'source_link'  => 'https://example.com/books/'.($i + 1),
                'cover_type'   => 'auto',
                'cover_path'   => 'placeholders/book_cover.jpg',
                'type'         => 'single',
                'keywords'     => ['إسلام', 'عقيدة', 'فقه'],
                'views_count'  => rand(10, 3000),
                'rating_sum'   => rand(10, 50),
                'rating_count' => rand(2, 10),
            ]);
        }
    }

    private function seedVisuals(int $sectionId, int $userId, int $count): void
    {
        $titles = [
            'شرح العقيدة الطحاوية', 'محاضرة في التوحيد', 'درس في أصول الفقه',
            'خطبة الجمعة', 'تفسير سورة الفاتحة', 'الرد على الشبهات',
            'محاضرة في السيرة النبوية', 'شرح رياض الصالحين', 'دروس في الفقه الإسلامي',
            'تعليم أحكام التجويد', 'شرح الأربعين النووية', 'دروس في العقيدة',
        ];

        // Sample YouTube IDs for placeholder
        $youtubeIds = [
            'dQw4w9WgXcQ', 'jNQXAC9IVRw', 'kJQP7kiw5Fk',
            'OPf0YbXqDm0', 'RgKAFK5djSk', 'fJ9rUzIMcZQ',
        ];

        for ($i = 0; $i < $count; $i++) {
            $ytId = $youtubeIds[$i % count($youtubeIds)];
            Visual::create([
                'section_id'  => $sectionId,
                'user_id'     => $userId,
                'title'       => $titles[$i % count($titles)],
                'description' => 'وصف الفيديو: '.$titles[$i % count($titles)],
                'type'        => 'link',
                'url'         => 'https://www.youtube.com/watch?v='.$ytId,
                'thumbnail'   => 'https://img.youtube.com/vi/'.$ytId.'/hqdefault.jpg',
                'keywords'    => 'إسلام,فيديو,محاضرة',
                'views_count' => rand(100, 10000),
                'rating'      => round(rand(30, 50) / 10, 1),
            ]);
        }
    }

    private function seedAudios(int $sectionId, int $userId, int $count): void
    {
        $titles = [
            'تلاوة سورة البقرة', 'شرح صوتي للعقيدة', 'محاضرة صوتية في التوحيد',
            'درس صوتي في الفقه', 'خطبة الجمعة صوتية', 'تفسير صوتي للقرآن',
            'دروس في السيرة النبوية', 'شرح صحيح البخاري', 'أحكام التجويد صوتياً',
            'دروس في الحديث النبوي', 'شرح الأربعين النووية صوتياً', 'محاضرات في الفقه',
        ];

        $youtubeIds = [
            'dQw4w9WgXcQ', 'jNQXAC9IVRw', 'kJQP7kiw5Fk',
            'OPf0YbXqDm0', 'RgKAFK5djSk', 'fJ9rUzIMcZQ',
        ];

        for ($i = 0; $i < $count; $i++) {
            $ytId = $youtubeIds[$i % count($youtubeIds)];
            Audio::create([
                'section_id'  => $sectionId,
                'user_id'     => $userId,
                'title'       => $titles[$i % count($titles)],
                'description' => 'وصف الصوتية: '.$titles[$i % count($titles)],
                'type'        => 'link',
                'url'         => 'https://www.youtube.com/watch?v='.$ytId,
                'keywords'    => 'إسلام,صوت,محاضرة',
                'views_count' => rand(50, 5000),
                'rating'      => round(rand(30, 50) / 10, 1),
            ]);
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function arabicContent(): string
    {
        return 'بسم الله الرحمن الرحيم. الحمد لله رب العالمين، والصلاة والسلام على أشرف الأنبياء والمرسلين، نبينا محمد وعلى آله وصحبه أجمعين. أما بعد: فهذا مقال مفيد يتناول موضوعاً مهماً من موضوعات العقيدة الإسلامية، نسأل الله أن ينفع به القارئ الكريم، وأن يجعله خالصاً لوجهه الكريم. إن من أهم ما يجب على المسلم معرفته هو أصول الدين وقواعده الكبرى، التي بنى عليها العلماء الأجلاء مؤلفاتهم ودروسهم. وقد اعتنى أهل العلم بهذه المسائل عناية فائقة، وأفردوا لها المصنفات والرسائل. والله أعلم، وصلى الله على نبينا محمد وعلى آله وصحبه أجمعين.';
    }

    private function randomArabicName(): string
    {
        $names = [
            'د. عبدالله المطلق', 'الشيخ محمد العثيمين', 'د. صالح الفوزان',
            'الشيخ عبدالعزيز بن باز', 'د. ناصر العمر', 'الشيخ سفر الحوالي',
            'د. عوض القرني', 'الشيخ محمد الغامدي',
        ];

        return $names[array_rand($names)];
    }
}
