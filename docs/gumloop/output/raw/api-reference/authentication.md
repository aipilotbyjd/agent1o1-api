> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Authentication

The Gumloop API supports two authentication methods. Both grant the same permissions and work on every endpoint — pick whichever matches how your app gets the credential.

## API key

For scripts and integrations you control end to end. Generate one from the [Connectors page](https://www.gumloop.com/personal/connectors), then pass it as a bearer token.

```bash theme={"dark"}
curl https://api.gumloop.com/api/v1/start_pipeline \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"user_id": "xxxxxxxxxxxx", "saved_item_id": "xxxxxxxxxxxx"}'
```

<Warning>API keys require the [Pro plan or above](https://www.gumloop.com/pricing).</Warning>

### Personal vs Team keys

Gumloop offers two flavors of API key, selectable when you generate one.

|                     | Personal key                    | Team key                                                                   |
| ------------------- | ------------------------------- | -------------------------------------------------------------------------- |
| Acts as             | The owning user only            | Any team member (set `user_id` per request)                                |
| Default credentials | The user's personal credentials | The credentials configured per node (`Personal Default` or `Team Default`) |
| Use when            | Solo use, local development     | Team automations, server-to-server, CI/CD                                  |

When a request includes `project_id`, each node's *Credentials to use* setting decides whether the run uses the calling user's personal credentials or the team's credentials.

<Note>The API parameter is still named `project_id` for backwards compatibility — it's the same thing the UI now calls your **team** ID.</Note>

<Tip>You can find your `user_id` on the [Profile Settings page](https://www.gumloop.com/settings/profile/general). See [Finding Your User ID](/api-reference/getting-started#finding-your-user-id) for details.</Tip>

## OAuth 2.0

If you're building an app that other Gumloop users sign in to, use [OAuth 2.0](/api-reference/oauth). Once you've completed the flow and have an access token, pass it the same way:

```bash theme={"dark"}
curl https://api.gumloop.com/api/v1/agents \
  -H "Authorization: Bearer ACCESS_TOKEN"
```
