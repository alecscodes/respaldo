<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\HomepageUpdateRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    /**
     * Display the homepage settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Homepage', [
            'homepage_enabled' => filter_var(
                Setting::get('homepage_enabled', false),
                FILTER_VALIDATE_BOOLEAN
            ),
        ]);
    }

    /**
     * Update the homepage settings.
     */
    public function update(HomepageUpdateRequest $request): RedirectResponse
    {
        Setting::set('homepage_enabled', $request->boolean('homepage_enabled') ? '1' : '0');

        return redirect()->route('homepage.edit')->with('success', 'Homepage settings updated successfully.');
    }
}
