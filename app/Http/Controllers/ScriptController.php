<?php

namespace App\Http\Controllers;

use App\Services\ScriptGeneratorService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScriptController extends Controller
{
    public function download(Request $request): Response
    {
        $script = (new ScriptGeneratorService)->generateScript($request->user(), config('app.url'));

        return response($script, 200, [
            'Content-Type' => 'application/x-sh',
            'Content-Disposition' => 'attachment; filename="respaldo.sh"',
            'X-Executable' => 'true',
        ]);
    }
}
