<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;

class VoiceController extends Controller
{
    public function sendVoice(Request $request)
    {
        // Get text input from the user
        $text = $request->input('text');

        // Path to the speaker WAV file (if required)
        $speakerWavPath = storage_path('app/public/speaker.wav');

        // Send request to the Python API
        $response = Http::attach(
            'speaker_wav', file_get_contents($speakerWavPath), 'speaker.wav'
        )->post('http://localhost:5000/generate-voice', [
            'text' => $text,
            'language' => 'en'
        ]);

        // Check if the request was successful
        if ($response->successful()) {
            $filePath = storage_path('app/public/output.wav');
            file_put_contents($filePath, $response->body());

            // Now, send the generated voice file as an MMS or do whatever you need
            return response()->download($filePath);
        }

        return response()->json(['error' => 'Failed to generate voice'], 500);
    }
}
