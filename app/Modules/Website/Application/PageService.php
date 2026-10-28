<?php

namespace App\Modules\Website\Application;

use App\Modules\SettingsAudit\Application\AuditService;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use App\Modules\Website\Infrastructure\WebsitePage;
use App\Modules\Website\Infrastructure\WebsitePageAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PageService
{
    public function __construct(private readonly PageSchema $schema, private readonly AuditService $audit)
    {
    }

    private function snapshot(WebsitePage $page): array
    {
        return ['title' => $page->title, 'meta_description' => $page->meta_description, 'blocks' => $page->blocks];
    }

    private function version(WebsitePage $page, int $expected): void
    {
        if ($page->version !== $expected) {
            throw ValidationException::withMessages(['expected_version' => 'صفحه در پنجره دیگری تغییر کرده است. نسخه تازه را بارگذاری کنید؛ تغییرات شما ذخیره نشد.']);
        }
    }

    private function assets(WebsitePage $page, array $blocks): void
    {
        $ids = collect($blocks)->pluck('asset_id')->filter()->map(fn ($id) => (int) $id)->unique();
        if ($ids->count() !== $page->assets()->whereIn('id', $ids)->whereHas('media')->count()) {
            throw ValidationException::withMessages(['blocks' => 'تصویر باید از کتابخانه همین صفحه انتخاب شود؛ تصویر حذف‌شده یا متعلق به صفحه دیگر مجاز نیست.']);
        }
    }

    private function record(WebsitePage $page, string $action, int $actor): void
    {
        $page->revisions()->create(['version' => $page->version, 'action' => $action,
            'snapshot' => $this->snapshot($page), 'actor_id' => $actor, 'created_at' => now()]);
        $this->audit->record(
            $actor,
            'website.page.'.$action,
            null,
            ['page_id' => $page->id, 'version' => $page->version, 'published_version' => $page->published_version]
        );
    }

    public function create(array $data, int $actor): WebsitePage
    {
        return DB::transaction(function () use ($data, $actor): WebsitePage {
            $page = new WebsitePage();
            $page->fill(['slug' => $data['slug'], 'title' => $data['title'], 'meta_description' => $data['meta_description'] ?? null,
                'blocks' => [['id' => (string) Str::uuid(), 'type' => 'text', 'heading' => $data['title'], 'text' => '', 'layout' => 'stacked', 'background' => 'plain']]]);
            $page->version = 1;
            $page->save();
            $this->record($page, 'created', $actor);

            return $page;
        });
    }

    public function save(WebsitePage $page, array $data, int $actor): WebsitePage
    {
        $blocks = $this->schema->validate($data['blocks']);

        return DB::transaction(function () use ($page, $data, $blocks, $actor): WebsitePage {
            $page = WebsitePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            $this->version($page, (int) $data['expected_version']);
            $this->assets($page, $blocks);
            $page->fill(['title' => $data['title'], 'meta_description' => $data['meta_description'] ?? null, 'blocks' => $blocks]);
            $page->version++;
            $page->save();
            $this->record($page, 'saved', $actor);

            return $page;
        });
    }

    public function publication(WebsitePage $page, int $expected, int $actor, bool $publish): WebsitePage
    {
        return DB::transaction(function () use ($page, $expected, $actor, $publish): WebsitePage {
            $page = WebsitePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            $this->version($page, $expected);
            if ($publish) {
                $blocks = $this->schema->validate($page->blocks);
                $this->assets($page, $blocks);
            }
            $page->version++;
            $page->published_snapshot = $publish ? $this->snapshot($page) : null;
            $page->published_version = $publish ? $page->version : null;
            $page->save();
            $this->record($page, $publish ? 'published' : 'unpublished', $actor);

            return $page;
        });
    }

    public function restore(WebsitePage $page, int $revision, int $expected, int $actor): WebsitePage
    {
        $snapshot = $page->revisions()->findOrFail($revision)->snapshot;

        return DB::transaction(function () use ($page, $snapshot, $expected, $actor): WebsitePage {
            $page = $this->save($page, $snapshot + ['expected_version' => $expected], $actor);
            $this->audit->record($actor, 'website.page.restored', null, ['page_id' => $page->id, 'version' => $page->version]);

            return $page;
        });
    }

    public function upload(WebsitePage $page, UploadedFile $image, string $alt, int $actor): WebsitePageAsset
    {
        $path = null;
        try {
            return DB::transaction(function () use ($page, $image, $alt, $actor, &$path): WebsitePageAsset {
                $page = WebsitePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
                if ($page->assets()->count() >= 40) {
                    throw ValidationException::withMessages(['image' => 'کتابخانه هر صفحه حداکثر ۴۰ تصویر دارد. تصاویر استفاده‌نشده را حذف کنید.']);
                }
                $asset = $page->assets()->create();
                app(MediaService::class)->save('page_asset', $asset->id, $image, $alt);
                $asset->load('media');
                $path = $asset->media->path;
                $this->audit->record($actor, 'website.page.asset_added', null, ['page_id' => $page->id, 'asset_id' => $asset->id]);

                return $asset;
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function remove(WebsitePage $page, int $expected, int $actor): void
    {
        DB::transaction(function () use ($page, $expected, $actor): void {
            $page = WebsitePage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            $this->version($page, $expected);
            $media = WebsiteMedia::query()->where('owner_type', 'page_asset')->whereIn('owner_id', $page->assets()->select('id'))->get();
            $paths = $media->pluck('path')->all();
            WebsiteMedia::query()->whereIn('id', $media->pluck('id'))->delete();
            $this->audit->record($actor, 'website.page.deleted', ['page_id' => $page->id, 'version' => $page->version], null);
            $page->delete();
            DB::afterCommit(fn () => Storage::disk('local')->delete($paths));
        });
    }

    public function render(WebsitePage $page, bool $preview = false): array
    {
        $snapshot = $preview ? $this->snapshot($page) : $page->published_snapshot;
        abort_unless(is_array($snapshot), 404);
        $blocks = $snapshot['blocks'];
        $assets = $page->assets()->with('media')->whereIn('id', collect($blocks)->pluck('asset_id')->filter())->get()->keyBy('id');
        $services = collect();
        $articles = collect();
        if (collect($blocks)->contains('type', 'services')) {
            $services = app(ServiceContentService::class)->catalogQuery()->limit(12)->get();
        }
        if (collect($blocks)->contains('type', 'articles')) {
            $articles = app(ArticleService::class)->publicQuery()->with('media')->orderByDesc('published_at')->limit(12)->get();
        }

        return ['page' => $page, 'snapshot' => $snapshot, 'pageAssets' => $assets, 'pageServices' => $services, 'pageArticles' => $articles,
            'isPreview' => $preview, 'canonicalUrl' => $page->slug === 'home' ? route('website.home') : route('website.pages.show', $page->slug)];
    }
}
