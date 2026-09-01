<?php

declare(strict_types=1);

return [

    /*
     * How much one push may carry. A device that has been offline for a week
     * still only holds a few hundred changes, so these are generous; they are
     * here to bound a single request's memory, not to ration the learner.
     * Past the cap the client is told to send the batch in chunks.
     */
    'max_sub_steps' => (int) env('SYNC_MAX_SUB_STEPS', 500),
    'max_submissions' => (int) env('SYNC_MAX_SUBMISSIONS', 500),

    'evidence' => [
        // The plain local disk — storage/app/private. No object store, no
        // new infrastructure; a VPS with a backed-up storage directory.
        'disk' => env('SYNC_EVIDENCE_DISK', 'local'),

        // 25 MB. A phone photo is 2-5 MB; this leaves room for a scanned
        // PDF without letting a video through by accident.
        'max_bytes' => (int) env('SYNC_EVIDENCE_MAX_BYTES', 25 * 1024 * 1024),

        // Evidence is a photo of work, a document, or a written answer.
        'extensions' => [
            'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif',
            'pdf', 'txt', 'csv',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'odt', 'ods', 'odp',
        ],
    ],

];
