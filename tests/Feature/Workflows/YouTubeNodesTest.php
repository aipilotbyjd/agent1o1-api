<?php

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\YouTube\YouTubeAddVideoToPlaylistNode;
use App\Nodes\Integrations\YouTube\YouTubeCreatePlaylistNode;
use App\Nodes\Integrations\YouTube\YouTubeDeleteCommentNode;
use App\Nodes\Integrations\YouTube\YouTubeDeletePlaylistNode;
use App\Nodes\Integrations\YouTube\YouTubeDeleteVideoNode;
use App\Nodes\Integrations\YouTube\YouTubeGetCaptionsNode;
use App\Nodes\Integrations\YouTube\YouTubeGetChannelNode;
use App\Nodes\Integrations\YouTube\YouTubeGetVideoNode;
use App\Nodes\Integrations\YouTube\YouTubeListCommentsNode;
use App\Nodes\Integrations\YouTube\YouTubeListPlaylistsNode;
use App\Nodes\Integrations\YouTube\YouTubeListPlaylistVideosNode;
use App\Nodes\Integrations\YouTube\YouTubeListSubscribersNode;
use App\Nodes\Integrations\YouTube\YouTubeListSubscriptionsNode;
use App\Nodes\Integrations\YouTube\YouTubePostCommentNode;
use App\Nodes\Integrations\YouTube\YouTubeRateVideoNode;
use App\Nodes\Integrations\YouTube\YouTubeRemoveVideoFromPlaylistNode;
use App\Nodes\Integrations\YouTube\YouTubeReplyToCommentNode;
use App\Nodes\Integrations\YouTube\YouTubeSearchVideosNode;
use App\Nodes\Integrations\YouTube\YouTubeSubscribeToChannelNode;
use App\Nodes\Integrations\YouTube\YouTubeUnsubscribeFromChannelNode;
use App\Nodes\Integrations\YouTube\YouTubeUpdatePlaylistNode;
use App\Nodes\Integrations\YouTube\YouTubeUpdateVideoNode;
use App\Nodes\Integrations\YouTube\YouTubeUploadVideoNode;
use App\Services\Ai\EndpointUrlGuard;
use App\Services\Workflows\NodeRegistry;
use Illuminate\Support\Facades\Http;

const YT_BASE = ['access_token' => 'ya29-test'];

it('registers every youtube node under the google_youtube category', function () {
    foreach ([
        'search_videos', 'get_video', 'get_channel', 'list_playlist_videos', 'list_comments',
        'list_playlists', 'create_playlist', 'update_playlist', 'delete_playlist', 'add_video_to_playlist', 'remove_video_from_playlist',
        'update_video', 'rate_video', 'delete_video', 'post_comment', 'reply_to_comment', 'delete_comment',
        'list_subscriptions', 'subscribe_to_channel', 'unsubscribe_from_channel', 'list_subscribers', 'upload_video', 'get_captions',
    ] as $suffix) {
        expect(app(NodeRegistry::class)->resolve("google_youtube_{$suffix}")->category())->toBe('google_youtube');
    }
});

it('searches videos and flattens each result', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => ['kind' => 'youtube#video', 'videoId' => 'abc123XYZ_-'],
        'snippet' => ['title' => 'Intro', 'channelId' => 'UC1', 'channelTitle' => 'Ch', 'publishedAt' => '2026-01-01T00:00:00Z', 'thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/x.jpg']]],
    ]]])]);

    $output = (new YouTubeSearchVideosNode)->execute(Run::factory()->create(), [...YT_BASE, 'query' => 'laravel', 'order' => 'date', 'channel_id' => 'UC1'], []);

    expect($output['videos'][0])->toBe([
        'video_id' => 'abc123XYZ_-',
        'title' => 'Intro',
        'channel_id' => 'UC1',
        'channel_title' => 'Ch',
        'published_at' => '2026-01-01T00:00:00Z',
        'thumbnail' => 'https://i.ytimg.com/x.jpg',
        'url' => 'https://www.youtube.com/watch?v=abc123XYZ_-',
    ]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/youtube/v3/search')
        && str_contains($request->url(), 'type=video') && str_contains($request->url(), 'order=date') && str_contains($request->url(), 'channelId=UC1'));
});

