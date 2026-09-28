<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureClientLocation
{
    public function handle(Request $request, Closure $next): Response
    {
        $latitude = filter_var($request->input('client_latitude'), FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($request->input('client_longitude'), FILTER_VALIDATE_FLOAT);
        $accuracy = filter_var($request->input('client_accuracy'), FILTER_VALIDATE_FLOAT);

        if ($latitude !== false && $longitude !== false
            && $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180) {
            $request->session()->put('security.client_location', [
                'latitude' => round((float) $latitude, 6),
                'longitude' => round((float) $longitude, 6),
                'accuracy_meters' => $accuracy !== false && $accuracy >= 0
                    ? min(round((float) $accuracy, 1), 100000)
                    : null,
            ]);
        }

        $request->request->remove('client_latitude');
        $request->request->remove('client_longitude');
        $request->request->remove('client_accuracy');

        return $next($request);
    }
}
