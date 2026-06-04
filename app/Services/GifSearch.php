<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Searches a third-party GIF provider (Giphy or Tenor) and returns a
 * normalised list of results for the praise-wall comment picker.
 */
class GifSearch
{
    /**
     * Search for GIFs matching the query.
     *
     * Returns an empty list when no API key is configured or the request
     * fails, so the picker degrades gracefully instead of erroring.
     *
     * @return list<array{id: string, preview: string, full: string}>
     */
    public function search(string $query, int $limit = 16, int $offset = 0): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        return config('services.gif.provider') === 'tenor'
            ? $this->searchTenor($query, $limit, $offset)
            : $this->searchGiphy($query, $limit, $offset);
    }

    /**
     * @return list<array{id: string, preview: string, full: string}>
     */
    protected function searchGiphy(string $query, int $limit, int $offset = 0): array
    {
        $key = config('services.gif.giphy_key');

        if (blank($key)) {
            return [];
        }

        $response = Http::get('https://api.giphy.com/v1/gifs/search', [
            'api_key' => $key,
            'q' => $query,
            'limit' => $limit,
            'offset' => $offset,
            'rating' => config('services.gif.rating', 'pg'),
            'bundle' => 'messaging_non_clips',
        ]);

        if ($response->failed()) {
            return [];
        }

        return collect($response->json('data', []))
            ->map(fn (array $gif): array => [
                'id' => (string) ($gif['id'] ?? ''),
                'preview' => (string) data_get($gif, 'images.fixed_width_small.url', ''),
                'full' => (string) data_get($gif, 'images.fixed_height.url', ''),
            ])
            ->filter(fn (array $gif): bool => $gif['full'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, preview: string, full: string}>
     */
    protected function searchTenor(string $query, int $limit, int $offset = 0): array
    {
        $key = config('services.gif.tenor_key');

        if (blank($key)) {
            return [];
        }

        // Tenor paginates with an opaque "pos" cursor rather than a numeric
        // offset, so we only serve the first page here. Infinite scroll stops
        // cleanly once that page is consumed.
        if ($offset > 0) {
            return [];
        }

        $response = Http::get('https://tenor.googleapis.com/v2/search', [
            'key' => $key,
            'q' => $query,
            'limit' => $limit,
            'contentfilter' => config('services.gif.rating', 'pg') === 'pg' ? 'high' : 'medium',
            'media_filter' => 'gif,tinygif',
        ]);

        if ($response->failed()) {
            return [];
        }

        return collect($response->json('results', []))
            ->map(fn (array $gif): array => [
                'id' => (string) ($gif['id'] ?? ''),
                'preview' => (string) data_get($gif, 'media_formats.tinygif.url', ''),
                'full' => (string) data_get($gif, 'media_formats.gif.url', ''),
            ])
            ->filter(fn (array $gif): bool => $gif['full'] !== '')
            ->values()
            ->all();
    }
}