it('reads a video by link with its counts', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => 'dQw4w9WgXcQ',
        'snippet' => ['title' => 'Song', 'channelId' => 'UC1'],
        'contentDetails' => ['duration' => 'PT3M33S'],
        'statistics' => ['viewCount' => '100', 'likeCount' => '10', 'commentCount' => '2'],
    ]]])]);

    $output = (new YouTubeGetVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5s'], []);

    expect($output)->toMatchArray(['video_id' => 'dQw4w9WgXcQ', 'duration' => 'PT3M33S', 'views' => 100, 'likes' => 10, 'comments' => 2]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'id=dQw4w9WgXcQ'));
});

it('reads a video from a short link', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [['id' => 'dQw4w9WgXcQ', 'snippet' => []]]])]);

    (new YouTubeGetVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'https://youtu.be/dQw4w9WgXcQ?si=x'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'id=dQw4w9WgXcQ'));
});

it('fails clearly when a video does not exist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => []])]);

    (new YouTubeGetVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'nope'], []);
})->throws(RuntimeException::class, 'no video found');

it('reads the connected account\'s own channel when none is given', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => 'UC1', 'snippet' => ['title' => 'Me', 'customUrl' => '@me'],
        'statistics' => ['subscriberCount' => '1200', 'videoCount' => '30', 'viewCount' => '99999'],
        'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU1']],
    ]]])]);

    $output = (new YouTubeGetChannelNode)->execute(Run::factory()->create(), YT_BASE, []);

    expect($output)->toMatchArray(['channel_id' => 'UC1', 'handle' => '@me', 'subscribers' => 1200, 'videos' => 30, 'views' => 99999, 'uploads_playlist_id' => 'UU1', 'url' => 'https://www.youtube.com/channel/UC1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'mine=true') && ! str_contains($request->url(), 'forHandle'));
});

it('looks a channel up by handle or by id', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [['id' => 'UC2', 'snippet' => []]]])]);

    (new YouTubeGetChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => '@laravel'], []);
    (new YouTubeGetChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => 'UC2'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'forHandle=%40laravel') && ! str_contains($request->url(), 'mine='));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'id=UC2'));
});

it('fails clearly when a channel does not exist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response([])]);

    (new YouTubeGetChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => 'UCnone'], []);
})->throws(RuntimeException::class, 'no channel found');

it('lists a playlist\'s videos', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'snippet' => ['title' => 'Ep 1', 'resourceId' => ['videoId' => 'v1'], 'videoOwnerChannelId' => 'UC1', 'channelTitle' => 'Ch'],
        'contentDetails' => ['videoId' => 'v1', 'videoPublishedAt' => '2026-02-02T00:00:00Z'],
    ]]])]);

    $output = (new YouTubeListPlaylistVideosNode)->execute(Run::factory()->create(), [...YT_BASE, 'playlist_id' => 'PL1'], []);

    expect($output['videos'][0])->toMatchArray(['video_id' => 'v1', 'title' => 'Ep 1', 'published_at' => '2026-02-02T00:00:00Z', 'channel_id' => 'UC1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/playlistItems') && str_contains($request->url(), 'playlistId=PL1'));
});

it('turns a channel id into its uploads playlist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => []])]);

    (new YouTubeListPlaylistVideosNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel_id' => 'UCabc123'], []);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'playlistId=UUabc123'));
});

it('needs a playlist or a channel', function () {
    Http::fake();

    (new YouTubeListPlaylistVideosNode)->execute(Run::factory()->create(), YT_BASE, []);
})->throws(InvalidArgumentException::class, 'choose a playlist or enter a channel ID');

it('triggers on new videos, newest first, keyed by video id', function () {
    $node = new YouTubeListPlaylistVideosNode;

    expect($node->triggerItems(['videos' => [['video_id' => 'v1']]], []))->toHaveCount(1)
        ->and($node->triggerItemId(['video_id' => 'v1']))->toBe('v1')
        ->and($node->triggerListsNewestFirst())->toBeTrue()
        ->and($node->triggerName())->toBe('YouTube: New video');
});

it('lists the newest comments and triggers on them', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => 'c1',
        'snippet' => ['totalReplyCount' => 2, 'topLevelComment' => ['snippet' => [
            'authorDisplayName' => 'Ada', 'authorChannelId' => ['value' => 'UCada'], 'textDisplay' => 'Great', 'likeCount' => 3, 'publishedAt' => '2026-01-01T00:00:00Z', 'videoId' => 'v1',
        ]]],
    ]]])]);

    $node = new YouTubeListCommentsNode;
    $output = $node->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'v1'], []);

    expect($output['comments'][0])->toBe([
        'comment_id' => 'c1', 'author' => 'Ada', 'author_channel_id' => 'UCada', 'text' => 'Great', 'likes' => 3, 'reply_count' => 2, 'published_at' => '2026-01-01T00:00:00Z', 'video_id' => 'v1',
    ]);
    expect($node->triggerItemId($output['comments'][0]))->toBe('c1');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/commentThreads') && str_contains($request->url(), 'order=time'));
});

