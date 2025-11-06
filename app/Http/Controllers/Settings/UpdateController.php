<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\GitUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function __construct(
        public GitUpdateService $gitUpdateService
    ) {}

    /**
     * Check if updates are available.
     */
    public function check(Request $request): JsonResponse
    {
        return response()->json($this->gitUpdateService->checkForUpdates());
    }

    /**
     * Perform the update.
     */
    public function update(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        try {
            $updateResult = $this->gitUpdateService->performUpdate();
        } catch (\Throwable $e) {
            $updateResult = [
                'success' => false,
                'message' => 'Update failed',
                'output' => null,
                'error' => $e->getMessage(),
            ];
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($updateResult, $updateResult['success'] ? 200 : 500);
        }

        return back()->with('updateResult', $updateResult);
    }
}
