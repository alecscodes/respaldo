<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TelegramUpdateRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
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
}