it('shows the youtube error message', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['error' => ['message' => 'quotaExceeded']], 403)]);

    (new YouTubeSearchVideosNode)->execute(Run::factory()->create(), [...YT_BASE, 'query' => 'x'], []);
})->throws(RuntimeException::class, 'YouTube API error [/search]: quotaExceeded');

it('marks reads, writes and deletions so an agent asks before the risky ones', function () {
    $effects = [
        YouTubeListPlaylistsNode::class => ActionEffect::Read,
        YouTubeListSubscriptionsNode::class => ActionEffect::Read,
        YouTubeListSubscribersNode::class => ActionEffect::Read,
        YouTubeCreatePlaylistNode::class => ActionEffect::Write,
        YouTubePostCommentNode::class => ActionEffect::Write,
        YouTubeRateVideoNode::class => ActionEffect::Write,
        YouTubeDeleteVideoNode::class => ActionEffect::Destructive,
        YouTubeDeletePlaylistNode::class => ActionEffect::Destructive,
        YouTubeDeleteCommentNode::class => ActionEffect::Destructive,
        YouTubeRemoveVideoFromPlaylistNode::class => ActionEffect::Destructive,
        YouTubeUnsubscribeFromChannelNode::class => ActionEffect::Destructive,
    ];

    foreach ($effects as $class => $effect) {
        expect((new $class)->effect([]))->toBe($effect);
    }
});

it('lists the account\'s own playlists, or a channel\'s', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => 'PL1', 'snippet' => ['title' => 'Mix', 'channelId' => 'UC1'], 'status' => ['privacyStatus' => 'public'], 'contentDetails' => ['itemCount' => 4],
    ]]])]);

    $output = (new YouTubeListPlaylistsNode)->execute(Run::factory()->create(), YT_BASE, []);
    (new YouTubeListPlaylistsNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel_id' => 'UC9'], []);

    expect($output['playlists'][0])->toMatchArray(['playlist_id' => 'PL1', 'title' => 'Mix', 'privacy' => 'public', 'video_count' => 4, 'url' => 'https://www.youtube.com/playlist?list=PL1']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/playlists') && str_contains($request->url(), 'mine=true'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'channelId=UC9') && ! str_contains($request->url(), 'mine='));
});

it('creates a private playlist by default', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['id' => 'PL2', 'snippet' => ['title' => 'New']])]);

    $output = (new YouTubeCreatePlaylistNode)->execute(Run::factory()->create(), [...YT_BASE, 'title' => 'New'], []);

    expect($output)->toMatchArray(['playlist_id' => 'PL2', 'title' => 'New']);
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/playlists?part=snippet,status')
        && $request['snippet']['title'] === 'New' && $request['status']['privacyStatus'] === 'private');
});

it('keeps a playlist\'s other fields when updating one', function () {
    Http::fake([
        'www.googleapis.com/youtube/v3/playlists?part=snippet%2Cstatus&id=PL1' => Http::response(['items' => [['id' => 'PL1', 'snippet' => ['title' => 'Old', 'description' => 'Keep me'], 'status' => ['privacyStatus' => 'unlisted']]]]),
        'www.googleapis.com/youtube/*' => Http::response(['id' => 'PL1', 'snippet' => ['title' => 'Renamed']]),
    ]);

    (new YouTubeUpdatePlaylistNode)->execute(Run::factory()->create(), [...YT_BASE, 'playlist_id' => 'PL1', 'title' => 'Renamed'], []);

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request['id'] === 'PL1' && $request['snippet'] === ['title' => 'Renamed', 'description' => 'Keep me']
        && $request['status']['privacyStatus'] === 'unlisted');
});

it('fails clearly when the playlist to update does not exist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => []])]);

    (new YouTubeUpdatePlaylistNode)->execute(Run::factory()->create(), [...YT_BASE, 'playlist_id' => 'PLnone'], []);
})->throws(RuntimeException::class, 'no playlist found');

