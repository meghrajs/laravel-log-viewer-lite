<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class LogTestController extends Controller
{
    public function generate()
    {
        $uuid = Str::uuid();
        $logPath = storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log');

        $levels = [
            'DEBUG' => [
                'Loading configuration files.',
                'User input validation passed.',
                'Cache hit for user profile.',
                'API response time: 87ms.',
                'Session token refreshed.',
                'Executing scheduled command.',
            ],
            'INFO' => [
                'User successfully logged in.',
                'New account created.',
                'Email verification sent.',
                'Backup completed successfully.',
                'Settings updated by admin.',
            ],
            'NOTICE' => [
                'User attempted password reset.',
                'New device detected for login.',
                'Feature X will be deprecated soon.',
                'User updated profile picture.',
            ],
            'WARNING' => [
                'Disk space below 10%.',
                'High memory usage detected.',
                'User reached max login attempts.',
                'Failed login attempt from unknown IP.',
                'Queue worker delayed.',
            ],
            'ERROR' => [
                'Database connection failed.',
                'Mail service not responding.',
                'File upload failed due to permissions.',
                'Route not found: /api/ghost',
                'Unhandled exception thrown.',
            ],
            'CRITICAL' => [
                'Payment gateway rejected transaction.',
                'Service X failed health check.',
                'Inconsistent data detected in DB.',
                'Session hijacking attempt suspected.',
            ],
            'ALERT' => [
                'Multiple failed login attempts detected.',
                'Admin privileges escalated unexpectedly.',
                'Abnormal traffic pattern noticed.',
                'Login from banned IP address.',
            ],
            'EMERGENCY' => [
                'Production server down.',
                'Kernel panic – manual reboot required.',
                'No healthy instances available.',
                'Data corruption detected in live DB.',
            ],
        ];

        $lines = [];

        for ($i = 0; $i < 20; $i++) {
            $level = array_rand($levels);
            $message = $levels[$level][array_rand($levels[$level])];

            $hour = rand(0, 23);
            $minute = str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT);
            $second = str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT);

            $timestamp = now()
                ->startOfDay()
                ->addHours($hour)
                ->setTime($hour, $minute, $second)
                ->format('Y-m-d H:i:s');

            $lines[] = "[$timestamp] local.$level: [$uuid] $level: $message";
        }

        File::append($logPath, implode(PHP_EOL, $lines) . PHP_EOL);

        return response('Random logs generated across various times!', 200);
    }
}
