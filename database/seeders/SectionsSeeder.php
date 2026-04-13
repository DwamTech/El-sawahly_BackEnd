<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SectionsSeeder extends Seeder
{
    /**
     * The only sections that should exist after seeding.
     * Any section NOT in this list will be deleted.
     */
    private array $required = [
        [
            'name'    => 'معتقدات',
            'name_sw' => 'ITIKADI',
            'type'    => 'مقال',
            'slug'    => 'muqatadat',
        ],
        [
            'name'    => 'شبهات',
            'name_sw' => 'SHUBUHATI',
            'type'    => 'مقال',
            'slug'    => 'shubuhat',
        ],
        [
            'name'    => 'فتاوي',
            'name_sw' => 'FATWA',
            'type'    => 'مقال',
            'slug'    => 'fatawa',
        ],
        [
            'name'    => 'مقالات',
            'name_sw' => 'MAKALA',
            'type'    => 'مقال',
            'slug'    => 'maqalat',
        ],
        [
            'name'    => 'كتب',
            'name_sw' => 'VITUKO',
            'type'    => 'كتب',
            'slug'    => 'kutub',
        ],
        [
            'name'    => 'فيديوهات',
            'name_sw' => 'VEDIO',
            'type'    => 'فيديو',
            'slug'    => 'videos',
        ],
        [
            'name'    => 'صوتيات',
            'name_sw' => 'SAUTI',
            'type'    => 'صوت',
            'slug'    => 'audios',
        ],
    ];

    public function run(): void
    {
        $requiredSlugs = array_column($this->required, 'slug');

        // Upsert required sections
        foreach ($this->required as $data) {
            Section::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name'      => $data['name'],
                    'name_sw'   => $data['name_sw'],
                    'type'      => $data['type'],
                    'slug'      => $data['slug'],
                    'is_active' => true,
                ]
            );
        }

        // Remove any section NOT in the required list
        Section::whereNotIn('slug', $requiredSlugs)->delete();

        $this->command->info('Sections seeded: '.implode(', ', array_column($this->required, 'name')));
    }
}
