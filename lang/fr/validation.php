<?php

// Messages de validation en français pour les règles utilisées par l'application.
// Les règles absentes ici retombent sur les messages anglais du framework.
return [
    'after' => 'Le champ :attribute doit être postérieur à :date.',
    'array' => 'Le champ :attribute doit être une liste.',
    'between' => [
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caractères.',
        'array' => 'Le champ :attribute doit contenir entre :min et :max éléments.',
        'file' => 'Le fichier :attribute doit peser entre :min et :max Ko.',
    ],
    'date_format' => 'Le champ :attribute doit respecter le format :format.',
    'email' => 'Le champ :attribute doit être une adresse email valide.',
    'exists' => 'La valeur choisie pour :attribute n\'existe pas.',
    'extensions' => 'Le fichier :attribute doit avoir l\'une des extensions suivantes : :values.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'gte' => [
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
        'string' => 'Le champ :attribute doit contenir au moins :value caractères.',
        'array' => 'Le champ :attribute doit contenir au moins :value éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :value Ko.',
    ],
    'in' => 'La valeur choisie pour :attribute n\'est pas valide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'numeric' => 'Le champ :attribute ne peut pas dépasser :max.',
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
        'array' => 'Le champ :attribute ne peut pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne peut pas dépasser :max Ko.',
    ],
    'min' => [
        'numeric' => 'Le champ :attribute doit être au moins égal à :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :min Ko.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être du texte.',
    'url' => 'Le champ :attribute doit être une URL valide.',

    'attributes' => [],
];
