<?php

namespace App\Nodes\Integrations\GoogleMaps;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleMapsGetPlaceNode extends AbstractGoogleMapsNode
{
    public function type(): string
    {
        return 'google_maps_get_place';
    }

    public function name(): string
    {
        return 'Google Maps: Get Place';
    }

    public function description(): string
    {
        return 'Gets the details of one place from its ID: address, phone, website, rating and whether it is still open for business.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['place_id'],
            'properties' => [
                ...$this->credentialFields(),
                'place_id' => Field::text('Place ID', 'From Search Places, e.g. ChIJ…', 'ChIJN1t_tDeuEmsRUsoyG83frY4'),
                'language' => Field::advanced(Field::text('Language', 'A language code for the result, e.g. en.', 'en')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'place' => ['title' => 'Place', 'description' => 'The place: id, name, address, latitude, longitude, rating, reviews, website, phone, maps_url, status, category and types.'],
                'name' => ['title' => 'Name', 'description' => 'The place’s name.'],
                'address' => ['title' => 'Address', 'description' => 'Its address.'],
                'phone' => ['title' => 'Phone', 'description' => 'Its phone number.'],
                'website' => ['title' => 'Website', 'description' => 'Its website.'],
                'rating' => ['title' => 'Rating', 'description' => 'Its average rating.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = trim((string) ($config['place_id'] ?? ''));

        if (preg_match('/^[A-Za-z0-9_-]{10,300}$/', $id) !== 1) {
            throw new InvalidArgumentException('That is not a place ID. Use the ID from Search Places.');
        }

        $body = $this->call($run, $config, 'GET', self::PLACES_URL.'/places/'.$id, query: ['languageCode' => filled($config['language'] ?? null) ? strtolower(trim((string) $config['language'])) : null], headers: ['X-Goog-FieldMask' => str_replace('places.', '', self::PLACE_FIELDS)]);
        $place = $this->shapePlace($body);

        return ['place' => $place, 'name' => $place['name'], 'address' => $place['address'], 'phone' => $place['phone'], 'website' => $place['website'], 'rating' => $place['rating']];
    }
}