it('deletes a playlist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response('', 204)]);

    $output = (new YouTubeDeletePlaylistNode)->execute(Run::factory()->create(), [...YT_BASE, 'playlist_id' => 'PL1'], []);

    expect($output)->toBe(['deleted' => true, 'playlist_id' => 'PL1']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/playlists?id=PL1'));
});

it('adds a video to a playlist from a link, at a position', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['id' => 'item1', 'snippet' => ['position' => 0]])]);

    $output = (new YouTubeAddVideoToPlaylistNode)->execute(Run::factory()->create(), [...YT_BASE, 'playlist_id' => 'PL1', 'video_id' => 'https://youtu.be/dQw4w9WgXcQ', 'position' => 0], []);

    expect($output)->toBe(['playlist_item_id' => 'item1', 'playlist_id' => 'PL1', 'video_id' => 'dQw4w9WgXcQ', 'position' => 0]);
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['snippet']['playlistId'] === 'PL1'
        && $request['snippet']['resourceId'] === ['kind' => 'youtube#video', 'videoId' => 'dQw4w9WgXcQ']
        && $request['snippet']['position'] === 0);
});

it('removes a video from a playlist by finding its playlist item', function () {
    Http::fake([
        'www.googleapis.com/youtube/v3/playlistItems?*' => Http::sequence()
            ->push(['items' => [['id' => 'itemA'], ['id' => 'itemB']]])
            ->push('', 204)
            ->push('', 204),
    ]);

    $output = (new YouTubeRemoveVideoFromPlaylistNode)->execute(Run::factory()->create(), [...YT_BASE, 'playlist_id' => 'PL1', 'video_id' => 'dQw4w9WgXcQ'], []);

    expect($output)->toBe(['removed' => 2, 'playlist_id' => 'PL1', 'video_id' => 'dQw4w9WgXcQ']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'id=itemA'));
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'id=itemB'));
});

it('fails clearly when the video is not in the playlist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => []])]);

    (new YouTubeRemoveVideoFromPlaylistNode)->execute(Run::factory()->create(), [...YT_BASE, 'playlist_id' => 'PL1', 'video_id' => 'dQw4w9WgXcQ'], []);
})->throws(RuntimeException::class, 'is not in that playlist');

it('sends a video\'s current settings back with the change, since an update resets what it omits', function () {
    Http::fake([
        'www.googleapis.com/youtube/v3/videos?part=snippet%2Cstatus&id=dQw4w9WgXcQ' => Http::response(['items' => [[
            'id' => 'dQw4w9WgXcQ',
            'snippet' => ['title' => 'Old', 'description' => 'Desc', 'categoryId' => '22', 'tags' => ['a'], 'channelId' => 'UC1', 'thumbnails' => []],
            'status' => ['privacyStatus' => 'private', 'embeddable' => true, 'uploadStatus' => 'processed'],
        ]]]),
        'www.googleapis.com/youtube/*' => Http::response(['id' => 'dQw4w9WgXcQ', 'snippet' => ['title' => 'New']]),
    ]);

    (new YouTubeUpdateVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'dQw4w9WgXcQ', 'title' => 'New', 'privacy' => 'public'], []);

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request['snippet'] === ['title' => 'New', 'description' => 'Desc', 'categoryId' => '22', 'tags' => ['a']]
        && $request['status'] === ['privacyStatus' => 'public', 'embeddable' => true]);
});

it('fails clearly when the video to update does not exist', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => []])]);

    (new YouTubeUpdateVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'nope'], []);
})->throws(RuntimeException::class, 'no video found');

it('rates and deletes a video', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response('', 204)]);

    $rated = (new YouTubeRateVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'dQw4w9WgXcQ', 'rating' => 'like'], []);
    $deleted = (new YouTubeDeleteVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'dQw4w9WgXcQ'], []);

    expect($rated)->toBe(['video_id' => 'dQw4w9WgXcQ', 'rating' => 'like'])
        ->and($deleted)->toBe(['deleted' => true, 'video_id' => 'dQw4w9WgXcQ']);
    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/videos/rate?id=dQw4w9WgXcQ&rating=like'));
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/videos?id=dQw4w9WgXcQ'));
});

