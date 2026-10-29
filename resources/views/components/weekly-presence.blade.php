@props(['presence'])
<p class="weekly-status">{{ App\Modules\Scheduling\Application\WeeklyAnnouncementService::STATUSES[$presence['status']] }}</p>
@foreach($presence['shifts'] as $shift)
    <p class="weekly-time"><x-ui-icon name="clock"/>@if($shift['label'])<span>{{ $shift['label'] }}</span>@endif <bdi>{{ $shift['start'] }}</bdi><span>تا</span><bdi>{{ $shift['end'] }}</bdi></p>
@endforeach
