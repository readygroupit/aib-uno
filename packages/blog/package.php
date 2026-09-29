<?php

declare(strict_types=1);

/**
 * Articolo di blog generico - titolo, slug per l'URL, estratto, corpo,
 * immagine di copertina, autore, stato di pubblicazione.
 */
return [
    'name' => 'blog',
    'label' => 'Blog',
    'category' => 'contenuti',
    'description' => 'Articoli di blog con stato di pubblicazione.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'posts' => [
            'table' => 'blog_posts',
            'fields' => [
                'title' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Titolo',
                    'base' => true,
                ],
                'slug' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Slug (URL)',
                    'defaultRequired' => true,
                    'help' => "Parte finale dell'indirizzo dell'articolo (es. 'come-scegliere-un-fornitore') - va tenuto unico a mano, non c'e ancora un controllo automatico.",
                ],
                'excerpt' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Estratto',
                    'defaultRequired' => true,
                    'help' => "Riassunto breve mostrato nell'elenco articoli, prima di aprire quello intero.",
                ],
                'body' => [
                    'sql' => 'TEXT',
                    'label' => 'Testo',
                    'defaultRequired' => true,
                ],
                'cover_image_url' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Immagine di copertina (URL)',
                    'defaultRequired' => true,
                ],
                'author_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Autore',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/users/cerca'],
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato',
                    'base' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'bozza', 'pubblicato', 'archiviato'.",
                ],
                'published_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Data pubblicazione',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Articolo',
                                'area' => 'main',
                                'fields' => ['title', 'slug', 'excerpt', 'body'],
                            ],
                            [
                                'title' => 'Pubblicazione',
                                'area' => 'sidebar',
                                'fields' => ['stage', 'published_at', 'author_user_id', 'cover_image_url'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
