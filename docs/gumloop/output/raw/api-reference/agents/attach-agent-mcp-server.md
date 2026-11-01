> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Attach or update an agent MCP server

> Attach an MCP server (connector) to an agent, or update its configuration if it's already attached (upsert).

The `server_id` is validated against the caller's MCP catalog. Catalog identity fields (`type`, `server_id`, `secret_id`, `mcp_server_url`) always come from the catalog and cannot be spoofed via the request body — the body carries only free-form connector configuration (e.g. approval mode, tool restrictions); any identity keys in it are ignored.

Attach may succeed before OAuth is completed; `auth_status` reflects the catalog's authentication state.




## OpenAPI

````yaml put /agents/{agent_id}/mcp-servers/{server_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/mcp-servers/{server_id}:
    put:
      tags:
        - Agents
      summary: Attach or update an agent MCP server
      description: >
        Attach an MCP server (connector) to an agent, or update its
        configuration if it's already attached (upsert).


        The `server_id` is validated against the caller's MCP catalog. Catalog
        identity fields (`type`, `server_id`, `secret_id`, `mcp_server_url`)
        always come from the catalog and cannot be spoofed via the request body
        — the body carries only free-form connector configuration (e.g. approval
        mode, tool restrictions); any identity keys in it are ignored.


        Attach may succeed before OAuth is completed; `auth_status` reflects the
        catalog's authentication state.
      operationId: attachAgentMcpServer
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent. Also accepts the reserved aliases `gumball` and
            `analytics`.
        - in: path
          name: server_id
          required: true
          schema:
            type: string
          description: ID of the MCP server from the caller's catalog.
      requestBody:
        required: false
        content:
          application/json:
            schema:
              type: object
              description: >-
                Free-form connector configuration. Identity keys are
                catalog-derived and ignored.
            examples:
              empty:
                summary: Attach with default config
                value: {}
      responses:
        '200':
          description: The attached (or updated) MCP server.
          content:
            application/json:
              schema:
                type: object
                properties:
                  agent_id:
                    type: string
                    example: abc123DEFghiJKL
                  mcp_server:
                    type: object
                    description: >-
                      The resulting MCP server tool config, with secrets and
                      server URL redacted.
                  created:
                    type: boolean
                    description: >-
                      `true` if the server was newly attached; `false` if an
                      existing attachment was updated.
                    example: true
                  auth_status:
                    type: string
                    nullable: true
                    description: >-
                      Catalog authentication status for the server (e.g. whether
                      OAuth has been completed).
                    example: authenticated
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller lacks update access to the agent, or the MCP
            server is restricted for the organization (`mcp_server_restricted`).
        '404':
          description: >-
            Agent not found, or the server ID is not in the caller's catalog
            (`mcp_server_not_found`).
        '409':
          description: >-
            The agent has duplicate entries for this server
            (`ambiguous_mcp_server`); remove them and retry.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X PUT
            'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL/mcp-servers/srv_github'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN' \
              -H 'Content-Type: application/json' \
              -d '{}'
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