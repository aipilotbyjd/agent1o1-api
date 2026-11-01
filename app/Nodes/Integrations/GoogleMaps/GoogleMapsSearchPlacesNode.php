<?php

namespace App\Nodes\Integrations\GoogleMaps;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleMapsSearchPlacesNode extends AbstractGoogleMapsNode
{
    public function type(): string
    {
        return 'google_maps_search_places';
    }

    public function name(): string
    {
        return 'Google Maps: Search Places';
    }

    public function description(): string
    {
        return 'Finds businesses and places from a search like “dentists in Leeds”, with their address, phone, website and rating.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'query' => Field::text('Search for', 'What and where, as you would type it into Google Maps.', 'coffee shops near King’s Cross, London'),
                'max_results' => Field::integer('Most places', null, 10, 1, 20),
                'min_rating' => Field::advanced(Field::integer('Lowest rating', 'Skip places rated below this (1 to 5).', null, 1, 5)),
                'open_now' => Field::advanced(Field::boolean('Open now only')),
                'language' => Field::advanced(Field::text('Language', 'A language code for the results, e.g. en.', 'en')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'places' => ['title' => 'Places', 'description' => 'Each: id, name, address, latitude, longitude, rating, reviews, website, phone, maps_url, status, category and types.'],
                'count' => ['title' => 'Count', 'description' => 'How many places were returned.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = trim((string) ($config['query'] ?? ''));

        if ($query === '') {
            throw new InvalidArgumentException('What to search for is empty.');
        }

        $body = $this->call($run, $config, 'POST', self::PLACES_URL.'/places:searchText', array_filter([
            'textQuery' => mb_substr($query, 0, 300),
            'maxResultCount' => max(1, min(20, (int) ($config['max_results'] ?? 10))),
            'minRating' => filled($config['min_rating'] ?? null) ? max(1, min(5, (int) $config['min_rating'])) : null,
            'openNow' => (bool) ($config['open_now'] ?? false) ?: null,
            'languageCode' => filled($config['language'] ?? null) ? strtolower(trim((string) $config['language'])) : null,
        ], fn (mixed $value): bool => $value !== null), headers: ['X-Goog-FieldMask' => self::PLACE_FIELDS]);

        $places = array_map(fn (mixed $place): array => $this->shapePlace((array) $place), (array) ($body['places'] ?? []));

        return ['places' => $places, 'count' => count($places)];
    }
}
