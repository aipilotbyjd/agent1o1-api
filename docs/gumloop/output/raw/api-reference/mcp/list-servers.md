> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List MCP servers

> Return the catalog of MCP servers visible to the caller — Gumloop-hosted (`gumcp_server`), user-deployed Gumstack (`gumstack_server`), and custom (`mcp_server`) — along with each server's connection state.



## OpenAPI

````yaml get /mcp/servers
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /mcp/servers:
    get:
      tags:
        - MCP
      summary: List MCP servers
      description: >-
        Return the catalog of MCP servers visible to the caller — Gumloop-hosted
        (`gumcp_server`), user-deployed Gumstack (`gumstack_server`), and custom
        (`mcp_server`) — along with each server's connection state.
      operationId: listMcpServers
      parameters:
        - in: query
          name: team_id
          required: false
          schema:
            type: string
          description: >-
            Scope the catalog to a single team. When omitted, returns servers
            visible to the authenticated user.
      responses:
        '200':
          description: MCP servers visible to the caller.
          content:
            application/json:
              schema:
                type: object
                properties:
                  servers:
                    type: array
                    items:
                      type: object
                      required:
                        - server_id
                        - type
                        - status
                        - gumloop_auth_url
                      properties:
                        server_id:
                          type: string
                          description: Stable identifier for the server.
                          example: gumloop_slack
                        name:
                          type: string
                          nullable: true
                          example: Slack
                        type:
                          type: string
                          description: >-
                            One of `gumcp_server`, `gumstack_server`, or
                            `mcp_server`.
                          example: gumcp_server
                        status:
                          type: string
                          description: >-
                            Connection state. `connected` means the server is
                            ready to accept tool calls; other values (for
                            example `unauthenticated`, `blocked`) indicate the
                            user must complete OAuth or the server is otherwise
                            unavailable.
                          example: connected
                        icon_url:
                          type: string
                          nullable: true
                          example: https://www.gumloop.com/icons/slack.png
                        description:
                          type: string
                          nullable: true
                          example: Send and read Slack messages.
                        gumloop_auth_url:
                          type: string
                          description: >-
                            URL the user should visit to connect or reauthorize
                            this server.
                          example: https://www.gumloop.com/oauth/connect/slack
                        mcp_url:
                          type: string
                          nullable: true
                          description: >-
                            For `mcp_server` (custom) and `gumstack_server`
                            entries, the upstream MCP URL. `null` for
                            Gumloop-hosted servers.
                          example: null
                        tool_count:
                          type: integer
                          nullable: true
                          example: 12
                        allowed_tool_call_ids:
                          type: array
                          nullable: true
                          description: >-
                            Populated only by the retrieve endpoint. Always
                            `null` here.
                          items:
                            type: string
                          example: null
              examples:
                multiple:
                  summary: Mixed connection states
                  value:
                    servers:
                      - server_id: gumloop_slack
                        name: Slack
                        type: gumcp_server
                        status: connected
                        icon_url: https://www.gumloop.com/icons/slack.png
                        description: Send and read Slack messages.
                        gumloop_auth_url: https://www.gumloop.com/oauth/connect/slack
                        mcp_url: null
                        tool_count: 12
                        allowed_tool_call_ids: null
                      - server_id: gumloop_linear
                        name: Linear
                        type: gumcp_server
                        status: unauthenticated
                        icon_url: https://www.gumloop.com/icons/linear.png
                        description: Read and create Linear issues.
                        gumloop_auth_url: https://www.gumloop.com/oauth/connect/linear
                        mcp_url: null
                        tool_count: 8
                        allowed_tool_call_ids: null
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller does not have agent access on the requested
            team.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl
            'https://api.gumloop.com/api/v1/mcp/servers?team_id=YOUR_TEAM_ID' \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.mcp.list_servers(team_id="YOUR_TEAM_ID")
            for server in response.servers:
                print(server.server_id, server.status)
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      description: >-
        A personal API key or an [OAuth 2.0](/api-reference/oauth) access token.
        Personal API keys also require the `x-auth-key` header with your user
        ID.

````