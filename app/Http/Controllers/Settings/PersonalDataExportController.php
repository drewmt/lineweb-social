<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Privacy\PersonalDataExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

class PersonalDataExportController extends Controller
{
    public function __invoke(Request $request, PersonalDataExport $export): StreamedJsonResponse
    {
        $user = $request->user();
        $filename = 'lineweb-social-'.$user->handle.'-'.now()->format('Y-m-d').'.json';

        return new StreamedJsonResponse(
            $export->for($user),
            headers: [
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
            encodingOptions: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
