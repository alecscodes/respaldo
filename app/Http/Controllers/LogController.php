<?php

namespace App\Http\Controllers;

use App\Enums\LogLevel;
use App\Http\Requests\SearchLogsRequest;
use App\Models\App;
use App\Models\Log;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    public function index(SearchLogsRequest $request): Response
    {
        $query = Log::query()->latest()
            ->when($request->filled('category'), fn ($q) => $q->byCategory($request->input('category')))
            ->when($request->filled('level'), fn ($q) => $q->byLevel($request->input('level')))
            ->when($request->filled('user_id'), fn ($q) => $q->byUser($request->input('user_id')))
            ->when($request->filled('app_id'), fn ($q) => $q->byApp($request->input('app_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->input('date_from'))))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->input('date_to'))))
            ->when($request->filled('search'), fn ($q) => $q->search(
                $request->input('search'),
                $request->boolean('use_regex')
            ));

        $logs = $query->with('user:id,name,email')
            ->paginate($request->input('per_page', 50))
            ->through(fn (Log $log) => [
                'id' => $log->id,
                'level' => $log->level,
                'category' => $log->category,
                'message' => $log->message,
                'context' => $log->context,
                'user' => $log->user?->only(['id', 'name', 'email']),
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
            'categories' => Log::distinct()->pluck('category')->sort()->values(),
            'apps' => App::orderBy('name')->get()->map(fn (App $app) => [
                'id' => $app->id,
                'name' => $app->name,
            ]),
            'users' => User::whereIn('id', Log::distinct()->whereNotNull('user_id')->pluck('user_id'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            'levels' => LogLevel::values(),
            'filters' => array_filter([
                'search' => $request->input('search'),
                'category' => $request->input('category'),
                'level' => $request->input('level'),
                'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
                'app_id' => $request->filled('app_id') ? (int) $request->input('app_id') : null,
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function destroy(SearchLogsRequest $request): RedirectResponse
    {
        $query = Log::query()
            ->when($request->filled('category'), fn ($q) => $q->byCategory($request->input('category')))
            ->when($request->filled('level'), fn ($q) => $q->byLevel($request->input('level')))
            ->when($request->filled('user_id'), fn ($q) => $q->byUser($request->input('user_id')))
            ->when($request->filled('app_id'), fn ($q) => $q->byApp($request->input('app_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->input('date_from'))))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->input('date_to'))))
            ->when($request->filled('search'), fn ($q) => $q->search(
                $request->input('search'),
                $request->boolean('use_regex')
            ));

        $count = $query->count();
        $query->delete();

        $this->log('warning', 'system', 'Logs deleted', [
            'deleted_count' => $count,
            'filters_applied' => array_filter($request->only(['category', 'level', 'user_id', 'app_id', 'date_from', 'date_to', 'search'])),
        ]);

        $message = $count === 1
            ? '1 log deleted successfully.'
            : "{$count} logs deleted successfully.";

        return redirect()->route('logs.index')->with('success', $message);
    }
}
