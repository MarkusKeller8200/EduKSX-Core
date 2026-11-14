<?php

// Titel der gemeinsamen Fehlercodes (API-Standard A6): für Menschen und übersetzt; `code` bleibt englisch.

return [
    'bad_request' => 'La requête n’est pas valable.',
    'unauthenticated' => 'Connexion requise.',
    'forbidden' => 'Accès refusé.',
    'not_found' => 'Introuvable.',
    'method_not_allowed' => 'Méthode non autorisée.',
    'conflict' => 'Conflit avec l’état actuel.',
    'precondition_failed' => 'L’état a changé entre-temps.',
    'payload_too_large' => 'La requête est trop volumineuse.',
    'unsupported_media_type' => 'Format non pris en charge.',
    'validation_failed' => 'Les données saisies ne sont pas valables.',
    'precondition_required' => 'If-Match manquant.',
    'too_many_requests' => 'Trop de requêtes.',
    'internal_error' => 'Erreur interne.',
    'service_unavailable' => 'Service temporairement indisponible.',
    'invalid_cursor' => 'Curseur non valable.',
    'invalid_signature' => 'Signature non valable.',
    'invalid_event' => 'Événement non valable.',
    'unknown_source' => 'Expéditeur non autorisé.',
];
