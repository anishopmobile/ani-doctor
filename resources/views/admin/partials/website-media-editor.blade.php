<section class="border rounded p-3 mt-3">
    <h3 class="h6">تصویر محتوای نمایشی</h3>
    @if($owner->media)
        <img class="img-fluid rounded mb-2" src="{{ route('website.media.show', $owner->media->id) }}" alt="{{ $owner->media->alt_text }}" style="max-height:180px">
        <form method="post" action="{{ route('website.media.destroy', $owner->media) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">حذف تصویر فعلی</button>
        </form>
    @endif
    <form method="post" action="{{ route('website.media.store', [$ownerType, $owner->id]) }}" enctype="multipart/form-data" class="mt-2">
        @csrf
        <label class="d-block">تصویر (JPEG، PNG یا WebP، حداکثر ۲ مگابایت)
            <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
        </label>
        <label class="d-block mt-2">متن جایگزین تصویر
            <input class="form-control" type="text" name="alt_text" maxlength="180" required>
        </label>
        <button type="submit" class="btn btn-outline-primary mt-2">بارگذاری یا جایگزینی تصویر</button>
    </form>
</section>
