<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class OcrProcessor
{
    public function extract(string $absolutePath): array
    {
        $process = new Process([
            env('PYTHON_BINARY', 'python'),
            base_path('ocr/ocr_invoice.py'),
            $absolutePath,
        ]);

        $process->setTimeout(180);
        $process->setEnv([
            'TESSERACT_CMD' => env('TESSERACT_CMD', 'C:\Program Files\Tesseract-OCR\tesseract.exe'),
            'POPPLER_PATH' => env('POPPLER_PATH'),
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'OCR processing failed.');
        }

        $result = json_decode($process->getOutput(), true);

        if (! is_array($result)) {
            throw new RuntimeException('OCR module returned an invalid response.');
        }

        return $result;
    }
}
