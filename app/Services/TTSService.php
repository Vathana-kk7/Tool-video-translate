<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TTSService
{
    /**
     * បម្លែងអត្ថបទទៅជាសំឡេងដោយប្រើ Edge TTS
     */
    public function generateSpeech(string $text, string $outputPath): string
    {
        try {
            // ១. កំណត់ផ្លូវទៅកាន់ Python Script
            $scriptPath = base_path('tts_edge.py');

            // ២. បង្កើត Folder បើមិនទាន់មាន
            $dir = dirname($outputPath);
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            // ៣. រៀបចំ Command (ប្រើ 'py' សម្រាប់ Windows និងបន្ថែម 2>&1 ដើម្បីចាប់ Error)
            // ប្រសិនបើ 'py' មិនដើរ សូមប្តូរទៅជា 'python' វិញ
            $command = "py " . escapeshellarg($scriptPath) . " " . escapeshellarg($text) . " " . escapeshellarg($outputPath);

            // ៤. ដំណើរការ Command និងទាញយក Error
            $output = [];
            $resultCode = null;

            // យើងបន្ថែម " 2>&1" នៅខាងចុង command ដើម្បីឱ្យវាបង្ហាញកំហុស (Error) ទាំងអស់មកក្នុង $output
            exec($command . " 2>&1", $output, $resultCode);

            // ៥. ពិនិត្យលទ្ធផល ប្រសិនបើ resultCode ខុសពី 0 មានន័យថា error ហើយ
            if ($resultCode !== 0) {
                // បង្រួម Error ដែលទាញបានពី Python មកជាអត្ថបទតែមួយ
                $errorMessage = implode("\n", $output);

                // កត់ត្រាចូលក្នុង Log ដើម្បីឱ្យយើងងាយស្រួលតាមដាន
                Log::error("Edge TTS Detail Error: " . $errorMessage);

                // បោះ Exception ប្រាប់ទៅខាងក្រៅ
                throw new \Exception("Edge TTS failed: " . $errorMessage);
            }

            // ពិនិត្យមើលថា តើ File សំឡេងពិតជាបានកើតឡើងមែនឬអត់
            if (!file_exists($outputPath)) {
                throw new \Exception("Audio file was not created at: " . $outputPath);
            }

            return $outputPath;

        } catch (\Exception $e) {
            Log::error('TTS Error (Edge TTS)', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
