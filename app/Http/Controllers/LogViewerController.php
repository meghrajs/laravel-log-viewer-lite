<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogViewerController extends Controller
{
    public function index()
    {
        return view('logviewer.index');
    }

    public function list()
    {
        $files = File::files(storage_path('logs'));

        $logFiles = collect($files)
            ->filter(fn($file) => str_contains($file->getFilename(), 'laravel-') && str_ends_with($file->getFilename(), '.log'))
            ->map(fn($file) => $file->getFilename())
            ->sortDesc()
            ->values();

        return response()->json(['files' => $logFiles]);
    }

    public function fetch(Request $request)
    {
        $fileName = $request->query('file', 'laravel-' . now()->format('Y-m-d') . '.log');
        $logPath = storage_path('logs/' . $fileName);

        if (!File::exists($logPath)) {
            return response()->json(['message' => 'Log file does not exist.'], 404);
        }

        // Configurable period
        $groupPeriod = config('logviewer.group_period_hours', 3); // default 3 hours
        $lines = file($logPath, FILE_IGNORE_NEW_LINES);

        $grouped = [];
        foreach ($lines as $line) {
            preg_match('/\[(\d{4}-\d{2}-\d{2}) (\d{2}):\d{2}:\d{2}\]/', $line, $matches);
            $hour = isset($matches[2]) ? (int)$matches[2] : 0;

            $groupIndex = intdiv($hour, $groupPeriod);
            $start = str_pad($groupIndex * $groupPeriod, 2, '0', STR_PAD_LEFT);
            $end = str_pad(min(($groupIndex + 1) * $groupPeriod - 1, 23), 2, '0', STR_PAD_LEFT);
            $label = "{$start}:00 - {$end}:59";

            $grouped[$label][] = e($line);
        }

        ksort($grouped);

        return response()->json([
            'file' => $fileName,
            'group_period_hours' => $groupPeriod,
            'meta' => [
                'size' => File::size($logPath),
                'lines' => count($lines),
                'last_modified' => date('Y-m-d H:i:s', File::lastModified($logPath)),
            ],
            'groups' => $grouped,
        ]);
    }

    public function clear(Request $request)
    {
        $fileName = $request->input('file');
        if ($fileName) {
            $logPath = storage_path('logs/' . $fileName);
            if (File::exists($logPath)) {
                File::put($logPath, '');
                return response()->json(['message' => "$fileName cleared successfully."]);
            } else {
                return response()->json(['message' => "$fileName not found."], 404);
            }
        }

        // Clear all logs fallback
        foreach (File::files(storage_path('logs')) as $file) {
            if (str_contains($file->getFilename(), 'laravel-')) {
                File::put($file->getPathname(), '');
            }
        }

        return response()->json(['message' => 'All log files cleared.']);
    }
}
