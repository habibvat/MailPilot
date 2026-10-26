<?php

return [
    // Fuseau utilisé pour les jours / heures d'envoi.
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // Un contact n'est pas recontacté avant ce délai (jours).
    'recontact_days' => 60,

    // Durée de validité du cache MX par domaine (jours).
    'mx_cache_days' => 30,

    // Disjoncteurs.
    'bounce_rate_threshold' => 0.05,
    'bounce_rate_min_sends' => 50,
    'max_consecutive_failures' => 10,

    // Nombre maximum de tentatives pour une erreur SMTP temporaire.
    'max_attempts' => 3,

    // Préfixes d'adresses de rôle considérés à risque.
    'role_accounts' => ['noreply', 'no-reply', 'postmaster', 'abuse', 'mailer-daemon'],

    'disposable_domains_file' => resource_path('data/disposable_domains.txt'),

    'import_chunk_size' => 5000,
    'verify_batch_size' => 200,
];
