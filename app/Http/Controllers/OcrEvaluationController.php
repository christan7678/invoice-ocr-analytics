<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class OcrEvaluationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $documents = $request->user()->company->uploadedDocuments()
            ->with('ocrExtractionResult')
            ->latest()
            ->take(20)
            ->get();

        return view('ocr_evaluation.index', [
            'documents' => $documents,
        ]);
    }
}
