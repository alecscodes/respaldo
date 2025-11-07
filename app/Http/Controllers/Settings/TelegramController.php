<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TelegramUpdateRequest;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TelegramController extends Controller
{
    /**
     * Display the Telegram settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Telegram', [
            'telegram_bot_token' => Setting::get('telegram_bot_token'),
            'telegram_chat_id' => Setting::get('telegram_chat_id'),
        ]);
    }

    /**
     * Update the Telegram settings.
     */
    public function update(TelegramUpdateRequest $request): RedirectResponse
    {
        Setting::set('telegram_bot_token', $request->telegram_bot_token);
        Setting::set('telegram_chat_id', $request->telegram_chat_id);

        return redirect()->route('telegram.edit')->with('success', 'Telegram settings updated successfully.');
    }

    /**
     * Send a test Telegram message.
     */
    public function test(Request $request, TelegramNotificationService $telegramService): RedirectResponse
    {
        $request->validate([
            'telegram_bot_token' => ['required', 'string'],
            'telegram_chat_id' => ['required', 'string'],
        ]);

        $originalToken = Setting::get('telegram_bot_token');
        $originalChatId = Setting::get('telegram_chat_id');

        Setting::set('telegram_bot_token', $request->telegram_bot_token);
        Setting::set('telegram_chat_id', $request->telegram_chat_id);

        try {
            $success = $telegramService->sendNotification('Respaldo message delivered 🔄');
        } catch (\Exception $e) {
            $success = false;
        }

        if (! $success) {
            Setting::set('telegram_bot_token', $originalToken);
            Setting::set('telegram_chat_id', $originalChatId);
        }

        return back()->with([
            'success' => $success,
            'message' => $success
                ? 'Test message sent successfully!'
                : 'Failed to send test message. Please check your bot token and chat ID.',
        ]);
    }
}
