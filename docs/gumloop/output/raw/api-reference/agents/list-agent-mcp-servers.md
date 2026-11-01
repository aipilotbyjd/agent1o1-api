> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List agent MCP servers

> List the MCP servers (connectors) attached to an agent. Sensitive fields such as `secret_id` and `mcp_server_url` are scrubbed from the response.




## OpenAPI

````yaml get /agents/{agent_id}/mcp-servers
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /agents/{agent_id}/mcp-servers:
    get:
      tags:
        - Agents
      summary: List agent MCP servers
      description: >
        List the MCP servers (connectors) attached to an agent. Sensitive fields
        such as `secret_id` and `mcp_server_url` are scrubbed from the response.
      operationId: listAgentMcpServers
      parameters:
        - in: path
          name: agent_id
          required: true
          schema:
            type: string
          description: >-
            ID of the agent. Also accepts the reserved aliases `gumball` and
            `analytics`.
      responses:
        '200':
          description: The MCP servers attached to the agent.
          content:
            application/json:
              schema:
                type: object
                properties:
                  agent_id:
                    type: string
                    example: abc123DEFghiJKL
                  mcp_servers:
                    type: array
                    description: >-
                      Attached MCP server tool configs, with secrets and server
                      URLs redacted.
                    items:
                      type: object
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — the caller does not have read access to this agent's
            configuration.
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
            curl
            'https://api.gumloop.com/api/v1/agents/abc123DEFghiJKL/mcp-servers'
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