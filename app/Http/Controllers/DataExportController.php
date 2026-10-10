<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\CalendarEvent;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\ConnectedCalendar;
use App\Models\DailyPlan;
use App\Models\Domain;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\Observation;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        $sections = [
            'domains' => Domain::forUser($user)->withTrashed(),
            'projects' => Project::forUser($user)->withTrashed(),
            'tasks' => Task::forUser($user)->withTrashed(),
            'milestones' => Milestone::forUser($user)->withTrashed(),
            'people' => Person::forUser($user)->withTrashed(),
            'notes' => Note::forUser($user)->withTrashed(),
            'activities' => ActivityLog::forUser($user)->withTrashed(),
            'daily_plans' => DailyPlan::forUser($user),
            'captures' => Capture::forUser($user)->select(['id', 'raw_text', 'source', 'mode', 'timezone', 'client_captured_at', 'status', 'created_at', 'updated_at']),
            'capture_items' => CaptureItem::forUser($user)->select(['id', 'capture_id', 'sequence', 'action_type', 'excerpt', 'payload', 'status', 'target_type', 'target_id', 'executed_at', 'undone_at']),
            'observations' => Observation::forUser($user),
            'calendars' => ConnectedCalendar::forUser($user)->select(['id', 'name', 'timezone', 'mode', 'reminder_minutes']),
            'calendar_events' => CalendarEvent::forUser($user)->select(['id', 'connected_calendar_id', 'title', 'description', 'location', 'starts_at', 'ends_at', 'all_day', 'start_date', 'end_date', 'status', 'removed_at']),
            'preferences' => AppSetting::forUser($user)->where('key', 'timezone')->select(['key', 'value']),
        ];

        return response()->streamDownload(function () use ($sections) {
            echo '{"format":"chart-personal-export","version":1,"exported_at":'.json_encode(now()->toIso8601String()).',"data":{';
            $firstSection = true;
            foreach ($sections as $name => $query) {
                echo ($firstSection ? '' : ',').json_encode($name).':[';
                $firstSection = false;
                $first = true;
                foreach ($query->orderBy('id')->lazy(200) as $record) {
                    echo ($first ? '' : ',').json_encode($record->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                    $first = false;
                }
                echo ']';
            }
            echo '}}';
        }, 'chart-export-'.now()->format('Y-m-d-His').'.json', ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
