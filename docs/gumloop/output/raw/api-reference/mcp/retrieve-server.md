> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve an MCP server

> Return a single MCP server. The response populates `allowed_tool_call_ids` with the tool call IDs the caller is permitted to invoke on this server.



## OpenAPI

````yaml get /mcp/servers/{server_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /mcp/servers/{server_id}:
    get:
      tags:
        - MCP
      summary: Retrieve an MCP server
      description: >-
        Return a single MCP server. The response populates
        `allowed_tool_call_ids` with the tool call IDs the caller is permitted
        to invoke on this server.
      operationId: retrieveMcpServer
      parameters:
        - in: path
          name: server_id
          required: true
          schema:
            type: string
          description: Identifier of the MCP server to retrieve.
        - in: query
          name: team_id
          required: false
          schema:
            type: string
          description: Scope the lookup to a single team.
      responses:
        '200':
          description: The requested MCP server.
          content:
            application/json:
              schema:
                type: object
                properties:
                  server:
                    type: object
                    required:
                      - server_id
                      - type
                      - status
                      - gumloop_auth_url
                    properties:
                      server_id:
                        type: string
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
                        example: https://www.gumloop.com/oauth/connect/slack
                      mcp_url:
                        type: string
                        nullable: true
                        example: null
                      tool_count:
                        type: integer
                        nullable: true
                        example: 12
                      allowed_tool_call_ids:
                        type: array
                        nullable: true
                        description: >-
                          Tool call IDs the caller is permitted to invoke on
                          this server, after RBAC and policy checks.
                        items:
                          type: string
                        example:
                          - gumloop_slack__slack_send_message
                          - gumloop_slack__slack_list_channels
              examples:
                connected:
                  summary: Connected server
                  value:
                    server:
                      server_id: gumloop_slack
                      name: Slack
                      type: gumcp_server
                      status: connected
                      icon_url: https://www.gumloop.com/icons/slack.png
                      description: Send and read Slack messages.
                      gumloop_auth_url: https://www.gumloop.com/oauth/connect/slack
                      mcp_url: null
                      tool_count: 12
                      allowed_tool_call_ids:
                        - gumloop_slack__slack_send_message
                        - gumloop_slack__slack_list_channels
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller does not have agent access on the requested
            team.
        '404':
          description: MCP server not found.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl
            'https://api.gumloop.com/api/v1/mcp/servers/gumloop_slack?team_id=YOUR_TEAM_ID'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: >
            from gumloop import Gumloop


            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")


            response = client.mcp.get_server("gumloop_slack",
            team_id="YOUR_TEAM_ID")

            print(response.server.status, response.server.allowed_tool_call_ids)
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