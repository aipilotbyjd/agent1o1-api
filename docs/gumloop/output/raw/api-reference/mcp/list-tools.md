> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List MCP server tools

> Return the tools exposed by an MCP server. When the server is not in `connected` state, `tools` is empty and `gumloop_auth_url` is returned so the caller can prompt the user to authenticate.



## OpenAPI

````yaml get /mcp/servers/{server_id}/tools
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /mcp/servers/{server_id}/tools:
    get:
      tags:
        - MCP
      summary: List MCP server tools
      description: >-
        Return the tools exposed by an MCP server. When the server is not in
        `connected` state, `tools` is empty and `gumloop_auth_url` is returned
        so the caller can prompt the user to authenticate.
      operationId: listMcpServerTools
      parameters:
        - in: path
          name: server_id
          required: true
          schema:
            type: string
          description: Identifier of the MCP server.
        - in: query
          name: team_id
          required: false
          schema:
            type: string
          description: Scope the lookup to a single team.
      responses:
        '200':
          description: Tools available on the server, plus the server's connection state.
          content:
            application/json:
              schema:
                type: object
                properties:
                  tools:
                    type: array
                    items:
                      type: object
                      required:
                        - tool_call_id
                        - name
                      properties:
                        tool_call_id:
                          type: string
                          description: >-
                            Composite identifier used to route tool calls —
                            `"{server_id}__{tool_name}"`. Pass `server_id` and
                            `tool_name` separately to `POST /mcp/tools/call`.
                          example: gumloop_slack__slack_send_message
                        name:
                          type: string
                          example: slack_send_message
                        description:
                          type: string
                          nullable: true
                          example: Send a message to a Slack channel.
                        input_schema:
                          type: object
                          description: >-
                            JSON Schema describing the tool's arguments.
                            Defaults to `{}` when the upstream server does not
                            advertise a schema.
                          example:
                            type: object
                            properties:
                              channel:
                                type: string
                              text:
                                type: string
                            required:
                              - channel
                              - text
                        server_id:
                          type: string
                          nullable: true
                          example: gumloop_slack
                        server_type:
                          type: string
                          nullable: true
                          example: gumcp_server
                        server:
                          type: object
                          description: Server metadata snapshot. Defaults to `{}`.
                          example: {}
                  server_id:
                    type: string
                    nullable: true
                    example: gumloop_slack
                  status:
                    type: string
                    nullable: true
                    example: connected
                  gumloop_auth_url:
                    type: string
                    nullable: true
                    example: https://www.gumloop.com/oauth/connect/slack
              examples:
                connected:
                  summary: Connected server
                  value:
                    tools:
                      - tool_call_id: gumloop_slack__slack_send_message
                        name: slack_send_message
                        description: Send a message to a Slack channel.
                        input_schema:
                          type: object
                          properties:
                            channel:
                              type: string
                            text:
                              type: string
                          required:
                            - channel
                            - text
                        server_id: gumloop_slack
                        server_type: gumcp_server
                        server: {}
                    server_id: gumloop_slack
                    status: connected
                    gumloop_auth_url: https://www.gumloop.com/oauth/connect/slack
                unauthenticated:
                  summary: Server not yet connected
                  value:
                    tools: []
                    server_id: gumloop_slack
                    status: unauthenticated
                    gumloop_auth_url: https://www.gumloop.com/oauth/connect/slack
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
            'https://api.gumloop.com/api/v1/mcp/servers/gumloop_slack/tools?team_id=YOUR_TEAM_ID'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: >
            from gumloop import Gumloop


            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")


            response = client.mcp.list_tools("gumloop_slack",
            team_id="YOUR_TEAM_ID")

            for tool in response.tools:
                print(tool.tool_call_id, tool.name)
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