<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteMediaRequest;
use App\Http\Requests\WebsitePageRequest;
use App\Http\Requests\WebsitePageVersionRequest;
use App\Modules\Website\Application\MediaService;
use App\Modules\Website\Application\PageService;
use App\Modules\Website\Infrastructure\WebsitePage;
use Illuminate\Http\Request;

final class WebsitePageController extends Controller
{
    public function index()
    {
        return view('admin.website-pages', ['pages' => WebsitePage::query()->orderBy('slug')->paginate(20)]);
    }

    public function store(WebsitePageRequest $request, PageService $pages)
    {
        $page = $pages->create($request->validated(), $request->user()->id);

        return redirect()->route('website.pages.edit', $page);
    }

    public function edit(WebsitePage $page)
    {
        return view('admin.website-page-builder', ['page' => $page,
            'assets' => $page->assets()->with('media')->orderBy('id')->get(),
            'revisions' => $page->revisions()->orderByDesc('version')->limit(50)->get()]);
    }

    private function saved(Request $request, WebsitePage $page)
    {
        if ($request->expectsJson()) {
            return response()->json(['version' => $page->version, 'published_version' => $page->published_version,
                'preview_url' => route('website.pages.preview', $page), 'edit_url' => route('website.pages.edit', $page)]);
        }

        return redirect()->route('website.pages.edit', $page)->with('status', 'نسخه صفحه ذخیره شد.');
    }

    public function update(WebsitePageRequest $request, WebsitePage $page, PageService $pages)
    {
        return $this->saved($request, $pages->save($page, $request->validated(), $request->user()->id));
    }

    public function publish(WebsitePageVersionRequest $request, WebsitePage $page, PageService $pages)
    {
        return $this->saved($request, $pages->publication($page, (int) $request->validated('expected_version'), $request->user()->id, true));
    }

    public function unpublish(WebsitePageVersionRequest $request, WebsitePage $page, PageService $pages)
    {
        return $this->saved($request, $pages->publication($page, (int) $request->validated('expected_version'), $request->user()->id, false));
    }

    public function restore(WebsitePageVersionRequest $request, WebsitePage $page, int $revision, PageService $pages)
    {
        return $this->saved($request, $pages->restore($page, $revision, (int) $request->validated('expected_version'), $request->user()->id));
    }

    public function preview(WebsitePage $page, PageService $pages)
    {
        return response()->view('website.pages.show', $pages->render($page, true))->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function upload(WebsiteMediaRequest $request, WebsitePage $page, PageService $pages)
    {
        $pages->upload($page, $request->file('image'), $request->validated('alt_text'), $request->user()->id);

        return redirect()->route('website.pages.edit', $page)->with('status', 'تصویر به کتابخانه خصوصی صفحه اضافه شد؛ شناسه آن را در بلوک انتخاب کنید.');
    }

    public function removeAsset(Request $request, WebsitePage $page, int $asset, MediaService $media)
    {
        $item = $page->assets()->with('media')->findOrFail($asset);
        abort_unless($item->media, 404);
        $media->remove($item->media);

        return redirect()->route('website.pages.edit', $page)->with('status', 'تصویر و فایل آن حذف شدند.');
    }

    public function destroy(WebsitePageVersionRequest $request, WebsitePage $page, PageService $pages)
    {
        $pages->remove($page, (int) $request->validated('expected_version'), $request->user()->id);

        return redirect()->route('website.pages.index')->with('status', 'صفحه و تصاویر آن حذف شدند.');
    }
}
