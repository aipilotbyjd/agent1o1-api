<?php

namespace App\Enums\Connectors;

enum ConnectorAuthType: string
{
    case OAuth2 = 'oauth2';
    case ApiKey = 'api_key';
    case BearerToken = 'bearer_token';
    case BasicAuth = 'basic_auth';
}
