<?php

namespace App\Nodes\Integrations\GoogleMaps;

use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class GoogleMapsGeocodeNode extends AbstractGoogleMapsNode
{
    public function type(): string
    {
        return 'google_maps_geocode';
    }

    public function name(): string
    {
        return 'Google Maps: Geocode Address';
    }

    public function description(): string
    {
        return 'Turns an address into coordinates, or coordinates into an address, and tidies it into street, city, region and country.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'address' => Field::text('Address', 'An address to find the coordinates of. Leave empty to look up coordinates instead.', '1600 Amphitheatre Parkway, Mountain View'),
                'latitude' => Field::text('Latitude', 'Reverse lookup: with the longitude, finds the address.', '37.4220'),
                'longitude' => Field::text('Longitude', null, '-122.0841'),
                'region' => Field::advanced(Field::text('Favour this country', 'A two-letter country code to prefer when an address is ambiguous.', 'GB')),
            ],
        ];
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'found' => ['title' => 'Found', 'description' => 'True when Google found a match.'],
                'result' => ['title' => 'Branch', 'description' => 'found or none, so an edge can follow it.'],
                'formatted_address' => ['title' => 'Address', 'description' => 'The full address.'],
                'latitude' => ['title' => 'Latitude', 'description' => 'The latitude.'],
                'longitude' => ['title' => 'Longitude', 'description' => 'The longitude.'],
                'place_id' => ['title' => 'Place ID', 'description' => 'Google’s ID for it.'],
                'street' => ['title' => 'Street', 'description' => 'Street number and name.'],
                'city' => ['title' => 'City', 'description' => 'The city.'],
                'region' => ['title' => 'Region', 'description' => 'The state or region.'],
                'postal_code' => ['title' => 'Postcode', 'description' => 'The postal code.'],
                'country' => ['title' => 'Country', 'description' => 'The country.'],
                'country_code' => ['title' => 'Country code', 'description' => 'The two-letter country code.'],
                'accuracy' => ['title' => 'Accuracy', 'description' => 'ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER or APPROXIMATE.'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $address = trim((string) ($config['address'] ?? ''));
        $latitude = trim((string) ($config['latitude'] ?? ''));
        $longitude = trim((string) ($config['longitude'] ?? ''));

        if ($address === '' && ($latitude === '' || $longitude === '')) {
            throw new InvalidArgumentException('Give an address, or both a latitude and a longitude.');
        }

        if ($address === '' && (! is_numeric($latitude) || ! is_numeric($longitude) || abs((float) $latitude) > 90 || abs((float) $longitude) > 180)) {
            throw new InvalidArgumentException('The latitude must be between -90 and 90 and the longitude between -180 and 180.');
        }

        $body = $this->call($run, $config, 'GET', self::GEOCODE_URL, query: [
            ...($address !== '' ? ['address' => $address] : ['latlng' => "{$latitude},{$longitude}"]),
            'region' => filled($config['region'] ?? null) ? strtolower(trim((string) $config['region'])) : null,
            // The key travels in the query for Geocoding, which does not read the header.
            'key' => $this->resolveAccessToken($run, $config),
        ]);

        $this->assertGeocodeOk($body);
        $match = (array) (($body['results'] ?? [])[0] ?? []);

        if ($match === []) {
            return ['found' => false, 'result' => 'none'];
        }

        $component = function (string $type, string $name = 'long_name') use ($match): ?string {
            foreach ((array) ($match['address_components'] ?? []) as $part) {
                if (in_array($type, (array) ($part['types'] ?? []), true)) {
                    return $part[$name] ?? null;
                }
            }

            return null;
        };

        return [
            'found' => true,
            'result' => 'found',
            'formatted_address' => $match['formatted_address'] ?? null,
            'latitude' => $match['geometry']['location']['lat'] ?? null,
            'longitude' => $match['geometry']['location']['lng'] ?? null,
            'place_id' => $match['place_id'] ?? null,
            'street' => trim(($component('street_number') ?? '').' '.($component('route') ?? '')) ?: null,
            'city' => $component('locality') ?? $component('postal_town') ?? $component('administrative_area_level_2'),
            'region' => $component('administrative_area_level_1'),
            'postal_code' => $component('postal_code'),
            'country' => $component('country'),
            'country_code' => $component('country', 'short_name'),
            'accuracy' => $match['geometry']['location_type'] ?? null,
        ];
    }
}