it('posts a comment and replies to one', function () {
    Http::fake([
        'www.googleapis.com/youtube/v3/commentThreads*' => Http::response(['id' => 'c1', 'snippet' => ['topLevelComment' => ['snippet' => ['textDisplay' => 'Nice', 'publishedAt' => '2026-01-01T00:00:00Z']]]]),
        'www.googleapis.com/youtube/v3/comments*' => Http::response(['id' => 'r1', 'snippet' => ['textDisplay' => 'Thanks', 'publishedAt' => '2026-01-02T00:00:00Z']]),
    ]);

    $comment = (new YouTubePostCommentNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'text' => 'Nice'], []);
    $reply = (new YouTubeReplyToCommentNode)->execute(Run::factory()->create(), [...YT_BASE, 'comment_id' => 'c1', 'text' => 'Thanks'], []);

    expect($comment)->toBe(['comment_id' => 'c1', 'video_id' => 'dQw4w9WgXcQ', 'text' => 'Nice', 'published_at' => '2026-01-01T00:00:00Z'])
        ->and($reply)->toBe(['reply_id' => 'r1', 'comment_id' => 'c1', 'text' => 'Thanks', 'published_at' => '2026-01-02T00:00:00Z']);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/commentThreads')
        && $request['snippet']['videoId'] === 'dQw4w9WgXcQ' && $request['snippet']['topLevelComment']['snippet']['textOriginal'] === 'Nice');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/comments?part=snippet')
        && $request['snippet'] === ['parentId' => 'c1', 'textOriginal' => 'Thanks']);
});

it('deletes a comment', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response('', 204)]);

    $output = (new YouTubeDeleteCommentNode)->execute(Run::factory()->create(), [...YT_BASE, 'comment_id' => 'c1'], []);

    expect($output)->toBe(['deleted' => true, 'comment_id' => 'c1']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/comments?id=c1'));
});

it('lists the channels the account subscribes to', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => 'sub1', 'snippet' => ['title' => 'Laravel', 'publishedAt' => '2026-01-01T00:00:00Z', 'resourceId' => ['channelId' => 'UC7'], 'thumbnails' => ['default' => ['url' => 'https://i.ytimg.com/t.jpg']]],
    ]]])]);

    $output = (new YouTubeListSubscriptionsNode)->execute(Run::factory()->create(), YT_BASE, []);

    expect($output['subscriptions'][0])->toBe([
        'subscription_id' => 'sub1', 'channel_id' => 'UC7', 'title' => 'Laravel', 'thumbnail' => 'https://i.ytimg.com/t.jpg',
        'subscribed_at' => '2026-01-01T00:00:00Z', 'url' => 'https://www.youtube.com/channel/UC7',
    ]);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'mine=true'));
});

it('subscribes to a channel by handle', function () {
    Http::fake([
        'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UC7']]]),
        'www.googleapis.com/youtube/v3/subscriptions*' => Http::response(['id' => 'sub1', 'snippet' => ['title' => 'Laravel', 'resourceId' => ['channelId' => 'UC7']]]),
    ]);

    $output = (new YouTubeSubscribeToChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => '@laravel'], []);

    expect($output)->toMatchArray(['subscription_id' => 'sub1', 'channel_id' => 'UC7']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'forHandle=%40laravel'));
    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['snippet']['resourceId'] === ['kind' => 'youtube#channel', 'channelId' => 'UC7']);
});

it('fails clearly when a handle matches no channel', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => []])]);

    (new YouTubeSubscribeToChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => '@nobody'], []);
})->throws(RuntimeException::class, 'no channel found with the handle');

it('unsubscribes by finding the subscription for the channel', function () {
    Http::fake([
        'www.googleapis.com/youtube/v3/subscriptions?part=id*' => Http::response(['items' => [['id' => 'sub1']]]),
        'www.googleapis.com/youtube/v3/subscriptions?id=sub1' => Http::response('', 204),
    ]);

    $output = (new YouTubeUnsubscribeFromChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => 'UC7'], []);

    expect($output)->toBe(['unsubscribed' => true, 'channel_id' => 'UC7']);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'forChannelId=UC7'));
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'id=sub1'));
});

it('fails clearly when not subscribed to the channel', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => []])]);

    (new YouTubeUnsubscribeFromChannelNode)->execute(Run::factory()->create(), [...YT_BASE, 'channel' => 'UC7'], []);
})->throws(RuntimeException::class, 'not subscribed to the channel');

