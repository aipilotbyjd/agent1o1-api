> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Detach an agent MCP server

> Detach an MCP server (connector) from an agent. This is idempotent — detaching a server that isn't attached returns `detached: false` rather than an error.




## OpenAPI

````yaml delete /agents/{agent_id}/mcp-servers/{server_id}
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/mcp-servers/{server_id}:
    delete:
      tags:
        - Agents
      summary: Detach an agent MCP server
      description: >
        Detach an MCP server (connector) from an agent. This is idempotent —
        detaching a server that isn't attached returns `detached: false` rather
        than an error.
      operationId: detachAgentMcpServer
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
          description: ID of the MCP server to detach.
      responses:
        '200':
          description: The detach result.
          content:
            application/json:
              schema:
                type: object
                properties:
                  agent_id:
                    type: string
                    example: abc123DEFghiJKL
                  server_id:
                    type: string
                    example: srv_github
                  detached:
                    type: boolean
                    description: >-
                      `true` if the server was attached and is now removed;
                      `false` if it was not attached.
                    example: true
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have update access to this agent.
        '404':
          description: Agent not found.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl -X DELETE
            'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL/mcp-servers/srv_github'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
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