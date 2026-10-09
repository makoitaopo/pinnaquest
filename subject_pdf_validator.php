<?php

require_once __DIR__ . '/accepted_subjects.php';

/**
 * Subject signals use distinctive course terms rather than the uploaded
 * filename or the subject selected in the form.
 */
function pinnaquest_pdf_subject_signals(): array
{
    return [
        'Readings in Philippine History' => [
            'philippine history' => 3,
            'historical method' => 3,
            'source criticism' => 3,
            'primary source' => 2,
            'secondary source' => 2,
            'pre colonial' => 3,
            'spanish colonial period' => 3,
            'american colonial period' => 3,
            'philippine revolution' => 3,
            'philippine american war' => 3,
            'philippine independence' => 2,
            'historical analysis' => 2,
            'historical interpretation' => 2,
            'oral history' => 2,
            'katipunan' => 3,
            'andres bonifacio' => 3,
            'jose rizal' => 2,
            'martial law' => 2,
            'historiography' => 2,
        ],
        'Understanding the Self' => [
            'understanding the self' => 3,
            'self concept' => 3,
            'self awareness' => 3,
            'self esteem' => 2,
            'self efficacy' => 2,
            'personal identity' => 2,
            'social identity' => 2,
            'philosophical perspective' => 2,
            'psychological perspective' => 2,
            'sociological perspective' => 2,
            'material self' => 3,
            'spiritual self' => 3,
            'digital self' => 3,
            'political self' => 3,
            'body image' => 2,
            'self development' => 2,
            'self regulation' => 2,
            'erik erikson' => 2,
            'charles horton cooley' => 2,
            'george herbert mead' => 2,
        ],
        'Art Appreciation' => [
            'art appreciation' => 3,
            'elements of art' => 3,
            'principles of design' => 3,
            'visual arts' => 2,
            'art criticism' => 2,
            'aesthetics' => 2,
            'color theory' => 2,
            'art medium' => 2,
            'contemporary art' => 2,
            'philippine art' => 2,
            'installation art' => 3,
            'visual composition' => 2,
            'sculpture' => 2,
            'painting' => 2,
            'architecture' => 1,
            'art movement' => 2,
            'line shape color' => 1,
            'texture and space' => 1,
        ],
        'Physical Education' => [
            'physical education' => 3,
            'physical fitness' => 3,
            'health related fitness' => 3,
            'skill related fitness' => 3,
            'cardiovascular endurance' => 2,
            'muscular strength' => 2,
            'muscular endurance' => 2,
            'physical activity' => 2,
            'fitt principle' => 3,
            'fitness test' => 2,
            'fitness testing' => 2,
            'aerobic exercise' => 2,
            'movement competency' => 2,
            'warm up' => 1,
            'training program' => 1,
            'flexibility exercise' => 2,
            'body mass index' => 1,
            'sports participation' => 1,
        ],
        'Science and Development of Reading' => [
            'science of reading' => 3,
            'reading development' => 3,
            'phonological awareness' => 3,
            'phonemic awareness' => 3,
            'alphabetic principle' => 3,
            'orthographic mapping' => 3,
            'reading comprehension' => 2,
            'reading fluency' => 2,
            'reading instruction' => 2,
            'word recognition' => 2,
            'vocabulary development' => 2,
            'emergent literacy' => 2,
            'literacy development' => 2,
            'language acquisition' => 2,
            'simple view of reading' => 3,
            'scarborough rope' => 3,
            'systematic phonics' => 2,
            'phonics instruction' => 2,
            'decoding skills' => 2,
            'reading difficulties' => 2,
            'dyslexia' => 2,
        ],
    ];
}

function pinnaquest_normalize_pdf_subject_text(string $text): string
{
    $normalized = strtolower($text);
    $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? '';

    return ' ' . trim($normalized) . ' ';
}

/**
 * Return the best-supported subject only when the extracted text has enough
 * distinct signals and a clear lead over the other subjects.
 */
function pinnaquest_classify_pdf_subject_text(string $text): array
{
    $normalizedText = pinnaquest_normalize_pdf_subject_text($text);
    $wordCount = str_word_count($normalizedText);

    if ($wordCount < 35) {
        return [
            'status' => 'insufficient_text',
            'detected_subject' => null,
            'scores' => [],
        ];
    }

    $scores = [];
    $signalCounts = [];

    foreach (pinnaquest_pdf_subject_signals() as $subject => $signals) {
        $scores[$subject] = 0;
        $signalCounts[$subject] = 0;

        foreach ($signals as $phrase => $weight) {
            $normalizedPhrase = trim(pinnaquest_normalize_pdf_subject_text($phrase));
            if (str_contains($normalizedText, ' ' . $normalizedPhrase . ' ')) {
                $scores[$subject] += $weight;
                $signalCounts[$subject]++;
            }
        }
    }

    arsort($scores);
    $rankedSubjects = array_keys($scores);
    $bestSubject = $rankedSubjects[0];
    $runnerUpScore = $scores[$rankedSubjects[1]] ?? 0;
    $hasConfidentMatch = $scores[$bestSubject] >= 5
        && $signalCounts[$bestSubject] >= 2
        && ($scores[$bestSubject] - $runnerUpScore) >= 2;

    return [
        'status' => $hasConfidentMatch ? 'classified' : 'uncertain',
        'detected_subject' => $hasConfidentMatch ? $bestSubject : null,
        'scores' => $scores,
    ];
}

/**
 * Extract selectable text locally with Poppler, then check it against the
 * subject selected by the uploader. The uploaded PDF is never sent externally.
 */
function pinnaquest_validate_pdf_subject(string $pdfPath, string $selectedSubject): array
{
    if (!in_array($selectedSubject, PINNAQUEST_ACCEPTED_SUBJECTS, true)) {
        return [
            'valid' => false,
            'message' => 'Please select one of the accepted subjects.',
        ];
    }

    if (!is_file($pdfPath) || !function_exists('exec')) {
        return [
            'valid' => false,
            'message' => 'The PDF could not be checked. Please try again or contact the administrator.',
        ];
    }

    $output = [];
    $exitCode = 1;
    $command = 'pdftotext -q -enc UTF-8 -f 1 -l 30 '
        . escapeshellarg($pdfPath)
        . ' - 2>/dev/null';
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        return [
            'valid' => false,
            'message' => 'The PDF could not be read. Please upload a valid, unprotected PDF.',
        ];
    }

    $text = implode("\n", $output);
    if (trim($text) === '') {
        return [
            'valid' => false,
            'message' => 'No readable text was found. This may be a scanned PDF; please OCR it or upload a searchable PDF.',
        ];
    }

    $classification = pinnaquest_classify_pdf_subject_text($text);
    if ($classification['status'] === 'insufficient_text') {
        return [
            'valid' => false,
            'message' => 'The PDF does not contain enough readable text to verify its subject. Please upload a more complete, searchable PDF.',
        ];
    }

    if ($classification['status'] !== 'classified') {
        return [
            'valid' => false,
            'message' => 'The PDF subject could not be confirmed from its text. Please upload a clearer or more complete PDF.',
        ];
    }

    if ($classification['detected_subject'] !== $selectedSubject) {
        return [
            'valid' => false,
            'message' => 'The PDF content appears to be for "' . $classification['detected_subject']
                . '", but you selected "' . $selectedSubject . '". No material was saved.',
        ];
    }

    return [
        'valid' => true,
        'message' => 'PDF content matches the selected subject.',
    ];
}