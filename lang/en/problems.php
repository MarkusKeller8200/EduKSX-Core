<?php

// Titel der gemeinsamen Fehlercodes (API-Standard A6): für Menschen und übersetzt; `code` bleibt englisch.

return [
    'bad_request' => 'The request is invalid.',
    'unauthenticated' => 'Authentication required.',
    'forbidden' => 'Access denied.',
    'not_found' => 'Not found.',
    'method_not_allowed' => 'Method not allowed.',
    'conflict' => 'Conflict with the current state.',
    'precondition_failed' => 'The state has changed in the meantime.',
    'payload_too_large' => 'The request is too large.',
    'unsupported_media_type' => 'Unsupported format.',
    'validation_failed' => 'The input is not valid.',
    'precondition_required' => 'If-Match is missing.',
    'too_many_requests' => 'Too many requests.',
    'internal_error' => 'Internal error.',
    'service_unavailable' => 'Service temporarily unavailable.',
    'invalid_cursor' => 'Invalid cursor.',
    'invalid_signature' => 'Invalid signature.',
    'invalid_event' => 'Invalid event.',
    'unknown_source' => 'Sender not allowed.',
];
