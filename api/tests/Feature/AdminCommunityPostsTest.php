<?php

namespace Tests\Feature;

use App\Filament\Resources\CommunityPosts\Pages\ListCommunityPosts;
use App\Filament\Resources\CommunityPosts\Pages\ViewCommunityPost;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Models\CommunityPost;
use App\Models\CommunityPostReport;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCommunityPostsTest extends TestCase
{
    use RefreshDatabase;

    private function reportedThread(): array
    {
        $ku = University::create(['name' => 'Kolej Universiti', 'short_name' => 'KU', 'country' => 'Malaysia', 'active' => true]);
        $author = User::factory()->create(['status' => 'active']);
        $parent = CommunityPost::create(['user_id' => $author->id, 'university_id' => $ku->id, 'body' => 'Parent post', 'reply_count' => 1]);
        $reply = CommunityPost::create(['user_id' => $author->id, 'university_id' => $ku->id, 'parent_id' => $parent->id, 'body' => 'Bad reply', 'status' => 'hidden', 'hidden_reason' => 'reports', 'report_count' => 1]);
        CommunityPostReport::create(['community_post_id' => $reply->id, 'reporter_id' => User::factory()->create()->id, 'reason' => 'spam', 'note' => 'ads']);

        return [$parent, $reply, $author];
    }

    public function test_list_view_and_widget_render(): void
    {
        [$parent, $reply] = $this->reportedThread();
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']), 'web');

        Livewire::test(ListCommunityPosts::class)->assertOk()->assertCanSeeTableRecords([$reply])->assertCanNotSeeTableRecords([$parent]);
        Livewire::test(ViewCommunityPost::class, ['record' => $reply->id])->assertOk()->assertSee('Parent post')->assertSee('ads');
        Livewire::test(ViewCommunityPost::class, ['record' => $parent->id])->assertOk()->assertSee('Bad reply');
        Livewire::test(NeedsAttentionWidget::class)->assertOk()->assertSee('Reported posts');
    }

    public function test_remove_and_suspend_author_actions(): void
    {
        [, $reply, $author] = $this->reportedThread();
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']), 'web');

        Livewire::test(ViewCommunityPost::class, ['record' => $reply->id])
            ->callAction('remove', data: ['reason' => 'spam'])
            ->assertHasNoActionErrors();
        $this->assertSame('removed', $reply->fresh()->status);
        $this->assertDatabaseHas('community_post_reports', ['community_post_id' => $reply->id, 'resolution' => 'removed']);

        Livewire::test(ViewCommunityPost::class, ['record' => $reply->id])
            ->callAction('suspendAuthor', data: ['reason' => 'repeat spam'])
            ->assertHasNoActionErrors();
        $this->assertSame('suspended', $author->fresh()->status);
    }
}