it('lists subscribers as the subscriber, and triggers on new ones keyed by channel id', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [[
        'id' => 'sub9', 'snippet' => ['publishedAt' => '2026-03-03T00:00:00Z', 'resourceId' => ['channelId' => 'UCme']],
        'subscriberSnippet' => ['channelId' => 'UCfan', 'title' => 'A Fan', 'thumbnails' => ['default' => ['url' => 'https://i.ytimg.com/f.jpg']]],
    ]]])]);

    $node = new YouTubeListSubscribersNode;
    $output = $node->execute(Run::factory()->create(), YT_BASE, []);

    expect($output['subscribers'][0])->toMatchArray(['channel_id' => 'UCfan', 'title' => 'A Fan', 'subscribed_at' => '2026-03-03T00:00:00Z', 'url' => 'https://www.youtube.com/channel/UCfan'])
        ->and($node->triggerItems($output, []))->toHaveCount(1)
        ->and($node->triggerItemId($output['subscribers'][0]))->toBe('UCfan')
        ->and($node->triggerName())->toBe('YouTube: New subscriber');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'mySubscribers=true') && str_contains($request->url(), 'subscriberSnippet'));
});

it('uploads a video from a link through a resumable session', function () {
    Http::fake([
        'cdn.example.test/*' => Http::response('video-bytes', 200, ['Content-Type' => 'video/mp4']),
        'www.googleapis.com/upload/youtube/v3/videos?upload_id=abc' => Http::response(['id' => 'newVid12345', 'snippet' => ['title' => 'Recap']]),
        'www.googleapis.com/upload/youtube/v3/videos?*' => Http::response('', 200, ['Location' => 'https://www.googleapis.com/upload/youtube/v3/videos?upload_id=abc']),
    ]);
    app()->instance(EndpointUrlGuard::class, new class extends EndpointUrlGuard
    {
        public function problem(string $url): ?string
        {
            return null;
        }
    });

    $output = (new YouTubeUploadVideoNode)->execute(Run::factory()->create(), [
        ...YT_BASE, 'video_url' => 'https://cdn.example.test/v.mp4', 'title' => 'Recap', 'tags' => ['a', ' '], 'made_for_kids' => false,
    ], []);

    expect($output)->toMatchArray(['video_id' => 'newVid12345', 'title' => 'Recap']);
    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), 'uploadType=resumable')
        && $request->header('X-Upload-Content-Type') === ['video/mp4'] && $request->header('X-Upload-Content-Length') === ['11']
        && $request['snippet'] === ['title' => 'Recap', 'tags' => ['a']]
        && $request['status'] === ['privacyStatus' => 'private', 'selfDeclaredMadeForKids' => false]);
    Http::assertSent(fn ($request) => $request->method() === 'PUT' && $request->body() === 'video-bytes');
});

it('refuses to upload from a private address', function () {
    Http::fake();

    (new YouTubeUploadVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_url' => 'http://169.254.169.254/latest', 'title' => 'x'], []);
})->throws(InvalidArgumentException::class, 'YouTube:');

it('fails clearly when the video file cannot be downloaded', function () {
    Http::fake(['cdn.example.test/*' => Http::response('', 404)]);
    app()->instance(EndpointUrlGuard::class, new class extends EndpointUrlGuard
    {
        public function problem(string $url): ?string
        {
            return null;
        }
    });

    (new YouTubeUploadVideoNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_url' => 'https://cdn.example.test/v.mp4', 'title' => 'x'], []);
})->throws(InvalidArgumentException::class, 'could not download');

it('reads the captions of an owned video in the chosen language', function () {
    Http::fake([
        'www.googleapis.com/youtube/v3/captions?*' => Http::response(['items' => [
            ['id' => 'cap-fr', 'snippet' => ['language' => 'fr']],
            ['id' => 'cap-en', 'snippet' => ['language' => 'en']],
        ]]),
        'www.googleapis.com/youtube/v3/captions/cap-en*' => Http::response("1\n00:00:00,000 --> 00:00:01,000\nHello\n"),
    ]);

    $output = (new YouTubeGetCaptionsNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'dQw4w9WgXcQ', 'language' => 'EN'], []);

    expect($output)->toMatchArray(['caption_id' => 'cap-en', 'language' => 'en', 'format' => 'srt'])
        ->and($output['captions'])->toContain('Hello');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/captions/cap-en') && str_contains($request->url(), 'tfmt=srt'));
});

it('fails clearly when a video has no captions in that language', function () {
    Http::fake(['www.googleapis.com/youtube/*' => Http::response(['items' => [['id' => 'c', 'snippet' => ['language' => 'fr']]]])]);

    (new YouTubeGetCaptionsNode)->execute(Run::factory()->create(), [...YT_BASE, 'video_id' => 'dQw4w9WgXcQ', 'language' => 'en'], []);
})->throws(RuntimeException::class, 'no captions found');
