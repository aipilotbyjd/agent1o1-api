> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# OAuth 2.0

Gumloop supports OAuth 2.0, which is recommended if you're building an application that other Gumloop users sign in to. The flow follows the standard **authorization code grant with PKCE (S256)** and issues refresh tokens.

## Register an OAuth application

OAuth client registration is currently invite-only.

<Info>
  Email **[support@gumloop.com](mailto:support@gumloop.com)** with your app name, use case, redirect URI(s), and logo. We'll review and reach out with a `client_id`.
</Info>

## Redirect the user to Gumloop

When authorizing a user, redirect to the authorization endpoint with the correct parameters and scopes.

```http theme={"dark"}
GET https://api.gumloop.com/oauth/authorize
```

| Parameter                    | Description                                                                  |
| ---------------------------- | ---------------------------------------------------------------------------- |
| `client_id`                  | (required) Client ID from your registered OAuth app                          |
| `redirect_uri`               | (required) One of your app's registered redirect URIs                        |
| `response_type=code`         | (required) Only `code` is supported                                          |
| `scope`                      | (required) Space-separated list of [scopes](#scopes)                         |
| `code_challenge`             | (required) Your PKCE code challenge                                          |
| `code_challenge_method=S256` | (required) Only `S256` is supported                                          |
| `state`                      | (optional, recommended) Opaque value echoed back on redirect to prevent CSRF |

### Example

```http theme={"dark"}
GET https://api.gumloop.com/oauth/authorize
  ?response_type=code
  &client_id=YOUR_CLIENT_ID
  &redirect_uri=https%3A%2F%2Fyourapp.com%2Foauth%2Fcallback
  &scope=gumloop_api
  &code_challenge=E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM
  &code_challenge_method=S256
  &state=SECURE_RANDOM
```

## Handle the redirect

After the user approves your app, Gumloop redirects them back to your `redirect_uri` with the authorization `code` and your `state` in the query string. Always validate that `state` matches the value you sent.

```http theme={"dark"}
GET https://yourapp.com/oauth/callback?code=9a5190f637d8...&state=SECURE_RANDOM
```

## Exchange the code for tokens

Exchange the `code` (plus your PKCE `code_verifier`) for an access token.

```http theme={"dark"}
POST https://api.gumloop.com/oauth/token
Content-Type: application/x-www-form-urlencoded
```

| Parameter                       | Description                                                                       |
| ------------------------------- | --------------------------------------------------------------------------------- |
| `grant_type=authorization_code` | (required)                                                                        |
| `code`                          | (required) Authorization code from the previous step                              |
| `redirect_uri`                  | (required) Same value sent in the authorize request                               |
| `client_id`                     | (required) Your client ID                                                         |
| `code_verifier`                 | (required) The PKCE verifier matching the challenge sent in the authorize request |

### Example

```bash theme={"dark"}
curl -X POST https://api.gumloop.com/oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=authorization_code" \
  -d "code=9a5190f637d8..." \
  -d "redirect_uri=https://yourapp.com/oauth/callback" \
  -d "client_id=YOUR_CLIENT_ID" \
  -d "code_verifier=YOUR_CODE_VERIFIER"
```

### Response

```json theme={"dark"}
{
  "access_token": "...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope": "gumloop_api",
  "refresh_token": "..."
}
```

## Make API requests

Pass the access token as a bearer header on every request, exactly like an API key:

```bash theme={"dark"}
curl https://api.gumloop.com/api/v1/agents \
  -H "Authorization: Bearer ACCESS_TOKEN"
```

## Refresh an access token

When `expires_in` elapses, exchange the refresh token for a new access token.

```http theme={"dark"}
POST https://api.gumloop.com/oauth/token
Content-Type: application/x-www-form-urlencoded
```

| Parameter                  | Description                                         |
| -------------------------- | --------------------------------------------------- |
| `grant_type=refresh_token` | (required)                                          |
| `refresh_token`            | (required) Refresh token from the previous response |
| `client_id`                | (required) Your client ID                           |

## Revoke a token

```http theme={"dark"}
POST https://api.gumloop.com/oauth/revoke
Content-Type: application/x-www-form-urlencoded
```

| Parameter   | Description                                      |
| ----------- | ------------------------------------------------ |
| `token`     | (required) The access or refresh token to revoke |
| `client_id` | (required) Your client ID                        |

## Scopes

| Scope         | Grants                                               |
| ------------- | ---------------------------------------------------- |
| `gumloop_api` | Call the Gumloop developer API on behalf of the user |
| `userinfo`    | Read the user's basic profile (email, name)          |

<Warning>The `gumloop_api` scope requires the authorizing user to be on the [Pro plan or above](https://www.gumloop.com/pricing). Token exchange will fail if the user's account does not meet this requirement.</Warning>
