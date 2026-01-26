<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_article_without_issue_id()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $section = Section::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/articles', [
            'title' => 'Test Article',
            'slug' => 'test-article',
            'content' => 'Content here',
            'section_id' => $section->id,
            'author_name' => 'Author',
            'status' => 'published',
            'gregorian_date' => '2023-01-01',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('articles', [
            'title' => 'Test Article',
            // 'issue_id' => null // This column should not exist
        ]);
        
        // Ensure column is gone (this is a bit meta, usually we trust migration)
        // But we can check if model throws error if we try to access it? 
        // No, model just ignores it if not in fillable.
    }

    public function test_can_create_article_without_author_name()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $section = Section::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/articles', [
            'title' => 'Test Article No Author',
            'slug' => 'test-article-no-author',
            'content' => 'Content here',
            'section_id' => $section->id,
            // 'author_name' => 'Author', // Omitted
            'status' => 'published',
            'gregorian_date' => '2023-01-01',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('articles', [
            'title' => 'Test Article No Author',
            'author_name' => null,
        ]);
    }
}
