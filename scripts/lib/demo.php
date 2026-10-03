<?php
declare(strict_types=1);

use App\Services\CinemaService;
use App\Support\Database as D;

// Called inside the seed transaction. A saved display row, including cleared
// flags or an empty tag list, always wins over demonstration defaults.
function seed_movie_display(int $movie, array $sample): void
{
    if (D::one('SELECT movie_id FROM cinema_movie_display WHERE movie_id=?', [$movie])) {
        return;
    }
    D::execute('INSERT INTO cinema_movie_display (movie_id,banner,is_hot,is_featured) VALUES (?,?,?,?)', [$movie, $sample['banner'] ?? null, $sample['is_hot'] ?? 0, $sample['is_featured'] ?? 0]);
    if (!D::one('SELECT movie_id FROM cinema_movie_tags WHERE movie_id=? LIMIT 1', [$movie])) {
        $tags = CinemaService::parseTags(implode(',', $sample['tags'] ?? explode(' · ', $sample[1])));
        foreach ($tags as $tag) {
            D::execute('INSERT INTO cinema_movie_tags (movie_id,tag) VALUES (?,?)', [$movie, $tag]);
        }
    }
}
