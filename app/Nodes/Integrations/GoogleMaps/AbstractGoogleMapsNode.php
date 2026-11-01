<?php

namespace App\Nodes\Integrations\GoogleMaps;

use App\Contracts\DeclaresEffect;
use App\Contracts\DeclaresOutputSchema;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\CallsApiKeyService;
use RuntimeException;

/**
 * Shared by the Google Maps nodes: look up places and addresses with the
 * account's own Google Maps Platform key (Places API and Geocoding API must be
 * enabled on it). The key goes in a header for Places and in the query for
 * Geocoding, as Google requires.
 */
abstract class AbstractGoogleMapsNode implements DeclaresEffect, DeclaresOutputSchema, NodeContract
{
    use CallsApiKeyService;

    protected const string PLACES_URL = 'https://places.googleapis.com/v1';

    protected const string GEOCODE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * The place fields asked for, which decide what Google bills for.
     */
    protected const string PLACE_FIELDS = 'places.id,places.displayName,places.formattedAddress,places.location,places.rating,places.userRatingCount,places.websiteUri,places.nationalPhoneNumber,places.internationalPhoneNumber,places.googleMapsUri,places.businessStatus,places.types,places.priceLevel,places.primaryTypeDisplayName';

    public function category(): string
    {
        return 'maps_platform';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    protected function serviceName(): string
    {
        return 'Google Maps';
    }

    protected function authenticate(string $key, array $headers, array $query): array
    {
        return [[...$headers, 'X-Goog-Api-Key' => $key], $query];
    }

    /**
     * One place from Google's Places response, in the shape the nodes return.
     *
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>
     */
    protected function shapePlace(array $place): array
    {
        return [
            'id' => $place['id'] ?? null,
            'name' => $place['displayName']['text'] ?? null,
            'address' => $place['formattedAddress'] ?? null,
            'latitude' => $place['location']['latitude'] ?? null,
            'longitude' => $place['location']['longitude'] ?? null,
            'rating' => $place['rating'] ?? null,
            'reviews' => $place['userRatingCount'] ?? null,
            'website' => $place['websiteUri'] ?? null,
            'phone' => $place['internationalPhoneNumber'] ?? $place['nationalPhoneNumber'] ?? null,
            'maps_url' => $place['googleMapsUri'] ?? null,
            'status' => $place['businessStatus'] ?? null,
            'category' => $place['primaryTypeDisplayName']['text'] ?? null,
            'types' => array_values((array) ($place['types'] ?? [])),
        ];
    }

    /**
     * Geocoding answers 200 with a status; anything but OK or ZERO_RESULTS is a failure.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws RuntimeException
     */
    protected function assertGeocodeOk(array $body): void
    {
        $status = (string) ($body['status'] ?? 'UNKNOWN_ERROR');

        if (in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            return;
        }

        throw new RuntimeException(match ($status) {
            'REQUEST_DENIED' => 'Google Maps refused the API key'.(filled($body['error_message'] ?? null) ? ': '.$body['error_message'] : '').'. Check that the Geocoding API is enabled on it.',
            'OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT' => 'Google Maps says the quota or billing limit was reached. Try again later or check billing.',
            'INVALID_REQUEST' => 'Google Maps rejected the request. Check the address or coordinates.',
            default => "Google Maps returned an error ({$status}).",
        });
    }
}
